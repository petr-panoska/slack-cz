#!/usr/bin/env bash
# Porovná infra/Caddyfile v repu proti /etc/caddy/Caddyfile na betě.
# Repo je kanonický zdroj. Drift = parita rozbitá, exit 1.
#
# Usage:
#   ./scripts/check-caddy.sh           # nebo: make checkCaddy
#
# Volá se taky implicitně z `make deploy` jako preflight gate (vedle
# `checkServerEnv`). Když drift, deploy se nezačne.

set -u

REPO_FILE="infra/Caddyfile"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/slack_cz_prod}"
SSH_HOST="${SSH_HOST:-deploy@178.105.81.158}"
SERVER_PATH="/etc/caddy/Caddyfile"

if [ ! -f "$REPO_FILE" ]; then
    echo "✗ chybí $REPO_FILE v repu" >&2
    exit 1
fi

TMP_REMOTE=$(mktemp)
trap 'rm -f "$TMP_REMOTE"' EXIT

if ! ssh -i "$SSH_KEY" -o ConnectTimeout=10 "$SSH_HOST" "sudo cat $SERVER_PATH" > "$TMP_REMOTE" 2>/dev/null; then
    echo "✗ nepodařilo se stáhnout $SERVER_PATH ze $SSH_HOST" >&2
    exit 1
fi

if diff -q "$REPO_FILE" "$TMP_REMOTE" > /dev/null 2>&1; then
    echo "✓ Server ($SSH_HOST:$SERVER_PATH) je v souladu s repem ($REPO_FILE)."
    exit 0
fi

cat <<EOF
✗ DRIFT mezi repem a serverem.

  repo:   $REPO_FILE
  server: $SSH_HOST:$SERVER_PATH

Jak číst diff níž:
  '-' řádky = jsou v repu,    chybí na serveru
  '+' řádky = jsou na serveru, chybí v repu

─── diff ───────────────────────────────────────────────────────
EOF
diff -u --label "repo:   $REPO_FILE" --label "server: $SSH_HOST:$SERVER_PATH" \
    "$REPO_FILE" "$TMP_REMOTE" || true
cat <<EOF
─── jak to spravit ─────────────────────────────────────────────

Pokud má vyhrát REPO (tj. push repo verze → server dostane '-' řádky,
přijde o '+' řádky):

    make deployCaddy

Pokud má vyhrát SERVER (tj. zarovnat repo na to, co tam reálně běží):

    ssh -i $SSH_KEY $SSH_HOST "sudo cat $SERVER_PATH" > $REPO_FILE
    git diff $REPO_FILE        # zkontroluj
    git add $REPO_FILE && git commit -m "infra: sync Caddyfile from server"

EOF
exit 1
