dcSetup:
	docker compose run php composer install

dcInitDb:
	docker compose run php bin/console doctrine:database:create --if-not-exists
	docker compose run php bin/console doctrine:migrations:migrate --no-interaction

dcClearCache:
	docker compose run php bin/console cache:clear --no-warmup

# Compile AssetMapper output to public/assets/. Required in prod —
# without it Symfony can't resolve the manifest and returns 500.
dcAssetMapCompile:
	docker compose run php bin/console asset-map:compile

# Clear+warm prod cache explicitly, regardless of .env.local APP_ENV.
dcClearCacheProd:
	docker compose run -e APP_ENV=prod php bin/console cache:clear

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

# Push current local Postgres data to beta production (beta.slack.cz).
# DESTRUCTIVE: drops + recreates all app tables on beta. Use until proper
# legacy import runs natively on prod. Flow:
#   1) pg_dump z lokálního kontejneru (plain SQL, --clean --if-exists, no owner)
#   2) scp na deploy@178.105.81.158:/tmp/
#   3) psql restore + cache:clear přes scripts/sync-beta-restore.sh
syncBetaFromLocal:
	@echo "→ pg_dump z slack-cz-database-1..."
	docker exec slack-cz-database-1 pg_dump -U app -d app \
		--clean --if-exists --no-owner --no-privileges --no-acl > /tmp/slack-cz.sql
	@echo "→ scp na betu..."
	scp -i ~/.ssh/slack_cz_prod /tmp/slack-cz.sql deploy@178.105.81.158:/tmp/slack-cz.sql
	@echo "→ restore + cache:clear na betě..."
	ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158 'bash -s' < scripts/sync-beta-restore.sh
	@rm /tmp/slack-cz.sql
	@echo "✓ Beta data synced from local."

# Deploy aktuálního `main` na beta.slack.cz (jen pull+build na serveru, NEpushuje).
# Předpoklad: commit + push do origin/main už máš hotový.
#   make deploy
deploy:
	@echo "→ deploying origin/main na beta.slack.cz..."
	ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158 'bash -s' < scripts/deploy.sh
	@echo "→ smoke test:"
	@for path in / /mapa /wiki /docs /o-projektu; do \
		printf '  %s  %s\n' "$$(curl -s -o /dev/null -w '%{http_code}' https://beta.slack.cz$$path)" "$$path"; \
	done
	@echo "✓ Hotovo. https://beta.slack.cz"