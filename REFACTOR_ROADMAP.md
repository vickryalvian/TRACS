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
