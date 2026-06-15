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

## Phase 5 PHP API Foundation Check

The isolated foundation includes a dependency-free CLI check:

```bash
find api tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/php-api-foundation.php
```

It verifies:

- The exact `success`, `message`, `data`, `errors`, and `meta` keys.
- JSON object parsing and malformed JSON rejection.
- Required-field error mapping.
- Backend `YYYY-MM-DD` validation while UI remains `dd-mm-yyyy`.
- Unauthenticated requests return a safe JSON error with no data.
- Invalid CSRF requests return the same sanitized five-key JSON envelope.

It does not connect to MySQL. Authenticated, permission, CSRF, audit-write, and
object-scope integration tests must use a disposable database and fixture
accounts. Do not create fake production sessions or change production records
for these checks.

## Phase 5.5 Pilot API Contract

Run:

```bash
php tests/php-api-foundation.php
php tests/php-api-contract.php
find api tests public/api/v1 -name '*.php' -exec php -l {} \;
```

The contract test verifies:

- Exact five-key response envelope.
- Allowlisted user and role fields only.
- Sorted, unique effective permissions.
- CSRF token/header handoff.
- `meta.request_id`.
- Exclusion of email, password, account status, division, and 2FA fields.
- The public route uses the authenticated Phase 5 GET bootstrap and standard
  response helper.

With the local Docker app running, verify unauthenticated and wrong-method
behavior:

```bash
curl -i http://127.0.0.1:8080/api/v1/context.php
curl -i -X POST http://127.0.0.1:8080/api/v1/context.php
```

Expect `401` and `405` respectively, valid JSON, `Cache-Control: no-store`,
`Allow: GET` on the second response, no `X-Powered-By`, and a request ID in
metadata.

For an authenticated manual check, sign in through the normal login and 2FA
flow, then run this in the same browser's developer console:

```js
const response = await fetch('/api/v1/context.php', {
  credentials: 'same-origin',
  headers: { Accept: 'application/json' },
});
console.log(response.status, await response.json());
```

Expect `200`, `Context loaded.`, only the documented user fields, effective
permission keys, a non-empty CSRF token, and `meta.request_id`. GET must not
write module data. Permission-denial testing is not applicable to this
bootstrap route because every fully authenticated active account is allowed;
module pilots must add explicit `403` tests.

## Phase 6 Shift Assignment Contract

Run:

```bash
php tests/php-api-foundation.php
php tests/php-api-contract.php
php tests/shift-assignment-api-contract.php
find api tests public/api/v1 -name '*.php' -exec php -l {} \;
```

The test verifies the five-key envelope, request ID, canonical shift hours,
Shift 3 cross-day storage, eight-hour rest threshold, supported views,
date-format boundary, permission/action mapping, absent assignment-delete
behavior, and sensitive-field exclusions.

Manual endpoint checks:

```bash
curl -i http://127.0.0.1:8080/api/v1/shift-assignment/context.php
curl -i -X POST http://127.0.0.1:8080/api/v1/shift-assignment/context.php
```

Expect unauthenticated GET `401` and POST `405` with `Allow: GET`. With normal
fixture accounts in a disposable environment, verify `403` without
`shifts.view`, `200` with it, and the role/division matrix in
`docs/shift-assignment-api-contract.md`. Do not alter real schedules.

## Phase 7 Shift Assignment Read API

Run:

```bash
php tests/php-api-foundation.php
php tests/php-api-contract.php
php tests/shift-assignment-api-contract.php
php tests/shift-assignment-assignments-api-contract.php
find api tests public/api/v1 -name '*.php' -exec php -l {} \;
```

The Phase 7 contract verifies strict query validation, daily/weekly/monthly
defaults and range limits, role filtering, five-key output, ISO/display dates,
Shift 3 `16:00-24:00`, summary and jumpshift compatibility, request IDs,
sensitive-field exclusion, and GET-only route configuration.

Live unauthenticated and method checks:

```bash
curl -i http://127.0.0.1:8080/api/v1/shift-assignment/assignments.php
curl -i -X POST http://127.0.0.1:8080/api/v1/shift-assignment/assignments.php
```

Expect `401` and `405` respectively. With an authenticated disposable fixture,
verify invalid query `422`, denied permission `403`, valid scoped data `200`,
and seeded assignments in daily, weekly, and monthly views. These GET checks
must not alter assignment or audit table counts.

## Phase 8 Shift Assignment React Shell

From `frontend/`:

```bash
npm run test:contracts
npm run build
```

The frontend contract check covers the five-key response, object-shaped
validation errors, `401` session callback, same-origin credentials,
daily/weekly/monthly ranges, range navigation, and `dd-mm-yyyy` display.

The build must contain separate sandbox and `shiftAssignment` entries and must
write only ignored files under `frontend/dist/`. No PHP page currently mounts
the Shift Assignment bundle, so browser auth/permission/render checks remain a
later authenticated pilot-mount task.

## Phase 9 Authenticated React Preview

Run:

```bash
cd frontend
npm run test:contracts
npm run build
npm run build:preview
cd ..
php tests/shift-assignment-react-preview.php
php -l public/shift-assignment-react-preview.php
php -l public/includes/react_manifest.php
```

The preview test verifies the allowlisted manifest entry, imported CSS
collection, safe missing-manifest behavior, authentication and `shifts.view`
source guards, dedicated React root, and absence of write methods.

Unauthenticated browser or curl access must redirect through the normal login
flow. With an authenticated fixture account, verify the preview renders without
console errors, the three GET APIs remain scoped, filters load data, and the
legacy page and sidebar remain unchanged.

## Phase 10 Role-Based Preview Parity

Canonical manual evidence and role expectations:

- `docs/shift-assignment-preview-parity.md`
- `docs/shift-assignment-role-test-matrix.md`

Run the non-mutating parity gate:

```bash
php tests/shift-assignment-preview-parity.php
```

The gate protects shared authentication and `shifts.view` requirements,
production navigation isolation, GET-only React API use, v1 route permissions,
default role grants, and server-side self/division scope characterization.

Authenticated role evidence still requires disposable fixture accounts and a
browser environment. Record each role result in the matrix without passwords,
2FA secrets, session IDs, or production user data.

## Phase 11 Limited Internal Pilot

Run:

```bash
php tests/shift-assignment-internal-pilot.php
php -l public/shift-assignment-react-preview.php
```

The pilot test verifies authentication, `shifts.view`, exact Super Admin
server-side access, safe-denial guard characterization, the read-only banner,
navigation isolation, approved GET resources, and absence of frontend writes.

With disposable accounts, confirm a Super Admin with `shifts.view` can render
the page while Admin, Supervisor, Agent, Intern, and users without `shifts.view`
receive safe denial. No credentials or sessions belong in test artifacts.

## Phase 12 Read-Only Production Candidate

From `frontend/`:

```bash
npm run test:contracts
npm run build
npm run build:preview
npm run test:preview-bundle
```

From the repository root:

```bash
php tests/shift-assignment-readonly-candidate.php
php tests/shift-assignment-internal-pilot.php
php tests/shift-assignment-preview-parity.php
```

The candidate checks cover display/ISO date conversion, staged filters, `401`
and `403` client handling, request cancellation, responsive read surfaces,
approved GET-only resources, access restrictions, navigation isolation, and
bundle entry/size budgets.

## Phase 13 Write API Contract Planning

Run the non-mutating planning guard:

```bash
php tests/shift-assignment-write-contract-plan.php
```

It verifies the documented endpoint, permission, CSRF, migration, and delete
decision gates. After Phase 14 it confirms the v1 assignments route exposes
only GET and the controlled create POST, while the React API client remains
read-only and no additional planned write route exists.

No write integration test is permitted against production data. A future
implementation needs a disposable MySQL database and fixture users before
testing CSRF, permission/scope, transactions, audit persistence, idempotency,
conflicts, or rollback.

## Phase 14 Controlled Create Assignment API

Run:

```bash
php tests/shift-assignment-create-api-contract.php
php tests/shift-assignment-assignments-api-contract.php
php tests/shift-assignment-api-contract.php
php tests/php-api-foundation.php
php tests/php-api-contract.php
```

The create contract uses a callback mock, not MySQL. It verifies required and
unknown fields, ISO dates, allowlisted type/status, Shift 3 `24:00` conversion,
manual source enforcement, response allowlisting, warning passthrough, GET/POST
configuration, exact temporary authorization, audit helper use, and the
absence of React or additional write paths.

Live unauthenticated checks:

```bash
curl -i -X POST \
  -H 'Content-Type: application/json' \
  --data '{}' \
  http://127.0.0.1:8080/api/v1/shift-assignment/assignments.php

curl -i -X PATCH \
  http://127.0.0.1:8080/api/v1/shift-assignment/assignments.php
```

Expect `401` for unauthenticated POST and `405` with `Allow: GET, POST` for
PATCH. Authenticated CSRF, role, success, overlap, and audit checks require a
disposable database.

### Manual staging-only success test

1. Restore a disposable MySQL snapshot and use a fixture Super Admin that has
   `shifts.manage`.
2. Record row counts for `shift_assignments`, `assignment_audit_logs`, and the
   available TRACS activity audit table.
3. Sign in, obtain the context CSRF token, and send one JSON POST with
   `X-CSRF-Token`.
4. Confirm `201`, the five-key envelope, `source=manual`, and the expected
   `dd-mm-yyyy` display date.
5. GET the same date/agent and confirm the record is visible.
6. Repeat the same overlapping slot and confirm `409` with no second assignment.
7. Test missing/invalid CSRF (`403`), non-Super Admin (`403`), missing
   `shifts.manage` (`403`), and invalid fields (`422`).
8. Confirm assignment and activity audit rows contain request/result metadata
   without notes, tokens, credentials, or raw errors.
9. Restore the disposable snapshot. Do not run this procedure on production.

## Phase 15 Disposable Create Integration

Prerequisites:

- local Docker Compose `tracs_db` is running;
- source `tracs_db` is used for schema only;
- host MySQL is reachable on `127.0.0.1:3307`;
- Docker CLI can run `mysqldump` and `mysql` inside `tracs_db`.

Run:

```bash
TRACS_ENV=test \
TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase15_test \
php tests/shift-assignment-create-api-integration.php
```

Optional local overrides:

```text
TRACS_TEST_DB_HOST
TRACS_TEST_DB_PORT
TRACS_TEST_DB_USER
TRACS_TEST_DB_PASS
TRACS_TEST_DB_CONTAINER
TRACS_TEST_SCHEMA_SOURCE
```

The runner skips without the two explicit safety variables, refuses a target
name without `test`, `local`, `dev`, or `disposable`, refuses matching
source/target names and production labels, and drops the target in `finally`.

The child request harness lives outside the web root. It creates session/CSRF
state and redirects `php://input` only inside its short-lived CLI process. It
does not add a production authentication bypass.

Validated on June 15, 2026:

- authenticated create succeeded;
- the row appeared through GET;
- Shift 3 persisted cross-day and displayed `16:00-24:00`;
- overlap created no second row;
- unauthenticated, CSRF, role, and explicit-permission denials passed;
- invalid date returned a field error;
- assignment, activity, and security audit rows were present;
- `tracs_phase15_test` was dropped.

At the end of Phase 15, React create UI remained disabled. Disposable CLI
evidence was the prerequisite for the separately approved Phase 16 activation.

## Phase 16 Controlled React Create Pilot

The unlinked authenticated preview at
`/shift-assignment-react-preview.php` now exposes `Add Assignment` only when
the Shift Assignment context returns `allowed_actions.create_assignment=true`.
That server-derived capability requires the exact `super_admin` role, explicit
`shifts.manage`, and the page still requires `shifts.view`.

The modal uses only fields accepted by the Phase 14 API. It displays dates as
`dd-mm-yyyy`, submits ISO dates, supports the three established shift presets,
sends the context CSRF token in the context-provided header, disables duplicate
submits, preserves backend field errors, warns before discarding dirty input,
and refreshes the current GET view after success.

Run the frontend and source contracts:

```bash
cd frontend && npm run test:contracts
cd frontend && npm run build
php tests/shift-assignment-create-ui-pilot.php
```

Browser mutation validation must use a disposable or staging database only:

1. Point the local application at a safely marked disposable database.
2. Sign in as an exact Super Admin role with explicit `shifts.view` and
   `shifts.manage`.
3. Open the preview by direct URL and confirm no navigation link exists.
4. Create one unique test assignment, including a Shift 3 `16:00-24:00` case.
5. Confirm the success toast, current-view refresh, GET visibility, and audit
   rows.
6. Confirm overlap, invalid input, expired session, denied permission, and
   invalid CSRF remain safely handled.
7. Drop or otherwise clean the disposable database.

Do not run this browser workflow against production. Update/delete,
template/copy, broad role access, navigation exposure, and legacy replacement
remain blocked.

## Phase 17 Disposable Browser Evidence

Phase 17 adds a guarded browser environment helper:

```bash
TRACS_ENV=test \
TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase17_test \
php tests/shift-assignment-create-ui-browser-environment.php setup
```

The helper refuses missing test/mutation flags and unsafe database names. It
clones schema only, seeds dedicated Phase 17 users and shift fixtures, and
supports explicit `verify` and `cleanup` actions. The test application runs in
a separate temporary container pointed only at the disposable database.

Authenticated browser validation completed on June 15, 2026:

- environment: local Docker disposable test;
- database: `tracs_phase17_test`;
- test URL: `http://127.0.0.1:8082/shift-assignment-react-preview.php`;
- authorized identity: `phase17-super`, exact `super_admin`, `shifts.view`,
  and explicit `shifts.manage`;
- target agent: fixture user ID `9733`;
- target date: July 13, 2026;
- assignment: `regular_shift`, Shift 3, `16:00-24:00`;
- real login and required 2FA setup completed;
- unauthenticated and expired sessions redirected to `/login.php`;
- non-Super Admin access returned the safe concealed not-found response;
- exact Super Admin without explicit `shifts.manage` saw no Add Assignment
  control;
- required-field errors remained in the modal and focused the agent field;
- Shift 3 preset populated `16:00` and `24:00`;
- successful create closed the modal and showed the safe success toast;
- the initial out-of-range state explained that filters could hide the row;
- daily July 13 filters showed one row, eight hours, and `16:00-24:00`;
- a duplicate browser create returned the safe overlap conflict message and
  retained the modal;
- no console error or warning was observed during the React flow;
- verification found one assignment audit and one Phase 5 activity audit.

The browser pass also found that the preview shell omitted the canonical
`page_helpers.php` dependency required by the shared footer. The preview now
loads that existing helper so footer rendering completes and the isolated
React module script is emitted.

Missing/invalid CSRF, `401`, `403`, and `422` response contracts remain covered
by the Phase 14-16 API, integration, and frontend contract tests. Phase 17 did
not deliberately tamper with an authenticated browser token.

Cleanup completed:

```bash
docker rm -f tracs_phase17_app
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase17_test \
php tests/shift-assignment-create-ui-browser-environment.php cleanup
```

Final database existence count was zero. No fixture or assignment remains.

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
