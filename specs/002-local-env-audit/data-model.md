# Phase 1 Data Model: Configuration Source-of-Truth Matrix

This feature has no application data entities. The "model" is the inventory of
configurable values, each mapped to its single authoritative source and the artifacts
that consume it. This matrix is the reference used to verify FR-002 (one authoritative
definition) and FR-003 (no orphaned references).

## Configurable values

| Value | Authoritative source | Default (target) | Consumers | Override location |
|-------|---------------------|------------------|-----------|-------------------|
| App host port | `.env` `APP_PORT` | `8000` | `compose.yaml` apache `ports` | `.env.local` |
| Adminer host port | `.env` `ADMINER_PORT` | `8080` | `compose.override.yaml` adminer `ports` | `.env.local` |
| Mail UI host port | `.env` `SMTP_UI_PORT` | `8025` | `compose.override.yaml` mailer `ports` | `.env.local` |
| Mail SMTP host port | `.env` `SMTP_MAIL_PORT` | `1025` | `compose.override.yaml` mailer `ports` | `.env.local` |
| App base URL | `.env` `APP_URL` | `http://localhost:8000` | `framework.router.default_uri` (CLI absolute URLs) | `.env.local` (prod: `.env.local`) |
| DB name | `.env` `POSTGRES_DB` | `app` | `compose.yaml` database env + composed `DATABASE_URL` | `.env.local` |
| DB user | `.env` `POSTGRES_USER` | `app` | `compose.yaml` database env + composed `DATABASE_URL` | `.env.local` |
| DB password | `.env` `POSTGRES_PASSWORD` | `password` | `compose.yaml` database env + composed `DATABASE_URL` | `.env.local` |
| App DB connection | `compose.yaml` php `DATABASE_URL` (composed from `POSTGRES_*`) | derived | Symfony Doctrine | (change via `POSTGRES_*`) |
| Container user (uid:gid) | `.env` `UID` / `GID` (default 1000) | `1000:1000` | `compose.override.yaml` php `user`, Dockerfile build args | `.env.local` or live `id -u`/`id -g` via Makefile |
| Mailer transport (app) | `.env` `MAILER_DSN` (null) → `.env.local` `smtp://mailer:1025` | `smtp://mailer:1025` (dev) | Symfony Mailer | `.env.local` |
| App secret | `.env.local` `APP_SECRET` | (per-dev, fake placeholder in example) | Symfony framework | `.env.local` only (never committed) |
| Env-file chain for compose | `COMPOSE_ENV_FILES` | `.env,.env.local` | `docker compose` (both make + raw) | set by Makefile; documented `export` for raw use |

## Consistency rules (validation invariants)

1. **One name per value**: every `${VAR}` in `compose.yaml` / `compose.override.yaml`
   matches a key actually defined in `.env` (or has an intentional `:-default`). No
   value is defined under two different names.
2. **Port ↔ URL agreement**: `APP_URL` host:port equals the app's published port
   default (`APP_PORT`). If one changes, the other must change with it.
3. **Docs ↔ defaults agreement**: every port/URL stated in `docs/dev.md`, `README.md`,
   and `.env.local.example` equals the corresponding `.env` default.
4. **Secrets stay local**: `APP_SECRET` and any real API keys live only in `.env.local`
   (gitignored, dockerignored). Committed files hold only blank or fake placeholders.
5. **Path parity**: `docker compose config` produces identical output whether invoked
   via `make` or directly (given the documented `COMPOSE_ENV_FILES`), except the
   documented uid≠1000 raw-compose residual (see research F4).

## Lifecycle notes

- **`POSTGRES_*` are first-init-only**: changing user/password/db after the
  `database_data` volume exists requires `make nuke && make first-run` (already
  documented in `.env.local.example`). This constraint is retained, not changed.
- **`.env.local` creation**: bootstrapped from `.env.local.example` by `make first-run`
  on a fresh clone; this is the only auto-generated config file.
