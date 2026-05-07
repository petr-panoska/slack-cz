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