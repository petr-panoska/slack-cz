#!/usr/bin/env bash
# Deploy app na beta.slack.cz (Hetzner VPS, native PHP-FPM + Postgres + Caddy).
# Spouští se na produkčním serveru jako user `deploy`. Volá se přes SSH z lokálu
# přes `make deploy`.
#
# Předpoklad: do main na GitHubu už je pushnuté všechno, co chceš nasadit.
# Tento script jen stáhne main, doinstaluje deps, spustí migrace, rebuilduje
# asset manifest, vyčistí cache, reloaduje php-fpm.
set -euo pipefail

cd /var/www/slack-cz

echo "→ git pull --ff-only origin main"
git pull --ff-only origin main

echo "→ composer install --no-dev --optimize-autoloader"
composer install --no-dev --optimize-autoloader --no-interaction

# Idempotentní mkdir pro adresáře co musí být writable PHP-FPM. ACL pro www-data
# se nastavuje 1× ručně (viz deploy.md). var/log si jinak Symfony vytváří lazy
# až při prvním logu, ale eager mkdir je čistší + nezakope checkServerEnv.
echo "→ mkdir -p public/uploads public/media/cache var/log"
mkdir -p public/uploads public/media/cache var/log

echo "→ doctrine:migrations:migrate"
APP_ENV=prod php bin/console doctrine:migrations:migrate -n

echo "→ asset-map:compile"
APP_ENV=prod php bin/console asset-map:compile

echo "→ cache:clear (prod) — composer hook už cache pre-warmnul, tohle ji invaliduje"
APP_ENV=prod php bin/console cache:clear

echo "→ cache:pool:clear cache.app (doctrine result cache + feed cache po deployi)"
APP_ENV=prod php bin/console cache:pool:clear cache.app

echo "→ systemctl reload php8.3-fpm (refresh opcache)"
sudo systemctl reload php8.3-fpm

echo "✓ Deploy hotový."
