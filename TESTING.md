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

## Phase 31 Disposable DB Validation Gate

Phase 31 restores the disposable database validation path before any template
commit work. Run the preflight before mutation drills:

```bash
TRACS_ENV=test \
TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase31_test \
php tests/disposable-db-preflight.php
```

The preflight verifies Docker daemon/socket access, the `tracs_db` container,
MySQL on `127.0.0.1:3307`, source schema availability, safe target DB naming,
mutation opt-in, and stale disposable cleanup.

Docker path:

```bash
docker compose up -d db
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase31_test \
php tests/disposable-db-preflight.php
```

Local MySQL fallback path when Docker is unavailable:

1. Start a local MySQL instance that is not production.
2. Import the TRACS schema into a non-production source database.
3. Export explicit test variables:

```bash
export TRACS_ENV=test
export TRACS_ALLOW_MUTATION_TESTS=1
export TRACS_TEST_DB_HOST=127.0.0.1
export TRACS_TEST_DB_PORT=3307
export TRACS_TEST_DB_USER=root
export TRACS_TEST_DB_PASS=root_secret
export TRACS_TEST_SCHEMA_SOURCE=tracs_db
export TRACS_TEST_DB_NAME=tracs_phase31_test
```

4. Run the same preflight and drills. Never point these variables at
   production, and never use a target database name without `test`, `local`,
   `dev`, `disposable`, or `staging`.

Required recovery validation:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-delete-restore-drill.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-dependent-restore-drill.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-template-preview-integration.php
```

Template Commit API remains blocked until this gate is green in the current
environment.

## Phase 32 Template Commit Integration

Phase 32 adds the backend-only Template Commit API:

```text
POST /api/v1/shift-assignment/templates/commit.php
```

Run only against disposable DB:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase32_test \
php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase32_test \
php tests/shift-assignment-template-commit-integration.php
```

The integration validates unauthenticated, CSRF, non-Super-Admin, confirmation
variants, invalid payload, conflict `409`, Shift 1/2/3 creation including
`16:00-24:00`, GET visibility, audit created IDs, preview non-mutation, and
rollback cleanup by created assignment IDs.

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

## Phase 18 Controlled Update API

The controlled update route is:

```text
PATCH /api/v1/shift-assignment/assignment.php?id=<assignment_id>
```

It requires a fully authenticated session, valid mutation CSRF, the exact
`super_admin` role, and explicit `shifts.manage`. The endpoint accepts a
non-empty partial JSON body, merges allowed fields with the scoped current
record, and delegates overlap, duration, holiday, overtime, warning,
notification, and persistence behavior to `ShiftingAssignmentService`.

Run the non-mutating contract:

```bash
php tests/shift-assignment-update-api-contract.php
```

Run the mutation integration only against a safely marked disposable database:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-update-api-integration.php
```

On June 15, 2026, this created `tracs_phase18_test`, cloned schema only, and
verified `401`, `403`, `404`, `409`, and `422` paths plus authenticated update
success. A Shift 3 update persisted as a cross-day record, appeared through
GET, and produced assignment and API activity before/after audits. The
conflict test left the prior row unchanged. The disposable database was
removed and its absence confirmed.

The React preview remains create-only. Edit controls must not be enabled until
a later approved phase adds frontend contracts and disposable browser
validation.

## Phase 19 Controlled React Edit Pilot

The direct-URL preview now shows Edit actions only when the Shift Assignment
context returns `allowed_actions.update_assignment=true`. That capability
requires the exact `super_admin` role plus explicit `shifts.manage`; the page
still requires `shifts.view`. React sends only changed allowlisted fields to:

```text
PATCH /api/v1/shift-assignment/assignment.php?id=<id>
```

The modal is prefilled from the safe assignments response, displays dates as
`dd-mm-yyyy`, sends the context CSRF token in memory, prevents duplicate
submits, blocks unchanged submissions, preserves Shift 3 `24:00`, keeps dirty
forms open on failure, and refreshes the current filters after success.

Authenticated browser validation completed on June 15, 2026:

- environment: local disposable Docker application;
- database: `tracs_phase19_test`;
- identity: `phase17-super`, exact `super_admin`, `shifts.view`, and explicit
  `shifts.manage`;
- created two disposable assignments for agent `9733` on July 13, 2026;
- Create remained functional;
- Edit changed the Shift 3 assignment from `assigned` to `confirmed`;
- refreshed UI retained `16:00-24:00`;
- unchanged submit was blocked without an API request;
- overlap update returned a safe conflict and kept the modal open;
- assignment and API activity before/after audit rows were verified;
- `tracs_phase19_app` and `tracs_phase19_test` were removed; final database
  existence count was zero.

Browser validation found and fixed two pilot defects: closed-modal
initialization now accepts a null assignment, and inherited custom-template
ID `0` is normalized to no template rather than rejected. The shared toast now
uses action-aware Create/Edit titles.

Delete, template, copy, overtime, navigation exposure, legacy replacement, and
production mutation remain blocked.

## Phase 20 Create/Edit Hardening Gate

Phase 20 keeps the direct-URL pilot and existing POST/PATCH contracts only. The
Create and Edit modals now share safe `401`, `403`, `404`, `405`, `409`, `422`,
and network-error messaging; focus and scroll to the first invalid control;
mark required fields accessibly; disable the complete fieldset while saving;
and report when a successful mutation cannot be refreshed immediately.

Automated regression covers:

- Create/Edit validation, presets, `24:00`, dirty-form, saving, CSRF, and
  capability gates;
- one approved POST and one approved PATCH, with no delete/template/copy route;
- overlap and validation failure contracts;
- post-save refresh success/failure behavior;
- exact `super_admin`, `shifts.view`, and `shifts.manage` policy;
- preview/navigation/legacy/Calendar isolation.

Disposable mutation validation on June 15, 2026 reran both the create and
update integration suites against `tracs_phase20_test`. They verified
authenticated success, read-API
visibility, Shift 3 cross-day persistence, overlap rejection, and create/update
audits; their temporary databases were dropped by the test runners.

The schema-only clone was recreated with `tracs_phase20_app` for browser
regression. The in-app
browser blocked the localhost login redirect before rendering the page, so no
new Phase 20 authenticated browser mutation is claimed. Phase 19 remains the
latest genuine authenticated Create/Edit browser evidence. The Phase 20 clone
and container were removed after the blocked attempt.

Run the Phase 20 source gate:

```bash
php tests/shift-assignment-create-edit-hardening.php
```

Delete, template generation, copy/paste, production navigation, and legacy
replacement require separate explicit approval.

## Phase 21 Controlled Delete API

The backend-only delete contract is:

```text
DELETE /api/v1/shift-assignment/assignment.php?id=<id>
```

It requires an authenticated session, valid CSRF, exact `super_admin`, and the
explicit `shifts.manage` role permission. The React preview contains no DELETE
caller or control.

The current `shift_assignments` schema has no `deleted_at` or `is_deleted`
column. Phase 21 therefore uses a transaction-protected hard delete, matching
the only schema-compatible behavior without a migration. Before deletion the
service writes the complete assignment snapshot to `assignment_audit_logs`;
the existing foreign key sets that audit's `assignment_id` to `NULL` while
preserving the JSON snapshot. The API activity audit retains the original
target ID and safe before data. Delete fails closed if the required assignment
audit cannot be written. Linked `shift_warnings` rows are removed.

Template-generated or monthly-template-owned assignments return `409` and are
not deleted. Template/copy cleanup must be designed with those workflows.

Disposable validation on June 15, 2026 used `tracs_phase21_test` and verified:

- `401`, `403`, `404`, `409`, and `422` delete paths;
- exact-role, explicit-permission, and CSRF enforcement;
- GET, POST, and PATCH regression behavior;
- successful deletion and removal from GET;
- unrelated assignments remain intact;
- linked warning cleanup;
- preserved before-delete assignment and activity audits;
- automatic disposable database removal.

Run:

```bash
php tests/shift-assignment-delete-api-contract.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-delete-api-integration.php
```

React Delete UI remains blocked pending a separate confirmation/UX phase and
fresh authenticated browser validation.

## Delete UI Safety Gate

Phase 22 is planning and non-mutating validation only. Active React Delete UI
remains prohibited.

Required future browser matrix:

- Delete is absent for unauthenticated, non-Super-Admin, and missing-permission
  users.
- The dialog shows agent, date, shift type, time range, status, and available
  role/division context.
- The hard-delete warning is visible and readable in light/dark mode.
- The destructive button remains disabled until the operator types `DELETE`.
- Double submit is impossible and the dialog is disabled while deleting.
- `401`, `403`, `404`, `405`, `409`, `422`, network, and unexpected failures
  keep the assignment visible and the dialog open.
- Template-owned `409` explains that deletion must occur through the future
  template/copy workflow.
- Success refreshes the current view and removes only the confirmed assignment.
- Before-delete audit and manual restoration evidence are verified in the
  disposable database.
- The disposable database/container is removed after testing.

Non-mutating Phase 22 check:

```bash
php tests/shift-assignment-delete-safety-gate.php
```

Approval for active Delete UI requires this checklist, a reviewed restoration
exercise, and fresh authenticated disposable-browser evidence.

## Phase 23 Disposable Restoration Drill

Run only with explicit disposable mutation consent:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-delete-restore-drill.php
```

On June 15, 2026, the drill used `tracs_phase23_test` to create, update, delete,
and exactly restore a manual Shift 3 assignment. It loaded the full
`assignment_audit_logs.before_snapshot`, verified every current
`shift_assignments` column, restored the original primary key in a transaction,
wrote one `shift_assignment.restore` activity audit referencing the delete
audit, verified the row through scoped GET, compared key fields, and confirmed
there was no duplicate.

The before snapshot is sufficient for exact restoration of the assignment row.
It is not sufficient for complete operational-state restoration: warning rows
removed by delete and foreign-key-cascaded dependent records are not stored in
that snapshot. React Delete UI remains blocked until dependent-record
retention/restoration is explicitly designed and tested. The disposable
database was removed after the drill.

## Phase 24 Dependent Restoration Drill

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-dependent-restore-drill.php
```

The guarded test creates `tracs_phase24_test`, exercises protected
create/update/delete, loads `_dependents`, exactly restores the assignment,
warning resolution state, and holiday coverage row, verifies retained
notifications/audits and GET visibility, checks duplicate counts, and drops
the database in `finally`.

Phase 24 passed on June 15, 2026. This clears the backend dependent-record gate
for a separately approved Delete UI pilot. It does not activate Delete UI or
authorize production mutation.

## Phase 25 Controlled Delete UI Pilot

```bash
cd frontend && npm run test:contracts
cd frontend && npm run build
php tests/shift-assignment-delete-ui-pilot.php
php tests/shift-assignment-delete-safety-gate.php
```

Authenticated browser validation completed on June 16, 2026 using
`tracs_phase25_test` and temporary `tracs_phase25_app`:

- exact Super Admin with `shifts.view` and `shifts.manage`;
- Create produced Shift 3 `16:00-24:00`; Edit changed it to `confirmed`;
- Delete showed full details and the hard-delete warning;
- blank/lowercase confirmation stayed disabled and cancel retained the row;
- exact `DELETE` removed the assignment and refreshed to zero records;
- removing `shifts.manage` hid Add/Edit/Delete after reload;
- a live template-item link returned safe `409` and remained visible;
- create/update/delete assignment and activity audits survived;
- warning and holiday-coverage dependent snapshot keys existed;
- browser console contained no errors or warnings.

Cleanup removed the temporary app and database. Final database existence count
was zero. Production data was not used.

## Phase 26 Delete Pilot Hardening

```bash
cd frontend && npm run test:contracts
cd frontend && npm run build
php tests/shift-assignment-delete-hardening.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-delete-hardening-integration.php
```

Authenticated browser regression completed on June 16, 2026 using
`tracs_phase26_test` and temporary `tracs_phase26_app`:

- Create Shift 1 `00:00-08:00` succeeded.
- Edit to Shift 2 `08:00-16:00`, then Shift 3 `16:00-24:00`, succeeded.
- An overlapping edit returned safe `409` without mutation.
- Leading-space and lowercase confirmations stayed disabled.
- Exact `DELETE` deleted and refreshed the list to zero records.
- The delete snapshot retained warning and holiday-coverage dependency keys.
- A live template-item link returned safe `409` and retained the assignment.
- Create and Edit still succeeded after the delete flow.
- Removing `shifts.manage` hid Add, Edit, and Delete.
- Browser console contained no warnings or errors.

The Phase 26 API matrix also performed exact assignment/dependent restoration,
verified restored GET visibility and duplicate prevention, and removed its
disposable database. The browser database and app were also removed.
Production data was not used.

## Phase 27 Template API Contract Planning

Phase 27 is non-mutating documentation and source-contract validation only.
It defines the future template generation and copy/paste contracts without
creating routes, React controls, schema changes, or data writes.

Required contract command:

```bash
php tests/shift-assignment-template-contract-plan.php
```

The contract check verifies:

- future routes are documented as
  `templates/preview.php`, `templates/commit.php`,
  `templates/copy-preview.php`, and `templates/copy-commit.php`;
- preview and copy-preview are explicitly non-mutating;
- commit routes require CSRF, confirmation, audit evidence, conflict re-checks,
  and rollback data;
- granular template permissions are documented but not seeded;
- legacy monthly preview side effects are documented;
- after Phase 28, only the approved non-mutating `templates/preview.php`
  route exists;
- the React preview does not expose template or copy controls.

Phase 27 uses the normal non-mutating validation suite:

```bash
cd frontend && npm run test:contracts
cd frontend && npm run build
php tests/php-api-foundation.php
php tests/php-api-contract.php
php tests/shift-assignment-api-contract.php
php tests/shift-assignment-assignments-api-contract.php
php tests/shift-assignment-create-api-contract.php
php tests/shift-assignment-update-api-contract.php
php tests/shift-assignment-delete-api-contract.php
php tests/shift-assignment-delete-restore-drill.php
php tests/shift-assignment-dependent-restore-drill.php
php tests/shift-assignment-template-contract-plan.php
find api tests public/api/v1/shift-assignment -name '*.php' -exec php -l {} \;
php -l public/shift-assignment-react-preview.php
```

No browser mutation or production data access is required for Phase 27 because
no executable template workflow is added.

## Phase 28 Template Preview API

Phase 28 adds only:

```text
POST /api/v1/shift-assignment/templates/preview.php
```

The endpoint is non-mutating and remains backend/API-only. It does not add
commit, copy-preview, copy-commit, React template UI, navigation, schema, or
legacy-page changes.

Required checks:

```bash
php tests/shift-assignment-template-preview-api-contract.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-template-preview-integration.php
```

The disposable integration uses `tracs_phase28_test`, validates auth, CSRF,
exact Super Admin, invalid payloads, Shift 1/2/3 preview generation, overlap
conflict output, warning output, and proves these persisted table counts remain
unchanged after a valid preview:

- `shift_assignments`
- `shift_warnings`
- `holiday_coverage_assignments`
- `shift_monthly_templates`
- `shift_monthly_template_items`
- `assignment_audit_logs`

The disposable database is removed after the test. Commit/copy work and React
template UI require separate approval.

## Phase 29 Template Preview UI Pilot

Phase 29 adds the controlled React Template Preview UI inside the isolated
Shift Assignment preview only. It calls the existing non-mutating endpoint:

```text
POST /api/v1/shift-assignment/templates/preview.php
```

Access remains capability gated by the backend context: exact Super Admin plus
`shifts.view`, `shifts.manage`, and a valid CSRF token. The UI must remain
preview-only:

- no commit button;
- no apply/generate-save button;
- no copy-to-month action;
- no assignment list refresh from template preview alone;
- no template/copy endpoint beyond the Phase 28 preview route.

Phase 29 validation commands:

```bash
cd frontend && npm run test:contracts
cd frontend && npm run build
php tests/shift-assignment-template-preview-ui-pilot.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-template-preview-ui-integration.php
```

The disposable preview validation uses `tracs_phase29_test` and must prove the
same persisted counts remain unchanged after preview: assignments, warnings,
holiday coverage, monthly templates, monthly template items, and assignment
audits. Browser click validation should be repeated on a disposable/staging
app before template commit work begins.

## Phase 30 Template Commit Safety Gate

Phase 30 is non-mutating documentation and guard coverage only. It hardens the
future commit contract for:

- preview-to-commit integrity;
- exact `APPLY TEMPLATE` confirmation;
- `X-CSRF-Token`, exact Super Admin, and `shifts.manage`;
- final conflict re-check;
- audit coverage for created assignment ids;
- bulk rollback or future `template_batch_id` migration planning.

Phase 30 validation:

```bash
php tests/shift-assignment-template-commit-contract-gate.php
```

The test must prove the commit/copy route files do not exist and that the React
module still has no commit/apply/save/copy caller. Commit implementation
requires a later disposable DB mutation test plan.

## Phase 33 Template Commit Hardening

Run the full non-production disposable gate before any React Apply Template UI:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase33_test php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase33_test php tests/shift-assignment-template-commit-integration.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 php tests/shift-assignment-template-commit-rollback-drill.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 php tests/shift-assignment-template-commit-race-conflict-drill.php
```

The hardened drill validates exact `APPLY TEMPLATE` confirmation, unsupported
conflict-policy rejection, server-side race conflict re-check, preview
non-mutation, created-id rollback targeting, unrelated assignment retention,
and commit/rollback audit evidence. The drill refuses production-like database
names and drops the disposable database after the run.

## Phase 34 Template Commit UI Gate

Run the non-mutating UI guard:

```bash
php tests/shift-assignment-template-commit-ui-gate.php
```

The guard proves the React module still has no active Apply Template UI, no
commit/apply/generate-save button, no API caller for `templates/commit.php`,
and no copy/paste UI. It also checks that the future multi-step flow, exact
`APPLY TEMPLATE` UX, disabled conditions, rollback evidence display, and
future `commitTemplatePreview(payload)` behavior are documented.
No active Apply Template UI is added in Phase 34.
no commit/apply/generate-save button is added in Phase 34.
no API caller for `templates/commit.php` is added in Phase 34.

## Phase 35 Apply Template UI Pilot

The Apply Template UI pilot is controlled inside the React preview only.
It requires exact APPLY TEMPLATE before commit.

Run the frontend and UI contract checks:

```bash
cd frontend && npm run test:contracts
php tests/shift-assignment-template-apply-ui-contract.php
```

The pilot contract verifies exact `APPLY TEMPLATE` client-side gating, CSRF on
the commit request, disabled apply when preview has conflicts or blocked items,
safe `409` stale-preview handling, rollback evidence display, no copy/paste UI,
and that Create/Edit/Delete plus Template Preview contracts remain intact.

Disposable backend regression remains:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase35_test php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase35_test php tests/shift-assignment-template-commit-integration.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase35_test php tests/shift-assignment-template-apply-ui-browser-validation.php
```

Authenticated browser validation is required before production replacement or
copy/paste work. If a browser fixture is unavailable, do not claim browser
mutation evidence; rely on frontend contracts plus disposable API drills only.

## Phase 36 Apply Template Hardening Gate

Run:

```bash
cd frontend && npm run test:contracts
cd frontend && npm run build
php tests/shift-assignment-template-apply-ui-contract.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase36_test php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase36_test php tests/shift-assignment-template-apply-ui-browser-validation.php
```

Phase 36 hardens accessibility and disabled-state presentation, but live
authenticated browser click-through remains required before copy/paste work.
In the local Codex environment, in-app browser control failed before navigation
with a missing browser tool metadata field, and standalone Playwright was not
installed. Record this as blocked rather than successful browser evidence.
Phase 36 is blocked by browser tooling for live authenticated click-through.

## Phase 37 Authenticated Browser Validation Gate

Phase 37 restores the live authenticated browser path without weakening
production auth. The in-app browser remains blocked in this environment by a
missing `sandboxPolicy` metadata field, so the repeatable path is a dev-only
Playwright/Chrome run against a guarded disposable database and a test-only
session endpoint.

Run:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase37_test php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase37_test npm run test:e2e:shift-template-apply --prefix frontend
php tests/shift-assignment-auth-browser-validation.php
```

The session harness is `public/__test/shift-assignment-auth-session.php`. It
returns not-found outside `TRACS_ENV=test`, requires
`TRACS_ALLOW_MUTATION_TESTS=1`, and refuses database names that do not look
disposable. The browser validation signs in as the seeded exact Super Admin
pilot user, opens `shift-assignment-react-preview.php`, confirms the pilot
banner, generates a Shift 1 template preview, rejects invalid `APPLY TEMPLATE`
confirmation variants, applies the template, verifies created count, request
id, rollback ids/reference, assignment refresh, commit audit evidence, and
rollback targeting. It also proves the unrelated baseline assignment remains,
the conflict preview keeps Apply disabled, no copy endpoints are called, no
copy/paste or rollback UI appears, and the browser console/network capture is
clean.

Phase 37 found one live-browser UI issue: the legacy unsaved-change overlay
could intercept the Apply Template flow. The template preview form now opts out
with `data-unsaved-ignore` because the React modal owns its own dirty-form
guard. With the Phase 37 browser command passing against `tracs_phase37_test`
and cleanup confirmed, Phase 38 copy-preview may proceed from the authenticated
browser-validation gate, subject to its own explicit approval and safeguards.

## Phase 38 Copy Schedule Preview Contract Gate

Phase 38 is documentation and non-mutating guard coverage only. It documents
the future `POST /api/v1/shift-assignment/templates/copy-preview.php` contract
without creating the route, copy-commit route, copy/paste UI, rollback UI, or
schema changes.

Run:

```bash
php tests/shift-assignment-copy-preview-contract-gate.php
```

The guard verifies canonical docs include `source_start_date`,
`source_end_date`, `target_start_date`, `target_end_date`, source-to-target
transformation rules, `source_range`, `target_range`, non-mutating preview
guarantees, conflict/warning/blocked item behavior, exact pilot permission
policy, future `shifts.template.copy_preview`, and future `APPLY COPY`
copy-commit relationship. It also verifies no `copy-preview.php` or
`copy-commit.php` route exists and React does not contain Copy Schedule UI,
`copy-preview.php`, `copy-commit.php`, or `APPLY COPY` callers.

Future copy-preview must be POST plus CSRF even though it is non-mutating. It
must return preview-only output with the message
`Preview only - this will not create or modify assignments.` Future copy-commit
must be a separate phase with its own endpoint, exact confirmation phrase,
server-side source/target revalidation, conflict re-check, audit created IDs,
rollback targeting, disposable DB proof, and authenticated browser evidence.

## Phase 39 Copy Schedule Preview API

Phase 39 implements the non-mutating copy-preview route:

```bash
php tests/shift-assignment-copy-preview-api-contract.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase39_test php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase39_test php tests/shift-assignment-copy-preview-integration.php
```

The integration seeds source Shift 1, Shift 2, and Shift 3 `16:00-24:00`
assignments, previews them into a target range, verifies source assignment IDs
are not reused as preview IDs, verifies target overlap conflicts and blocked
items, and compares persisted counts before and after preview calls.

No-mutation evidence covers `shift_assignments`, `shift_warnings`,
`holiday_coverage_assignments`, `shift_monthly_templates`,
`shift_monthly_template_items`, `assignment_audit_logs`, and
`tracs_user_activity_logs`. Copy-commit, React copy/paste UI, rollback UI,
schema changes, Calendar changes, legacy-page changes, and navigation changes
remain absent.

## Phase 40 Copy Schedule Preview UI

Phase 40 adds the preview-only React Copy Schedule Preview pilot. It uses the
existing non-mutating API and adds no copy-commit endpoint or copy mutation UI.

Required checks:

```bash
cd frontend && npm run test:contracts
cd frontend && npm run build
npm run test:e2e:shift-template-apply
npm run test:e2e:shift-copy-preview
php tests/shift-assignment-copy-preview-ui-contract.php
php tests/shift-assignment-copy-preview-api-contract.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase40_test php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase40_test php tests/shift-assignment-copy-preview-integration.php
```

The UI contract verifies `Copy Schedule Preview` is gated by
`allowed_actions.copy_preview`, calls
`/api/v1/shift-assignment/templates/copy-preview.php` with CSRF, validates
source/target date rules, renders source range, target range, preview items,
summary, warnings, conflicts, and blocked items, and keeps the copy workflow
preview-only.

Forbidden in Phase 40:

- `templates/copy-commit.php`;
- Apply Copy, Commit Copy, Paste Schedule, Save Copied Schedule, or Generate
  Copied Schedule controls;
- rollback UI;
- schema, Calendar, legacy Shift Assignment, or production navigation changes.

Use `tracs_phase40_test` for disposable checks and confirm cleanup with:

```sql
SELECT COUNT(*) AS tracs_phase40_test_count
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'tracs_phase40_test';
```

## Phase 41 Copy Preview UI Hardening

Phase 41 keeps copy preview non-mutating and strengthens the UI/browser gate.
Run the Phase 40 commands plus:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase41_test php tests/disposable-db-preflight.php
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase41_test php tests/shift-assignment-copy-preview-integration.php
cd frontend && TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase41_test npm run test:e2e:shift-copy-preview
```

The browser regression now verifies missing source/target date validation,
same-range rejection, range-length mismatch, ranges above 35 days, stale-result
messaging after editing a successful preview, valid Shift 1/2/3 copy preview,
target conflict/blocked rendering, no assignment/warning/audit count mutation,
no `copy-commit.php` call, and no Apply Copy/Commit Copy/Paste Schedule or
rollback UI.

Phase 42 copy-commit contract work may proceed only after this hardening gate
passes and must remain contract-only unless separately approved.

## Phase 42 Copy Commit Contract Gate

Phase 42 is documentation and non-mutating guard coverage only. It defines the
future `POST /api/v1/shift-assignment/templates/copy-commit.php` contract and
adds no endpoint or Apply Copy UI.

Run:

```bash
php tests/shift-assignment-copy-commit-contract-gate.php
```

The guard verifies:

- `api/v1/shift-assignment/templates/copy-commit.php` does not exist;
- `public/api/v1/shift-assignment/templates/copy-commit.php` does not exist;
- React contains no Apply Copy, Commit Copy, Paste Schedule, or rollback UI;
- React API client contains no copy-commit caller;
- no copy mutation service/repository exists.

The documented future contract requires exact `APPLY COPY`, server-side preview recomputation, final conflict re-check, atomic all-or-nothing batch behavior, audit-created assignment IDs, and rollback targeting that removes only returned `created_assignment_ids`.

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
