dcSetup:
	docker compose run php composer install
	docker compose run php sh -c "mkdir -p /var/www/html/public/media/cache && chmod 777 /var/www/html/public/media /var/www/html/public/media/cache"

dcInitDb:
	docker compose run php bin/console doctrine:database:create --if-not-exists
	docker compose run php bin/console doctrine:migrations:migrate --no-interaction

dcDropdb:
	docker compose exec -T php bin/console doctrine:database:drop --force --if-exists

dcClearCache:
	docker compose run php bin/console cache:clear --no-warmup

# Compile AssetMapper output to public/assets/. Required in prod —
# without it Symfony can't resolve the manifest and returns 500.
dcAssetMapCompile:
	docker compose run php bin/console asset-map:compile

# Clear+warm prod cache explicitly, regardless of .env.local APP_ENV.
dcClearCacheProd:
	docker compose run -e APP_ENV=prod php bin/console cache:clear

# Ověří, že běhové prostředí (PHP verze + extensions) splňuje požadavky
# Symfony 7.4. Spouští se přes symfony-cli v php containeru.
#   make dcCheckRequirements
dcCheckRequirements:
	docker compose run --rm php symfony check:requirements

# Loads legacy MySQL dump into the `mysql` container. Needed only after a fresh
# `docker compose down -v` or first-time setup — the dump doesn't auto-load.
loadLegacyDump:
	docker compose exec -T mysql sh -c "mysql -uroot -proot old --default-character-set=utf8mb4" < slackcz_44953.sql

# One-time legacy import into a fresh local schema. Assumes the legacy MySQL dump
# is already loaded (`make loadLegacyDump`). To repeat: drop the DB, reload the dump,
# then run this again. Photos are a separate step (`make importLegacyPhotos`) because
# they need the legacy photo files staged from ../old-slack-cz, not just the DB dump.
legacyImport:
	docker compose exec -T php bin/console app:import:lines --truncate
	docker compose exec -T php bin/console app:import:users --truncate
	docker compose exec -T php bin/console app:import:highline-crossings --truncate
	docker compose exec -T php bin/console app:import:longline-crossings --truncate

# Stage the legacy photo tree (cover foto.jpg + foto/ galleries) from the sibling
# ../old-slack-cz download into var/legacy-import (bind-mounted into the php container,
# gitignored). Re-runnable; --delete keeps it in sync with the source.
stageLegacyPhotos:
	@test -d ../old-slack-cz/line/high || { echo "✗ Missing ../old-slack-cz/line/high (legacy photo download)"; exit 1; }
	mkdir -p var/legacy-import
	rsync -a --delete ../old-slack-cz/line var/legacy-import/
	@echo "✓ Legacy photos staged in var/legacy-import/line/high"

# Import legacy highline cover + gallery photos into the new schema. Prereqs:
# highlines already imported (`make legacyImport`) + legacy MySQL dump loaded.
# Stages the files, then runs the importer with --truncate (safe to re-run).
# Files land in public/uploads/line/<id>/; orphan + deleted-line photos are
# exported to var/legacy-orphan-photos/ for manual review (reported on stdout).
importLegacyPhotos: stageLegacyPhotos
	docker compose exec -T php bin/console app:import:line-photos --truncate

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

# Push lokálních WebP masterů fotek (public/uploads/line/) na betu. Tyhle soubory
# jsou mimo git i mimo pg_dump, takže je syncBetaFromLocal nedostane — doplní je rsync.
# Páruje se se syncBetaFromLocal (ten veze line_photo řádky + cover_photo_id).
# Push-only (BEZ --delete), ať nesmaže fotky nahrané přímo na betě.
# `public/uploads/line/` na betě vlastní www-data (tvoří ho PHP-FPM), deploy tam
# nemá write → remote rsync běží přes `sudo` (deploy má NOPASSWD sudo z provisioningu),
# a --chown sjednotí ownership na www-data:www-data. --chmod normalizuje perms (Caddy
# servíruje staticky, 644/755). Liip cache (public/media/cache/) NEsyncujem — vygeneruje se on-demand.
#   make syncBetaPhotos
syncBetaPhotos:
	@test -d public/uploads/line || { echo "✗ public/uploads/line neexistuje — spusť nejdřív 'make importLegacyPhotos'"; exit 1; }
	@echo "→ rsync WebP masterů na betu (přes sudo rsync)..."
	rsync -avz --human-readable --chmod=D755,F644 --chown=www-data:www-data \
		--rsync-path="sudo rsync" -e "ssh -i ~/.ssh/slack_cz_prod" \
		public/uploads/line/ \
		deploy@178.105.81.158:/var/www/slack-cz/public/uploads/line/
	@echo "✓ Foto mastery synced. (Liip cache se na betě vygeneruje při prvním requestu.)"

# Provisioning serveru (idempotentní). Update-za-chodu mode — pustí
# `scripts/setup-server.sh` na betač jako deploy uživatel. Doinstaluje nové
# apt packages / vytvoří chybějící adresáře + ACL. Pro fresh louku z root
# usera spusť skript ručně přes `ssh root@HOST 'bash -s' < scripts/setup-server.sh`
# (viz docs/deploy.md sekce "Server provisioning").
#   make setupServer
setupServer:
	ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158 'bash -s' < scripts/setup-server.sh

# Preflight check produkčního prostředí — verifikuje PHP extensions, FS perms,
# .env.local klíče, Postgres connect, atd. proti lokálnímu HEADu. Volá se
# automaticky z `make deploy` jako fail-fast gate. Lze pustit i samostatně:
#   make checkServerEnv
checkServerEnv:
	@LOCAL_HEAD=$$(git rev-parse HEAD); \
	ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158 'bash -s' -- "$$LOCAL_HEAD" \
		< scripts/check-server-env.sh

# Drift check pro /etc/caddy/Caddyfile na betě vs infra/Caddyfile v repu.
# Repo je kanonický zdroj — server musí sedět. Volá se taky implicitně
# z `make deploy` jako preflight gate. Exit 1 = drift, deploy se zastaví.
#   make checkCaddy
checkCaddy:
	@./scripts/check-caddy.sh

# Push infra/Caddyfile na betu (validate → cp → restart caddy + smoke test).
# Spusť kdykoliv změníš infra/Caddyfile a chceš to mít živé.
#   make deployCaddy
deployCaddy:
	@./scripts/deploy-caddy.sh

# Deploy aktuálního `main` na beta.slack.cz (jen pull+build na serveru, NEpushuje).
# Předpoklad: commit + push do origin/main už máš hotový.
#   make deploy
# Spustí nejdřív preflighty (`checkServerEnv` + `checkCaddy`) — pokud server
# nemá co potřebuje, nebo Caddyfile drift-uje, deploy se nezačne.
deploy: checkServerEnv checkCaddy
	@echo "→ deploying origin/main na beta.slack.cz..."
	ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158 'bash -s' < scripts/deploy.sh
	@echo "→ smoke test:"
	@for path in / /mapa /wiki /docs /o-projektu; do \
		printf '  %s  %s\n' "$$(curl -s -o /dev/null -w '%{http_code}' https://beta.slack.cz$$path)" "$$path"; \
	done
	@echo "✓ Hotovo. https://beta.slack.cz"