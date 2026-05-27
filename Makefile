# DC = docker compose invocation with the same env-file chain as a bare
# `docker compose` would use given `export COMPOSE_ENV_FILES=.env,.env.local`.
# Docker Compose auto-loads .env; COMPOSE_ENV_FILES layers .env.local on top so
# overrides (APP_PORT, POSTGRES_PASSWORD, …) take effect on BOTH paths identically.
# UID/GID match the host user so bind-mount writes land owned by you.
ENV_FILES := $(shell [ -f .env.local ] && echo .env,.env.local || echo .env)
DC = COMPOSE_ENV_FILES=$(ENV_FILES) UID=$$(id -u) GID=$$(id -g) docker compose

# Bootstrap a freshly cloned repo to a working localhost in one step.
# Idempotent — safe to re-run anytime. After completion the app is at
# http://localhost:$${APP_PORT:-8000}. Sets host UID/GID so bind-mounted
# files end up owned by you (no chmod needed).
.PHONY: first-run
first-run: ## Bootstrap a fresh clone (env + stack + db + migrations)
	@test -f .env.local || cp .env.local.example .env.local
	@echo "→ starting stack…"
	@$(DC) up -d --wait
	@echo "→ composer install…"
	@$(DC) exec -T php composer install --no-interaction
	@echo "→ database create + migrate…"
	@$(DC) exec -T php bin/console doctrine:database:create --if-not-exists
	@$(DC) exec -T php bin/console doctrine:migrations:migrate --no-interaction
	@echo
	@$(DC) ps --format 'table {{.Service}}\t{{.Status}}\t{{.Ports}}'
	@echo
	@set -a; [ -f .env.local ] && . ./.env.local; set +a; \
	 echo "✓ App     http://localhost:$${APP_PORT:-8000}"; \
	 echo "✓ Mailpit http://localhost:$${SMTP_UI_PORT:-8025}"; \
	 echo "✓ Adminer http://localhost:$${ADMINER_PORT:-8080}/?pgsql=database&username=app&db=app"

# Start the dev stack (default profile only). Honors host UID/GID for bind-mount permissions.
.PHONY: up
up: ## Start the dev stack (default profile)
	@$(DC) up -d --wait

# Stop the dev stack but preserve data volumes.
.PHONY: down
down: ## Stop the dev stack (preserve volumes)
	@$(DC) down

# Stop the dev stack AND wipe all data volumes. Destructive — loses DB data.
.PHONY: nuke
nuke: ## Stop AND wipe all data volumes (DESTRUCTIVE)
	@$(DC) down -v

# Self-documenting help — lists every target with a `## ` comment.
.PHONY: help
help: ## Show this help
	@awk 'BEGIN{FS=":.*?## "} /^[a-zA-Z][a-zA-Z0-9_-]*:.*?## /{printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Prefer `make first-run` for fresh clones. This target installs composer
# dependencies in an already-running stack.
dcSetup: ## composer install in running stack
	docker compose exec -T php composer install

dcInitDb: ## doctrine database:create + migrate (stack must be up)
	docker compose exec -T php bin/console doctrine:database:create --if-not-exists
	docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction

dcClearCache: ## cache:clear --no-warmup
	docker compose exec -T php bin/console cache:clear --no-warmup

# Compile AssetMapper output to public/assets/. Required in prod —
# without it Symfony can't resolve the manifest and returns 500.
dcAssetMapCompile: ## asset-map:compile (produces public/assets/manifest.json)
	docker compose exec -T php bin/console asset-map:compile

# Clear+warm prod cache explicitly, regardless of .env.local APP_ENV.
dcClearCacheProd: ## cache:clear with APP_ENV=prod forced
	docker compose exec -T -e APP_ENV=prod php bin/console cache:clear

# Loads legacy MySQL dump into the `mysql` container. Needed only after a fresh
# `docker compose down -v` or first-time setup — the dump doesn't auto-load.
# Brings up the legacy profile transparently.
loadLegacyDump: ## Load slackcz_44953.sql into legacy MySQL
	@docker compose --profile legacy up -d --wait mysql
	docker compose --profile legacy exec -T mysql sh -c "mysql -uroot -proot old --default-character-set=utf8mb4" < slackcz_44953.sql

# Re-run all legacy imports inside an existing schema. Truncate of `highline`
# fails when crossings still have FKs, so wipe crossings first.
legacyImport: ## Re-run legacy imports inside existing schema
	@docker compose --profile legacy up -d --wait mysql
	docker compose exec -T php bin/console dbal:run-sql "DELETE FROM highline_crossing"
	docker compose exec -T php bin/console app:import:highlines --truncate
	docker compose exec -T php bin/console app:import:users --truncate
	docker compose exec -T php bin/console app:import:crossings --truncate

# Full fresh start: drop new DB, migrate, run all imports.
# Assumes legacy MySQL is already loaded (run `make loadLegacyDump` once after first `up`).
legacyImportFresh: ## Drop + create + migrate + full legacy import
	@docker compose --profile legacy up -d --wait mysql
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
syncBetaFromLocal: ## pg_dump local → restore on beta.slack.cz (destructive)
	@echo "→ pg_dump z database service..."
	docker compose exec -T database pg_dump -U app -d app \
		--clean --if-exists --no-owner --no-privileges --no-acl > /tmp/slack-cz.sql
	@echo "→ scp na betu..."
	scp -i ~/.ssh/slack_cz_prod /tmp/slack-cz.sql deploy@178.105.81.158:/tmp/slack-cz.sql
	@echo "→ restore + cache:clear na betě..."
	ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158 'bash -s' < scripts/sync-beta-restore.sh
	@rm /tmp/slack-cz.sql
	@echo "✓ Beta data synced from local."

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