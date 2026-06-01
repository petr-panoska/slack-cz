# Dev cookbook

Rychlý reference pro běžné operace. Když si nepamatuješ, koukni sem.

> **Setup:** native docker-ce na Linuxu (Ubuntu 25.10, Docker Engine 29.x). Žádný Docker Desktop. Předpokládá se, že tvoje shell session je členem `docker` group (jinak každý `docker` command volá permission denied — fix: `sudo usermod -aG docker $USER` + logout/login, jednorázově).

## Containery (Docker compose)

| Service | Host port | Účel |
|---|---|---|
| `apache` | **8000** (pevný) | hlavní web |
| `database` | dynamický | Postgres 16 (new app DB), interně 5432 |
| `mysql` | – | legacy MySQL (jen interní 3306) |
| `adminer` | **8080** (pevný) | DB UI |
| `mailer` | dynamický | Mailpit (interní UI 8025, SMTP 1025) |
| `php` | – | PHP-FPM (jen interní 9000) |

```bash
docker compose ps                              # status
docker compose up -d                           # start (detached)
docker compose down                            # stop (volumes zachovány)
docker compose down -v                         # stop + smaž volumes (ztratíš DB!)
docker compose logs -f apache                  # tail logs konkrétní service
docker compose port database 5432              # zjisti aktuální host port pro Postgres
docker compose port mailer 8025                # ... pro Mailpit UI
```

## Foto galerie — upload adresáře

PHP-FPM v `php` containeru jede jako `www-data`. Bind-mountnuté adresáře pro upload (`public/uploads/`) a imagine cache (`public/media/cache/`) tvoří host user (`panda`, uid 1000), takže www-data tam defaultně nemá write. Po fresh checkoutu / po `docker compose down -v`:

```sh
mkdir -p public/uploads public/media/cache
chmod 777 public/uploads public/media/cache
```

Oba adresáře jsou v `.gitignore`, takže permisivní mód nikoho neuráží. Symptom při chybějícím write: `Warning: mkdir(): Permission denied` při uploadu fotky.

## Symfony console

Vždy přes `php` container:

```bash
docker compose exec -T php bin/console <cmd>
```

### Make targety (zkratky)

**Lokální (Docker compose):**

| Target | Co dělá |
|---|---|
| `make dcSetup` | `composer install` |
| `make dcInitDb` | `doctrine:database:create --if-not-exists` + migrate |
| `make dcClearCache` | cache:clear --no-warmup (dle aktuálního `APP_ENV` v containeru / `.env.local`) |
| `make dcAssetMapCompile` | `bin/console asset-map:compile` — generuje `public/assets/manifest.json`. **NUTNÉ v prod** — bez něj 500 |
| `make dcClearCacheProd` | cache:clear s vynuceným `APP_ENV=prod` (i kdyby `.env.local` měl `APP_ENV=dev`) |
| `make loadLegacyDump` | jednorázový load `slackcz_44953.sql` po fresh `docker compose down -v` |
| `make legacyImport` | re-run importů uvnitř existujícího schématu (DELETE crossings → highlines/users/crossings truncate) |
| `make legacyImportFresh` | drop + create + migrate + full import (předpokládá nahraný legacy dump) |

**Produkce (SSH na `beta.slack.cz`):**

| Target | Co dělá |
|---|---|
| `make setupServer` | `ssh deploy@HOST 'bash -s' < scripts/setup-server.sh` — idempotentní provisioning. Doinstaluje apt packages, vytvoří chybějící adresáře + ACL. Pro fresh louku spusť skript ručně jako root, viz `deploy.md`. |
| `make checkServerEnv` | Preflight skript přes SSH — verifikuje že server má vše co lokální HEAD potřebuje (PHP ext, FS perms, .env.local klíče, …). Exit 1 zablokuje `make deploy`. Pusť i ručně před `git push` pro fast feedback. |
| `make checkCaddy` | Drift check `infra/Caddyfile` (repo) vs `/etc/caddy/Caddyfile` (server). Exit 1 = drift, `make deploy` zablokovaný. Volá se taky implicitně z `make deploy` jako preflight gate. |
| `make deployCaddy` | Push `infra/Caddyfile` na server: scp → `caddy validate` → atomic cp → `systemctl restart caddy` + smoke test. Spusť kdykoliv změníš `infra/Caddyfile`. |
| `make deploy` | Závisí na `checkServerEnv` + `checkCaddy`. Po úspěšných preflightech: `ssh deploy@HOST 'bash -s' < scripts/deploy.sh` + post-deploy smoke test. |
| `make syncBetaFromLocal` | pg_dump lokál → scp → psql restore + cache:clear na `beta.slack.cz` (destruktivní, viz `deploy.md`) |

Detail flow + script ecosystem v `deploy.md` sekce „Infrastruktura na první pohled".

### Časté konzole příkazy

```bash
# vyčistit cache (NUTNÉ po změnách Twig templates / config)
docker compose exec -T php bin/console cache:clear --env=dev

# vyčistit konkrétní cache pool (např. feed cache)
docker compose exec -T php bin/console cache:pool:clear cache.app

# debug routes / parameters / DI / mapping
docker compose exec -T php bin/console debug:router
docker compose exec -T php bin/console debug:container --parameter=...
docker compose exec -T php bin/console doctrine:mapping:info --em=default
docker compose exec -T php bin/console doctrine:mapping:info --em=old

# importmap (asset mapper)
docker compose exec -T php bin/console importmap:require <package>

# logy
docker compose exec -T php tail -n 30 var/log/dev.log
docker compose exec -T php tail -n 30 var/log/dev.log | grep CRITICAL
```

## Databáze

### Postgres (nová app)

```bash
docker compose exec -T database psql -U app -d app -c "\dt"
docker compose exec -T database psql -U app -d app -c "SELECT COUNT(*) FROM highline;"
```

V Adminer: <http://localhost:8080/?pgsql=database&username=app&db=app> (heslo `password`).

### MySQL (legacy)

```bash
docker compose exec -T mysql mysql -uroot -proot old --default-character-set=utf8mb4 -e "SHOW TABLES;"
docker compose exec -T mysql mysql -uroot -proot old --default-character-set=utf8mb4 -e "SELECT COUNT(*) FROM highline;"
```

V Adminer: <http://localhost:8080/?server=mysql&username=root&db=old> (heslo `root`).

> ⚠ MySQL klient neumí Postgres syntax — `COUNT(*) FILTER (WHERE ...)` nefunguje, použít `SUM(condition)`.

## Migrace + import

### Doctrine migrations

```bash
docker compose exec -T php bin/console make:migration --no-interaction      # generování
docker compose exec -T php bin/console doctrine:migrations:migrate -n        # spuštění
docker compose exec -T php bin/console doctrine:migrations:status            # přehled
```

> ⚠ Po přidání entity do `src/Entity/` zkontroluj, že `make:migration` negeneruje migraci pro něco z `App\Old\Entity\*`. Pokud ano → entita patří do `src/Old/Entity/`, viz `architecture.md`.

### Legacy import — fresh end-to-end

> ⚠ **Při úplně čistém startu** (např. po `docker compose down -v` nebo po reinstalu Dockeru) je MySQL volume prázdný — legacy dump se musí jednorázově nahrát, jinak importy padnou na "table doesn't exist". Compose neudělá nic auto-loadu.

```bash
# 0) Pokud je legacy MySQL prázdný, nahraj dump:
docker compose exec -T mysql sh -c "mysql -uroot -proot old --default-character-set=utf8mb4" < slackcz_44953.sql

# 1) Nová DB:
docker compose exec -T php bin/console doctrine:database:drop --force --if-exists
docker compose exec -T php bin/console doctrine:database:create
docker compose exec -T php bin/console doctrine:migrations:migrate -n

# 2) Importy v tomhle pořadí (kvůli FK):
docker compose exec -T php bin/console app:import:highlines --truncate
docker compose exec -T php bin/console app:import:users --truncate
docker compose exec -T php bin/console app:import:crossings --truncate
```

Nebo `make legacyImportFresh` (vyžaduje, aby MySQL měla dump nahrátý — krok 0 výše dělá jen jednorázově po smazání volumes).

## Účty / správa uživatelů

```bash
# Seznam všech uživatelů (id, email, nick, verified, active)
docker compose exec -T php bin/console app:user:list

# Filtr substringem na email/nick
docker compose exec -T php bin/console app:user:list -s pepa

# Jen neaktivovaní (isVerified=false)
docker compose exec -T php bin/console app:user:list --unverified

# Vygenerovat password-reset URL pro usera (email nebo id).
# Použij když mailer nejede (dev = Mailpit místo reálných mailů, prod beta = null://null).
# Vrátí absolutní URL — domain se bere z framework.router.default_uri (= DEFAULT_URI env).
docker compose exec -T php bin/console app:user:reset-password panda@example.com
docker compose exec -T php bin/console app:user:reset-password 42

# Grant/revoke ROLE_ADMIN
docker compose exec -T php bin/console app:admin:grant <email>
docker compose exec -T php bin/console app:admin:grant <email> --revoke
```

> `app:user:reset-password` reálně zapíše `ResetPasswordRequest` do DB (token s lifetime z bundlu) — token je jednorázový a vyprší stejně jako kdyby přišel mailem. Workflow přes `SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface`, žádný hack.

## Smoke testy v terminálu

```bash
# stránka odpovídá?
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/mapa

# vidět konkrétní content
curl -s http://localhost:8000/mapa | grep -oE 'feed-item|panel-map' | sort -u

# JSON endpoint — všechny highlines pro markery
curl -s http://localhost:8000/mapa/data | head -c 500

# JSON endpoint — N posledních přechodů (sidebar feed + emoji markery na mapě)
curl -s http://localhost:8000/mapa/feed | head -c 500

# JSON endpoint — time-travel okno (-7 dní od daného data)
curl -s "http://localhost:8000/mapa/feed?date=2025-09-01&days=7" | head -c 500

# asset existuje (po importmap:require / asset()):
curl -s http://localhost:8000/$(curl -s http://localhost:8000/ | grep -oE 'assets/images/[^"]+' | head -1) -o /dev/null -w "%{http_code}\n"
```

## Asset Mapper / Twig — známé bolístky

- **Po editaci Twig template** v dev mode se občas drží stará verze v `var/cache`. Když se chová divně → `cache:clear --env=dev`.
- **Twig filtry, které NEjsou nainstalované** (a budou házet 500):
  - `format_datetime` (potřebuje `twig/intl-extra`) — místo toho použij `|date('j. n. Y')`
  - `|u.truncate(...)` (potřebuje `twig/string-extra`) — místo toho použij CSS `-webkit-line-clamp`
- **Leaflet markery** mají v Asset Mapperu rozbité default ikony → musí se URL předat přes Stimulus values, viz `assets/controllers/map_controller.js` + `templates/pages/mapa.html.twig`. Pro plnohodnotnou mapu se 254 lajnami + time-travel use `map_controller`; pro slim single-line view (např. detail) je `highline_detail_map_controller`.
- **Re-import highlines vs. crossings**: `legacyImport` target v Makefile padne, pokud už existují crossings (FK z crossings → highline brání DELETE). Order pro re-run uvnitř existujícího schématu: `dbal:run-sql "DELETE FROM highline_crossing"` → `app:import:highlines --truncate` → `app:import:crossings --truncate`. Nebo nech to udělat `legacyImportFresh` (drop + create + migrate + full import).
- **Asset URL hashe** se mění při edit → curl test musí parsovat URL z HTML, ne hardcodovat.
- **Limit posledních přechodů** je centralizovaný v `App\Repository\HighlineCrossingRepository::RECENT_LIMIT`. Měň jen tam — propíše se na index page stripe, mapové emoji markery i sidebar feed. Repo metody `findRecent()` a `findRecentForJson()` ho přebírají defaultem.

## Feed (Slack.cz TV)

```bash
# vyčistit jen feed cache (nedělej cache:clear jen kvůli feedu — to drahá operace)
docker compose exec -T php bin/console cache:pool:clear cache.app

# změnit channels/queries: edituj config/packages/feed.yaml + cache:clear
# přidat / vyměnit YOUTUBE_API_KEY: edituj .env.local + cache:pool:clear cache.app
```

Quota math: každá search query = 100 units / fetch. Default cache TTL = 1800 s (30 min). 1 query × 30 min cache = 4 800 units / den / query. Free limit 10 000/den ⇒ pohodlně 2 search queries + libovolný počet channels (channel = 2 units).

## Známé containery / služby co máš pod prsty

| Co | URL |
|---|---|
| App | <http://localhost:8000/> |
| Mapa | <http://localhost:8000/mapa> |
| Detail lajny | <http://localhost:8000/highline/{slug}> (např. `cimburi-cimbuline`) |
| Deník uživatele | <http://localhost:8000/denik/{id}> |
| O projektu | <http://localhost:8000/o-projektu> |
| Adminer (PG) | <http://localhost:8080/?pgsql=database&username=app&db=app> |
| Adminer (MySQL legacy) | <http://localhost:8080/?server=mysql&username=root&db=old> |
| Mailpit UI | `docker compose port mailer 8025` → otevři ten port (host port je dynamický) |
