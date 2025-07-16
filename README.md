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
- entities are mapped with Doctrine and lives in `src/Entity/Old` dir