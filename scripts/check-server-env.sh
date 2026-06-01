#!/usr/bin/env bash
# Preflight check pro slack.cz prod prostředí. Volá se přes SSH z lokálu, běží
# na serveru (deploy user). Lokální verze tohohle skriptu je kanonický spec —
# když přidáš novou závislost (PHP extension, writable dir, env klíč),
# updatuj tenhle skript ve stejném commitu jako kód, a další deploy padne
# fail-fast, pokud server není pre-provisioned.
#
# Usage:
#   LOCAL_HEAD=$(git rev-parse HEAD)
#   ssh -i ~/.ssh/slack_cz_prod deploy@178.105.81.158 'bash -s' -- "$LOCAL_HEAD" \
#       < scripts/check-server-env.sh
#
# Nebo: `make checkServerEnv`. Volá se taky implicitně z `make deploy` jako
# fail-fast preflight.
#
# Exit 0 = vše OK, exit 1 = aspoň jeden check failnul.

set -u
# NE set -e — chceme aby všechny checky doběhly, ne padly na první chybě.

LOCAL_HEAD="${1:-unknown}"

APP_DIR="/var/www/slack-cz"
PHP_FPM_USER="www-data"
PHP_VERSION_MIN="8.3"

# Spec: extensions vyžadované aplikací. Když přidáš dependency, doplň sem.
REQUIRED_EXTS=(pgsql pdo_pgsql mbstring xml curl zip intl opcache readline mysqli pdo_mysql gd exif)

# Spec: adresáře co PHP-FPM musí umět zapsat.
WRITABLE_DIRS=(var/cache var/log public/uploads public/media/cache)

# Spec: klíče očekávané v .env.local.
REQUIRED_ENV_KEYS=(APP_ENV APP_SECRET DATABASE_URL OLD_DATABASE_URL MAILER_DSN DEFAULT_URI)

# Spec: systemd služby co musí běžet.
REQUIRED_SERVICES=(caddy php8.3-fpm postgresql)

PASS_COUNT=0
FAIL_COUNT=0

pass()    { printf '  ✓ %s\n' "$1"; PASS_COUNT=$((PASS_COUNT + 1)); }
fail()    { printf '  ✗ %s\n' "$1"; FAIL_COUNT=$((FAIL_COUNT + 1)); }
info()    { printf '    %s\n' "$1"; }
section() { printf '\n== %s ==\n' "$1"; }

# Sudo preflight — všechny FS-as-www-data checky to vyžadují. Pokud deploy
# nemá NOPASSWD sudoers, FS sekce se přeskočí s warningem.
SUDO_AS_WWWDATA_OK=0
if sudo -n -u "$PHP_FPM_USER" true 2>/dev/null; then
    SUDO_AS_WWWDATA_OK=1
fi

# ===========================================================================
section "Git"
# ===========================================================================

if [[ -d "$APP_DIR/.git" ]]; then
    SERVER_HEAD=$(git -C "$APP_DIR" rev-parse HEAD 2>/dev/null || echo unknown)
    info "server HEAD : $SERVER_HEAD"
    info "local  HEAD : $LOCAL_HEAD"

    if [[ "$LOCAL_HEAD" != "unknown" && "$LOCAL_HEAD" != "$SERVER_HEAD" ]]; then
        git -C "$APP_DIR" fetch --quiet origin main 2>/dev/null || true
        if git -C "$APP_DIR" cat-file -e "$LOCAL_HEAD" 2>/dev/null; then
            BEHIND=$(git -C "$APP_DIR" rev-list --count "$SERVER_HEAD..$LOCAL_HEAD" 2>/dev/null || echo "?")
            info "behind     : $BEHIND commit(s)"

            NEW_MIGRATIONS=$(git -C "$APP_DIR" diff --name-only --diff-filter=A "$SERVER_HEAD..$LOCAL_HEAD" -- 'migrations/*.php' 2>/dev/null | wc -l)
            [[ "$NEW_MIGRATIONS" -gt 0 ]] && info "new migrations: $NEW_MIGRATIONS"

            if git -C "$APP_DIR" diff --quiet "$SERVER_HEAD..$LOCAL_HEAD" -- composer.lock 2>/dev/null; then
                :
            else
                info "composer.lock changed → new deps will install"
            fi

            CHANGED_DOCKERFILE=$(git -C "$APP_DIR" diff --name-only "$SERVER_HEAD..$LOCAL_HEAD" -- docker/ 2>/dev/null | wc -l)
            [[ "$CHANGED_DOCKERFILE" -gt 0 ]] && info "docker/ changed (info only — prod is native, ne Docker)"

            CHANGED_DEPLOY_SCRIPTS=$(git -C "$APP_DIR" diff --name-only "$SERVER_HEAD..$LOCAL_HEAD" -- scripts/ 2>/dev/null | wc -l)
            [[ "$CHANGED_DEPLOY_SCRIPTS" -gt 0 ]] && info "scripts/ changed → deploy/check skripty samy se updatují"
        else
            info "local HEAD ještě není na originu (push origin main first)"
        fi
    elif [[ "$LOCAL_HEAD" == "$SERVER_HEAD" ]]; then
        info "server is up-to-date with local — deploy bude no-op"
    fi
    pass "git checkout present"
else
    fail "$APP_DIR není git checkout"
fi

# ===========================================================================
section "PHP"
# ===========================================================================

if command -v php >/dev/null; then
    PHP_VER=$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo unknown)
    if php -r 'exit(version_compare(PHP_VERSION, "'"$PHP_VERSION_MIN"'", ">=") ? 0 : 1);' 2>/dev/null; then
        pass "version $PHP_VER (>= $PHP_VERSION_MIN)"
    else
        fail "version $PHP_VER (need >= $PHP_VERSION_MIN)"
    fi
else
    fail "php binary chybí"
fi

MISSING_EXTS=()
for ext in "${REQUIRED_EXTS[@]}"; do
    if ! php -m 2>/dev/null | grep -qiw "$ext"; then
        MISSING_EXTS+=("$ext")
    fi
done

if [[ ${#MISSING_EXTS[@]} -eq 0 ]]; then
    pass "extensions: ${REQUIRED_EXTS[*]}"
else
    fail "chybí extensions: ${MISSING_EXTS[*]}"
    APT_PKGS=""
    for e in "${MISSING_EXTS[@]}"; do APT_PKGS+="php8.3-$e "; done
    info "fix: sudo apt install -y $APT_PKGS && sudo systemctl reload php8.3-fpm"
fi

# GD WebP support — liip_imagine `highline_*` filtery thumbují i WebP originály.
if php -r '$i=function_exists("gd_info")?gd_info():[]; exit(($i["WebP Support"]??false)?0:1);' 2>/dev/null; then
    pass "gd: WebP support"
else
    fail "gd: WebP support chybí (libwebp-dev při buildu php8.3-gd?)"
fi

# Symfony requirements checker — pokud je nainstalovaný v projektu (jako dev
# dep), zavolat. Symfony CLI to umí přes `symfony check:requirements`.
if [[ -x "$APP_DIR/vendor/bin/requirements-checker" ]]; then
    if "$APP_DIR/vendor/bin/requirements-checker" >/dev/null 2>&1; then
        pass "symfony/requirements-checker: OK"
    else
        fail "symfony/requirements-checker: report níž"
        "$APP_DIR/vendor/bin/requirements-checker" 2>&1 | sed 's/^/    /'
    fi
fi

# ===========================================================================
section "System binaries"
# ===========================================================================

for bin in composer caddy psql setfacl sudo getfacl; do
    if command -v "$bin" >/dev/null; then
        pass "$bin: $(command -v "$bin")"
    else
        fail "$bin: není v PATH"
    fi
done

# ===========================================================================
section "Services"
# ===========================================================================

for svc in "${REQUIRED_SERVICES[@]}"; do
    state=$(systemctl is-active "$svc" 2>&1)
    if [[ "$state" == "active" ]]; then
        pass "$svc: active"
    else
        fail "$svc: $state"
    fi
done

# ===========================================================================
section "Filesystem (PHP-FPM writes)"
# ===========================================================================

if [[ "$SUDO_AS_WWWDATA_OK" -eq 0 ]]; then
    fail "sudo -n -u $PHP_FPM_USER nefunguje — všechny FS perm checky se skipují"
    info "fix: deploy potřebuje NOPASSWD sudoers entry (viz deploy.md)"
else
    for p in "${WRITABLE_DIRS[@]}"; do
        abs="$APP_DIR/$p"
        if [[ ! -d "$abs" ]]; then
            fail "$p: adresář chybí (deploy.sh ho mkdir-p, ale ACL musíš ručně)"
            info "fix: cd $APP_DIR && mkdir -p $p && sudo setfacl -R -m u:$PHP_FPM_USER:rwX $p && sudo setfacl -dR -m u:$PHP_FPM_USER:rwX $p"
            continue
        fi
        if sudo -n -u "$PHP_FPM_USER" test -w "$abs" 2>/dev/null; then
            pass "$p: writable by $PHP_FPM_USER"
        else
            fail "$p: NOT writable by $PHP_FPM_USER"
            info "fix: sudo setfacl -R -m u:$PHP_FPM_USER:rwX $abs && sudo setfacl -dR -m u:$PHP_FPM_USER:rwX $abs"
        fi
    done
fi

# .env.local readable by www-data (PHP-FPM ho potřebuje načíst)
ENV_LOCAL="$APP_DIR/.env.local"
if [[ -f "$ENV_LOCAL" ]]; then
    if [[ "$SUDO_AS_WWWDATA_OK" -eq 1 ]]; then
        if sudo -n -u "$PHP_FPM_USER" test -r "$ENV_LOCAL" 2>/dev/null; then
            pass ".env.local readable by $PHP_FPM_USER"
        else
            fail ".env.local NOT readable by $PHP_FPM_USER"
            info "fix: sudo chgrp $PHP_FPM_USER $ENV_LOCAL && sudo chmod 640 $ENV_LOCAL"
        fi
    fi
else
    fail ".env.local chybí"
fi

# ===========================================================================
section ".env.local content"
# ===========================================================================

if [[ -f "$ENV_LOCAL" ]]; then
    for key in "${REQUIRED_ENV_KEYS[@]}"; do
        if grep -q "^${key}=" "$ENV_LOCAL" 2>/dev/null; then
            pass "$key set"
        else
            fail "$key chybí"
        fi
    done
fi

# ===========================================================================
section "Postgres"
# ===========================================================================

if [[ -f "$ENV_LOCAL" ]]; then
    DB_URL=$(grep '^DATABASE_URL=' "$ENV_LOCAL" 2>/dev/null | head -1 | cut -d= -f2-)
    DB_URL="${DB_URL%\"}"; DB_URL="${DB_URL#\"}"
    DB_URL="${DB_URL%\'}"; DB_URL="${DB_URL#\'}"
    # Doctrine přidává `?serverVersion=&charset=…` které psql neumí — striponout.
    DB_URL_PSQL="${DB_URL%%\?*}"
    if [[ -n "${DB_URL_PSQL:-}" ]]; then
        if echo "SELECT 1" | psql "$DB_URL_PSQL" -tA >/dev/null 2>&1; then
            pass "DATABASE_URL connect OK"
        else
            fail "DATABASE_URL connect failed"
            info "ověř credentials v .env.local + že postgres user/db existují"
        fi
    else
        fail "DATABASE_URL nečitelná z .env.local"
    fi
fi

# ===========================================================================
section "Pending Doctrine migrations"
# ===========================================================================

if [[ -x "$APP_DIR/bin/console" ]]; then
    cd "$APP_DIR" || true
    PENDING=$(APP_ENV=prod php bin/console doctrine:migrations:list --no-interaction 2>/dev/null | grep -c 'not migrated' || true)
    if [[ "$PENDING" -gt 0 ]]; then
        info "$PENDING pending migration(s) na serveru — deploy je spustí"
    fi
    pass "doctrine migrations: $PENDING pending"
fi

# ===========================================================================
section "Summary"
# ===========================================================================

printf '  %d OK, %d FAIL\n' "$PASS_COUNT" "$FAIL_COUNT"

if [[ $FAIL_COUNT -gt 0 ]]; then
    printf '\n✗ Preflight FAIL — deploy zablokovaný.\n'
    exit 1
fi

printf '\n✓ Preflight PASS — server je ready.\n'
exit 0
