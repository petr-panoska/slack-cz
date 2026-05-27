# Phase 0 Research: Local Environment Audit

This is an audit of an existing environment, so "research" is a catalog of concrete
findings observed in the current repository plus the decided best-practice resolution
for each. Findings are ordered by severity. Each carries Decision / Rationale /
Alternatives considered.

## Findings catalog

### F1 — Mail-service port variable-name mismatch (startup-breaking) — RESOLVED in triage

- **Observed**: `compose.override.yaml` published the mailer port via `${MAIL_SMTP_PORT:-1025}` / `${MAIL_UI_PORT:-8025}`, but `.env` defines those values under `SMTP_MAIL_PORT` / `SMTP_UI_PORT`. The names never matched, so the override silently fell back to `1025`, which collided with another local project and crashed `make up` ("port is already allocated").
- **Decision**: Align the override to the `.env` names (`SMTP_MAIL_PORT` / `SMTP_UI_PORT`). Already applied during initial triage.
- **Rationale**: A single canonical name per value; no silent fallback.
- **Alternatives considered**: Rename `.env` to match the override instead — rejected because the audit must also confirm *no other* mismatches exist (FR-003), and `.env` is the documented source devs read.
- **Audit action**: Grep all compose files for every `${VAR...}` and confirm each resolves to a defined variable (no orphans).

### F2 — Committed host ports disagree with docs and convention (high)

- **Observed**: `.env` sets `APP_PORT=20000`, `ADMINER_PORT=20001`, `SMTP_MAIL_PORT=8888`, `SMTP_UI_PORT=8025`. Compose service defaults are `${APP_PORT:-8000}` / `${ADMINER_PORT:-8080}`. `docs/dev.md`, `README.md`, and `.env.local.example` all describe 8000 / 8080 / 8025 / 1025. So the docs are already correct against convention; `.env` is the outlier.
- **Decision** (per Clarifications): Restore conventional ecosystem defaults — app 8000, Adminer 8080, mail UI 8025, mail SMTP 1025 — as the committed `.env` defaults. Keep each individually overridable via `.env.local`. Docs need no port change.
- **Rationale**: Conventional ports are what newcomers expect and what the docs already state; reconciling `.env` to them fixes the inconsistency with the fewest doc edits.
- **Alternatives considered**: Keep the 20000-range and rewrite all docs — rejected per the clarification (conventional defaults chosen).
- **Note on SMTP 1025 collision**: The mail SMTP *host* port is only needed to connect an external mail client; the app reaches Mailpit internally at `mailer:1025` over the compose network regardless. Default the host publish to the conventional `1025` but keep it overridable; document that a machine already using 1025 sets `SMTP_MAIL_PORT` in `.env.local`. (This machine has such a collision — it is the documented override case, not a reason to change the default.)

### F3 — Application base URL points at the wrong port (high)

- **Observed**: `.env` has `APP_URL=http://localhost:8000` while the app actually ran on 20000, so console-generated absolute URLs (e.g. `app:user:reset-password`) were wrong locally.
- **Decision**: After F2 restores the app default to 8000, `APP_URL=http://localhost:8000` becomes correct. Verify `framework.router.default_uri` consumes `APP_URL` and the generated URL matches the live port.
- **Rationale**: Single source of truth — port default and base URL must agree.
- **Alternatives considered**: None; this is a direct consequence of F2.

### F4 — `make up` and raw `docker compose up` diverge (high)

- **Observed**: Docker Compose auto-loads `.env` for interpolation but **not** `.env.local`. The Makefile manually `set -a; . ./.env.local` and sets `UID=$(id -u) GID=$(id -g)`. Therefore raw `docker compose up` ignores every `.env.local` override and runs PHP as the override's default `1000:1000`. Confirmed: `docker compose config` (no make) shows the `.env` ports and `user: 1000:1000`, missing `.env.local`.
- **Decision** (per Clarifications — "make parity"): Make both paths produce identical configuration:
  1. Keep all non-secret compose-interpolation **defaults** in committed `.env` (ports restored per F2, `POSTGRES_*`, and `UID=1000` / `GID=1000`). Raw `docker compose up` then Just Works on the common single-user host.
  2. Have both paths honor `.env.local` overrides by standardizing on `COMPOSE_ENV_FILES` (Compose v2.24+; host has v2.40.3). The Makefile sets `COMPOSE_ENV_FILES=.env,.env.local` instead of hand-sourcing the file; document the same one-line `export` for developers who invoke `docker compose` directly.
  3. Simplify the Makefile `DC` macro accordingly (drop bespoke `set -a; . .env.local` sourcing).
- **Rationale**: Compose's native env-file mechanism is the standard way to layer overrides; using it for both paths removes the Makefile as a hidden special case. Defaulting `UID/GID` to 1000 covers the dominant WSL/Ubuntu case so the no-config path is healthy.
- **Alternatives considered**:
  - *direnv `.envrc`* exporting `COMPOSE_ENV_FILES` — cleaner UX but adds a tool dependency; rejected (no new tooling), but noted as an optional convenience devs may add locally.
  - *Hardcode UID/GID in `.env`* without the Makefile's live `id -u` — rejected for hosts where uid≠1000; instead keep live UID/GID export in the Makefile and document `.env.local` `UID=`/`GID=` for non-1000 raw-compose users.
- **Documented exception**: For a host whose uid≠1000, the *no-config* raw `docker compose up` path uses 1000:1000 (bind-mount writes owned by uid 1000); full parity there requires either `make` or a one-line `.env.local`/export. This residual is recorded as a documented best-practices exception, not a defect.

### F5 — CRLF line endings in tracked text files (medium)

- **Observed**: `.env` (70), `Makefile` (156), `README.md` (69), `docs/dev.md` (232), `docs/deploy.md` carry CRLF. Git warns "CRLF will be replaced by LF". A `.gitattributes` enforcing `eol=lf` exists but is **untracked** (not yet applied). `.editorconfig` already specifies `end_of_line = lf`.
- **Decision**: Commit `.gitattributes`, then `git add --renormalize .` so all tracked text files are stored with LF. CRLF in `Makefile`/`bin/*`/`*.sh` is an active hazard inside Linux containers (shebang `\r` breaks execution); `.gitattributes` already covers these.
- **Rationale**: Cross-platform (WSL2) edits otherwise reintroduce churn and can break container scripts; `.gitattributes` + renormalize is the standard fix.
- **Alternatives considered**: Rely on `.editorconfig` alone — rejected; editorconfig governs editors, not git's storage/normalization.

### F6 — Dockerfile / `.dockerignore` hygiene (low — mostly compliant)

- **Observed**: Image pins `php:8.4.1-fpm-alpine`, pulls Composer from `composer:2`, creates an `app` user matching build-arg UID/GID, installs only needed extensions, bakes no secrets. `.dockerignore` is comprehensive (excludes `.git`, `vendor`, `var`, `.env.local`, `.env.*.local`, tooling/docs/SQL). Compose uses top-level `name:` and no obsolete `version:` key.
- **Decision**: Keep as-is; record as **pass** on the best-practices checklist. Optional minor note: `apk add` and `docker-php-ext-install` are two RUN layers — acceptable, no change required.
- **Rationale**: Already follows image-hygiene conventions; changing for its own sake adds risk.
- **Alternatives considered**: Multi-stage build to drop `*-dev` headers from the final image — deferred; marginal size benefit, out of scope for this audit.

### F7 — Env-file layering clarity (low)

- **Observed**: `.env` doubles as Symfony's committed defaults **and** Compose's interpolation source (ports, `POSTGRES_*`). `.env.local.example` documents the override flow well, including the Postgres "first-init only" caveat. `.env.test` carries a clearly-fake test secret. `.gitignore` correctly ignores `.env.local` / `.env.*.local`.
- **Decision**: Keep the cascade; add a short comment block in `.env` grouping the compose-interpolation variables (ports, `UID`/`GID`, `POSTGRES_*`) and noting they are read by both Symfony and Compose. No structural change.
- **Rationale**: The overlap is inherent to Symfony+Compose and is fine once documented; explicit grouping prevents future "is this Symfony or Compose?" confusion.
- **Alternatives considered**: Split compose vars into a separate file — rejected; would break Compose's automatic `.env` auto-load and add a file for no real gain.

## Resolved unknowns

All Technical Context items are known; no `NEEDS CLARIFICATION` remained after
`/speckit.clarify`. The two design decisions that required investigation (port
reconciliation direction and start-path parity mechanism) are resolved in F2 and F4.

## Best-practices checklist (final — confirmed during implementation)

| # | Item | Status |
|---|------|--------|
| 1 | No obsolete `version:` key; uses top-level `name:` | ✅ Pass |
| 2 | Base vs override concerns cleanly separated | ✅ Pass (base = shared services/healthchecks; override = host publishing + dev tools) |
| 3 | Every compose `${VAR}` resolves to a defined variable | ✅ Pass (T003 — no orphans, no unset warnings) |
| 4 | Conventional host ports, consistent across artifacts | ✅ Pass (T004 — `.env` defaults 8000/8080/8025/1025 = docs) |
| 5 | App base URL matches reachable port | ✅ Pass (T005/T011 — `APP_URL=…:8000` via `%env(APP_URL)%`) |
| 6 | Both start paths yield identical config | ✅ Pass (T012 — make path == raw path) + documented uid≠1000 exception |
| 7 | Secrets absent from committed files and image layers | ✅ Pass (`.env.local` gitignored + dockerignored; `APP_SECRET` blank in `.env`) |
| 8 | Example/template files contain only fake placeholders | ✅ Pass (`.env.local.example`, `.env.test`) |
| 9 | Image pinned, minimal, host-matching ownership | ✅ Pass (`php:8.4.1-fpm-alpine`, app user = build UID/GID) |
| 10 | Build context excludes non-image files | ✅ Pass (`.dockerignore` excludes secrets/vendor/var/tooling) |
| 11 | Meaningful healthchecks on every long-running service | ✅ Pass (T016 — added PHP-socket healthcheck to adminer; all others present) |
| 12 | LF line endings enforced + applied | ⚠️ Documented exception — `.gitattributes` tracked (enforced; overrides host `core.autocrlf`), but repo-wide `git add --renormalize .` deferred to a dedicated standalone commit (per user decision: keep this feature's diff focused). ~180 pre-existing files remain CRLF until then. |
| 13 | `.editorconfig` present and consistent with `.gitattributes` | ✅ Pass (both enforce LF/UTF-8) |
| 14 | Optional services opt-in only (legacy MySQL profile) | ✅ Pass (`profiles: [legacy]`) |
| 15 | Docs match running stack | ✅ Pass (T010/T019 — docs/README reference 8000/8080/8025) |

**Outcome**: 14/15 pass; item 12 is a documented, deliberate deferral (the durable fix
— `.gitattributes` — is in place; only the bulk historical conversion is sequenced into
its own commit). No unaddressed items remain.
