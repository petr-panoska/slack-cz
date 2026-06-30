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

**Upload pipeline** (user uploady i legacy import): každá fotka projde `App\Service\PhotoNormalizer` → vytáhne datum+GPS přes `exiftool`, převede přes ImageMagick (`magick`) na **WebP master** (auto-orient, ≤ 2560 px, q85, strip metadat), HEIC z iPhonů včetně. Oboje je v `php` containeru (`docker/php/Dockerfile`: `imagemagick imagemagick-heic imagemagick-webp exiftool`) — **po editaci Dockerfile nezapomeň `docker compose build php`**, jinak `magick`/`exiftool` chybí a upload spadne. Upload limit je v `conf.d/uploads.ini` (32M) kvůli mobil fotkám — default `php.ini-development` má jen 2M. Detail pipeline v `architecture.md` § *Foto galerie — upload pipeline*.

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
| `make dcCheckRequirements` | `symfony check:requirements` v `php` containeru (preflight prostředí) |
| `make dcInitDb` | `doctrine:database:create --if-not-exists` + migrate |
| `make dcDropdb` | `doctrine:database:drop --force --if-exists` (reset před fresh importem — viz `migration.md`) |
| `make dcClearCache` | cache:clear --no-warmup (dle aktuálního `APP_ENV` v containeru / `.env.local`) |
| `make dcAssetMapCompile` | `bin/console asset-map:compile` — generuje `public/assets/manifest.json`. **NUTNÉ v prod** — bez něj 500 |
| `make dcClearCacheProd` | cache:clear s vynuceným `APP_ENV=prod` (i kdyby `.env.local` měl `APP_ENV=dev`) |
| `make loadLegacyDump` | jednorázový load `slackcz_44953.sql` po fresh `docker compose down -v` |
| `make legacyImport` | jednorázový import do čerstvého schématu (highlines/users/crossings `--truncate`); předpokládá nahraný legacy dump |
| `make stageLegacyPhotos` | rsync legacy foto stromu z `../old-slack-cz` do `var/legacy-import/` (mountnuté do `php` containeru, gitignored) |
| `make importLegacyPhotos` | stage + `app:import:line-photos --truncate` — cover + galerie do WebP masterů. Orphany / fotky smazaných lajn → `var/legacy-orphan-photos/`. Až **po** `legacyImport` (potřebuje highlines). |

**Produkce (SSH na `beta.slack.cz`):**

| Target | Co dělá |
|---|---|
| `make setupServer` | `ssh deploy@HOST 'bash -s' < scripts/setup-server.sh` — idempotentní provisioning. Doinstaluje apt packages, vytvoří chybějící adresáře + ACL. Pro fresh louku spusť skript ručně jako root, viz `deploy.md`. |
| `make checkServerEnv` | Preflight skript přes SSH — verifikuje že server má vše co lokální HEAD potřebuje (PHP ext, FS perms, .env.local klíče, …). Exit 1 zablokuje `make deploy`. Pusť i ručně před `git push` pro fast feedback. |
| `make checkCaddy` | Drift check `infra/Caddyfile` (repo) vs `/etc/caddy/Caddyfile` (server). Exit 1 = drift, `make deploy` zablokovaný. Volá se taky implicitně z `make deploy` jako preflight gate. |
| `make deployCaddy` | Push `infra/Caddyfile` na server: scp → `caddy validate` → atomic cp → `systemctl restart caddy` + smoke test. Spusť kdykoliv změníš `infra/Caddyfile`. |
| `make deploy` | Závisí na `checkServerEnv` + `checkCaddy`. Po úspěšných preflightech: `ssh deploy@HOST 'bash -s' < scripts/deploy.sh` + post-deploy smoke test. |
| `make syncBetaFromLocal` | pg_dump lokál → scp → psql restore + cache:clear na `beta.slack.cz` (destruktivní, viz `deploy.md`) |
| `make syncBetaPhotos` | rsync WebP masterů `public/uploads/line/` na betu (mimo `pg_dump` → samostatně). Páruje se se `syncBetaFromLocal`. |

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
docker compose exec -T database psql -U app -d app -c "SELECT COUNT(*) FROM line;"
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
docker compose exec -T php bin/console app:import:lines --truncate
docker compose exec -T php bin/console app:import:users --truncate
docker compose exec -T php bin/console app:import:line-crossings --truncate
docker compose exec -T php bin/console app:import:longline-crossings --truncate
```

Krok 2 (jen importy) je zabalený v `make legacyImport`. Pro zopakování celého: dropni novou DB (krok 1), nahraj legacy dump (krok 0), pak `make legacyImport`.

**Fotky jsou separátní krok** (potřebují legacy *soubory* z `../old-slack-cz`, ne jen DB dump):

```bash
make importLegacyPhotos   # rsync ../old-slack-cz → var/legacy-import + app:import:line-photos --truncate
```

Importuje cover (`foto.jpg`) + galerii (`highline_foto`) jako **WebP mastery** do `public/uploads/line/<id>/`. Orphany (soubory na disku bez DB řádku) a fotky smazaných lajn se neimportují — vyexpedují se do `var/legacy-orphan-photos/` a reportnou. Detail modelu (cover, datum, GPS, normalizace) v `migration.md` § *Line photos* a `architecture.md` § *Foto galerie — upload pipeline*. Běž až **po** `make legacyImport` (fotka se váže na highline přes `legacyId`).

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

## Testy (PHPUnit)

`tests/Controller/PublicPagesSmokeTest.php` — smoke testy DB-free veřejných rout (kernel nabootuje, routing + security + Twig fungují). Běží na SQLite bez schématu, stejně jako CI workflow `.github/workflows/symfony.yml` — nepotřebují Postgres ani síť.

```bash
# Lokálně 1:1 jako CI (SQLite, test env):
docker compose exec -T php sh -c '
  mkdir -p data && touch data/database.sqlite
  DATABASE_URL="sqlite:///%kernel.project_dir%/data/database.sqlite" APP_ENV=test \
  vendor/bin/phpunit
'
```

> `phpunit.dist.xml` má `failOnDeprecation/Notice/Warning=true` → test, který spustí **přímou** (naši) deprecation, shodí build. Proto se do smoke testů zatím nedávají form-rendering routy (`/registrace` apod. — `RegistrationForm` má array-option constraints; viz `roadmap.md`). Vendor (indirect) deprecations se ignorují (`ignoreIndirectDeprecations="true"`).

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
- **Leaflet markery** mají v Asset Mapperu rozbité default ikony → musí se URL předat přes Stimulus values, viz `assets/controllers/map_controller.js` + `templates/pages/map.html.twig`. Pro plnohodnotnou mapu se 254 lajnami + time-travel use `map_controller`; pro slim single-line view (např. detail) je `line_detail_map_controller`.
- **Legacy import je jednorázová lokální věc na čisté schéma.** Spouští se při nahazování projektu (`make legacyImport`), kdy je DB prázdná — proto FK z crossings → highline nevadí (žádné crossings ještě nejsou). Když to chceš zopakovat: dropni novou DB, znovu nahraj legacy dump (adminer / `make loadLegacyDump`), pak `make legacyImport`.
- **Asset URL hashe** se mění při edit → curl test musí parsovat URL z HTML, ne hardcodovat.
- **Limit posledních přechodů** je centralizovaný v `App\Repository\LineCrossingRepository::RECENT_LIMIT`. Měň jen tam — propíše se na index page stripe, mapové emoji markery i sidebar feed. Repo metody `findRecent()` a `findRecentForJson()` ho přebírají defaultem.

## Feed (slackTV)

Prezentace: teaser panel na `/` + dedikovaná stránka `/tv` (`app_tv`). `/tv` má click-to-play (`tv_controller.js`, youtube-nocookie embed na klik).

```bash
# vyčistit jen feed cache (nedělej cache:clear jen kvůli feedu — to drahá operace)
docker compose exec -T php bin/console cache:pool:clear cache.app

# změnit channels/queries: edituj config/packages/feed.yaml + cache:clear
# řadit zdroj od nejstarších: místo holého id dej `{ id: '@@x', sort: asc }` (default desc)
# přidat / vyměnit YOUTUBE_API_KEY: edituj .env.local + cache:pool:clear cache.app
```

Quota math: každá search query = 100 units / fetch. Default cache TTL = **21600 s (6 h)** (`feed.cache.ttl_seconds` ve `feed.yaml`) ⇒ 4 misses/den ⇒ **400 units/den/query**. Free limit 10 000/den, takže aktuální config (1 query, 0 kanálů) jede s ~25× rezervou — prostor pro víc queries i kanálů (channel = 2 units). Detailní vzorec + historie přepalu kvóty v `architecture.md` § *Quota economics*.

## Známé containery / služby co máš pod prsty

| Co | URL |
|---|---|
| App | <http://localhost:8000/> |
| Mapa | <http://localhost:8000/mapa> |
| Detail lajny | <http://localhost:8000/lajna/{slug}> (např. `cimburi-cimbuline`) |
| Deník uživatele | <http://localhost:8000/denik/{id}> |
| O projektu | <http://localhost:8000/o-projektu> |
| Adminer (PG) | <http://localhost:8080/?pgsql=database&username=app&db=app> |
| Adminer (MySQL legacy) | <http://localhost:8080/?server=mysql&username=root&db=old> |
| Mailpit UI | `docker compose port mailer 8025` → otevři ten port (host port je dynamický) |
