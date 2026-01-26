dcSetup:
	docker compose run --rm php composer install

dcInitDb:
	docker compose run --rm php bin/console doctrine:database:create --if-not-exists
	docker compose run --rm php bin/console doctrine:migrations:migrate --no-interaction

dcInitDbOld:
	docker compose run --rm php bin/console doctrine:database:create --if-not-exists --connection=old