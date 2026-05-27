# Implementation Plan: Audit Local Development Environment

**Branch**: `002-local-env-audit` | **Date**: 2026-05-27 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/002-local-env-audit/spec.md`

## Summary

Bring the local development environment back into a coherent, best-practice state.
The audit found the stack drifted: a mail-port variable-name mismatch broke startup
(already fixed during triage), committed `.env` host ports (20000/20001/8888) disagree
with the documentation and with the app's own base URL, and the two start paths
(`make up` vs raw `docker compose up`) diverge because only the Makefile loads
`.env.local`. The plan reconciles every configurable value to a single authoritative
source, restores the conventional ecosystem ports (8000/8080/8025), makes both start
paths produce identical configuration, normalizes line endings, and verifies the docs
against the running stack — then records the result against a best-practices checklist.

This is a configuration/documentation remediation (per Clarifications: audit **and**
apply fixes). No application source code changes; no new tooling introduced.

## Technical Context

**Language/Version**: PHP 8.4 (image `php:8.4.1-fpm-alpine`); config in YAML, dotenv, GNU Make, Dockerfile  
**Primary Dependencies**: Docker Engine 24+, Docker Compose v2 (host has v2.40.3), Symfony 7.3 env-file cascade, Apache httpd 2.4, PostgreSQL 16, Mailpit, Adminer  
**Storage**: PostgreSQL 16 (app), MySQL 8.4 (opt-in legacy profile) — not modified by this feature  
**Testing**: Manual quickstart verification (fresh-clone → running app) + `docker compose config` diff checks; no automated test suite added  
**Target Platform**: Linux / WSL2 dev hosts running Docker Engine + Compose v2  
**Project Type**: Web application (containerized) — this feature touches repo-root infrastructure/config only  
**Performance Goals**: N/A (developer-experience feature; success is correctness + consistency, not throughput)  
**Constraints**: No new services added/removed; no application-code linting tooling; defaults must work zero-config for the common single-user (uid 1000) host; every value individually overridable  
**Scale/Scope**: ~9 in-scope artifacts (compose base + override, `.env*` cascade, Makefile, Dockerfile, `.dockerignore`, `.gitattributes`, `.editorconfig`, docs)

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

The project constitution (`.specify/memory/constitution.md`) is an unfilled template
with only placeholder principles — no ratified gates exist. **Gate status: PASS (no
constraints defined).** This feature is low-risk (config + docs, reversible via git,
no schema or data changes) and would satisfy common-sense principles (simplicity,
single source of truth, documented behavior) regardless.

## Project Structure

### Documentation (this feature)

```text
specs/002-local-env-audit/
├── plan.md              # This file
├── research.md          # Phase 0 — findings catalog + decisions
├── data-model.md        # Phase 1 — configuration source-of-truth matrix
├── quickstart.md        # Phase 1 — fresh-clone acceptance walkthrough
├── contracts/
│   ├── env-and-ports.md # Phase 1 — env-variable + host-port contract
│   └── make-targets.md  # Phase 1 — developer command interface contract
└── tasks.md             # Phase 2 — created by /speckit.tasks (NOT here)
```

### Source Code (repository root)

This feature modifies repository-root infrastructure and documentation files only —
there is no `src/` involvement. Concrete files in scope:

```text
.
├── compose.yaml             # base services, healthchecks, volumes
├── compose.override.yaml    # local-dev publishing + dev tools (mailer, adminer)
├── .env                     # committed defaults (Symfony + compose interpolation)
├── .env.dev                 # dev-only non-secret overrides
├── .env.local.example       # template copied to .env.local on first run
├── .env.test                # test-env config
├── Makefile                 # developer entry points (first-run/up/down/…)
├── docker/php/Dockerfile    # PHP-FPM image build
├── .dockerignore            # build-context exclusions
├── .gitattributes           # line-ending normalization (currently untracked)
├── .editorconfig            # editor formatting rules
├── docs/dev.md              # dev cookbook + endpoint reference
└── README.md                # top-level setup instructions
```

**Structure Decision**: No code-layout decision needed. The "structure" for this
feature is the configuration inventory above; the authoritative-source mapping is
captured in `data-model.md`.

## Complexity Tracking

No constitution violations; no complexity to justify. Table omitted.
