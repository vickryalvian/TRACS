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
are planned first; delete remains blocked pending a retention decision.

Phase 14 implements the controlled create backend contract only. The React
preview API client remains GET-only, no Add button or modal is activated, and
the pilot banner remains read-only. UI activation requires staging database
evidence and a separately approved branch.

Phase 15 supplies disposable-database integration evidence but does not change
that UI gate. React create still requires approved staging browser evidence,
form accessibility, unsaved-change, duplicate-submit, modal/toast, and rollback
review.
