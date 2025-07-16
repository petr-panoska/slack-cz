# slack-cz

## Docker development

### Install
```
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