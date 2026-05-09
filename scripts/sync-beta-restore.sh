#!/usr/bin/env bash
# Restore /tmp/slack-cz.sql into the beta Postgres DB and clear app cache.
# Runs on the production VPS as user `deploy`. Invoked via SSH from
# `make syncBetaFromLocal`.
#
# Gotcha: psql rejects Doctrine-specific query params (serverVersion, charset)
# in the URL, so we strip everything after `?` before connecting.
set -euo pipefail

cd /var/www/slack-cz

DB_URL=$(grep -E '^DATABASE_URL=' .env.local \
  | sed -E 's/^DATABASE_URL=//; s/^"//; s/"$//; s/\?.*$//')

if [ -z "$DB_URL" ]; then
  echo "ERROR: DATABASE_URL not found in /var/www/slack-cz/.env.local" >&2
  exit 1
fi

echo "→ psql restore /tmp/slack-cz.sql"
psql "$DB_URL" -v ON_ERROR_STOP=1 -f /tmp/slack-cz.sql > /dev/null

echo "→ APP_ENV=prod cache:clear (as www-data)"
sudo -u www-data bash -c \
  "cd /var/www/slack-cz && APP_ENV=prod php bin/console cache:clear --no-warmup" \
  > /dev/null

echo "→ Row counts:"
psql "$DB_URL" -c "
SELECT 'highline' AS t, count(*) FROM highline
UNION ALL SELECT 'highline_crossing', count(*) FROM highline_crossing
UNION ALL SELECT 'user', count(*) FROM \"user\"
UNION ALL SELECT 'doctrine_migration_versions', count(*) FROM doctrine_migration_versions
ORDER BY t;
"

rm /tmp/slack-cz.sql
