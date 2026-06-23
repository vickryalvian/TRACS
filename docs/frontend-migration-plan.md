# TRACS Frontend Migration Plan

## Migration Unit

The unit of migration is one reviewable behavior slice within one module, not an
entire system phase. Existing PHP rendering remains the fallback until all
required slices reach parity.

Recommended module sequence:

1. Shift Assignment.
2. Checklist and Reminder.
3. Task Assignment and Monitoring.
4. Dashboard widgets, one widget at a time.
5. Cases board and ticket workflow.
6. Shift Reports.
7. MoM / Meeting.
8. Cancellation Feedback.
9. Domain Transfer Log and Finance.
10. Domain Price Crosscheck.
11. Infrastructure Pulse.
12. OpsTrack, Network Pulse, and TV Mode.
13. User Management, roles, and permissions.
14. Activity Log, reports, and CSV exports.
15. Settings and Profile.
16. Authentication, login, and 2FA UI.
17. Super Admin monitoring and deployment tools.

Authentication and permission contracts are tested early, but their UI migrates
late because errors affect every protected route.

## Per-Module Stages

1. **Inventory:** routes, assets, APIs, permissions, tables, uploads, exports,
   audit effects, notifications, and linked modules.
2. **Characterize:** automate or document current behavior and role scope.
3. **API readiness:** expose stable read contracts without changing business rules.
4. **Read-only React island:** render existing data while PHP remains available.
5. **Filters and navigation:** reproduce query, date, sorting, and responsive behavior.
6. **Mutations:** migrate one create/update/action flow at a time.
7. **Advanced interaction:** drag/drop, realtime/polling, uploads, or complex tables.
8. **Parity review:** Calendar visual reference, accessibility, permissions, and data.
9. **Controlled cutover:** server-side feature flag selects React or PHP rendering.
10. **Cleanup:** remove legacy frontend code only after stable production evidence.

## Shift Assignment Batches

Shift Assignment is the first major migration after Calendar, but must be split:

1. Contract tests and read-only timeline.
2. Shared toolbar, range navigation, filters, and search.
3. Daily, Weekly, and Monthly views using the same assignment source.
4. Assignment create/edit/confirm/status actions.
5. Drag/resize, overlap, rest, jumpshift, and overtime feedback.
6. Workload recap, warnings, Schedule Insights, and audit.
7. Copy Last Week and replacement.
8. Monthly templates and configuration.
9. Permission matrix, seeded-data parity, fallback, and cutover.

The canonical shifts remain:

- Shift 1: `00:00-08:00`.
- Shift 2: `08:00-16:00`.
- Shift 3: `16:00-24:00`, stored as a cross-day midnight end.

Seeded assignments must remain visible in Daily and Weekly views throughout the
migration.

## Dashboard Strategy

Do not replace `public/index.php` in one batch. Migrate independent widgets
behind separate roots while the PHP page continues coordinating layout:

- Stat strip.
- Cases.
- Checklist and Reminder.
- Assignments and Activity.
- Shift Handover.
- Currency Converter.
- Infrastructure Pulse summary.
- Attention Center and notifications.

Shared state between widgets should stay server-backed until multiple React
widgets demonstrate a need for a common client cache.

## Feature Flags And Rollback

Flags are evaluated by PHP before rendering and must default to the current PHP
implementation. Suggested future shape:

```text
TRACS_REACT_SHIFT_ASSIGNMENT=0
TRACS_REACT_CHECKLIST_REMINDER=0
```

Do not expose an unrestricted browser switch. A rollback should disable the
module flag and restore the PHP rendering without changing the database.

Every module batch documents:

- Branch and commit.
- Changed files and generated assets.
- Enabled roles/environments.
- API and database dependencies.
- Test evidence.
- Feature-flag rollback.
- Code rollback.
- Database restore or down migration when applicable.

## Migration Risk

| Risk | Areas | Required approach |
| --- | --- | --- |
| Critical | Auth, roles, permissions, User Management, Shift Assignment, Server Health | Small slices, complete role matrix, no client authority |
| High | Calendar, Dashboard, Cases, MoM, Domain Price, notifications, uploads | Cross-module tests and PHP fallback |
| Medium | Shift Reports, Checklist, Reminders, Tasks, Domains, Finance, Activity, Feedback | Standard island migration and object-scope tests |
| Medium / unknown | Infrastructure Pulse, OpsTrack, Network Pulse, TV Mode | Separate implemented behavior from planned telemetry |

## Definition Of Done

- Existing business behavior and data are preserved.
- PHP permission and object-scope enforcement remains authoritative.
- API contracts and errors are documented and tested.
- React has loading, empty, error, success, and permission states.
- Tailwind follows TRACS tokens and does not leak globally.
- Calendar reference behavior and visual density are matched.
- Manual and automated regression checks pass.
- PHP fallback and rollback are proven before release.

Shared React implementation must follow
`docs/tailwind-design-system-plan.md` and `docs/design-token-map.md`. The Phase 3
CSS templates under `frontend/src/styles/` remain non-production scaffolding
until a component-foundation batch explicitly connects them to a Vite entry.

Phase 4 connects those templates only to
`frontend/src/modules/_sandbox/main.jsx`. The sandbox is a local build and
component validation surface, not a migrated module. It has no production PHP
mount, navigation link, real API endpoint, or database access. Its build output
stays under ignored `frontend/dist/`.

The first real module batch must not reuse the sandbox entry. It should add a
separate named entry, characterize the existing PHP behavior, prepare and test
the required PHP API contracts, add an authenticated PHP root with a PHP
fallback, and load only that module's manifest assets. Shift Assignment remains
the intended first module after the shared foundation and PHP API prerequisites
are approved.

Phase 5 provides those future API primitives under the `TRACS\Api` namespace,
but no React module calls them yet. A module may adopt them only after its
current endpoint behavior, permissions, object scope, CSRF, audit effects, and
failure states have tests. The frontend client must consume the five-key
envelope while treating `401`, `403`, `404`, `409`, `422`, and `500` as distinct
server-authoritative states.

Phase 5.5 provides `GET /api/v1/context.php` as the only production pilot. A
future React shell may use it to obtain safe identity, effective permissions,
and the CSRF header/token pair, but no current page calls it yet. UI controls
may use returned permissions for presentation only; every module API must repeat
the authoritative permission and object-scope checks in PHP.

Phase 8 adds an isolated, read-only Shift Assignment React entry. It consumes
the global context, Shift Assignment context, and Shift Assignment assignments
GET contracts. It provides daily/weekly/monthly range controls, scoped filters,
summary cards, responsive assignment table/cards, warnings, and loading,
empty, validation, session, permission, and network states.

The entry is build-only and is not mounted by a PHP page or linked from
navigation. The existing `public/shifting-assignment.php` remains the production
UI and rollback path. No create, update, delete, template, copy, overtime, or
holiday mutation is present in the React shell.

Phase 9 mounts that same read-only entry at the unlinked authenticated preview:

```text
/shift-assignment-react-preview.php
```

The page requires the normal full session and `shifts.view`, uses the existing
TRACS shell, loads only the allowlisted Shift Assignment manifest entry, and
shows a safe build-required state when assets are missing. Production
navigation and `public/shifting-assignment.php` remain unchanged.

Phase 11 limits that direct-URL preview to the exact `super_admin` role in
addition to `shifts.view`. The implementation reuses the existing audited,
safe-denial page guard; it does not introduce a general feature-flag system or
add a navigation link. Expansion beyond Super Admin requires explicit approval.

Phase 12 hardens the same isolated page as a read-only production candidate:
compact Calendar-aligned density, staged filters, `dd-mm-yyyy` inputs with ISO
API conversion, stale-request cancellation, responsive table/cards, clearer
error states, and holiday/overtime notices. It remains unlinked and restricted.

Phase 13 plans mutations without activating them. The preview retains its
read-only banner and GET-only API client. Future forms remain absent or disabled
until one backend endpoint has passed CSRF, permission/scope, transaction,
audit, idempotency, rollback, and disposable-database tests. Create and update
are planned first; Phase 21 resolves the backend retention decision while
keeping React Delete UI blocked.

Phase 14 implements the controlled create backend contract only. The React
preview API client remains GET-only, no Add button or modal is activated, and
the pilot banner remains read-only. UI activation requires staging database
evidence and a separately approved branch.

Phase 15 supplies disposable-database integration evidence but does not change
that UI gate. React create still requires approved staging browser evidence,
form accessibility, unsaved-change, duplicate-submit, modal/toast, and rollback
review.

Phase 16 activates only the controlled create path in the direct-URL preview.
The button is rendered solely from the backend `create_assignment` capability,
the modal sends the context CSRF token without persistent storage, and the
existing GET view refreshes after success. Accessibility labels, field errors,
duplicate-submit protection, dirty-form confirmation, and success/conflict
feedback are included. Update, delete, templates, copy/paste, navigation
exposure, and legacy replacement remain blocked.

Phase 17 proves the activated create path in an isolated browser environment.
A temporary app container targeted a schema-only disposable clone, and the
real login plus 2FA flow established the session. The browser confirmed
capability-gated controls, frontend validation, Shift 3 `24:00`, successful
create and refresh, filtered row visibility, safe overlap feedback, role
concealment, and missing-permission hiding. Database audits were verified
before the container and database were destroyed.

No browser authentication bypass or public test route was added. The
environment helper is CLI-only under `tests/`, requires explicit mutation
consent, and refuses unsafe database names.

Phase 18 adds the controlled assignment PATCH contract behind the same exact
Super Admin, explicit `shifts.manage`, session, and CSRF gates. It is not called
by React. The preview remains create-only until a separate edit-UI phase adds
capability metadata, modal behavior, frontend contracts, and disposable browser
evidence. Legacy Shift Assignment and production navigation remain unchanged.

Phase 19 activates that PATCH contract only in the direct-URL preview. Edit
actions appear on desktop rows and mobile cards solely from the tightened
server capability. The modal sends changed fields only, preserves current
filters on refresh, and handles `401`, `403`, `404`, `409`, and `422`
distinctly. Disposable browser evidence covers Create regression, successful
Shift 3 update, unchanged-submit prevention, overlap rejection, and audits.

Phase 20 hardens the combined Create/Edit pilot without adding an endpoint or
expanding access. Shared safe mutation errors, invalid-field focus, accessible
required markers, save-time form disabling, and refresh-failure feedback are
covered by frontend and PHP source contracts. Disposable create/update
integration passed again. A fresh browser run was attempted against
`tracs_phase20_test`, but localhost navigation was blocked; Phase 19 remains
the latest authenticated browser evidence. Delete/template/copy and production
cutover remain gated.

Phase 21 adds only the controlled backend DELETE contract on the existing
single-assignment route. It does not expose a React control or capability.
Because the current schema lacks soft-delete support, the service preserves a
before snapshot and performs a scoped hard delete in one transaction. A
separate approved phase must design confirmation, stale-row handling, post-
delete refresh, and browser evidence before React Delete UI can exist.

Phase 22 defines that gate without implementing it. The future dialog must show
the assignment identity, warn that deletion is hard, require exact typed
`DELETE`, avoid optimistic removal, and preserve the row on every failure.
Restoration is manual and audited from the full assignment before snapshot;
the current create API is only a logical replacement path, not exact undo.

Phase 23 proves that exact assignment-row restore procedure in
`tracs_phase23_test`, including original ID, all current columns, scoped GET
visibility, duplicate prevention, and restore audit. It does not restore linked
warnings or cascaded dependent rows, so React Delete UI remains blocked.
## Phase 25 Controlled Delete Pilot

The isolated Shift Assignment preview now exposes Delete only from the
server-issued exact-Super-Admin plus `shifts.manage` capability. The modal
requires typed `DELETE`, displays safe assignment details, sends CSRF to the
existing DELETE contract, handles protected-template `409`, and refreshes the
unchanged filter/range query after success.

The legacy page remains authoritative, no navigation link was added, and
template/copy controls remain absent.

## Phase 26 Delete Regression Gate

The isolated preview passes a combined Create/Edit/Delete regression gate.
Delete confirmation remains exact and case-sensitive, recovery is described
as manual rather than undo, protected template links fail closed, and write
actions disappear when `shifts.manage` is removed. The legacy page remains
authoritative and preview access remains direct URL only.

## Phase 27-29 Template UI Planning Boundary

No template generation or copy/paste UI is added in Phase 27 or Phase 28.
Phase 29 adds only the first non-mutating Template Preview UI. Future React
write work must wait for backend contracts that split every bulk operation into
preview and commit:

- Template Generator preview: non-mutating.
- Template Generator commit: confirmed mutation.
- Copy Schedule preview: non-mutating.
- Copy Schedule commit: confirmed mutation.

The future UI should be a gated modal or wizard visible only from server-issued
capabilities. It must collect the date range and pattern/source, render the
previewed assignments, show conflicts, warnings, weekly-hour results, holiday
and overtime advisories, then require explicit confirmation before commit.

Phase 28 implements the backend-only Template Generator preview API. The React
preview has no template button, modal, wizard, or API caller before Phase 29.
Phase 29 adds a "Preview Template" modal that calls only the Phase 28 endpoint,
renders preview items, warnings, conflicts, blocked items, and summary counts,
and does not render commit, apply, save, copy, or bulk-write controls.

Phase 30 keeps that UI preview-only. A future Commit Review step may appear
only after the commit API exists and passes disposable tests. That step must
show the final assignment count, warnings, conflicts, and blocked items,
require exact `APPLY TEMPLATE`, reject whitespace/case variations, and keep the
commit button disabled while conflicts exist. The future commit request must
use preview-to-commit integrity checks, server-side conflict re-check, CSRF,
and exact Super Admin plus `shifts.manage`.

The UI must never call a bulk mutation directly from the first form step, must
not optimistically write rows, and must keep Create/Edit/Delete pilot behavior
unchanged until the template commit contracts pass disposable database
validation.

Phase 30 implementation gate before any commit or copy endpoint:

- prove no production data is touched;
- decide whether preview state is persisted, signed, or recomputed;
- define preview-to-commit integrity and final conflict re-check behavior;
- decide audit storage for parent template actions;
- confirm bulk rollback evidence for generated assignments and dependents;
- decide whether current `monthly_template_id` and `generated_assignment_id`
  are sufficient or a future `template_batch_id` migration is required;
- run disposable database integration before exposing any commit UI.

Phase 31 restores that disposable integration gate. Before any React commit
review UI or bulk apply control is added, `tests/disposable-db-preflight.php`
must pass and the delete restoration, dependent restoration, and template
preview integration drills must run green against a safely marked disposable
database.

Phase 32 adds the backend Template Commit API only. The React preview still has
no commit/apply/generate-save button and no API caller for
`templates/commit.php`. Future UI work must wait for authenticated disposable
browser evidence against the committed backend route and must keep the exact
`APPLY TEMPLATE` confirmation.

Phase 33 hardens the backend route with disposable rollback and race-conflict
drills. The React preview remains preview-only for templates: no Apply
Template button, no commit caller, no copy controls, and no optimistic bulk
write. A future Apply Template UI still needs a separate approval phase and
authenticated disposable-browser validation.

Phase 34 defines that approval gate. The future UI may evolve the existing
Template Preview modal into Configure Preview, Review Preview, Commit Review,
and Commit Result steps, but this phase adds no active Apply Template UI, no
commit/apply/generate-save button, and no API caller for `templates/commit.php`.
No active Apply Template UI is added in Phase 34.
Commit must remain disabled for stale previews, conflicts, blocked items,
missing CSRF, missing permission, non-Super-Admin users, and non-exact
`APPLY TEMPLATE` confirmation.

Phase 35 activates the Apply Template UI pilot only inside the isolated React preview. Apply
Template appears after successful preview, remains disabled for conflicts,
blocked items, stale preview, missing CSRF, missing capability, and non-exact
confirmation, and calls only the existing protected commit endpoint. It does
not add copy/paste UI, navigation, legacy replacement, or optimistic bulk rows.
The pilot requires exact APPLY TEMPLATE before commit.

Phase 36 hardens the Apply Template disabled/error states and keeps copy/paste
blocked. Live authenticated browser click-through is still required; the Codex
browser tool failed before navigation in this environment, so Phase 37 cannot
proceed from this evidence alone.
Phase 36 is blocked by browser tooling for live authenticated click-through.

Phase 37 restores authenticated browser evidence through a guarded dev-only
Playwright/Chrome path while the in-app browser remains blocked by missing
`sandboxPolicy` metadata. The test session endpoint only works under
`TRACS_ENV=test`, `TRACS_ALLOW_MUTATION_TESTS=1`, and a disposable-safe
database name. The browser run against `tracs_phase37_test` validates the
pilot banner, Template Preview, exact `APPLY TEMPLATE` confirmation behavior,
Apply Template success, assignment refresh, audit/rollback ids, rollback
targeting, conflict-disabled Apply behavior, and absence of copy/paste or
rollback UI. It also found and fixed a legacy unsaved-change overlay intercept
by marking the React-owned template preview form with `data-unsaved-ignore`.
Phase 38 copy-preview may proceed from this browser-validation gate only as a
separately approved copy-specific phase.

Phase 38 Copy Schedule Preview Contract Gate documents the future
`POST /api/v1/shift-assignment/templates/copy-preview.php` UI flow only. The
future button label is `Copy Schedule Preview`, visible only to exact
`super_admin` plus `shifts.manage` during the pilot. The modal will collect
source and target date ranges (`source_start_date`, `source_end_date`,
`target_start_date`, `target_end_date`), optional scope, then render source
summary, target preview, warnings, conflicts, and blocked items with the text
`Preview only - this will not create or modify assignments.` No copy-preview
endpoint, copy-commit endpoint, copy/paste UI, copy API caller, rollback UI, or
schema change is added. Future copy-commit must be a separate phase with exact
`APPLY COPY`, server-side source/target revalidation, conflict re-check, audit
created IDs, rollback targeting, disposable DB evidence, and authenticated
browser evidence.

Phase 39 implements the backend copy-preview route only. React still has no
`Copy Schedule Preview` button, no copy/paste modal, no copy API caller, and no
rollback UI. The existing Template Preview/Apply pilot remains unchanged. A
future React copy-preview phase must render preview-only results from
`copy-preview.php` and must not include copy-commit behavior until the separate
`APPLY COPY` commit phase is approved.

Phase 40 adds that React copy-preview phase as a controlled pilot only. The
toolbar exposes `Copy Schedule Preview` for exact Super Admin users with
`shifts.manage` through `allowed_actions.copy_preview`. The modal collects
source and target date ranges, sends CSRF to the non-mutating copy-preview API,
and renders source range, target range, preview items, summary, warnings,
conflicts, and blocked items. It clearly states `Preview only - this will not
create or modify assignments.`

Phase 40 does not add Apply Copy, Commit Copy, Paste Schedule, copy-commit
endpoint calls, rollback UI, assignment-list mutation, production navigation,
Calendar changes, schema changes, or legacy-page replacement. Copy-commit must
remain a separate approved phase with exact `APPLY COPY`, final backend
conflict re-check, audit created IDs, rollback targeting, and authenticated
browser validation.

Phase 41 hardens Copy Schedule Preview before any copy-commit planning. The
modal keeps preview-only behavior, adds clearer accessible date validation and
stale-result messaging, and the browser regression exercises missing dates,
same-range rejection, range-length mismatch, ranges above 35 days, valid
preview rendering, conflict rendering, and no-mutation count checks. Apply
Template e2e remains passing. No Apply Copy, Commit Copy, Paste Schedule,
rollback UI, schema change, Calendar change, legacy-page replacement, or
production navigation exposure is added.

Phase 42 Copy Commit Contract Gate is contract-only. It documents a future
multi-step Apply Copy flow but adds no active UI. Future Apply Copy must be
available only after a successful non-stale copy preview with zero conflicts
and zero blocked items, must require exact `APPLY COPY`, must send CSRF, and
must display created-count and rollback-targeting evidence after backend
success. Future backend commit must use server-side preview recomputation,
re-check conflicts immediately before writing, use an atomic all-or-nothing batch, audit created assignment IDs, and return rollback targeting data. No copy-commit endpoint, copy mutation caller, Paste Schedule UI, rollback UI, schema change, Calendar change, legacy-page replacement, or production navigation exposure is added in Phase 42.

Phase 43 Copy Commit Environment Gate keeps the frontend unchanged. It adds a
test-only preflight that verifies disposable DB safety, Playwright/browser
dependency readiness, the guarded authenticated test session path, rollback
cleanup documentation, and the copy-commit absence guard. Apply Copy, Commit
Copy, Paste Schedule, rollback UI, and copy-commit API callers remain absent.
Copy Commit API or UI work may proceed only after this environment gate,
browser regressions, copy-preview regressions, Apply Template regressions, and
cleanup checks pass.
