# Contract: Environment Variables & Host Ports

The developer-facing contract for how the local stack is configured. After this
feature, the following MUST hold. Each item is verifiable (the check is given).

## Host-port contract

| Service | URL (default) | Variable | Verify |
|---------|---------------|----------|--------|
| Application | http://localhost:8000 | `APP_PORT` | `curl -s -o /dev/null -w '%{http_code}' http://localhost:8000/` → `200` |
| Adminer (DB UI) | http://localhost:8080 | `ADMINER_PORT` | page loads |
| Mailpit UI | http://localhost:8025 | `SMTP_UI_PORT` | page loads |
| Mailpit SMTP | localhost:1025 (host publish; app uses `mailer:1025` internally) | `SMTP_MAIL_PORT` | `docker compose port mailer 1025` |

- Each default is the conventional ecosystem port.
- Each port is overridable by setting its variable in `.env.local` (no other file edit required).
- The value shown in `docs/dev.md`, `README.md`, and `.env.local.example` equals the default above.

## Env-variable contract

- **Authoritative defaults** live in committed `.env`: `APP_PORT`, `ADMINER_PORT`,
  `SMTP_UI_PORT`, `SMTP_MAIL_PORT`, `APP_URL`, `POSTGRES_DB/USER/PASSWORD`,
  `UID`, `GID`, `MAILER_DSN`, `MESSENGER_TRANSPORT_DSN`.
- **Per-developer overrides** live in `.env.local` (gitignored). Anything set there
  wins for both `make up` and raw `docker compose up`.
- **Secrets** (`APP_SECRET`, API keys) appear only in `.env.local`. Committed files
  contain blank or clearly-fake placeholders. Verify: `git check-ignore .env.local`
  returns the path; `.dockerignore` lists `.env.local` and `.env.*.local`.
- **No orphaned references**: every `${VAR}` in compose files resolves to a defined
  variable. Verify: `docker compose config` emits no `WARN ... variable is not set`
  for required values, and a manual grep of `${...}` cross-checks `.env`.

## Parity contract

- `make up` and `docker compose up` (with `COMPOSE_ENV_FILES=.env,.env.local`
  exported, as documented) MUST yield identical `docker compose config` output:
  same published ports, same `user:`, same environment.
- Verify:
  ```bash
  docker compose config > /tmp/raw.yaml          # with COMPOSE_ENV_FILES set
  make print-config > /tmp/make.yaml             # or equivalent make path
  diff /tmp/raw.yaml /tmp/make.yaml              # → empty
  ```
- **Documented exception**: on a host where `id -u` ≠ 1000, raw `docker compose up`
  without an override runs as `1000:1000`; full parity there requires `make` or a
  one-line `.env.local` (`UID=`/`GID=`). This is the only permitted divergence.
