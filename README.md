# slack-cz

## Docker development

### Install

Requires Docker Engine 24+ with Compose v2, on Linux (incl. WSL2) or macOS.

```bash
git clone <repo-url> slack-cz
cd slack-cz
make first-run
```

That's it. `make first-run` copies `.env.local.example` to `.env.local`, brings up the stack with `--wait`, installs composer dependencies, and runs all migrations. Idempotent — safe to re-run.

### URLs

| What | URL |
|---|---|
| App | http://localhost:8000 |
| Mailpit (mail UI) | http://localhost:8025 |
| Adminer (DB UI) | http://localhost:8080/?pgsql=database&username=app&db=app |

### Common operations

```bash
make help        # list all Make targets
make up          # start the stack (default profile)
make down        # stop, preserve data volumes
make nuke        # stop AND wipe data volumes — destructive
```

### Optional: legacy MySQL import

The legacy MySQL service is opt-in via a compose profile. Targets that need it (`make loadLegacyDump`, `make legacyImport*`) bring it up automatically. To start it manually:

```bash
docker compose --profile legacy up -d --wait mysql
```

### Troubleshooting

Check Symfony environment requirements:

```bash
docker compose run --rm php php bin/console about
```

See [`docs/dev.md`](docs/dev.md) for the full operational reference (console commands, debugging, smoke tests).

## Old database

### Import data
- mysql database should be created automatically when running docker container
- database can be created with Doctrine (e.g. `docker compose run php bin/console doctrine:database:create --connection=old`)
- access database via [adminer](http://localhost:8080/?server=mysql&username=root&db=old)
- use adminer to import source dump (`*.sql.gz`)

### Entities
- legacy entities are mapped with Doctrine and live in `src/Old/Entity` (namespace `App\Old\Entity`); they are kept outside `src/Entity` so the default Postgres EM does not pick them up

## Documentation

Internal docs live in [`docs/`](docs/):
- [`architecture.md`](docs/architecture.md) — what is built and key decisions
- [`migration.md`](docs/migration.md) — legacy data import strategy
- [`crossing-styles.md`](docs/crossing-styles.md) — highline crossing styles taxonomy
- [`dev.md`](docs/dev.md) — operational cheat sheet (Docker, console, DB, cache, smoke tests)
- [`todo.md`](docs/todo.md) — open work items