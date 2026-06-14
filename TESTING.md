# TRACS Testing Baseline

## Purpose

This document defines the safety baseline required before the full TRACS
refactor begins. Phase 1 is documentation-only: it does not change application
behavior, UI layout, business rules, dependencies, or database schema.

The baseline protects the current PHP application while TRACS gradually moves
toward:

- React JS as the frontend foundation.
- Tailwind CSS mapped to the existing TRACS design system.
- A cleaner advanced PHP backend and API layer.
- MySQL as the primary database.
- Incremental, branch-based, reviewable, rollback-friendly delivery.

`public/calendar.php` and its completed React pilot are the zero-mistake
reference implementation for future modules. Its PHP shell, React island,
Tailwind isolation, API boundary, visual density, interactions, responsive
behavior, loading/empty/error states, modals, toasts, and `dd-mm-yyyy` display
format must be treated as reference behavior, not casually redesigned.

## Current State

TRACS does not yet have a formal automated test or CI suite. Until automated
coverage is approved and implemented, every release must use:

1. The manual smoke checklist in
   `docs/manual-smoke-checklist.md`.
2. The permission and API contract checklist in
   `docs/permission-api-contract-checklist.md`.
3. The Calendar reference regression checklist in
   `docs/calendar-reference-regression-checklist.md`.
4. The existing security checklist in `SECURITY_AUDIT_CHECKLIST.md`.
5. Module-specific documentation and deployment verification.

## Test Priorities

| Priority | Coverage |
| --- | --- |
| P0 | Login, 2FA, logout, sessions, CSRF, API authentication, roles, permissions, and exact Super Admin restrictions |
| P0 | Calendar, Shift Assignment, canonical shift times, and seeded schedule visibility |
| P1 | Dashboard, Cases, Checklist, Reminders, Tasks, Shift Reports, notifications, uploads, and CSV exports |
| P1 | MoM and its connected reminder, case, ticker, screenshot, and operational-status flows |
| P2 | User Management, Domain Price Crosscheck, Domain Transfer Log, Finance, Feedback, and Activity |
| P3 | Infrastructure Pulse, OpsTrack signals, Network Pulse, TV Mode, and other partial/prototype monitoring surfaces |

## Module Risk Levels

| Risk | Modules and surfaces |
| --- | --- |
| Critical | Authentication, 2FA, sessions, roles, permissions, User Management, Shift Assignment, and Server Health & Logs |
| High | Calendar, Dashboard, Cases, MoM, Domain Price Crosscheck, notifications, protected uploads, and exports |
| Medium | Shift Reports, Checklist, Reminders, Tasks, Domain Transfer Log, Finance, Activity Log, and Cancellation Feedback |
| Medium / unknown | Infrastructure Pulse, OpsTrack, Network Pulse, and TV Mode because some monitoring behavior is partial or prototype |

## Test Data Rules

- Never run destructive tests against production.
- Use a disposable MySQL database initialized from `config/install.sql`.
- Apply only reviewed migrations required by the tested revision.
- Use deterministic test users for Super Admin, Admin, Supervisor, Agent,
  Intern, Viewer, inactive, and pending-2FA states.
- Use `Asia/Jakarta` for date/time expectations.
- Mark test-created records with a clear test-only prefix.
- Preserve and verify object ownership and division scope.
- Back up any non-disposable database before integration or migration tests.
- Do not run `bin/seed-default-shift-schedule.php --apply` against shared data
  unless the target database and cleanup procedure are explicitly approved.

## Characterization Principle

Initial tests must record current behavior before enforcing a preferred future
design. If an endpoint currently differs from the target response envelope,
document the difference and protect the existing behavior first. Business or
contract changes require a separate reviewed change.

The future standard API envelope is:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {},
  "errors": [],
  "meta": {}
}
```

## Future Automated Test Tools

These tools are recommended but are not installed by this phase:

- PHPUnit for PHP unit, service, validation, security, and integration tests.
- Playwright for browser smoke, permission, and critical workflow tests.
- A disposable MySQL 8 test service through Docker Compose or CI services.
- Vitest for future shared React hooks, utilities, and components.
- JSON Schema or focused assertions for API response contracts.
- Shell checks for installer, migration, export, and deployment preflight.

Recommended future structure:

```text
tests/
  bootstrap/
    app.php
    database.php
  Fixtures/
    users.php
    operational.php
    shifts.php
  Unit/
    Core/
    Services/
  Integration/
    Api/
    Database/
    Security/
  Browser/
    auth/
    smoke/
    permissions/
    workflows/
  Contracts/
    api-response.schema.json
  snapshots/
    csv/
  manual/
```

Tests must remain outside `public/` and must never contain production secrets.

## Future CI Direction

CI is not implemented in Phase 1. A later approved workflow should:

1. Check out the reviewed commit.
2. Install pinned PHP and Node dependencies.
3. Start an isolated MySQL service.
4. Import `config/install.sql` and apply explicitly selected migrations.
5. Run PHP syntax checks and automated tests.
6. Build the Calendar frontend and any future React entries.
7. Run browser smoke and permission tests against a disposable application.
8. Upload only sanitized logs and screenshots on failure.
9. Fail without deploying when any P0 or P1 test fails.

## Refactor Entry Gate

A module may enter refactoring only after:

- Its current routes, APIs, permissions, database tables, and cross-module
  effects are documented.
- Its smoke, permission, and business-critical behaviors have baseline coverage.
- Its rollback path and database backup requirements are written.
- The Calendar reference checklist has been applied to its proposed UI.
- Existing PHP behavior remains available until replacement parity is verified.
