# Architektura

Symfony 7.3 / PHP 8.2+ aplikace. Vyvíjí se v Dockeru (Compose), produkce zatím není nasazená.

## Stack

| Vrstva | Komponenta | Pozn. |
|---|---|---|
| Web | Apache 2.4 (`:8000`) | servíruje `public/` |
| App | PHP-FPM (slack-cz-php) | Symfony 7.3, PHP 8.2+ |
| DB nová | PostgreSQL 16 (`:40219`) | Doctrine ORM, EM `default` |
| DB legacy | MySQL (`:3306` interní) | čte se z `slackcz_44953.sql` dump, EM `old` |
| Mail | Mailpit (UI `:42039`, SMTP `:37623`) | dev-only |
| Adminer | `:8080` | DB UI |

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

- **Asset Mapper** + **Stimulus** + **Turbo** (Symfony stack, žádný build step)
- **Importmap** (`importmap.php`) drží: stimulus, turbo, leaflet
- Stimulus controllery v `assets/controllers/`:
  - `hello_controller.js` — placeholder z generátoru
  - `map_controller.js` — Leaflet mapa, parametrizovatelný (data-url, ikony)
- Globální CSS v `assets/styles/app.css`
- Obrázky v `assets/images/` (logo, leaflet ikony, archivní artefakty)

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
  - `MockFeedFetcher` — fixturní data pro dev / kdyby chyběl key
  - `FeedFetcherDispatcher` — picknul real, když je API key, jinak mock
  - `CachedFeedFetcher` — decorator přes `cache.app`, TTL 30 min default

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
| `/o-projektu` | `app_about` | ✓ |
| `/profile` | `app_profile` | login required |
| `/login`, `/register`, `/logout` | auth | ✓ |
| `/reset-password`, `/reset-password/reset/{token}` | reset | ✓ |
| `/verify/email` | email verify | login required |
| `/old-users` | `app_old_users` | ✓ debug pohled na legacy uzivatele |

## Hotové features

- ✅ **Highline mapa** s 254 lajnami z legacy DB (Leaflet + OSM, popup)
- ✅ **Index page** se 2 panely: mini mapa + Slack.cz TV (YouTube feed)
- ✅ **Slack.cz TV** — YouTube feed, hashtag/search/channel zdroje, in-memory cache
- ✅ **About / O projektu** s historií slacklive 2007 → slack.cz 2010 → ČAS 2011 → dnes, vč. archivního Kolouchova úvodního slova
- ✅ Hlavní vizuál — světlý theme, magenta accent (`#e91e63` z původního slack.cz loga)
- ✅ Auth (registrace, login, reset, email verify) — předvyřízeno před začátkem této vývojové větve
