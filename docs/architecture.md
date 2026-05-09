# Architektura

Symfony 7.3 / PHP 8.2+ aplikace. Vyvíjí se v Dockeru (Compose); staging na <https://beta.slack.cz> (Hetzner CX22, native PHP 8.3 + Postgres 16 + Caddy — viz `deploy.md`), cutover legacy `slack.cz` ještě neproběhl.

Dev běží na **native docker-ce** (Linux) — žádný Docker Desktop, žádný `linuxkit`, žádný FUSE wrapper. Bind mount je raw `ext4` přímo z hostu, atomic-rename funguje korektně (důležité, viz historie editací; DD's `fakeowner` FUSE měl invalidation bug).

## Stack

| Vrstva | Komponenta | Host port |
|---|---|---|
| Web | Apache 2.4 | **8000** (pevný) |
| App | PHP-FPM (image `slack-cz-php`, Symfony 7.3, PHP 8.4 alpine) | – (jen interní 9000) |
| DB nová | PostgreSQL 16 (Doctrine EM `default`) | dynamický (compose ho přidělí) |
| DB legacy | MySQL (Doctrine EM `old`, čte `slackcz_44953.sql` dump) | – (interní) |
| Mail | Mailpit (UI 8025, SMTP 1025) | dynamické pro UI i SMTP |
| Adminer | DB UI | **8080** (pevný) |

Aktuální dynamické porty: `docker compose port database 5432`, `docker compose port mailer 8025` atp.

## Struktura kódu

```
src/
  Controller/       # PagesController, HighlineController, UserController, Registration/Reset/Security
  Entity/           # NOVÉ entity (Postgres, EM default): User, Highline, HighlineCrossing, ResetPasswordRequest
  Enum/             # HighlineType, HighlineCrossingStyle
  Feed/             # YouTube feed + cache + dispatcher (mock fallback)
  Form/             # Symfony forms
  Repository/       # Doctrine repos pro nové entity (HighlineCrossingRepository::RECENT_LIMIT je single source of truth)
  Security/         # EmailVerifier
  Old/              # LEGACY mapping (MySQL, EM old) — namespace App\Old\*
    Entity/         # legacy entity: Uzivatel
    Repository/     # repos pro legacy
  Command/          # Console commands: app:import:highlines, app:import:users, app:import:crossings
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
  - `map_controller.js` — hlavní Leaflet mapa: highline markery (`staticLayer`), emoji markery posledních přechodů (`usersLayer`, přepínatelné okem ze sidebaru), time-travel režim (`timelineLayer`). Leaflet zoom je přesunutý na `bottomright`, aby se nemlátil se sidebarem vlevo nahoře.
  - `highline_detail_map_controller.js` — slim mini-mapa pro detail lajny: jeden pin nebo polyline mezi `point1` a `point2` GPS (pokud má lajna oba body)
  - `user_denik_map_controller.js` — mini-mapa na `/denik/{id}` se všemi unikátními highlines, co user prošel
  - `crossing_feed_controller.js` — vertikální „news bar" sidebar na `/mapa`: list posledních N přechodů, dvě tlačítka v hlavičce (oko = visibility emoji markerů, šipka = collapse panelu), reaguje na time-travel režim (změní se v okno -7 dní zpět od virtuálního času)
  - `search_controller.js` — global highline search v hlavičce
  - `intro_controller.js` — slackvibes 📻 audio player; persistent přes Turbo přes `data-turbo-permanent`. Detaily v `docs/audio-player.md`.

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

## Routes (zkrácený přehled)

| Path | Name | Public? |
|---|---|---|
| `/` | `app_index` | ✓ |
| `/mapa` | `app_highline_map` | ✓ |
| `/mapa/data` | `app_highline_map_data` | ✓ JSON — všechny highlines (markery na mapě) |
| `/mapa/feed` | `app_highline_map_feed` | ✓ JSON — N posledních přechodů (default `RECENT_LIMIT`); s `?date=YYYY-MM-DD&days=7` vrací time-travel okno |
| `/mapa/timeline-data` | `app_highline_map_timeline` | ✓ JSON — vše pro time-travel playback (highlines + crossings chronologicky) |
| `/highline/{slug}` | `app_highline_detail` | ✓ |
| `/denik/{id}` | `app_user_denik` | ✓ deník konkrétního uživatele |
| `/o-projektu` | `app_about` | ✓ |
| `/profile` | `app_profile` | login required |
| `/login`, `/register`, `/logout` | auth | ✓ |
| `/reset-password`, `/reset-password/reset/{token}` | reset | ✓ |
| `/verify/email` | email verify | login required |
| `/old-users` | `app_old_users` | ✓ debug pohled na legacy uzivatele |

## Hotové features

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
