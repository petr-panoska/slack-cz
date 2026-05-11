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
    caddy
    git acl unzip openssl sudo
)
$SUDO apt install -y "${PKGS[@]}"
ok "apt packages installed/updated"

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
APP_URL=https://beta.slack.cz
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
for svc in postgresql php8.3-fpm caddy; do
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
echo ""
echo "Verifikuj (z lokálu):"
echo "  make checkServerEnv"
