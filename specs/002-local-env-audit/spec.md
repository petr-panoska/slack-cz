# Feature Specification: Audit Local Development Environment

**Feature Branch**: `002-local-env-audit`  
**Created**: 2026-05-27  
**Status**: Draft  
**Input**: User description: "Perform an audit of local environment - think about usage of docker compose, compose override, env files, etc... also incorporate industry standards and best practises, code styles, etc..."

## Overview

The project's local development environment is configured through several layered
artifacts: Docker Compose base + override files, a cascade of `.env*` files, a
`Makefile`, a PHP container image, and developer documentation. Over time these
have drifted out of sync with one another, and at least one drift caused the stack
to fail to start. This feature audits that environment against internal consistency
and recognized industry conventions, then brings it back into a coherent,
documented, best-practice state so that any developer can reliably run the project
locally.

## Clarifications

### Session 2026-05-27

- Q: What is the deliverable for this audit? → A: Audit + apply fixes — produce findings and remediate so the environment reaches the best-practice end-state.
- Q: Which way should the host-port inconsistency be reconciled? → A: Conventional defaults — return defaults to 8000 (app) / 8080 (database UI) / 8025 (mail UI) and update docs and base URL to match.
- Q: Should raw container tooling (`docker compose up`) behave identically to the task-runner path (`make up`)? → A: Make parity — both paths must produce identical effective configuration.
- Q: How far should "code styles" scope go? → A: Normalization only — line-ending normalization, editor config, and consistent formatting of in-scope config/docs files; no new application-code linting/formatting tooling.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - New contributor reaches a running app from a clean clone (Priority: P1)

A developer who has never worked on the project clones the repository and follows
the documented setup path. The environment starts on the first attempt, every
service becomes healthy, and the URLs the developer is told to open actually serve
the app — with no manual file edits, no guesswork about ports, and no hidden steps.

**Why this priority**: This is the entry point for every contributor. If a fresh
clone cannot reach a running app by following the documentation, nothing else about
the environment matters. The audit already surfaced a startup-breaking defect, so
this is both the highest-value and highest-risk story.

**Independent Test**: On a machine with only the documented prerequisites installed,
clone the repo, run the single documented bootstrap command, and confirm the app
responds successfully at every URL the documentation lists, with all services
reporting healthy.

**Acceptance Scenarios**:

1. **Given** a clean checkout with no local override file, **When** the developer runs the documented bootstrap command, **Then** all services start, become healthy, and the application responds successfully without any manual edits.
2. **Given** the stack is running, **When** the developer opens each URL listed in the documentation (application, database UI, mail UI), **Then** each URL serves the expected interface on the port the documentation states.
3. **Given** the documented bootstrap command, **When** it is run a second time on the same machine, **Then** it completes successfully and idempotently without errors or duplicate side effects.

---

### User Story 2 - Configuration has a single, predictable source of truth (Priority: P2)

A developer changes one setting — a host port, a database credential, a service
toggle — in one documented place, and that change takes effect consistently
everywhere it is used. Two developers starting the stack by different documented
means (for example, via the task runner versus invoking the container tooling
directly) get the same effective configuration.

**Why this priority**: Inconsistent or duplicated configuration is the root cause of
the drift this audit addresses (mismatched variable names, ports that disagree
between files and docs, a base URL that points at the wrong port). Eliminating
duplicate sources of truth prevents the whole class of defect from recurring.

**Independent Test**: Pick each configurable value (ports, credentials, mail
transport, base URL). Trace it through every artifact that references it and confirm
there is exactly one authoritative definition, that all references agree, and that
overriding it in the documented location changes the running stack as expected.

**Acceptance Scenarios**:

1. **Given** a configurable host port, **When** its value is traced across env files, compose files, and documentation, **Then** every reference resolves to the same effective value and there are no orphaned or misspelled variable names.
2. **Given** a developer overrides a port or credential in the documented override location, **When** the stack is started, **Then** the running services and the application's own configuration both reflect the override.
3. **Given** two documented ways to start the stack, **When** each is used on the same machine, **Then** the resulting service configuration (ports, environment, volumes) is identical.
4. **Given** the application generates an absolute URL from configuration (for example, a password-reset link from the console), **When** it is produced in the local environment, **Then** the URL points at the host and port on which the local app is actually reachable.

---

### User Story 3 - Environment conforms to documented best practices (Priority: P3)

A reviewer evaluates the local environment against a checklist of recognized
conventions — for Docker Compose structure, env-file layering, secret handling,
container image hygiene, line-ending and code-style normalization — and every item
either passes or has a recorded, deliberate rationale for deviating.

**Why this priority**: Beyond "it works," the environment should be maintainable and
recognizable to any developer familiar with the ecosystem. This hardens the
environment against future drift and lowers the learning curve, but it builds on the
correctness (P1) and consistency (P2) work rather than standing alone.

**Independent Test**: Walk the best-practices checklist item by item against the
committed configuration and confirm each item is either satisfied or accompanied by a
documented justification.

**Acceptance Scenarios**:

1. **Given** the best-practices checklist, **When** each item is evaluated against the committed environment, **Then** every item is marked pass or carries a written rationale for the exception.
2. **Given** the audit is complete, **When** a developer reads the environment documentation, **Then** it accurately describes the current configuration, prerequisites, available commands, and service endpoints with no stale instructions.
3. **Given** files are edited on different host operating systems, **When** they are committed, **Then** line endings and formatting remain normalized so the repository does not accumulate spurious whitespace or encoding changes.

---

### Edge Cases

- A required host port is already occupied by another project on the developer's machine — the developer must be able to relocate it through a single documented override, and the default values should minimize the chance of collision.
- The stack is started by invoking the container tooling directly rather than through the task runner — documented behavior must be predictable, and any prerequisite (such as which override file is loaded) must be stated.
- A fresh clone has no local override file — bootstrap must create one from the example automatically and succeed.
- Database credentials are changed after the data volume already exists — the documentation must explain that this requires reinitialization and how to do it safely.
- The repository is worked on from a Windows/WSL host — checkouts and edits must not introduce line-ending churn.
- A service fails to become healthy at startup — the failure must surface clearly rather than hanging or silently producing a broken app.
- The optional legacy database profile is or is not enabled — the default startup path must not depend on opt-in services.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The local environment MUST start successfully from a clean clone using a single documented command, with all default-profile services reaching a healthy state.
- **FR-002**: Every configurable value (host ports, database credentials, mail transport, application base URL, container user identity) MUST have exactly one authoritative definition; duplicate or conflicting definitions MUST be eliminated.
- **FR-003**: All variable references between env files and compose files MUST resolve correctly — there MUST be no misspelled, orphaned, or mismatched variable names that silently fall back to unintended defaults.
- **FR-004**: Default host port assignments MUST be the conventional ecosystem ports — 8000 (application), 8080 (database UI), 8025 (mail UI) — applied consistently across all artifacts, and these defaults MUST remain individually overridable for developers whose machines already use those ports.
- **FR-005**: Documentation MUST accurately reflect the actual configuration, including prerequisites, setup steps, available commands, service endpoints, and the exact ports on which services are reachable.
- **FR-006**: Overriding a configuration value in the single documented override location MUST propagate to both the running containers and the application's own runtime configuration.
- **FR-007**: Starting the stack through the task runner and through the container tooling directly MUST yield identical effective service configuration (ports, environment, file ownership, loaded overrides); neither path may silently diverge from the other.
- **FR-008**: Secrets and local-only values MUST be kept out of committed files and out of built image layers; committed example/template files MUST contain only clearly non-secret placeholder values.
- **FR-009**: The application's configured base URL in the local environment MUST match the host and port on which the local app is actually reachable, so generated absolute URLs are valid.
- **FR-010**: The compose configuration MUST follow current conventions for the tooling version in use (no obsolete keys, clear separation of base versus local-override concerns, explicit and meaningful health checks).
- **FR-011**: The container image definition MUST follow image-hygiene conventions (minimal layers, no secrets baked in, host-matching file ownership for bind mounts) and the build context MUST exclude files that do not belong in the image.
- **FR-012**: The repository MUST enforce normalized line endings and editor configuration, and the configuration and documentation files in scope MUST be consistently formatted, so that cross-platform edits do not introduce spurious diffs. Introducing application-code linting or formatting tooling is out of scope for this audit.
- **FR-013**: The audit MUST produce a checklist of evaluated best-practice items, each marked as satisfied or accompanied by a documented rationale for deliberate deviation.
- **FR-014**: Startup failures (occupied port, unhealthy service, missing required secret) MUST surface clearly and promptly rather than hanging or producing a silently broken application.
- **FR-015**: Optional or opt-in services (such as the legacy database) MUST NOT be required for the default startup path, and their opt-in nature MUST be documented.

### Configuration Artifacts In Scope

- **Compose base file**: Defines the services, networks, volumes, and health checks shared across all developers.
- **Compose override file**: Defines local-development-only adjustments (development conveniences, supporting tools, host-port publishing).
- **Env file cascade**: The layered base, environment-specific, local-override, example/template, and test env files — including which layer is authoritative for which value and which are committed versus ignored.
- **Task runner**: The documented entry points for bootstrapping, starting, stopping, and operating the stack.
- **Container image definition**: The PHP image build, including build arguments, installed extensions, file ownership, and build-context exclusions.
- **Developer documentation**: The setup and "cookbook" docs that tell developers how to run and operate the environment, including the service endpoint reference.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A developer with only the documented prerequisites can go from clone to a fully running, healthy app by running a single command, with zero manual file edits required.
- **SC-002**: 100% of service endpoints (ports and URLs) named in the documentation match the values that actually take effect when the stack starts.
- **SC-003**: Zero variable-name mismatches exist between env files and compose files; every interpolated variable resolves to an intentional value (verified by tracing each one).
- **SC-004**: Each configurable value has exactly one authoritative source; changing it in the documented location is reflected in the running stack on the next start in 100% of traced cases.
- **SC-005**: The task-runner path and the direct container-tooling path produce identical effective service configuration in 100% of traced settings (ports, environment, file ownership, loaded overrides).
- **SC-006**: Every item on the best-practices checklist is resolved to either "pass" or "documented exception," with no unaddressed items remaining.
- **SC-007**: The environment documentation is verified against the running environment with no stale or incorrect instructions.
- **SC-008**: A clean checkout followed immediately by the bootstrap command produces no spurious line-ending or formatting diffs in version control.

## Assumptions

- The deliverable is both the audit findings and the remediation that brings the environment into the desired best-practice state described here; the spec defines the target end-state rather than only a report.
- "Industry standards and best practices" refers to current, widely recognized conventions for the project's existing toolchain (Docker Compose v2, the Symfony env-file cascade, container image hygiene, and repository code-style/line-ending normalization). The audit aligns to these without introducing new tools or rewriting the application.
- Where documentation and configuration disagree on host ports, reconciliation returns the defaults to the conventional ecosystem ports (8000 / 8080 / 8025) and updates docs and the application base URL to match (see Clarifications). For other disagreements, they must agree on one authoritative source.
- Scope is the local development environment and the shared configuration that affects it (including the test environment's configuration consistency). Production deployment scripts and server provisioning are out of scope except where they share the same env-file or compose conventions being normalized.
- The existing service set (web, PHP runtime, primary database, database UI, mail catcher, and opt-in legacy database) is retained; the audit does not add or remove services beyond what is needed to satisfy consistency and best-practice requirements.
- A startup-breaking variable-name mismatch in the mail service port has already been identified and corrected during initial triage; the audit confirms no similar mismatches remain elsewhere.
- Developers run on Linux or WSL2 with a container engine and Compose v2 available, consistent with the current documented setup.
