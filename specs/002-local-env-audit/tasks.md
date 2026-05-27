---
description: "Task list for Audit Local Development Environment"
---

# Tasks: Audit Local Development Environment

**Input**: Design documents from `/specs/002-local-env-audit/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: No automated test suite is added by this feature (per plan). Verification is
done via the manual `quickstart.md` walkthrough and `docker compose config` diff checks.
"Verify" tasks below stand in for tests.

**Organization**: Tasks are grouped by the three user stories from spec.md so each can
be implemented and verified independently.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: US1 / US2 / US3 (maps to spec.md user stories)
- All paths are repository-root relative to `/home/david/projects/personal/slack-cz/`

## Path Conventions

This feature edits repository-root infrastructure/config files only (no `src/`).
Files in scope: `.env`, `.env.local.example`, `compose.yaml`, `compose.override.yaml`,
`Makefile`, `docker/php/Dockerfile`, `.dockerignore`, `.gitattributes`, `.editorconfig`,
`docs/dev.md`, `README.md`.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Capture the before-state so changes can be verified objectively.

- [X] T001 Capture baseline: save current `docker compose config` to `/tmp/env-audit-before.yaml` and record current `docker compose ps` health + published ports for before/after comparison
- [X] T002 [P] Confirm the triaged F1 fix is present: `compose.override.yaml` mailer publishes use `SMTP_MAIL_PORT`/`SMTP_UI_PORT` (matching `.env`), not the old `MAIL_*` names

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Establish a clean variable map before any value is reconciled — both user stories depend on every compose interpolation resolving to a real `.env` key.

**⚠️ CRITICAL**: Complete before Phase 3.

- [X] T003 Audit every `${VAR}` reference in `compose.yaml` and `compose.override.yaml` against keys defined in `.env`; list any orphaned/misspelled names. Cross-check against the matrix in `specs/002-local-env-audit/data-model.md`. Record result (expect: only F1, already fixed)

**Checkpoint**: Variable map is clean — value reconciliation can begin.

---

## Phase 3: User Story 1 - New contributor reaches a running app from a clean clone (Priority: P1) 🎯 MVP

**Goal**: A fresh clone + single command yields a healthy stack whose endpoints are reachable on the conventional ports the docs already state.

**Independent Test**: `quickstart.md` section A — clone, `make first-run`, all services healthy, app/adminer/mailpit return 200 on 8000/8080/8025; re-running `make first-run` is idempotent.

### Implementation for User Story 1

- [X] T004 [US1] In `.env`, restore conventional host-port defaults: `APP_PORT=8000`, `ADMINER_PORT=8080`, `SMTP_UI_PORT=8025`, `SMTP_MAIL_PORT=1025` (replaces the 20000/20001/8888 values)
- [X] T005 [US1] In `.env`, set `APP_URL=http://localhost:8000` so it matches the app's published port (resolves F3); confirm `framework.router.default_uri` consumes `APP_URL`
- [X] T006 [US1] In `Makefile`, ensure the `first-run` endpoint summary prints the actual effective ports (8000/8080/8025) rather than any hardcoded stale value
- [X] T007 [US1] **Verify (quickstart A)**: run `make nuke && make first-run` on a clean state; confirm all default-profile services reach healthy and `curl` returns 200 on 8000/8080/8025; re-run `make first-run` to confirm idempotency. NOTE: this host has a 1025 collision (other `api-maildev` project) — set `SMTP_MAIL_PORT` to a free port in **`.env.local`** (not `.env`) for the local run; this is the documented override case, not a default change

**Checkpoint**: Fresh-clone bootstrap works and endpoints match the docs (MVP complete).

---

## Phase 4: User Story 2 - Configuration has a single, predictable source of truth (Priority: P2)

**Goal**: Each value has one authoritative definition, and `make up` and raw `docker compose up` produce identical configuration.

**Independent Test**: `quickstart.md` sections B & C — no orphaned vars, docs/defaults agree, base-URL generation correct, and `docker compose config` is identical between the make path and the documented raw path.

### Implementation for User Story 2

- [X] T008 [US2] In `Makefile`, change the `DC` macro to set `COMPOSE_ENV_FILES=.env,.env.local` (and keep `UID=$(id -u) GID=$(id -g)`), and remove the bespoke `set -a; [ -f .env.local ] && . ./.env.local; set +a` sourcing
- [X] T009 [US2] In `.env`, add `UID=1000`/`GID=1000` defaults and a grouped, commented "compose interpolation variables" block (ports, UID/GID, POSTGRES_*) noting these are read by both Symfony and Docker Compose (resolves F4 step 1 + F7). **Depends on T004/T005** (same file)
- [X] T010 [US2] In `docs/dev.md`, document that invoking `docker compose` directly requires `export COMPOSE_ENV_FILES=.env,.env.local` for parity with `make`, and document the uid≠1000 raw-compose exception per `contracts/env-and-ports.md`
- [X] T011 [US2] **Verify (quickstart B)**: `docker compose config` emits no "variable is not set" warnings; ports/URLs in `docs/dev.md`/`README.md`/`.env.local.example` equal `.env` defaults; `app:user:reset-password` generates an `http://localhost:8000` URL
- [X] T012 [US2] **Verify (quickstart C)**: diff `docker compose config` from the make path vs the raw path (with `COMPOSE_ENV_FILES` exported) → identical, except the documented uid≠1000 `user:` residual

**Checkpoint**: One source of truth per value; both start paths converge.

---

## Phase 5: User Story 3 - Environment conforms to documented best practices (Priority: P3)

**Goal**: Line endings normalized, image/compose hygiene confirmed, docs accurate, and the best-practices checklist resolved.

**Independent Test**: `quickstart.md` section D (LF-only, no eol churn) plus a completed best-practices checklist where every item is pass or documented-exception.

### Implementation for User Story 3

- [X] T013 [P] [US3] Add the currently-untracked `.gitattributes` to version control and run `git add --renormalize .` to convert tracked CRLF files (`.env`, `Makefile`, `README.md`, `docs/*.md`) to LF (resolves F5)
- [X] T014 [P] [US3] Confirm `.editorconfig` is consistent with `.gitattributes` (both enforce LF); record as pass, no change expected
- [X] T015 [P] [US3] Confirm `docker/php/Dockerfile` + `.dockerignore` hygiene (pinned base image, Composer from `composer:2`, host-matching UID/GID, no secrets, build-context excludes `.env.local`/`vendor`/`var`/tooling); record findings (F6)
- [X] T016 [P] [US3] Evaluate healthchecks on all long-running services in `compose.yaml`/`compose.override.yaml`; add a healthcheck to `adminer` if it lacks one (currently relies on `--wait` running-state); record decision
- [X] T017 [US3] Complete the 15-item best-practices checklist in `specs/002-local-env-audit/research.md`, marking each item pass or documented-exception
- [X] T018 [US3] **Verify (quickstart D)**: clean checkout shows no eol churn (`git status --porcelain` clean); `grep -rIl $'\r'` finds no CRLF in the in-scope text files

**Checkpoint**: Best-practices checklist resolved; repo is normalized.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Final accuracy pass and full acceptance run.

- [X] T019 [P] Final accuracy pass on `docs/dev.md` and `README.md`: every command, port, and endpoint matches the running stack (resolves remaining FR-005 gaps)
- [X] T020 Run the full `quickstart.md` A–E walkthrough end-to-end (including the port-collision override edge case, section E) and confirm all pass
- [X] T021 [P] Ensure local `.env.local` contains no temporary test artifacts and no secrets are committed; confirm `git check-ignore .env.local` returns the path

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — start immediately.
- **Foundational (Phase 2)**: Depends on Setup — BLOCKS all user stories.
- **User Stories (Phase 3–5)**: All depend on Foundational. US1 → US2 → US3 in priority order for solo work.
- **Polish (Phase 6)**: Depends on all desired stories being complete.

### Cross-story file note (important)

`.env` is edited by **US1** (T004/T005: port + URL values) and **US2** (T009: UID/GID + grouping). These are different lines but the same file, so **T009 must run after T004/T005** — it is not parallel with them. The stories remain independently *testable* regardless.

### Within Each User Story

- US1: T004 → T005 (same file, sequential) → T006 → T007 (verify last).
- US2: T008 and T009 touch different files (Makefile vs `.env`) but T009 follows US1; T010 doc edit independent; T011/T012 verify last.
- US3: T013–T016 are [P] (different files/concerns); T017 aggregates after them; T018 verifies.

### Parallel Opportunities

- T002 can run alongside T001.
- Within US3: T013, T014, T015, T016 can run in parallel (distinct files/concerns).
- In Polish: T019 and T021 can run in parallel.

---

## Parallel Example: User Story 3

```bash
# These four can be worked in parallel (different files / independent checks):
Task: "T013 Add .gitattributes to tracking + git add --renormalize ."
Task: "T014 Confirm .editorconfig consistent with .gitattributes"
Task: "T015 Confirm Dockerfile + .dockerignore hygiene"
Task: "T016 Evaluate/add adminer healthcheck in compose files"
```

---

## Implementation Strategy

### MVP First (User Story 1 only)

1. Phase 1 Setup → Phase 2 Foundational → Phase 3 (US1).
2. **STOP and VALIDATE**: quickstart section A — fresh clone reaches a healthy app on conventional ports.
3. This alone fixes the headline problem (broken/inconsistent startup) and is shippable.

### Incremental Delivery

1. Setup + Foundational → clean variable map.
2. US1 → correct ports + working bootstrap (MVP).
3. US2 → single source of truth + start-path parity.
4. US3 → normalization + best-practices conformance.
5. Polish → final doc accuracy + full quickstart run.

---

## Notes

- [P] = different files, no incomplete dependencies.
- Verification tasks replace automated tests (none added per plan).
- Commit after each logical group; commit `.gitattributes` (T013) before renormalizing so git stores LF.
- The 1025 SMTP host-port collision on this machine is handled via `.env.local` (documented override), never by changing the committed default.
- Total: 21 tasks — Setup 2, Foundational 1, US1 4, US2 5, US3 6, Polish 3.
