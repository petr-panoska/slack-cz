# Architektura

Symfony 7.3 / PHP 8.2+ aplikace. Vyvíjí se v Dockeru (Compose), produkce zatím není nasazená.

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
  Controller/       # PagesController, HighlineController, Registration/Reset/Security
  Entity/           # NOVÉ entity (Postgres, EM default): User, Highline, ResetPasswordRequest
  Enum/             # HighlineType (a další)
  Feed/             # YouTube feed + cache + dispatcher (mock fallback)
  Form/             # Symfony forms
  Repository/       # Doctrine repos pro nové entity
  Security/         # EmailVerifier
  Old/              # LEGACY mapping (MySQL, EM old) — namespace App\Old\*
    Entity/         # legacy entity: Uzivatel
    Repository/     # repos pro legacy
  Command/          # Console commands (import:*)
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
  - `map_controller.js` — hlavní Leaflet mapa (statické markery + recent users + time-travel režim)
  - `highline_detail_map_controller.js` — slim mini-mapa pro detail lajny: jeden pin nebo polyline mezi `point1` a `point2` GPS (pokud má lajna oba body)
  - `intro_controller.js` — slackvibes 📻 audio player; persistent přes Turbo přes `data-turbo-permanent`. Detaily v `docs/audio-player.md`.
- Globální CSS v `assets/styles/app.css`
- Obrázky v `assets/images/` (logo, leaflet ikony, archivní artefakty)
- Audio v `public/audio/` (12 stop, 128 kbps stereo). Originály v `var/audio-original/` (gitignored).
- Google Fonts: **Space Grotesk** (400, 500, 600, 700) jako globální font na `body` — geometrický sans s charakterem, sedí k bold display logu. Linkovaný přes `<link>` tag v `base.html.twig`.

## Mapa — Leaflet

- Tile provider: **OpenStreetMap** (`tile.openstreetmap.org`)
- Markery: defaultní Leaflet ikony — kvůli AssetMapperu jsme musely PNG (`marker-icon`, `marker-icon-2x`, `marker-shadow`) stáhnout do `assets/images/leaflet/` a předat URLs z Twigu jako `data-*` (klasický bundling problém)
- Použití na 2 místech: full mapa `/mapa` a mini mapa v panelu na indexu — sdílí Stimulus controller

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
| `/mapa/data` | `app_highline_map_data` | ✓ JSON |
| `/highline/{slug}` | `app_highline_detail` | ✓ |
| `/o-projektu` | `app_about` | ✓ |
| `/profile` | `app_profile` | login required |
| `/login`, `/register`, `/logout` | auth | ✓ |
| `/reset-password`, `/reset-password/reset/{token}` | reset | ✓ |
| `/verify/email` | email verify | login required |
| `/old-users` | `app_old_users` | ✓ debug pohled na legacy uzivatele |

## Hotové features

- ✅ **Highline mapa** s 254 lajnami z legacy DB (Leaflet + OSM, popup linkuje na detail)
- ✅ **Highline detail** `/highline/{slug}` — slug unikátně v DB (gen. přes `AsciiSlugger`), info tabulka, mini-mapa s polyline mezi kotvícími body, list všech přechodů
- ✅ **Index page** se 2 panely: mini mapa + Slack.cz TV (YouTube feed)
- ✅ **Slack.cz TV** — YouTube feed, hashtag/search/channel zdroje, in-memory cache
- ✅ **About / O projektu** s historií slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes, vč. archivního Kolouchova úvodního slova
- ✅ Hlavní vizuál — světlý theme, magenta accent (`#e91e63` z původního slack.cz loga)
- ✅ Auth (registrace, login, reset, email verify) — předvyřízeno před začátkem této vývojové větve
- ✅ **slackvibes 📻 audio player** — persistent přes Turbo, docked v hlavičce / floating expandable / hidden, equalizer animace, draggable; viz `docs/audio-player.md`
- ✅ **Time-travel mapa** — historický playback highlines + crossings v čase, controly v `.map-tt-panel` (z-index 500)
