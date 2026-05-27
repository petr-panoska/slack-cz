# Contract: Developer Command Interface (Makefile)

The supported entry points for operating the local stack. After this feature these
targets MUST behave as described, and `make help` MUST list each with its `## ` blurb.

## Bootstrap & lifecycle

| Target | Contract | Verify |
|--------|----------|--------|
| `make first-run` | Idempotent fresh-clone bootstrap: create `.env.local` from example if missing → `up -d --wait` → `composer install` → DB create + migrate → print endpoint summary with **correct** ports. | On a clean clone, single command → app returns 200; re-running succeeds with no errors. |
| `make up` | Start default-profile services, wait for healthy. Uses `COMPOSE_ENV_FILES=.env,.env.local` and host UID/GID. | All default services `healthy`; app 200. |
| `make down` | Stop stack, preserve volumes. | `docker compose ps` empty; volumes remain. |
| `make nuke` | Stop and remove volumes (destructive). | Volumes gone. |
| `make help` | List every `## `-annotated target. | Output lists targets above. |

## Internals contract

- The `DC` macro sets `COMPOSE_ENV_FILES=.env,.env.local` and `UID`/`GID`, then calls
  `docker compose`. It no longer hand-sources `.env.local` via `set -a; . ./.env.local`.
- `first-run` is safe to re-run (idempotent): `--if-not-exists` on DB create, `migrate`
  is a no-op when up to date, `.env.local` copy is guarded by `test -f`.
- Legacy/import/deploy targets (`loadLegacyDump`, `legacyImport*`, `deploy*`,
  `syncBetaFromLocal`, `checkServerEnv`, `checkCaddy`, `setupServer`) are out of scope
  for this audit and remain unchanged in behavior.

## Endpoint summary contract

`make first-run` and the docs MUST print/show the post-setup endpoints using the
**actual effective ports** (default 8000 / 8080 / 8025), not hardcoded stale values.
