dcSetup:
	docker compose run php composer install

dcInitDb:
	docker compose run php bin/console doctrine:database:create --if-not-exists
	docker compose run php bin/console doctrine:migrations:migrate --no-interaction

dcClearCache:
	docker compose run php bin/console cache:clear --no-warmup

# Run all legacy imports (preserves existing user accounts via enrich logic)
legacyImport:
	docker compose exec -T php bin/console app:import:highlines --truncate
	docker compose exec -T php bin/console app:import:users --truncate
	docker compose exec -T php bin/console app:import:crossings --truncate

# Full fresh start: drop DB, migrate, run all imports
legacyImportFresh:
	docker compose exec -T php bin/console doctrine:database:drop --force --if-exists
	docker compose exec -T php bin/console doctrine:database:create
	docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec -T php bin/console app:import:highlines --truncate
	docker compose exec -T php bin/console app:import:users --truncate
	docker compose exec -T php bin/console app:import:crossings --truncate