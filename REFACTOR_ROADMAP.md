# TRACS Full Refactor Roadmap

## Target

The final target is the entire TRACS system, not only Calendar, Shift
Assignment, or Dashboard:

- React JS frontend.
- Tailwind CSS following the existing TRACS visual identity.
- Advanced PHP backend and API layer.
- MySQL as the primary database.
- Gradual module-by-module migration with the current PHP application working.

This is not a big-bang rewrite. Existing business logic, permissions, sessions,
CSRF protection, validation, database access, audit behavior, uploads, exports,
and operational data remain authoritative until an approved replacement reaches
verified parity.

## Reference Implementation

`public/calendar.php` is the zero-mistake reference implementation. The Calendar
pilot defines the expected direction for:

- PHP-authenticated application shell and permission boundary.
- Manifest-loaded React island.
- Tailwind utilities isolated from legacy pages without Preflight.
- Mapping to current TRACS CSS variables and design tokens.
- Layout, spacing, cards, toolbars, filters, date controls, modals, toasts,
  tables, responsive behavior, and accessibility.
- Loading, empty, error, retry, and success states.
- API dates in ISO format with UI dates displayed as `dd-mm-yyyy`.

Future modules must learn from Calendar without weakening source-module
permissions or copying source data unnecessarily.

## Delivery Phases

1. Foundation, testing baseline, rollback, inventory, and safety documentation.
2. Full frontend architecture and build/loading strategy.
3. TRACS design-system consolidation and Tailwind token mapping.
4. Shared React and Tailwind component foundation.
5. Advanced PHP/API response, middleware, validation, service, and repository
   foundations.
6. Shift Assignment migration in small behavior-preserving batches.
7. Module-by-module migration across the remaining TRACS system.
8. Data and API normalization using reversible migrations.
9. Security hardening and permission regression.
10. Performance, accessibility, and UX polish.
11. Documentation, deployment, rollback, and TRACS v2 readiness.

## Migration Principles

- One module and one reviewable behavioral slice per branch.
- Characterize current behavior before changing it.
- Keep a PHP fallback until React parity is proven.
- React never bypasses PHP authentication, permission, or object-scope rules.
- Tailwind translates the existing design system; it does not introduce a new
  visual identity.
- Database changes require backup, paired `up.sql` and `down.sql`, verification,
  and explicit rollback notes.
- Every batch records branch, changed files, risk, tests, and rollback command.

## Suggested Module Order

1. Shift Assignment.
2. Checklist and Reminder.
3. Task Assignment and Monitoring.
4. Dashboard widgets, one widget at a time.
5. Cases.
6. Shift Reports.
7. MoM / Meeting.
8. Cancellation Feedback.
9. Domain Transfer Log and Finance.
10. Domain Price Crosscheck.
11. Infrastructure Pulse.
12. OpsTrack, Network Pulse, and TV Mode.
13. User Management, roles, and permissions.
14. Activity, reports, and CSV exports.
15. Profile and settings.
16. Login, authentication, and 2FA UI.
17. Super Admin monitoring and deployment tooling.

Authentication and permission foundations are tested and hardened early, but
their frontend migration remains late because of the system-wide risk.

## Current Phase

Phase 16 enables the first controlled React mutation only inside the unlinked
preview. `Add Assignment` is rendered from the backend context capability,
uses the Phase 14 POST contract and in-memory CSRF handoff, validates supported
fields, refreshes the current read view, and retains the pilot warning.

Phase 17 validates that UI end to end against `tracs_phase17_test` through a
separate temporary app container. Real login/2FA, role concealment,
permission-gated visibility, frontend validation, Shift 3 creation, filtered
GET visibility, overlap feedback, audits, and cleanup passed. The temporary
container was removed and the disposable database existence count returned
zero.

Phase 15 validated that create contract against a guarded disposable MySQL
database. Authenticated create, read-after-write, Shift 3, overlap, CSRF, exact
role, explicit `shifts.manage`, audits, and database teardown passed.

Delete remains blocked pending an approved retention or soft-delete design.
No update/delete/template/copy action or endpoint, permission seed, migration,
navigation change, Calendar change, or legacy-page change is included.

Phase 12 previously hardened the authenticated, unlinked React preview as a
read-only production candidate:

```text
/shifting-assignment.php
/shift-assignment-react-preview.php
```

It adds Calendar-aligned density, staged `dd-mm-yyyy` filters, responsive
table/cards, operational notices, clearer state handling, stale-request
cancellation, and bundle budgets. Access remains exact `super_admin` plus
`shifts.view`; APIs and business rules are unchanged.

The existing PHP page, legacy API, CSV export, service, Calendar reference,
database schema, data, production navigation, and Calendar remain unchanged.
`public/shifting-assignment.php` remains the production Shift Assignment UI and
fallback.

Canonical Phase 2 plans:

- `docs/react-tailwind-architecture.md`
- `docs/frontend-migration-plan.md`
- `docs/php-api-architecture-plan.md`

Phase 3 consolidates the current TRACS and Calendar visual decisions into
semantic Tailwind tokens and component contracts. It adds a non-loaded CSS
template under `frontend/src/styles/` with no Preflight, the `tr:` prefix, and
React-root-scoped compatibility rules. No current PHP page or production build
loads this scaffold.

Canonical Phase 3 plans:

- `docs/tailwind-design-system-plan.md`
- `docs/design-token-map.md`

Phase 4 implementation and operation notes:

- `frontend/README.md`
- `docs/react-tailwind-architecture.md`
- `docs/frontend-migration-plan.md`

Phase 5 implementation and operation notes:

- `api/README.md`
- `docs/php-api-architecture-plan.md`
- `TESTING.md`
- `ROLLBACK.md`

Phase 5.5 pilot notes:

- `api/README.md`
- `docs/php-api-architecture-plan.md`
- `docs/API_SECURITY_INVENTORY.md`
- `tests/php-api-contract.php`

Phase 6 contract notes:

- `docs/shift-assignment-api-contract.md`
- `api/v1/shift-assignment/context.php`
- `public/api/v1/shift-assignment/context.php`
- `tests/shift-assignment-api-contract.php`

Phase 7 read-resource notes:

- `api/v1/shift-assignment/assignments.php`
- `public/api/v1/shift-assignment/assignments.php`
- `tests/shift-assignment-assignments-api-contract.php`
- `docs/shift-assignment-api-contract.md`

Phase 8 read-only frontend notes:

- `frontend/src/modules/shift-assignment/`
- `frontend/vite.config.js`
- `frontend/tests/apiClient-contract.mjs`
- `docs/frontend-migration-plan.md`

Phase 9 authenticated preview notes:

- `public/shift-assignment-react-preview.php`
- `public/includes/react_manifest.php`
- `public/assets/react-dist/`
- `frontend/vite.preview.config.js`
- `tests/shift-assignment-react-preview.php`

Phase 10 parity notes:

- `docs/shift-assignment-preview-parity.md`
- `docs/shift-assignment-role-test-matrix.md`
- `tests/shift-assignment-preview-parity.php`

Phase 11 internal pilot notes:

- `public/shift-assignment-react-preview.php`
- `tests/shift-assignment-internal-pilot.php`
- `docs/shift-assignment-preview-parity.md`

Phase 12 candidate notes:

- `frontend/src/modules/shift-assignment/`
- `frontend/tests/preview-bundle-contract.mjs`
- `tests/shift-assignment-readonly-candidate.php`
- `docs/shift-assignment-preview-parity.md`

Phase 13 write-contract notes:

- `docs/shift-assignment-write-api-contract.md`
- `tests/shift-assignment-write-contract-plan.php`
- `docs/permission-api-contract-checklist.md`
- `docs/API_SECURITY_INVENTORY.md`

Phase 14 controlled-create notes:

- `public/api/v1/shift-assignment/assignments.php`
- `api/v1/shift-assignment/assignments.php`
- `tests/shift-assignment-create-api-contract.php`
- `docs/shift-assignment-write-api-contract.md`

Phase 15 disposable-integration notes:

- `tests/shift-assignment-create-api-integration.php`
- `tests/fixtures/shift-assignment-api-request.php`
- `TESTING.md`
- `ROLLBACK.md`

Phase 16-17 controlled create UI notes:

- `frontend/src/modules/shift-assignment/`
- `tests/shift-assignment-create-ui-pilot.php`
- `tests/shift-assignment-create-ui-browser-environment.php`
- `tests/shift-assignment-create-ui-browser-validation.php`

Phase 18 controlled-update notes:

- `public/api/v1/shift-assignment/assignment.php`
- `api/v1/shift-assignment/assignment.php`
- `tests/shift-assignment-update-api-contract.php`
- `tests/shift-assignment-update-api-integration.php`

The PATCH route is server-side only. The isolated React preview remains
create-only, and legacy Shift Assignment remains the production source of
truth.

Phase 19 controlled-edit notes:

- `frontend/src/modules/shift-assignment/components/ShiftEditModal.jsx`
- `frontend/src/modules/shift-assignment/utils/shiftEdit.js`
- `frontend/tests/shift-edit-contract.mjs`
- `tests/shift-assignment-edit-ui-pilot.php`

The direct-URL pilot now supports controlled create and edit for exact Super
Admin plus explicit `shifts.manage`. Delete, template/copy, broad navigation,
and production replacement remain blocked.

Phase 20 create/edit hardening notes:

- `frontend/src/modules/shift-assignment/utils/shiftMutation.js`
- `frontend/tests/shift-mutation-contract.mjs`
- `tests/shift-assignment-create-edit-hardening.php`

The combined pilot now has shared safe error handling, invalid-field focus,
accessible required markers, save-state disabling, and explicit refresh
fallback. Disposable create/update integration passed again. The Phase 20
browser attempt was blocked at the localhost login redirect, so Phase 19
remains the latest authenticated browser evidence. Delete, template/copy,
navigation exposure, and production replacement remain blocked.

Phase 21 controlled-delete notes:

- `DELETE /api/v1/shift-assignment/assignment.php?id=<id>`
- `tests/shift-assignment-delete-api-contract.php`
- `tests/shift-assignment-delete-api-integration.php`

The backend-only route requires exact Super Admin, explicit `shifts.manage`,
session, CSRF, and scoped assignment access. Because the current schema has no
soft-delete field, the pilot uses a transaction-protected hard delete with a
preserved before snapshot. Template-owned assignments return `409`. React
Delete UI, template/copy, navigation exposure, and production replacement
remain blocked.

Phase 22 delete-safety notes:

- typed confirmation must be exactly `DELETE`;
- the dialog must show the complete human-readable assignment identity;
- hard-delete risk and template-owned `409` behavior must be explicit;
- the required full before snapshot is the authoritative restore source;
- exact SQL restoration and logical create-API replacement are distinct;
- a future soft-delete migration remains documentation-only.

No React Delete UI or caller is added. Activation requires a separately
approved phase, a reviewed restore exercise, and authenticated disposable
browser evidence.

Phase 23 restoration-drill notes:

- `tests/shift-assignment-delete-restore-drill.php`
- exact original-ID restore from the full assignment before snapshot;
- all current assignment columns and scoped GET visibility verified;
- separate `shift_assignment.restore` activity audit verified;
- `tracs_phase23_test` removed after success.

The snapshot restores the assignment row but not deleted warnings or
foreign-key-cascaded dependent records. React Delete UI remains blocked pending
dependent-state retention/restoration design.

Phase 24 dependent-restoration notes:

- required delete audit includes warning and holiday-coverage snapshots;
- exact dependent IDs, timestamps, status, text, and warning resolution state
  restored in `tracs_phase24_test`;
- notifications and audit records verified retained;
- template-item links fail closed before hard delete;
- task-management assignment references confirmed unrelated;
- disposable database removed after success.

The backend restoration gate is complete. A controlled React Delete UI pilot
may be planned next; production navigation/replacement and production data
mutation remain prohibited.

Phase 25 controlled-delete UI notes:

- Delete is capability-gated to exact Super Admin plus `shifts.manage`;
- confirmation shows assignment details and requires exact typed `DELETE`;
- the context CSRF token is sent to the existing DELETE endpoint;
- success refreshes the current range and filters;
- protected template links show safe `409` without removing data;
- authenticated Create/Edit/Delete browser validation passed in
  `tracs_phase25_test`;
- audits, dependent snapshots, permission hiding, and cleanup were verified.

Template/copy work, production navigation, legacy replacement, and production
hard-delete rollout remain separate approval gates.

Phase 26 delete-hardening notes:

- assignment ID and all safe identity fields are reviewed before delete;
- restoration is described as controlled manual recovery, not instant undo;
- confirmation rejects case changes and surrounding whitespace;
- Shift 1 to Shift 2 to Shift 3, overlap rejection, delete, restore,
  template-link `409`, and post-delete Create/Edit passed;
- permission removal hid all write actions;
- disposable databases and browser resources were removed.

Template generation, copy/paste, production navigation, and legacy replacement
still require separate explicit approval.

Phase 27 template-contract notes:

- legacy monthly-template actions remain characterized but unchanged;
- future v1 template generation is split into non-mutating preview and
  confirmed commit routes;
- future copy/paste is split into non-mutating copy-preview and confirmed
  copy-commit routes;
- all four planned routes require authenticated session, CSRF, exact
  permission/scope checks, audit evidence, and rollback planning;
- legacy monthly preview can update draft status, so future v1 preview must be
  side-effect free rather than a direct wrapper;
- current schema supports template ownership through `source`,
  `monthly_template_id`, and generated item links, but granular template
  permissions and audit action names still require future migrations with
  `up.sql` and `down.sql`;
- no endpoint, React UI, schema, Calendar, navigation, or legacy-page behavior
  changes in this phase.

Phase 28 template-preview notes:

- `POST /api/v1/shift-assignment/templates/preview.php` is implemented as a
  non-mutating backend/API-only route;
- it requires session auth, CSRF, exact Super Admin, and explicit
  `shifts.manage`;
- it accepts a scoped weekly-rotation/date pattern, returns preview items,
  summary, warnings, conflicts, and blocked items;
- Shift 1, Shift 2, and Shift 3 `16:00-24:00` preview output passed disposable
  validation;
- valid preview was proven not to change assignment, warning, holiday coverage,
  monthly template, monthly template item, or assignment audit counts in
  `tracs_phase28_test`;
- commit, copy-preview, copy-commit, React template UI, navigation, schema,
  Calendar, and legacy-page changes remain blocked.

Phase 29 template-preview UI notes:

- the isolated Shift Assignment React preview now includes a controlled
  "Preview Template" entry point;
- visibility is driven by server-issued `preview_template` capability for
  exact Super Admin plus explicit `shifts.manage`;
- the UI sends CSRF to the Phase 28 preview endpoint and renders summary,
  items, warnings, conflicts, and blocked items;
- the modal clearly states preview-only behavior and does not render commit,
  apply, save, copy, or bulk-write controls;
- Create/Edit/Delete pilot behavior remains unchanged;
- no new backend endpoint, schema, Calendar, legacy-page, or navigation change
  is included;
- disposable no-mutation validation uses `tracs_phase29_test` and must confirm
  persisted counts remain unchanged.

Phase 30 template-commit gate notes:

- no commit/copy endpoint or React commit UI is implemented;
- future commit preview-to-commit integrity means never trusting preview
  payloads blindly and recomputing or revalidating server-side;
- future commit requires exact `APPLY TEMPLATE`, CSRF, exact Super Admin plus
  `shifts.manage`, and final conflict re-check;
- default `conflict_policy = block`;
- audit must include created assignment ids, warnings, conflicts, skipped or
  blocked items, request id, and bulk rollback reference;
- current schema has monthly-template ownership fields but no
  `template_batch_id`, so arbitrary preview-batch rollback needs complete audit
  evidence or a future reviewed migration with `up.sql` and `down.sql`.

Phase 31 disposable-DB gate notes:

- no endpoint, UI, schema, Calendar, navigation, or legacy-page behavior is
  changed;
- disposable validation now has an explicit preflight for Docker socket,
  `tracs_db`, MySQL `127.0.0.1:3307`, safe target DB naming, mutation opt-in,
  source schema availability, and stale target cleanup;
- local MySQL fallback is documented only for non-production environments;
- Template Commit API remains blocked unless delete restoration, dependent
  restoration, template preview integration, and the Phase 30 guard pass in the
  current environment.

Phase 32 template-commit API notes:

- `POST /api/v1/shift-assignment/templates/commit.php` is implemented as a
  controlled backend-only bulk write;
- it requires auth, CSRF, exact Super Admin, explicit `shifts.manage`, and
  future `shifts.template.commit` if seeded;
- exact `APPLY TEMPLATE` is enforced;
- preview is recomputed server-side and conflicts block with `409`;
- created assignment IDs are returned and audited for rollback targeting;
- `tracs_phase32_test` validates Shift 1/2/3 creation, conflict no-write,
  GET visibility, audit IDs, and rollback cleanup;
- no React commit UI, copy endpoints, schema, Calendar, navigation, or legacy
  page changes are included.

Phase 33 template-commit hardening notes:

- rollback targeting now has an explicit disposable drill that removes only
  returned `created_assignment_ids` and preserves unrelated assignments;
- a race drill confirms preview-to-commit revalidation catches conflicts added
  after preview and before commit;
- preview non-mutation remains covered after commit exists;
- exact `APPLY TEMPLATE` rejects case, whitespace, double-space, and hyphen
  variants;
- commit audit contains created ids, generated count, and rollback ids;
- React Apply Template UI, copy endpoints, schema changes, Calendar changes,
  navigation changes, and legacy-page changes remain blocked.

Phase 34 template-commit UI gate notes:

- the future React flow is documented as Configure Preview, Review Preview,
  Commit Review, and Commit Result;
- exact `APPLY TEMPLATE` confirmation UX and disabled conditions are defined;
- rollback evidence display after success is documented;
- future `commitTemplatePreview(payload)` client behavior is documented only;
- a guard test proves there is no active Apply Template UI, no commit caller,
  no copy UI, and no copy endpoints;
- No active Apply Template UI is added in Phase 34;
- no commit/apply/generate-save button is added in Phase 34;
- no API caller for `templates/commit.php` is added in Phase 34;
- Template Preview UI remains preview-only and Create/Edit/Delete behavior is
  unchanged.

Phase 35 Apply Template UI pilot notes:

- the existing Template Preview modal now includes a controlled Commit Review
  and Commit Result surface;
- Apply Template requires successful preview, zero conflicts, zero blocked
  items, CSRF, `allowed_actions.apply_template`, and exact `APPLY TEMPLATE`;
- the pilot requires exact APPLY TEMPLATE before commit;
- React calls only `POST /api/v1/shift-assignment/templates/commit.php` and
  never adds copy-preview or copy-commit behavior;
- backend `409` marks the preview stale and requires regeneration;
- success displays created count, request id, and rollback evidence based on
  created assignment IDs, then refreshes assignments without optimistic rows;
- no rollback UI, copy/paste UI, schema changes, Calendar changes, navigation
  changes, or legacy-page changes are included.

Phase 36 Apply Template UI hardening notes:

- stale/error states announce with alert semantics;
- confirmation input and disabled Apply reason are explicitly labelled;
- no rollback UI, copy/paste UI, copy endpoints, schema changes, Calendar
  changes, navigation changes, or legacy-page changes are included;
- disposable apply workflow validation remains green;
- live authenticated browser click-through remains blocked by browser tooling,
  so copy-preview and copy-commit work must not proceed yet.
- Phase 36 is blocked by browser tooling for live authenticated click-through.

Phase 37 Authenticated Browser Validation Gate notes:

- the in-app browser path is still blocked by missing `sandboxPolicy`
  metadata, but a repeatable dev-only Playwright/Chrome path now validates the
  real React preview in a browser;
- `public/__test/shift-assignment-auth-session.php` creates a test-only full
  session only when `TRACS_ENV=test`, `TRACS_ALLOW_MUTATION_TESTS=1`, and the
  database name is disposable-safe;
- `TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase37_test npm run test:e2e:shift-template-apply --prefix frontend`
  passed against `tracs_phase37_test`;
- the browser flow verifies pilot banner, Template Preview, exact
  `APPLY TEMPLATE` rejection/acceptance, Apply Template success, assignment
  refresh, commit audit ids, rollback targeting, conflict-disabled Apply, no
  copy/paste UI, no rollback UI, and clean console/network capture;
- live browser testing found and fixed the legacy unsaved-change overlay
  intercept by adding `data-unsaved-ignore` to the React-owned template
  preview form;
- Phase 38 copy-preview may proceed from the authenticated browser-validation
  gate, but only as a separately approved phase with copy-specific safeguards.

Phase 38 Copy Schedule Preview Contract Gate notes:

- documents future `POST /api/v1/shift-assignment/templates/copy-preview.php`
  only; no copy-preview endpoint, copy-commit endpoint, copy/paste UI, rollback
  UI, schema change, Calendar change, navigation change, or legacy-page change
  is introduced;
- defines `source_start_date`, `source_end_date`, `target_start_date`, and
  `target_end_date`, matching source/target range length, safe maximum range,
  ISO backend dates, and `dd-mm-yyyy` UI display;
- source-to-target transformation preserves agent, shift type, time, and safe
  scope fields while recalculating target date offset, day-of-week labels,
  holiday/overtime advisories, jumpshift/rest warnings, and weekly hours;
- future copy-preview returns `source_range`, `target_range`, preview items,
  summary, warnings, conflicts, and blocked items without mutating
  assignments, warnings, holiday coverage, template rows, notifications, or
  audit state;
- future UI copy must say `Preview only - this will not create or modify assignments.`;
- future pilot access remains exact `super_admin` plus `shifts.manage` and
  CSRF until a reviewed `shifts.template.copy_preview` migration exists;
- future copy-commit remains a separate phase and must require exact
  `APPLY COPY`, server-side source/target revalidation, conflict re-check,
  audit created IDs, rollback targeting, disposable DB evidence, and
  authenticated browser evidence.

Phase 39 Copy Schedule Preview API notes:

- implements protected `POST /api/v1/shift-assignment/templates/copy-preview.php`
  plus the public wrapper;
- requires CSRF, exact `super_admin`, and `shifts.manage`; future
  `shifts.template.copy_preview` is required if seeded;
- transforms source assignments into in-memory target preview rows with
  negative preview IDs and `source_assignment_id` references;
- supports Shift 1, Shift 2, and Shift 3 `16:00-24:00`;
- returns target conflicts, warnings, and blocked items without writing;
- `tracs_phase39_test` proves no persisted counts change for assignments,
  warnings, holiday coverage, monthly templates, monthly template items,
  assignment audit logs, or activity logs;
- copy-commit endpoint, copy/paste UI, rollback UI, schema changes, Calendar
  changes, navigation changes, and legacy-page changes remain absent.
