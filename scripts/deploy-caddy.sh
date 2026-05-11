#!/usr/bin/env bash
# Push infra/Caddyfile na betu. Validate před swapem, pak restart
# (NE reload — viz `docs/deploy.md` "Caddy reload se zasekne").
#
# Usage:
#   ./scripts/deploy-caddy.sh        # nebo: make deployCaddy
#
# Flow:
#   1) scp infra/Caddyfile → deploy@beta:/tmp/Caddyfile.new
#   2) sudo caddy validate /tmp/Caddyfile.new  (fail-fast na syntax error)
#   3) sudo cp atomicky do /etc/caddy/Caddyfile
#   4) sudo systemctl restart caddy
#   5) smoke test: caddy běží + beta vrací 200

set -eu

REPO_FILE="infra/Caddyfile"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/slack_cz_prod}"
SSH_HOST="${SSH_HOST:-deploy@178.105.81.158}"
REMOTE_TMP="/tmp/Caddyfile.new"

if [ ! -f "$REPO_FILE" ]; then
    echo "✗ chybí $REPO_FILE v repu" >&2
    exit 1
fi

echo "→ scp $REPO_FILE → $SSH_HOST:$REMOTE_TMP"
scp -i "$SSH_KEY" -q "$REPO_FILE" "$SSH_HOST:$REMOTE_TMP"

echo "→ validate na serveru"
ssh -i "$SSH_KEY" "$SSH_HOST" "sudo caddy validate --config $REMOTE_TMP --adapter caddyfile"

echo "→ atomic swap do /etc/caddy/Caddyfile + restart"
ssh -i "$SSH_KEY" "$SSH_HOST" "sudo cp $REMOTE_TMP /etc/caddy/Caddyfile && sudo systemctl restart caddy && rm -f $REMOTE_TMP"

echo "→ smoke test"
ssh -i "$SSH_KEY" "$SSH_HOST" 'systemctl is-active caddy >/dev/null && echo "  caddy: active"'
printf "  beta.slack.cz: %s\n" "$(curl -s -o /dev/null -w '%{http_code}' https://beta.slack.cz/)"

echo "✓ Caddy synced."
