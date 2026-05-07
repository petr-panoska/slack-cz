# Dev cookbook

Rychlý reference pro běžné operace. Když si nepamatuješ, koukni sem.

## Containery (Docker compose)

| Service | Port (host) | Účel |
|---|---|---|
| `apache` | 8000 | hlavní web |
| `database` | 40219 | Postgres 16 (new app DB) |
| `mysql` | – | legacy MySQL (interně 3306) |
| `adminer` | 8080 | DB UI |
| `mailer` | 42039 (UI), 37623 (SMTP) | Mailpit |
| `php` | – | PHP-FPM (sdílí volume s apache) |

```bash
docker compose ps                  # status
docker compose up -d               # start (detached)
docker compose down                # stop
docker compose logs -f apache      # tail logs konkrétní service
```

## Symfony console

Vždy přes `php` container:

```bash
docker compose exec -T php bin/console <cmd>
```

Časté:

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

```bash
docker compose exec -T php bin/console doctrine:database:drop --force --if-exists
docker compose exec -T php bin/console doctrine:database:create
docker compose exec -T php bin/console doctrine:migrations:migrate -n
docker compose exec -T php bin/console app:import:highlines --truncate
# (později po implementaci):
docker compose exec -T php bin/console app:import:users --truncate
docker compose exec -T php bin/console app:import:crossings --truncate
```

## Smoke testy v terminálu

```bash
# stránka odpovídá?
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/mapa

# vidět konkrétní content
curl -s http://localhost:8000/mapa | grep -oE 'feed-item|panel-map' | sort -u

# JSON endpoint
curl -s http://localhost:8000/mapa/data | head -c 500

# asset existuje (po importmap:require / asset()):
curl -s http://localhost:8000/$(curl -s http://localhost:8000/ | grep -oE 'assets/images/[^"]+' | head -1) -o /dev/null -w "%{http_code}\n"
```

## Asset Mapper / Twig — známé bolístky

- **Po editaci Twig template** v dev mode se občas drží stará verze v `var/cache`. Když se chová divně → `cache:clear --env=dev`.
- **Twig filtry, které NEjsou nainstalované** (a budou házet 500):
  - `format_datetime` (potřebuje `twig/intl-extra`) — místo toho použij `|date('j. n. Y')`
  - `|u.truncate(...)` (potřebuje `twig/string-extra`) — místo toho použij CSS `-webkit-line-clamp`
- **Leaflet markery** mají v Asset Mapperu rozbité default ikony → musí se URL předat přes Stimulus values, viz `assets/controllers/map_controller.js` + `templates/pages/mapa.html.twig`. Když přidáš novou mapu, **použij stejný controller** (ne kopii).
- **Asset URL hashe** se mění při edit → curl test musí parsovat URL z HTML, ne hardcodovat.

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
| O projektu | <http://localhost:8000/o-projektu> |
| Adminer (PG) | <http://localhost:8080/?pgsql=database&username=app&db=app> |
| Adminer (MySQL legacy) | <http://localhost:8080/?server=mysql&username=root&db=old> |
| Mailpit UI | <http://localhost:42039/> |
