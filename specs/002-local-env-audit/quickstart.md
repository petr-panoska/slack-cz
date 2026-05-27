# Quickstart: Verify the Audited Local Environment

This doubles as the acceptance walkthrough for User Story 1 (fresh clone → running
app) and User Story 2 (single source of truth / start-path parity). Run it after the
remediation to confirm the environment is healthy and consistent.

## Prerequisites

- Linux or WSL2 host
- Docker Engine 24+ and Docker Compose v2 (`docker compose version`)
- Shell user is in the `docker` group

## A. Fresh-clone bootstrap (US1)

```bash
git clone <repo> slack-cz && cd slack-cz
make first-run
```

**Expect**: `.env.local` created from the example, all default-profile services reach
`healthy`, composer installs, DB is created and migrated, and a summary prints the
endpoints. No manual file edits.

**Verify endpoints (conventional ports):**

```bash
curl -s -o /dev/null -w 'app      %{http_code}\n' http://localhost:8000/      # 200
curl -s -o /dev/null -w 'adminer  %{http_code}\n' http://localhost:8080/      # 200
curl -s -o /dev/null -w 'mailpit  %{http_code}\n' http://localhost:8025/      # 200
```

**Idempotency:** run `make first-run` again → completes with no errors, no duplicate
side effects.

## B. Single source of truth (US2)

```bash
# Every compose variable resolves; no "variable is not set" warnings:
docker compose config 2>&1 | grep -i 'variable is not set' || echo "OK: no orphaned vars"

# Ports/URLs in docs match the effective defaults:
grep -nE '8000|8080|8025' docs/dev.md README.md .env.local.example
docker compose config | grep -E 'published:'   # should show 8000/8080/8025/1025

# Base URL matches the live port — generated absolute URL uses :8000:
docker compose exec -T php bin/console app:user:reset-password <known-email> | grep 'http://localhost:8000'
```

## C. Start-path parity (US2)

```bash
# make path
COMPOSE_ENV_FILES=.env,.env.local UID=$(id -u) GID=$(id -g) docker compose config > /tmp/a.yaml
# raw path (documented export)
export COMPOSE_ENV_FILES=.env,.env.local
docker compose config > /tmp/b.yaml
diff /tmp/a.yaml /tmp/b.yaml && echo "OK: identical config"
```

(On a host where `id -u` ≠ 1000, the `user:` line is the documented exception — see
`contracts/env-and-ports.md`.)

## D. Line-ending normalization (US3)

```bash
# After committing .gitattributes + renormalize, a clean checkout shows no eol churn:
git status --porcelain        # clean
grep -rIl $'\r' .env Makefile README.md docs/*.md && echo "FAIL: CRLF present" || echo "OK: LF only"
```

## E. Port-collision override (edge case)

```bash
# Simulate 8000 in use → relocate via a single override, no other edit:
echo 'APP_PORT=18000' >> .env.local
make up
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:18000/   # 200
# cleanup: remove the line, make up
```

## Success = all of A–E pass

Maps to SC-001 (A), SC-002/003/004 (B), SC-005 (C), SC-008 (D), and the port-collision
edge case (E).
