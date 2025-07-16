dcSetup:
	docker compose run php composer install

dcInitDb:
	docker compose run php bin/console doctrine:database:create --if-not-exists
	docker compose run php bin/console doctrine:migrations:migrate --no-interaction