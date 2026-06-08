# slack-cz

## Docker development

### Install
```
docker compose up -d
make dcSetup
make dcInitDb
```

app is running at [localhost:8000](http://localhost:8000)

you can access database via adminer at [localhost:8080](http://localhost:8080/?pgsql=database&username=app&db=app) (get credentials from `.env`)

### Troubleshooting
check symfony environment status:
```
docker compose run php symfony check:requirements
```

## Migrate data from legacy database

### Export data from legacy Mysql db
- `https://adminer17.vas-hosting.cz/?server=localhost&username=slackcz.44953&db=slackcz_44953&dump=`
- keep default export settings
- do not export `log` and `pristupy` tables
- save the dump as `slackcz_44953.sql` in the project root (plain SQL, not gzipped)

### Import data to local Mysql db
- the `old` MySQL database is created automatically with the `mysql` container
  (fallback: `docker compose run php bin/console doctrine:database:create --connection=old`)
- load the dump — needed after first setup or any `docker compose down -v`:
  ```
  make loadLegacyDump
  ```
- alternatively import it by hand via [adminer](http://localhost:8080/?server=mysql&username=root&db=old)

### Run import to Postgres
- `make legacyImport`

## Documentation

Internal docs live in [`docs/`](docs/) (index: [`docs/README.md`](docs/README.md)):
- [`architecture.md`](docs/architecture.md) — what is built, code structure, key decisions
- [`migration.md`](docs/migration.md) — legacy data import strategy
- [`crossing-styles.md`](docs/crossing-styles.md) — highline crossing styles taxonomy
- [`highline-edits.md`](docs/highline-edits.md) — trust model + proposal queue for highline edits
- [`audio-player.md`](docs/audio-player.md) — slackvibes 📻 player internals
- [`dev.md`](docs/dev.md) — operational cheat sheet (Docker, console, DB, cache, smoke tests)
- [`deploy.md`](docs/deploy.md) — production (Hetzner VPS, Caddy, `make deploy`, CI)
- [`roadmap.md`](docs/roadmap.md) — strategic decisions (Symfony 8.x upgrade assessment)
- [`todo.md`](docs/todo.md) — open work items