dcSetup:
	docker compose run php composer install

dcInitDb:
	docker compose run php bin/console doctrine:database:create --if-not-exists
	docker compose run php bin/console doctrine:migrations:migrate --no-interaction

dcClearCache:
	docker compose run php bin/console cache:clear --no-warmup

# Loads legacy MySQL dump into the `mysql` container. Needed only after a fresh
# `docker compose down -v` or first-time setup — the dump doesn't auto-load.
loadLegacyDump:
	docker compose exec -T mysql sh -c "mysql -uroot -proot old --default-character-set=utf8mb4" < slackcz_44953.sql

# Re-run all legacy imports inside an existing schema. Truncate of `highline`
# fails when crossings still have FKs, so wipe crossings first.
legacyImport:
	docker compose exec -T php bin/console dbal:run-sql "DELETE FROM highline_crossing"
	docker compose exec -T php bin/console app:import:highlines --truncate
	docker compose exec -T php bin/console app:import:users --truncate
	docker compose exec -T php bin/console app:import:crossings --truncate

# Full fresh start: drop new DB, migrate, run all imports.
# Assumes legacy MySQL is already loaded (run `make loadLegacyDump` once after first `up`).
legacyImportFresh:
	docker compose exec -T php bin/console doctrine:database:drop --force --if-exists
	docker compose exec -T php bin/console doctrine:database:create
	docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec -T php bin/console app:import:highlines --truncate
	docker compose exec -T php bin/console app:import:users --truncate
	docker compose exec -T php bin/console app:import:crossings --truncate