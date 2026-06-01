# Architektura

Symfony 7.4 LTS / PHP 8.2+ aplikace. Vyvíjí se v Dockeru (Compose); staging na <https://beta.slack.cz> (Hetzner CX22, native PHP 8.3 + Postgres 16 + Caddy — viz `deploy.md`), cutover legacy `slack.cz` ještě neproběhl.

Dev běží na **native docker-ce** (Linux) — žádný Docker Desktop, žádný `linuxkit`, žádný FUSE wrapper. Bind mount je raw `ext4` přímo z hostu, atomic-rename funguje korektně (důležité, viz historie editací; DD's `fakeowner` FUSE měl invalidation bug).

> **Infrastruktura, prod provoz, deploy flow, CI workflow, skripty** → `deploy.md` (sekce „Infrastruktura na první pohled" je 2-minutový rozcestník).
> **Operační cookbook** (`make` targety, časté konzole příkazy, smoke testy) → `dev.md`.

## Stack

| Vrstva | Komponenta | Host port |
|---|---|---|
| Web | Apache 2.4 | **8000** (pevný) |
| App | PHP-FPM (image `slack-cz-php`, Symfony 7.4, PHP 8.4 alpine) | – (jen interní 9000) |
| DB nová | PostgreSQL 16 (Doctrine EM `default`) | dynamický (compose ho přidělí) |
| DB legacy | MySQL (Doctrine EM `old`, čte `slackcz_44953.sql` dump) | – (interní) |
| Mail | Mailpit (UI 8025, SMTP 1025) | dynamické pro UI i SMTP |
| Adminer | DB UI | **8080** (pevný) |

Aktuální dynamické porty: `docker compose port database 5432`, `docker compose port mailer 8025` atp.

## Struktura kódu

```
src/
  Controller/       # PagesController, HighlineController, HighlineCrudController, CrossingController,
                    # UserController, MarkdownSectionController, Registration/Reset/Security
  Entity/           # NOVÉ entity (Postgres, EM default): User, Highline, HighlineCrossing, HighlineEdit, ResetPasswordRequest
  Enum/             # HighlineType, HighlineCrossingStyle, HighlineEditStatus, Gender
  Feed/             # YouTube feed + cache + dispatcher (mock fallback)
  Form/             # Symfony forms (HighlineForm, HighlineCrossingForm, RegistrationForm, ...)
  Repository/       # Doctrine repos (HighlineCrossingRepository::RECENT_LIMIT je single source of truth)
  Security/         # EmailVerifier
  Old/              # LEGACY mapping (MySQL, EM old) — namespace App\Old\*
    Entity/         # legacy entity: Uzivatel
    Repository/     # repos pro legacy
  Command/          # Console commands: app:import:*, app:admin:grant, app:edit:sync-from-history,
                    # app:user:list, app:user:reset-password
```

### Důležité — proč je `App\Old\Entity\` mimo `src/Entity/`

Default Postgres EM má `prefix: 'App\Entity'` a `dir: 'src/Entity'` — Doctrine driver scanuje rekurzivně a všechno pod `App\Entity\*` (včetně `App\Entity\Old\*`) by sebral do default EM. To by způsobilo, že každá `make:migration` chce přidat legacy tabulky (`uzivatel`, kdyby tam byl `Highline` zase kolize) do Postgres schématu.

Řešení: legacy mapping žije v `src/Old/Entity/` (namespace `App\Old\Entity`). Default EM o nich neví, `old` EM ano.

```yaml
# config/packages/doctrine.yaml
old:
    connection: old
    mappings:
        Old:
            is_bundle: false
            dir: '%kernel.project_dir%/src/Old/Entity'
            prefix: 'App\Old\Entity'
            alias: Old
```

Pravidlo: **nikdy nepřidávej legacy ORM entity pod `App\Entity\Old\*`. Dej je do `App\Old\Entity\*`.**

## Frontend

- **Asset Mapper** + **Stimulus** + **Turbo Drive** (Symfony stack, žádný build step)
- **Importmap** (`importmap.php`) drží: stimulus, turbo, leaflet
- Turbo je aktivně zapnuté — link kliky a form submity jsou frame swapy, ne full reloady. Předpokládá to, že stránky extendují `base.html.twig` a vrací 30x redirecty po POST.
- Stimulus controllery v `assets/controllers/`:
  - `hello_controller.js` — placeholder z generátoru
  - `csrf_protection_controller.js` — vendored z Symfony Flex recepty (přišel s 7.4): stateless CSRF, dopočítává `framework.csrf_protection.check_header` token na form submitech. Neměň ručně.
  - `map_controller.js` — hlavní Leaflet mapa: highline markery (`staticLayer`), emoji markery posledních přechodů (`usersLayer`, přepínatelné okem ze sidebaru), time-travel režim (`timelineLayer`). Leaflet zoom je přesunutý na `bottomright`, aby se nemlátil se sidebarem vlevo nahoře.
  - `highline_detail_map_controller.js` — slim mini-mapa pro detail lajny: jeden pin nebo polyline mezi `point1` a `point2` GPS (pokud má lajna oba body)
  - `highline_form_map_controller.js` — 2-endpoint GPS picker pro highline form. Alternující klik 1→2→1, oba markery draggable, polyline + live haversine length overlay. Sync se 4 input poli (point1Lat/Lng + point2Lat/Lng) oboustranně.
  - `user_denik_map_controller.js` — mini-mapa na `/denik/{id}` se všemi unikátními highlines, co user prošel
  - `crossing_feed_controller.js` — vertikální „news bar" sidebar na `/mapa`: list posledních N přechodů, dvě tlačítka v hlavičce (oko = visibility emoji markerů, šipka = collapse panelu), reaguje na time-travel režim (změní se v okno -7 dní zpět od virtuálního času)
  - `search_controller.js` — global highline search v hlavičce
  - `intro_controller.js` — slackvibes 📻 audio player; persistent přes Turbo přes `data-turbo-permanent`. Detaily v `docs/audio-player.md`.
  - `live_slug_controller.js` — live URL preview pro unverified highline edit form: slugify-uje typed name a updatuje `<code>` preview. Server na save přegeneruje slug přes `makeUniqueSlug`.
  - `photo_like_controller.js` — like button na `/highline/{slug}/fotky/{id}`; intercept-uje form submit, POST přes fetch s `Accept: application/json`, endpoint vrátí `{liked, count}` JSON, controller updatuje classy + `aria-pressed` + icon + count. Při fetch failu padá zpět na nativní form submit (graceful degradation).

#### Cross-controller event bus

Mapový sidebar a mapa potřebují komunikovat napříč nezávislými Stimulus controllery. Používáme `document`-level CustomEvents:

| Event | Producer | Consumer | Payload |
|---|---|---|---|
| `slack:map-mode` | `map` | `crossing-feed` | `{ mode: 'recent' \| 'time-travel', date?, days? }` — feed podle toho přefetchuje data (sloty se debouncují 200 ms + AbortController) |
| `slack:users-visibility` | `crossing-feed` (eye button) | `map` | `{ visible: bool }` — mapa přidá/odebere `usersLayer` |

Stav přežívá Turbo navigaci přes `sessionStorage` (`slack.cz:mapa:feed-collapsed`, `slack.cz:mapa:users-hidden`, `slack.cz:mapa:view`). Map controller čte `users-hidden` přímo na bootu (ne přes event), aby nedocházelo k race condition s pořadím Stimulus connectů.
- Globální CSS v `assets/styles/app.css`
- Obrázky v `assets/images/` (logo, leaflet ikony, archivní artefakty)
- Audio v `public/audio/` (12 stop, 128 kbps stereo). Originály v `var/audio-original/` (gitignored).
- Google Fonts: **Space Grotesk** (400, 500, 600, 700) jako globální font na `body` — geometrický sans s charakterem, sedí k bold display logu. Linkovaný přes `<link>` tag v `base.html.twig`.

## Mapa — Leaflet

- Tile provider: **OpenStreetMap** (`tile.openstreetmap.org`)
- Markery: defaultní Leaflet ikony — kvůli AssetMapperu jsme musely PNG (`marker-icon`, `marker-icon-2x`, `marker-shadow`) stáhnout do `assets/images/leaflet/` a předat URLs z Twigu jako `data-*` (klasický bundling problém)
- Zoom controly jsou na **`bottomright`** (default `topleft` koliduje se sidebarem `/mapa`); v time-travel módu se zoom navíc CSSkem zvedne nad time-travel panel
- Použití na 4 místech: full mapa `/mapa` (s sidebar feedem + time-travel), mini mapa v panelu na indexu (sdílí `map_controller`), mini-mapa detailu lajny (`highline_detail_map_controller`), mini-mapa deníku (`user_denik_map_controller`)

### Recent crossings — single source of truth

Konstanta `App\Repository\HighlineCrossingRepository::RECENT_LIMIT` (default 10) určuje, kolik nejnovějších přechodů se zobrazí v UI. Sjednocuje tři místa, která jindy „žila vlastním limitem":

| Místo | Metoda repa | Tvar |
|---|---|---|
| Index page „Posledních přechodů" stripe | `findRecent()` | entity (Twig partial `_recent_crossings.html.twig`) |
| `/mapa` emoji markery | `findRecentForJson()` | array (lat/lng + popup data) |
| `/mapa` sidebar feed | `findRecentForJson()` | array (sdílený endpoint `/mapa/feed`) |

Bez dedup by user — homepage list, emoji markery i sidebar zobrazují **stejné** přechody. Když má jeden user 3 ze 10 nejnovějších, mapa ukáže 3× jeho emoji na 3 různých lajnách (fan-offset stackuje pouze identické GPS).

Pro time-travel režim je separátní `findForFeedInRange(from, to)` (sidebar volá `/mapa/feed?date=YYYY-MM-DD&days=7`).

## Feed (Slack.cz TV)

- YouTube Data API v3 (`googleapis.com/youtube/v3`)
- API key v `.env.local` jako `YOUTUBE_API_KEY` (gitignored)
- `.env` deklaruje proměnnou prázdnou (dokumentace)
- Konfigurační parametry v `config/packages/feed.yaml`:
  - `feed.youtube.channels` — list channel IDs
  - `feed.youtube.queries` — list search queries (každá = 100 quota units/fetch)
- Architektura:
  - `FeedFetcherInterface` — kontrakt
  - `YoutubeFeedFetcher` — real (channels via `playlistItems`, queries via `search`)
  - `MockFeedFetcher` — fixturní data pro dev / fallback když nic reálného nemáme
  - `FeedFetcherDispatcher` — vybere real (když je API key), jinak mock
  - `CachedFeedFetcher` — decorator přes `cache.app`. Default TTL 6h (`feed.cache.ttl_seconds: 21600`).
    Kaskáda fallbacků: real fetch → last-known-good (7 dní, jen z reálných úspěšných fetchů) → mock (necachuje se). Prázdný real fetch se cachuje jen 60s, aby se po obnovení kvóty rychle vrátili reálná data.

### Quota economics

YouTube Data API daily free tier = **10 000 units / GCP project / den** (reset v PT půlnoc ≈ 09:00 CEST). Cena endpointů, které používáme:

| Endpoint | Cost | Kde |
|---|---|---|
| `search.list` | **100 units / call** | `feed.youtube.queries` |
| `channels.list` | 1 unit / call | `feed.youtube.channels` (lookup uploads playlist) |
| `playlistItems.list` | 1 unit / call | `feed.youtube.channels` (skutečná videa) |

Burn = **(počet queries × 100 + počet kanálů × 2) × (86400 / TTL)** units/den.

Aktuální config (1 query, 0 kanálů, TTL 6 h) = **400 units/den** — 25× headroom.

**Historicky jsme limit přepálili** s `feed.youtube.queries: [#czechslackline, czech slackline, czech highline]` a `feed.cache.ttl_seconds: 1800` (30 min):
3 queries × 48 fetchů/den × 100 units = **14 400 units/den** — kvóta vyčerpaná každé odpoledne. Plus každý `bin/console cache:clear` během vývoje vyhodí `cache.app` a vyvolá další 300 units instantně. Comment v `config/packages/feed.yaml` má aktuální vzorec, používej ho při ladění queries listu.

### Recovery po vyčerpání kvóty / rotaci klíče

```bash
# vyhodit 60s "empty fetch" lockout, aby další request hned re-tryoval
docker compose exec -T php bin/console cache:pool:clear cache.app

# načíst nový YOUTUBE_API_KEY z .env.local
docker compose exec -T php bin/console cache:clear --env=dev
```

### GCP "new project" search gate (gotcha)

Čerstvě založený GCP projekt s povolenou YouTube Data API: `/channels` jede (1 unit, 200 OK), ale `/search` vrací **403 Forbidden** i s plnou kvótou. To je standardní GCP posture pro neověřené projekty — `search` je gated dokud (a) nezapneš billing, nebo (b) projde quota-extension request přes Cloud Console. Rotace klíče na nový projekt sama o sobě `/search` nerozjede.

Diagnostika v `var/log/dev.log`: `quotaExceeded` → starý projekt vyčerpal kvótu, počkat na PT reset; `accessNotConfigured` / 403 jen na `/search` → nový projekt čeká na verifikaci.

## Auth

- `App\Entity\User` (email, password, roles, isVerified)
- Symfony security s form_login (login_path/check_path: `app_login`)
- Reset password přes `symfonycasts/reset-password-bundle`
- Email verify přes `symfonycasts/verify-email-bundle`
- `UserRepository` implementuje `PasswordUpgraderInterface`
- **`ROLE_ADMIN`** — kurátorská role (mark verified, schvalovat proposals, mazat verified lajny). Granted přes `bin/console app:admin:grant <email> [--revoke]`. Aktuální admin: `panda09823@gmail.com`. Symfony role hierarchy: ROLE_ADMIN ⇒ ROLE_USER auto.
- **Konzolové utility pro správu uživatelů**: `app:user:list` (id/email/nick/verified/active, volitelné `-s` substring filter, `--unverified`) a `app:user:reset-password <email|id>` (vygeneruje absolutní password-reset URL přes `ResetPasswordHelperInterface::generateResetToken()`). Workaround dokud nejede mailer na betě — host pro URL bere router z `framework.router.default_uri` (= `DEFAULT_URI` env). Návod v `dev.md` § *Účty / správa uživatelů* a `deploy.md` § *Reset hesla / aktivace účtu, když nechodí maily*.

## Routes (zkrácený přehled)

| Path | Name | Public? |
|---|---|---|
| `/` | `app_index` | ✓ |
| `/mapa` | `app_highline_map` | ✓ |
| `/mapa/data` | `app_highline_map_data` | ✓ JSON — všechny highlines (markery na mapě) |
| `/mapa/feed` | `app_highline_map_feed` | ✓ JSON — N posledních přechodů (default `RECENT_LIMIT`); s `?date=YYYY-MM-DD&days=7` vrací time-travel okno |
| `/mapa/timeline-data` | `app_highline_map_timeline` | ✓ JSON — vše pro time-travel playback (highlines + crossings chronologicky) |
| `/highline/{slug}` | `app_highline_detail` | ✓ |
| `/highline/nova` | `app_highline_new` | `ROLE_USER` — formulář nové lajny (priority 10 kvůli kolizi s `/highline/{slug}`) |
| `/highline/{slug}/upravit` | `app_highline_edit` | `ROLE_USER` — direct edit (owner of unverified / admin) nebo proposal (verified + non-admin); detail v `docs/highline-edits.md` |
| `/highline/{slug}/smazat` | `app_highline_delete` | `ROLE_USER` — owner-of-unverified nebo admin |
| `/highline/{slug}/verify` | `app_highline_verify` | `ROLE_ADMIN` — flag isVerified=true |
| `/highline/{slug}/fotky/pridat` | `app_highline_photo_new` | `ROLE_USER` — upload fotky |
| `/highline/{slug}/fotky/{id}` | `app_highline_photo_detail` | ✓ — photo detail (like, komentáře, prev/next) |
| `/highline/fotka/{id}/like` | `app_highline_photo_like` | `ROLE_USER` POST — toggle like |
| `/highline/fotka/{id}/smazat` | `app_highline_photo_delete` | `ROLE_USER` POST — owner uploadu / admin |
| `/highline/komentar/{id}/smazat` | `app_highline_photo_comment_delete` | `ROLE_USER` POST — owner komentu / admin |
| `/highline/{slug}/prechod/novy` | `app_crossing_new` | `ROLE_USER` — přidat přechod |
| `/prechod/{id}/upravit` | `app_crossing_edit` | `ROLE_USER` — vlastní přechody |
| `/prechod/{id}/smazat` | `app_crossing_delete` | `ROLE_USER` — vlastní přechody, CSRF |
| `/admin/navrhy` | `app_admin_proposals` | `ROLE_ADMIN` — fronta pending proposals + diff tabulky |
| `/admin/navrhy/{id}/schvalit` | `app_admin_proposal_approve` | `ROLE_ADMIN` |
| `/admin/navrhy/{id}/zamitnout` | `app_admin_proposal_reject` | `ROLE_ADMIN` |
| `/denik/{id}` | `app_user_denik` | ✓ deník konkrétního uživatele |
| `/o-projektu` | `app_about` | ✓ |
| `/profile` | `app_profile` | login required |
| `/login`, `/register`, `/logout` | auth | ✓ |
| `/reset-password`, `/reset-password/reset/{token}` | reset | ✓ |
| `/verify/email` | email verify | login required |
| `/old-users` | `app_old_users` | ✓ debug pohled na legacy uzivatele |
| `/wiki`, `/wiki/{slug}` | `app_wiki_*` | ✓ highline guidebook (17 kapitol z `wiki/`, live z GitHubu, `NN-slug.md` konvence, žádný frontmatter) |
| `/docs`, `/docs/{slug}` | `app_docs_*` | ✓ technická dokumentace (interní), live z `docs/*.md` na GitHubu |

## Hotové features

- ✅ **Highline CRUD + verifikační flow** — `HighlineCrudController` (new/edit/delete/verify + admin proposal queue). Trust model: kdokoli logged-in přidá lajnu (`unverified` + `createdBy=user`), edituje vlastní unverified lajny direct; verified (254 legacy + admin-schválené) jdou přes `HighlineEdit` proposal queue. Form má 2-endpoint GPS picker (Stimulus), length + center se počítají z point1/point2 přes haversine. ROLE_ADMIN přes `app:admin:grant`. Detail v `docs/highline-edits.md`.
- ✅ **Crossing CRUD** — `CrossingController` + `HighlineCrossingForm`. „Přidat přechod" na detailu lajny (logged-in), edit/delete vlastních přechodů z deníku (`show_actions` flag v `_recent_crossings.html.twig`).
- ✅ **Highline import** — 254 / 254 lajn z legacy MySQL do Postgres (re-runnable s `--truncate`, GPS fallback přes `gps` table). Detaily v `docs/migration.md`.
- ✅ **User import** — 441 unique-email userů (440 nových + 1 obohacený dev účet), 6 dropped legacy řádků mergováno. MD5 hesla 1:1 zachována, `migrate_from: legacy_md5` přehashuje na bcrypt při prvním přihlášení. Crossings remap přes merge mapu.
- ✅ **Crossings import** — 993 / 995 přechodů (2 skipy kvůli `0000-00-00` datu). Style enum (`App\Enum\HighlineCrossingStyle`, 9 hodnot, viz `docs/crossing-styles.md`), neznámé legacy hodnoty se reportují jako warning.
- ✅ **Highline mapa** s 254 lajnami (Leaflet + OSM, popup linkuje na detail)
- ✅ **Highline detail** `/highline/{slug}` — slug unikátně v DB (gen. přes `AsciiSlugger`), info tabulka, mini-mapa s polyline mezi kotvícími body, list všech přechodů
- ✅ **Index page** se 2 panely: mini mapa + Slack.cz TV (YouTube feed) + stripe „Posledních přechodů"
- ✅ **Slack.cz TV** — YouTube feed, hashtag/search/channel zdroje, in-memory cache
- ✅ **About / O projektu** s historií slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes, vč. archivního Kolouchova úvodního slova
- ✅ Hlavní vizuál — světlý theme, magenta accent (`#e91e63` z původního slack.cz loga)
- ✅ Auth (registrace, login, reset, email verify) — předvyřízeno před začátkem této vývojové větve
- ✅ **slackvibes 📻 audio player** — persistent přes Turbo, docked v hlavičce / floating expandable / hidden, equalizer animace, draggable; viz `docs/audio-player.md`
- ✅ **Intro splash overlay** — fullscreen logo + „Vstoupit" button (component `intro-overlay`), magenta glow, single-action vstup do appky + spuštění audio playeru
- ✅ **Time-travel mapa** — historický playback highlines + crossings v čase, controly v `.map-tt-panel` (z-index 500)
- ✅ **Crossing news-bar sidebar** na `/mapa` — vertikální panel vlevo s posledními N přechody (sdílí data s emoji markery na mapě). Eye toggle skryje/zobrazí emoji markery, šipka collapsne panel na samotnou hlavičku. V time-travel režimu se obsah přepíná na okno -7 dní zpět od virtuálního času.
- ✅ **Deník uživatele** `/denik/{id}` — hlavička (nick, město, ročník, datum prvního přechodu), mini-mapa s navštívenými highlines, list všech přechodů
- ✅ **Markdown sections** `/docs` + `/wiki` — sjednocený subsystém pro GitHub-backed MD obsah. Detail níže.
- ✅ **Highline foto galerie + sociální vrstva** — `HighlinePhoto` (highline FK, uploadedBy FK SET NULL, filename, caption, createdAt) + `HighlinePhotoLike` (UNIQUE photo+user) + `HighlinePhotoComment` (photo FK CASCADE, author FK SET NULL, text, createdAt). Upload přes `vich/uploader-bundle` (mapping `highline_photo` → `public/uploads/highline/{id}/<uniqid>.ext`, 4 MB cap, JPG/PNG/WebP). Thumby on-demand přes `liip/imagine-bundle` (filter sety `highline_thumb` 320×240 outbound, `highline_medium` 800×600 inset, `highline_full` 2400 inset; všechny s `auto_rotate` + `strip`). Origin EXIF strip + orientace přes `HighlinePhotoSanitizerSubscriber` (post-upload event, GD), takže ani originál v `/uploads/` neuniká GPS. Per-photo detail `/highline/{slug}/fotky/{id}` s AJAX like-toggle (Stimulus `photo_like_controller`, fetch s `Accept: application/json`, endpoint vrací `{liked, count}`), plain-text flat komentáři (owner/admin delete), prev/next navigací. Grid v `_highline_gallery.html.twig` zobrazuje overlay badges (likes ❤, komenty 💬). Homepage panel „Z galerie" rotuje N fotek z posledních 7 dní; fallback all-time top-liked. Cover header lajny v `highline_detail.html.twig` je zatím čistě `Highline::getLegacyCoverUrl()` (legacy URL `https://slack.cz/line/high/{legacyId}/foto.jpg`) — fotky z galerie do coveru zatím nemícháme. Legacy import `highline_foto` + `highline_media` deferred (vyžaduje SSH).

## Markdown sections (`/docs`, `/wiki`)

Jeden generický subsystém v `App\Markdown\Section\*` slouží jak technické dokumentaci (`/docs` = `docs/*.md` v repu), tak highline guidebooku (`/wiki` = `wiki/NN-skupina/NN-slug.md` v repu). Žádný per-sekci kód, žádný frontmatter — všechno řídí filename + obsah markdownu.

### Komponenty

```
src/Markdown/Section/
  Page.php                  # value object: slug, filename, body, GH urls, title (z prvního H1)
  Entry.php                 # lightweight DTO pro sidebar (slug, filename, label, group)
  Config.php                # per-sekci konfigurace (owner/repo/branch/path/prefix/token)
  FetcherInterface.php      # list() + get(slug)
  GithubFetcher.php         # git trees API recursive, raw.githubusercontent.com fetch, slug/folder label parsing
  CachedFetcher.php         # cache.app + 7d last-known-good fallback
src/Controller/MarkdownSectionController.php   # 4 routes (docs index/show, wiki index/show)
templates/pages/_section/
  _sidebar.html.twig        # shared partial — README link + chapter list, group separators
  index.html.twig           # README.md as index body + sidebar
  show.html.twig            # detail body + sidebar (vše v `.md-prose`, H1 nese sám body)
config/packages/markdown.yaml                  # per-sekci service wiring
```

### Service wiring

Každá sekce = trojice services v `config/packages/markdown.yaml`:

| Service ID | Třída | Účel |
|---|---|---|
| `app.section.<name>.config` | `Config` | GH coordinates + route prefix + token |
| `app.section.<name>.fetcher.inner` | `GithubFetcher` | actual HTTP fetch |
| `app.section.<name>.fetcher` | `CachedFetcher` | cache wrapper, injected do controlleru |

Přidání nové sekce = další trojice services + 2 route metody v controlleru. Controller binduje fetchery + configs přes `#[Autowire('@app.section.<name>.fetcher')]`.

### Konvence MD souborů (žádný frontmatter)

Po dropu YAML frontmatteru řídí všechno chování dva vstupy: **filename** a **obsah markdownu**.

- **Filename `NN-slug.md`** — `NN` určuje pořadí v sidebaru (lexikografický sort relativního path, takže `01-foo/02-bar.md` < `01-foo/03-baz.md` < `02-x/...`). Slug = filename bez `^\d+-` prefixu a `.md` přípony — `02-bezpecnost.md` je dostupné na `/wiki/bezpecnost`. URL slug musí být **unikátní napříč subtree**, jinak první vyhrává.
- **Label v sidebaru = první `#` H1** v souboru (fallback slug). Title v `<title>` tagu taky.
- **Pull-quote = první `>` blockquote hned po H1**, stylovaný přes CSS `.md-prose h1 + blockquote` (větší písmo, italic, accent background). Žádný speciální HTML tag, čistý markdown blockquote.
- **Skupinové separátory v sidebaru** se derivují z root `README.md` sekce: `## H2 nadpis` zaštiťuje skupinu, foldery linkované pod ním (přes `[text](NN-folder/...)`) dostanou ten H2 jako group label. Diakritika + `&` se zachovají, žádný extra soubor.

### Layout v GH repu

- **Docs** flat (`docs/*.md`), bez subfolderů, bez group separátorů (jejich `README.md` nemá H2 + linky → mapa folderů je prázdná).
- **Wiki** nested (`wiki/NN-skupina/NN-slug.md`). Root `wiki/README.md` udržuje index + definuje group labely. Fetcher používá git trees API `?recursive=1` (jeden call).

### README.md

Index `/docs` resp. `/wiki` rendrují `README.md` z root sekce (pulluje se přes `get('README')`, mimo slugMap). `/docs/README` resp. `/wiki/README` redirectují 301 na index. Subfolder `README.md` se ignorují (slug mapě se vyhnou — nemají v současném modelu žádný účel).

### CommonMark gotcha (relevantní pro inline base64 obrázky ve Wiki)

Wiki kapitoly můžou mít base64 obrázky inline jako MD reference-style:

```markdown
Text s ![alt][image1] obrázkem.

[image1]: data:image/png;base64,iVBOR...
```

**MUSÍ být `[image1]: data:...` (bare URL).** Pokud obalíš angular brackets — `[image1]: <data:...>` — CommonMark to fallne na autolink + odmítne parsovat při velkých URL (>10 KB). Výsledek: `<img>` tag se vůbec neudělá, ref-def se vypíše jako text.

### Cache

`CachedFetcher` cachuje per-sekci s prefix klíčem (`docs.list`, `wiki.content.bezpecnost`, ...) v `cache.app`, TTL 600 s. Failure mode: inner throw → fallback na last-known-good (7 dní). Po deployi se vyplatí `bin/console cache:pool:clear cache.app`, ať fetcher hned podruhé jde na GH (jinak čeká na expiraci) — taky po schema-changing refactoru `Page`/`Entry` to vyhodí staré serializované objekty.

### Internal MD link rewriting

`MarkdownRenderer::render($body, $internalRoutePrefix)` přepisuje relativní `*.md` linky (i v subfolderech: `01-pouzivani-highline/02-bezpecnost.md`) na `/{prefix}/{slug}` (`/wiki/bezpecnost`). Slug stripuje `^\d+-` přes `GithubFetcher::slugFromFilename()` (sjednocený zdroj transformace). Externí URL (s schemem `http:`, `mailto:` atd.) se ponechávají.
