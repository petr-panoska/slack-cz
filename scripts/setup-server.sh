#!/usr/bin/env bash
# scripts/setup-server.sh — provisioning ze zelené louky + idempotentní update.
#
# Předpoklad: Ubuntu 24.04 (Hetzner Cloud default image). PHP 8.3 + Postgres 16
# jsou v default apt repos, Caddy přidává vlastní apt repo (script ho přidá).
#
# Co skript NEDĚLÁ (úmyslně, citlivost):
#   - nepřepisuje .env.local (jedinečný APP_SECRET + DB heslo)
#   - nedropuje žádné Postgres role / DB / data
#   - nezasahuje do /etc/caddy/Caddyfile (manuální krok, viz docs/deploy.md)
#
# Usage:
#   # Fresh louka — z root usera (Hetzner default po prvním boote):
#   ssh root@HOST 'bash -s' < scripts/setup-server.sh
#
#   # Update za chodu — z deploy usera (po prvním setupu, když přibyla závislost):
#   ssh deploy@HOST 'bash -s' < scripts/setup-server.sh
#
# Exit 0 = vše OK, exit 1 = vyžaduje manuální zásah (viz výpis).
set -euo pipefail

# Apt noninteractive globálně. Inline `$SUDO VAR=val cmd` prefix nefunguje, když
# $SUDO je prázdný (root) — bash parser to bere jako command name.
export DEBIAN_FRONTEND=noninteractive

# ===== parametry =====
APP_DIR="/var/www/slack-cz"
APP_USER="deploy"
APP_REPO="https://github.com/petr-panoska/slack-cz.git"
PHP_FPM_USER="www-data"
DB_ROLE="slack_cz"
DB_NAME="slack_cz"

# ===== helpers =====
log()  { printf '\n→ %s\n' "$1"; }
ok()   { printf '  ✓ %s\n' "$1"; }
skip() { printf '  · %s (skip)\n' "$1"; }
note() { printf '  · %s\n' "$1"; }
die()  { printf '\n✗ %s\n' "$1" >&2; exit 1; }

# ===== sudo preflight =====
if [[ $EUID -eq 0 ]]; then
    SUDO=""
elif sudo -n true 2>/dev/null; then
    SUDO="sudo"
else
    die "potřebuje root nebo NOPASSWD sudo"
fi

# ===========================================================================
log "OS detection"
# ===========================================================================
. /etc/os-release
if [[ "${ID:-}" != "ubuntu" ]] || [[ "${VERSION_ID%%.*}" -lt 24 ]]; then
    die "testovaný na Ubuntu 24.04+. Detekováno: ${PRETTY_NAME:-?}"
fi
ok "$PRETTY_NAME"

# ===========================================================================
log "APT prerequisites + Caddy repo"
# ===========================================================================
$SUDO apt update -qq
$SUDO apt install -y \
    debian-keyring debian-archive-keyring apt-transport-https curl gnupg ca-certificates

CADDY_KEYRING="/usr/share/keyrings/caddy-stable-archive-keyring.gpg"
CADDY_LIST="/etc/apt/sources.list.d/caddy-stable.list"
if [[ -f "$CADDY_KEYRING" && -f "$CADDY_LIST" ]]; then
    skip "Caddy apt repo present"
else
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' \
        | $SUDO gpg --dearmor -o "$CADDY_KEYRING"
    curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' \
        | $SUDO tee "$CADDY_LIST" >/dev/null
    $SUDO apt update -qq
    ok "Caddy apt repo added"
fi

# ===========================================================================
log "APT packages"
# ===========================================================================
PKGS=(
    php8.3-fpm php8.3-cli
    php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl
    php8.3-zip php8.3-intl php8.3-opcache php8.3-readline
    php8.3-mysql php8.3-gd
    composer
    postgresql postgresql-contrib
    mariadb-server
    caddy
    git acl unzip openssl sudo
    imagemagick libimage-exiftool-perl libheif1
)
$SUDO apt install -y "${PKGS[@]}"
ok "apt packages installed/updated"

# ===========================================================================
log "ImageMagick HEIC + WebP delegáty (foto upload — App\\Service\\PhotoNormalizer)"
# ===========================================================================
# Ubuntu 24.04 má ImageMagick 6 → binárka je `convert` (ne `magick` jako Alpine IM7).
IM_BIN="$(command -v magick || command -v convert || true)"
[[ -n "$IM_BIN" ]] || die "imagemagick se nenainstaloval (magick/convert chybí)"

if "$IM_BIN" -list format 2>/dev/null | grep -qiE '^[[:space:]]*HEIC'; then
    skip "HEIC delegate present"
else
    # HEIC coder je v -extra variantě libmagickcore; název kvůli t64 transition
    # není stabilní napříč release → hledáme dynamicky, ať PKGS nepadnou na názvu.
    EXTRA="$(apt-cache --names-only search 'libmagickcore.*extra' 2>/dev/null | awk '{print $1}' | head -1 || true)"
    [[ -n "$EXTRA" ]] && $SUDO apt install -y "$EXTRA" || true
    if "$IM_BIN" -list format 2>/dev/null | grep -qiE '^[[:space:]]*HEIC'; then
        ok "HEIC delegate installed${EXTRA:+ ($EXTRA)}"
    else
        note "HEIC delegate CHYBÍ — iPhone HEIC uploady spadnou. Dořeš ručně (libheif + imagemagick heic delegate)."
    fi
fi

# WebP write je formát masteru — bez něj se fotky neuloží vůbec.
if "$IM_BIN" -list format 2>/dev/null | grep -qiE 'WEBP'; then
    ok "WebP supported"
else
    note "WebP CHYBÍ — fotky se neuloží. Dořeš (libwebp + imagemagick webp delegate)."
fi

# ===========================================================================
log "PHP-FPM upload limity (mobil fotky 3–12 MB; default je 2M)"
# ===========================================================================
# Drž v sync s Assert\File maxSize v LinePhoto (30M) + docker/php/Dockerfile.
PHP_UP_INI="/etc/php/8.3/fpm/conf.d/90-slack-uploads.ini"
PHP_UP_CONTENT=$'upload_max_filesize = 32M\npost_max_size = 40M'
if [[ -f "$PHP_UP_INI" ]] && [[ "$($SUDO cat "$PHP_UP_INI" 2>/dev/null)" == "$PHP_UP_CONTENT" ]]; then
    skip "$PHP_UP_INI up-to-date"
else
    printf '%s\n' "$PHP_UP_CONTENT" | $SUDO tee "$PHP_UP_INI" >/dev/null
    # fpm nemusí ještě běžet (fresh louka) — pak ho nastartuje sekce Systemd s ini už na místě.
    $SUDO systemctl reload php8.3-fpm 2>/dev/null || true
    ok "wrote $PHP_UP_INI (upload 32M / post 40M)"
fi

# ===========================================================================
log "Deploy user + sudoers"
# ===========================================================================
if id -u "$APP_USER" >/dev/null 2>&1; then
    skip "user $APP_USER exists"
else
    $SUDO useradd -m -s /bin/bash "$APP_USER"
    ok "created user $APP_USER"
fi

SUDOERS_FILE="/etc/sudoers.d/$APP_USER"
if [[ -f "$SUDOERS_FILE" ]]; then
    skip "$SUDOERS_FILE present"
else
    $SUDO mkdir -p /etc/sudoers.d
    echo "$APP_USER ALL=(ALL) NOPASSWD: ALL" | $SUDO tee "$SUDOERS_FILE" >/dev/null
    $SUDO chmod 440 "$SUDOERS_FILE"
    ok "wrote $SUDOERS_FILE (NOPASSWD ALL)"
fi

# ===========================================================================
log "App directory + git clone"
# ===========================================================================
if [[ -d "$APP_DIR/.git" ]]; then
    skip "$APP_DIR is a git checkout"
else
    $SUDO mkdir -p "$(dirname "$APP_DIR")"
    $SUDO chown "$APP_USER:$APP_USER" "$(dirname "$APP_DIR")"
    sudo -u "$APP_USER" git clone "$APP_REPO" "$APP_DIR"
    ok "cloned repo to $APP_DIR"
fi

# ===========================================================================
log "Postgres role + DB + .env.local (atomicky)"
# ===========================================================================
ENV_LOCAL="$APP_DIR/.env.local"

role_exists() { sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = '$DB_ROLE'" | grep -q 1; }
db_exists()   { sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname = '$DB_NAME'" | grep -q 1; }

ROLE_EXISTS=0; role_exists && ROLE_EXISTS=1 || true
DB_EXISTS=0;   db_exists   && DB_EXISTS=1   || true
ENV_EXISTS=0;  [[ -f "$ENV_LOCAL" ]] && ENV_EXISTS=1

if [[ "$ENV_EXISTS" -eq 1 ]]; then
    skip ".env.local existuje — žádná regenerace ničeho"
    if [[ "$ROLE_EXISTS" -eq 0 ]]; then
        die "WEIRD STATE: .env.local existuje, ale Postgres role '$DB_ROLE' chybí.
   Manuální recovery: buď restore z backupu, nebo:
     sudo -u postgres psql -c \"CREATE ROLE $DB_ROLE WITH LOGIN PASSWORD '<heslo z .env.local>';\""
    fi
    if [[ "$DB_EXISTS" -eq 0 ]]; then
        sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_ROLE;"
        sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL ON SCHEMA public TO $DB_ROLE;"
        ok "created missing database $DB_NAME (role existed)"
    fi
else
    # .env.local chybí — fresh setup
    if [[ "$ROLE_EXISTS" -eq 1 ]]; then
        die "WEIRD STATE: Postgres role '$DB_ROLE' existuje, ale .env.local chybí (nemůžu uhodnout heslo).
   Manuální recovery: buď DROP ROLE $DB_ROLE CASCADE; (POZOR — taky smaže DB!),
   nebo obnov .env.local s heslem co znáš."
    fi

    DB_PASS=$(openssl rand -hex 16)
    APP_SECRET=$(openssl rand -hex 32)

    sudo -u postgres psql -c "CREATE ROLE $DB_ROLE WITH LOGIN PASSWORD '$DB_PASS';"
    ok "created role $DB_ROLE"

    if [[ "$DB_EXISTS" -eq 0 ]]; then
        sudo -u postgres psql -c "CREATE DATABASE $DB_NAME OWNER $DB_ROLE;"
        sudo -u postgres psql -d "$DB_NAME" -c "GRANT ALL ON SCHEMA public TO $DB_ROLE;"
        ok "created database $DB_NAME"
    fi

    # Atomické zapsání .env.local s vygenerovaným heslem. Mode 640, owner deploy:www-data.
    sudo -u "$APP_USER" tee "$ENV_LOCAL" >/dev/null <<EOF
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=$APP_SECRET
DATABASE_URL=postgresql://$DB_ROLE:$DB_PASS@127.0.0.1:5432/$DB_NAME?serverVersion=16&charset=utf8
OLD_DATABASE_URL=mysql://nobody:nobody@127.0.0.1:3306/none?serverVersion=8.0
MAILER_DSN=null://null
DEFAULT_URI=https://beta.slack.cz
EOF
    $SUDO chown "$APP_USER:$PHP_FPM_USER" "$ENV_LOCAL"
    $SUDO chmod 640 "$ENV_LOCAL"
    ok "wrote $ENV_LOCAL (mode 640, owner $APP_USER:$PHP_FPM_USER)"
fi

# ===========================================================================
log "Writable dirs + ACLs"
# ===========================================================================
for p in var/cache var/log public/uploads public/media/cache; do
    abs="$APP_DIR/$p"
    if [[ ! -d "$abs" ]]; then
        sudo -u "$APP_USER" mkdir -p "$abs"
        ok "mkdir $p"
    fi
    $SUDO setfacl -R  -m "u:$PHP_FPM_USER:rwX" "$abs"
    $SUDO setfacl -dR -m "u:$PHP_FPM_USER:rwX" "$abs"
done
ok "ACLs applied"

# ===========================================================================
log "Matomo (self-hosted analytics) — kód"
# ===========================================================================
# Viz docs/deploy.md § Analytics. Vlastní subdoména (analytics.slack.cz), sdílí
# php-fpm socket s appkou (php_fastcgi v infra/Caddyfile), takže žádný nový pool.
ANALYTICS_DIR="/var/www/analytics"
if [[ -f "$ANALYTICS_DIR/index.php" ]]; then
    skip "$ANALYTICS_DIR už obsahuje Matomo kód"
else
    $SUDO mkdir -p "$ANALYTICS_DIR"
    $SUDO chown "$APP_USER:$APP_USER" "$ANALYTICS_DIR"
    curl -fsSL 'https://builds.matomo.org/matomo.zip' -o /tmp/matomo.zip
    rm -rf /tmp/matomo-extract
    sudo -u "$APP_USER" unzip -q /tmp/matomo.zip -d /tmp/matomo-extract
    sudo -u "$APP_USER" -- bash -c "cp -r /tmp/matomo-extract/matomo/. '$ANALYTICS_DIR'/"
    rm -rf /tmp/matomo.zip /tmp/matomo-extract
    ok "staženo a rozbaleno Matomo do $ANALYTICS_DIR"
fi

# Writable pro PHP-FPM: jen tmp/ (běžný provoz) + config/ (install wizard tam
# zapisuje config.ini.php). Zbytek stromu záměrně read-only, viz Matomo docs.
for p in tmp config; do
    abs="$ANALYTICS_DIR/$p"
    if [[ ! -d "$abs" ]]; then
        sudo -u "$APP_USER" mkdir -p "$abs"
    fi
    $SUDO setfacl -R  -m "u:$PHP_FPM_USER:rwX" "$abs"
    $SUDO setfacl -dR -m "u:$PHP_FPM_USER:rwX" "$abs"
done
ok "Matomo tmp/ + config/ ACL pro $PHP_FPM_USER"

# matomo.js writable = pluginy (pokud nějaké nainstalujeme) si tam smí
# dopsat vlastní JS tracking kód. Bez tohohle to jen warní v system checku,
# ale je to jednořádkové, tak rovnou.
if [[ -f "$ANALYTICS_DIR/matomo.js" ]]; then
    $SUDO setfacl -m "u:$PHP_FPM_USER:rw" "$ANALYTICS_DIR/matomo.js"
    ok "Matomo matomo.js ACL pro $PHP_FPM_USER"
fi

# ===========================================================================
log "Matomo — MariaDB role + DB (atomicky)"
# ===========================================================================
MATOMO_DB_NAME="matomo"
MATOMO_DB_USER="matomo"
# ⚠ MIMO $ANALYTICS_DIR záměrně — ten JE Caddy `root` pro analytics.slack.cz
# (na rozdíl od appky, kde .env.local sedí mimo public/). Cokoli uvnitř
# ANALYTICS_DIR je potenciálně stažitelné přes file_server, viz incident
# 2026-07-08 (heslo krátce veřejně na /db-credentials.txt).
MATOMO_CREDS_FILE="${ANALYTICS_DIR}-db-credentials.txt"
MYSQL_BIN="$(command -v mariadb || command -v mysql || true)"
[[ -n "$MYSQL_BIN" ]] || die "mariadb/mysql binárka chybí po apt installu mariadb-server"

matomo_db_user_exists() { $SUDO "$MYSQL_BIN" -N -e "SELECT 1 FROM mysql.user WHERE User='$MATOMO_DB_USER'" 2>/dev/null | grep -q 1; }

if [[ -f "$MATOMO_CREDS_FILE" ]]; then
    skip "$MATOMO_CREDS_FILE existuje — DB už byla vytvořená, žádná regenerace hesla"
else
    if matomo_db_user_exists; then
        die "WEIRD STATE: MariaDB user '$MATOMO_DB_USER' existuje, ale $MATOMO_CREDS_FILE chybí (nemůžu uhodnout heslo).
   Manuální recovery: buď obnov $MATOMO_CREDS_FILE s heslem co znáš, nebo:
     sudo $MYSQL_BIN -e \"DROP USER '$MATOMO_DB_USER'@'localhost';\" a spusť setup znovu."
    fi

    MATOMO_DB_PASS=$(openssl rand -hex 16)
    $SUDO "$MYSQL_BIN" -e "CREATE DATABASE IF NOT EXISTS $MATOMO_DB_NAME CHARACTER SET utf8mb4;"
    $SUDO "$MYSQL_BIN" -e "CREATE USER '$MATOMO_DB_USER'@'localhost' IDENTIFIED BY '$MATOMO_DB_PASS';"
    $SUDO "$MYSQL_BIN" -e "GRANT ALL PRIVILEGES ON $MATOMO_DB_NAME.* TO '$MATOMO_DB_USER'@'localhost'; FLUSH PRIVILEGES;"
    ok "vytvořena MariaDB DB '$MATOMO_DB_NAME' + user '$MATOMO_DB_USER'"

    sudo -u "$APP_USER" tee "$MATOMO_CREDS_FILE" >/dev/null <<EOF
# Matomo DB credentials — vyplň do install wizardu na https://analytics.slack.cz/
# (Database Server: localhost, Adapter: MySQLi/PDO_MYSQL)
Database name: $MATOMO_DB_NAME
Username:      $MATOMO_DB_USER
Password:      $MATOMO_DB_PASS
EOF
    $SUDO chmod 600 "$MATOMO_CREDS_FILE"
    ok "zapsán $MATOMO_CREDS_FILE (mode 600, obsahuje DB heslo)"
fi

# ===========================================================================
log "Composer install (initial bootstrap)"
# ===========================================================================
if [[ -d "$APP_DIR/vendor" ]]; then
    skip "vendor/ present (deploy.sh ho refreshne při příštím deployi)"
else
    sudo -u "$APP_USER" -- bash -c "cd '$APP_DIR' && composer install --no-dev --optimize-autoloader --no-interaction"
    ok "composer install initial"
fi

# ===========================================================================
log "Doctrine migrate (jen pending)"
# ===========================================================================
if [[ -x "$APP_DIR/bin/console" && -d "$APP_DIR/vendor" ]]; then
    PENDING=$(sudo -u "$APP_USER" -- bash -c "cd '$APP_DIR' && APP_ENV=prod php bin/console doctrine:migrations:list 2>/dev/null | grep -c 'not migrated' || true")
    if [[ "${PENDING:-0}" -gt 0 ]]; then
        sudo -u "$APP_USER" -- bash -c "cd '$APP_DIR' && APP_ENV=prod php bin/console doctrine:migrations:migrate -n"
        ok "ran $PENDING migration(s)"
    else
        skip "no pending migrations"
    fi
else
    skip "vendor/ nebo bin/console chybí, migrate odložené"
fi

# ===========================================================================
log "Systemd services"
# ===========================================================================
for svc in postgresql mariadb php8.3-fpm caddy; do
    if systemctl is-enabled --quiet "$svc"; then
        skip "$svc enabled"
    else
        $SUDO systemctl enable "$svc"
        ok "enabled $svc"
    fi
    if systemctl is-active --quiet "$svc"; then
        skip "$svc active"
    else
        $SUDO systemctl start "$svc"
        ok "started $svc"
    fi
done

# ===========================================================================
log "Caddy site bloky (jen info, NEZASAHUJEME)"
# ===========================================================================
if [[ -f /etc/caddy/Caddyfile ]]; then
    note "current /etc/caddy/Caddyfile site bloky:"
    $SUDO grep -E '^[^#{[:space:]].*\{$' /etc/caddy/Caddyfile 2>/dev/null | sed 's/^/    /' \
        || note "    (žádný site blok ještě)"
else
    note "/etc/caddy/Caddyfile chybí — viz docs/deploy.md sekce Caddy pro šablonu"
fi

# ===========================================================================
log "✓ Setup hotový"
# ===========================================================================
echo ""
echo "Co dořešit ručně (jen pro fresh louku — viz docs/deploy.md):"
echo "  1. /etc/caddy/Caddyfile site blok (beta.slack.cz / slack.cz)"
echo "     pak: sudo systemctl reload caddy"
echo "  2. SSH klíč pro $APP_USER (~/.ssh/authorized_keys)"
echo "     z lokálu: ssh-copy-id -i ~/.ssh/slack_cz_prod.pub $APP_USER@HOST"
echo "  3. GH repo secret DEPLOY_SSH_KEY"
echo "  4. DNS records v Cloudflare na tento server"
echo "  5. Matomo install wizard: https://analytics.slack.cz/"
echo "     DB creds: ${ANALYTICS_DIR}-db-credentials.txt (DNS + make deployCaddy musí být hotové první)"
echo ""
echo "Verifikuj (z lokálu):"
echo "  make checkServerEnv"
