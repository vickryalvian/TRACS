# Shift Assignment API Contract And Characterization

## Phase 6 Boundary

This document records current Shift Assignment behavior before any React or
Tailwind migration. Phase 6 adds one protected, read-only context endpoint. It
does not replace `public/shifting-assignment.php`,
`public/api/shifting-assignment.php`, or existing service behavior.

`public/calendar.php` remains the zero-mistake visual and interaction reference.
No Calendar file is changed by this phase.

## Current Implementation Map

| Area | Current file |
| --- | --- |
| PHP page shell | `public/shifting-assignment.php` |
| Browser behavior | `public/assets/shifting-assignment.js` |
| Legacy page styling | `public/assets/shifting-assignment.css` |
| Legacy GET/POST API | `public/api/shifting-assignment.php` |
| CSV workload export | `public/shifting-assignment-export.php` |
| Business rules and queries | `modules/shifting-assignment/ShiftingAssignmentService.php` |
| Base schema and permissions | `config/migrations/2026_06_08_shifting_assignment.sql` |
| Canonical shift hours | `config/migrations/2026_06_13_main_shift_hours.sql` |
| Audit fixes | `config/migrations/2026_06_13_shifting_assignment_audit_fixes.sql` |
| Operational notes | `docs/shifting-assignment-implementation-notes.md` |

## Data Inventory

Primary tables read by the service:

- `shift_assignments`
- `shift_assignment_types`
- `shift_templates`
- `shift_workload_settings`
- `public_holidays`
- `shift_coverage_rules`
- `shift_agent_availability`
- `shift_monthly_templates`
- `shift_monthly_template_items`
- `shift_warnings`
- `assignment_audit_logs`
- `holiday_coverage_assignments`
- `tracs_users`
- `tracs_divisions`
- `tracs_roles`

Current writes affect:

- `shift_assignments`
- `holiday_coverage_assignments`
- `shift_warnings`
- `assignment_audit_logs`
- `shift_templates`
- `shift_monthly_templates`
- `shift_monthly_template_items`
- `public_holidays`
- `shift_coverage_rules`
- `shift_workload_settings`
- `tracs_activity_logs` through the legacy route

Phase 6 adds no table, column, index, migration, seed, or data write.

## Current Permissions And Scope

The page and legacy API require `shifts.view`. Service methods add the
operation-specific checks below.

| Operation | Permission |
| --- | --- |
| View schedules, warnings, audit, filters, and recap | `shifts.view` |
| Create/update/resize/status/confirm assignments | `shifts.manage` |
| Copy last week, replace agent, dismiss warning | `shifts.manage` |
| Shift templates, holidays, coverage rules, workload settings | `shifts.settings` |
| Monthly template save/preview/duplicate/apply/archive | `shifts.monthly_templates` or `shifts.settings` |
| CSV export | `shifts.export` |

Default migration grants:

- Super Admin and Admin: all Shift Assignment permissions.
- Supervisor: view, manage, monthly templates, and export.
- Agent, Intern, and Viewer: view only.

Object scope is also server-side:

- Agent and Intern records are restricted to the current user.
- Supervisor records are restricted to the current division.
- A Supervisor with no division falls back to current-user scope.
- Admin and Super Admin are not restricted by those role scopes.
- Agent/Intern filter options contain only the current user and division.
- Supervisor filter options contain only the current division.
- Monthly templates are unavailable to Agent and Intern and division-scoped
  for Supervisor.

React metadata will never replace these checks.

## Current Reads And Actions

The legacy `GET /api/shifting-assignment.php` supports:

- `action=data`
- `action=assignment`
- `action=history`
- `action=monthly_template`

The legacy POST route supports:

- `save_assignment`
- `resize_assignment`
- `update_status`
- `confirm_assignment`
- `dismiss_warning`
- `copy_last_week`
- `replace_agent`
- `save_template`
- `preview_monthly_template`
- `save_monthly_template`
- `duplicate_monthly_template`
- `apply_monthly_template`
- `archive_monthly_template`
- `save_holiday`
- `save_coverage_rule`
- `save_settings`
- `deactivate`

There is no current assignment-delete action. `deactivate` applies only to a
shift template, holiday, or coverage rule. A future DELETE contract must not
be introduced until its business meaning, audit behavior, and compatibility
are approved.

Monthly template preview is not guaranteed to be side-effect free because it
may update a draft template to `previewed`. It must remain a protected mutation
in a future API.

## Current Filters And Views

Implemented query filters:

- ISO start and end dates
- division
- agent/user
- assignment type
- status
- holiday-only
- free-text search

Daily, weekly, and monthly are client view modes over date-range data. Default
weekly range is Monday through Sunday. The service swaps reversed ranges and
caps a range at 366 days.

There is no current role query filter. Role is agent metadata and contributes
to server scope. The Phase 6 context therefore reports
`role_filter_supported: false`.

## Characterized Business Rules

- Shift 1 displays `00:00-08:00`.
- Shift 2 displays `08:00-16:00`.
- Shift 3 displays `16:00-24:00`, while storage uses next-day `00:00` and
  `is_cross_day=true`.
- Each canonical shift is 480 minutes.
- UI dates remain `dd-mm-yyyy`; API/database dates use `YYYY-MM-DD`.
- Counted statuses are assigned, confirmed, active, and completed.
- Cancelled, no-show, and replaced statuses are retained but not counted.
- Overlapping counted assignments are rejected or surfaced as conflicts.
- Jumpshift warning applies when non-negative rest is below the configured
  minimum, defaulting to 480 minutes.
- Default weekly target is 2,400 minutes, maximum is 2,880 minutes, and
  overtime-risk threshold is 2,700 minutes.
- Assignment duration must meet configured minimum and maximum daily limits.
- Availability, assignment type, status, date, time, break, scope, and active
  agent checks remain server-side.
- Holiday, overtime, coverage, approval, notification, and audit behavior
  remains in the existing service.
- Copy-last-week creates copied real assignments and skips conflicts; it must
  not replace existing real schedules with dummy data.
- Monthly application creates real assignments, records generated links,
  handles conflicts according to the approved option, and audits the result.
- Database schedule day names remain English where seeded templates use day
  names.

## Phase 6 Read-Only Contract

New endpoint:

```text
GET /api/v1/shift-assignment/context.php
```

It requires:

- a fully authenticated active session;
- completed 2FA where applicable;
- `shifts.view`;
- GET as the only method.

GET does not require CSRF because it does not mutate data. The response carries
the current CSRF token and header name for future mutations, which must still
verify CSRF server-side.

The endpoint returns:

- allowlisted current-user identity and role;
- permission booleans and allowed-action hints;
- canonical shift definitions;
- role-scoped safe agent/division/template/type filter options;
- weekly defaults and workload thresholds;
- date/time contract and CSRF handoff;
- `meta.request_id`.

It does not return assignments, email addresses, passwords, 2FA data, template
notes, type descriptions, raw SQL, paths, logs, environment values, database
credentials, or server details.

## Phase 7 Assignment Read Contract

Implemented as an additive read-only endpoint:

```text
GET /api/v1/shift-assignment/assignments.php
```

It requires a fully authenticated active session and `shifts.view`, accepts GET
only, and reuses the current service for role/division scope, assignment reads,
workload recap, holidays, jumpshift warnings, overlap warnings, and coverage
warnings.

Supported query:

- `start_date` and `end_date` as ISO `YYYY-MM-DD` dates
- `view=daily|weekly|monthly`
- `agent_id`
- `role`
- `division`
- `shift_type`
- `status`

Dates are intentionally ISO-only at the API boundary. The response also returns
`dd-mm-yyyy` display fields for the UI. This avoids ambiguous server parsing
while preserving the TRACS display convention.

Range rules:

- Daily requires one date.
- Weekly supports at most seven inclusive dates.
- Monthly must stay within one calendar month.
- Start and end must be supplied together or both omitted.
- Defaults are today for daily, Monday-Sunday for weekly, and the current
  calendar month for monthly.

Positive integer validation applies to agent and division. Role, shift type,
status, and view use allowlists. Invalid query input returns `422` using the
standard five-key envelope.

The response returns:

- view and raw/display date range;
- allowlisted assignment, agent, division, shift, type, status, approval,
  source, overtime, holiday, availability, and duration fields;
- aggregate counts and the current workload recap;
- allowlisted jumpshift, overlap, and coverage warnings;
- allowlisted holiday notices;
- `meta.request_id`.

It excludes user emails, assignment notes, credentials, 2FA data, creator and
approver IDs, raw SQL, internal paths, logs, environment values, and server
details. Role filtering is applied only after the existing service has already
enforced the caller's server-side scope.

No write method exists at this route. The legacy page and API remain unchanged.

## Phase 8 Read-Only React Consumer

The isolated frontend entry consumes the three protected GET contracts:

- `/api/v1/context.php`
- `/api/v1/shift-assignment/context.php`
- `/api/v1/shift-assignment/assignments.php`

It uses ISO query dates internally and displays API `dd-mm-yyyy` fields. Filter
controls only change GET query parameters. The shell has no mutation request,
action button, PHP mount, navigation link, or database behavior.

HTTP failures remain distinct: `401` session expired, `403` permission denied,
`422` filter validation, unexpected response/network failure, and successful
empty data. Messages are rendered from sanitized API responses without raw
stack traces.

## Phase 9 Authenticated Preview

The read-only React consumer is mounted at:

```text
/shift-assignment-react-preview.php
```

The PHP page requires the normal authenticated session and `shifts.view`
before rendering. It injects no user/session payload; React obtains only the
allowlisted context and assignment contracts through authenticated GET calls.
The preview is not in sidebar/navigation and does not replace the legacy page.

Assets are built with:

```bash
cd frontend
npm run build:preview
```

Missing or malformed assets produce a safe build-required panel instead of a
fatal PHP error or filesystem detail.

## Phase 10 Parity Gate

Role-based expectations and the manual parity procedure are canonical in:

- `docs/shift-assignment-role-test-matrix.md`
- `docs/shift-assignment-preview-parity.md`

`tests/shift-assignment-preview-parity.php` provides a non-mutating source gate
for shared page authentication, `shifts.view`, navigation isolation, approved
GET resources, v1 route permissions, default role grants, and server-side
self/division scope. Live record and visual parity still require authenticated
disposable fixtures and recorded browser evidence.

## Phase 11 Internal Pilot Access

The preview page now requires all of:

1. a fully authenticated active session;
2. `shifts.view`;
3. the exact `super_admin` role.

The existing Super Admin page guard performs server-side safe denial and audit
characterization. The direct URL remains absent from production navigation.
This page gate does not replace API authentication or permission enforcement.
The React pilot remains read-only and uses only the approved GET contracts.

## Phase 12 Read-Only Candidate

The candidate keeps the API unchanged. Display-date inputs use `dd-mm-yyyy` and
are validated and converted to ISO before the GET request. Filter edits are
staged until Apply, obsolete reads are aborted, and the unsupported role
control is removed. Holiday, overtime, summary, and warning presentation uses
only fields already returned by the assignments contract.

The current versioned mutation routes are:

```text
POST   /api/v1/shift-assignment/assignments.php
PATCH  /api/v1/shift-assignment/assignment.php?id=<id>
DELETE /api/v1/shift-assignment/assignment.php?id=<id>
```

All mutations require exact Super Admin, explicit `shifts.manage`, and CSRF.
DELETE remains backend-only after the Phase 21 hard-delete retention decision.
Phase 22 requires exact typed `DELETE`, full assignment detail confirmation,
manual restoration from the required before snapshot, and fresh disposable
browser evidence before any React Delete UI can be approved.

Phase 23 proves the full assignment-row snapshot can restore the original ID
and all current columns in a disposable database. It does not prove restoration
of warnings or foreign-key-cascaded dependent rows, so the active Delete UI
gate remains closed.

Phase 24 adds `_dependents.shift_warnings` and
`_dependents.holiday_coverage_assignments` to the required before-delete audit
without changing the top-level assignment contract. Disposable validation
restored both child rows exactly, retained notifications and audits, and
returned the restored assignment through GET. A live
`shift_monthly_template_items.generated_assignment_id` link now blocks delete
even when source flags are inconsistent. The backend dependency gate passes;
active React Delete UI remains absent pending its own approved pilot.

## Phase 13 Write Contract Plan

The canonical future mutation plan is:

- `docs/shift-assignment-write-api-contract.md`

It defines create, update, blocked delete, template generation/copy/apply,
overtime, warning resolution, export, and deferred bulk contracts. It also
records the transition from the existing broad permissions to proposed
granular permissions, without seeding or enforcing new permission keys yet.

Phase 13 adds no route, service mutation, React action, schema change, seed, or
data write. That historical planning boundary is superseded only by the
controlled Phase 14 create contract below.

## Phase 14 Controlled Create Contract

The assignments resource now accepts:

```text
GET  /api/v1/shift-assignment/assignments.php
POST /api/v1/shift-assignment/assignments.php
```

GET retains `shifts.view`, query validation, role/division scope, and the Phase
7 response. POST requires authentication, automatic mutation CSRF validation,
an explicit role assignment for `shifts.manage`, and exact `super_admin`
because `shifts.create` has not been seeded.

POST accepts one strict JSON assignment, forces `source=manual`, maps
`agent_id` to the service's `user_id`, accepts ISO dates, and normalizes Shift
3 `24:00` to cross-day midnight storage. The current service enforces agent
scope, overlap, availability, duration, template/type/status, holiday,
overtime, approval, notification, warning, and audit behavior.

Success returns `201` with an allowlisted assignment summary and warnings.
Field errors return `422` with `{field,message}` rows. Domain conflicts return
`409`; malformed JSON returns `400`; authentication, CSRF, and authorization
retain `401`/`403`; unsupported methods return `405`.

No React write call or control is added. The preview remains read-only and
exact `super_admin` plus `shifts.view`. No update, delete, template, copy,
overtime, warning, or bulk endpoint exists.

## Phase 15 Create Integration Validation

The guarded disposable integration test passed authentication, CSRF, exact
role, explicit permission, validation, authenticated create, Shift 3
cross-day persistence, read-after-write, overlap rejection, assignment/activity
audits, security audits, and unconditional database teardown.

The temporary target was `tracs_phase15_test`; it was dropped after the run.
React remains read-only.

## Phase 18 Controlled Update Contract

The additive update route is:

```text
PATCH /api/v1/shift-assignment/assignment.php?id=<id>
```

It loads the assignment through existing scope rules, requires a non-empty
allowlisted partial payload, merges omitted values from the current row, and
delegates validation and persistence to the existing service. It returns
`404` for a missing scoped record, `409` for overlap/availability conflict,
`422` for field validation, and the standard five-key success envelope with
safe assignment fields and warnings.

The service and API activity logs both retain before/after evidence. The
disposable integration verified Shift 3 `24:00`, GET visibility, conflict
non-mutation, and database teardown. No React PATCH call or edit control exists.

## Visible Risks To Protect

1. The legacy aggregate payload includes agent email and mixed concerns; future
   v1 resources must allowlist fields and split context from data.
2. Legacy error envelopes differ from the five-key v1 envelope; do not silently
   replace the legacy route.
3. Monthly preview may change state despite its name.
4. Daily/weekly/monthly visibility depends on range and cross-day overlap,
   especially Shift 3.
5. Role and division scope can be weakened if clients receive unscoped data.
6. Copy/template generation can duplicate or hide real schedules if conflict
   and source rules drift.
7. CSV scope must remain identical to source workload data.
8. Client-visible permissions are hints only; every request must re-check PHP.

## Manual Characterization Checklist

- [ ] Super Admin and Admin see allowed global data and actions.
- [ ] Supervisor sees only the assigned division.
- [ ] Supervisor without a division falls back to self scope.
- [ ] Agent and Intern see only their own schedule.
- [ ] Viewer can view but cannot mutate or export unless explicitly granted.
- [ ] Daily, weekly, and monthly ranges show seeded real assignments.
- [ ] Shift 3 renders `16:00-24:00` and remains a cross-day record.
- [ ] Holiday notices and holiday assignments remain visible.
- [ ] Overtime and approval-pending assignments remain visible.
- [ ] Rest below eight hours creates a jumpshift warning.
- [ ] Weekly target, overtime-risk, and overload states remain accurate.
- [ ] Copy last week skips conflicts and does not replace real data.
- [ ] Monthly template preview/apply behavior and audits remain intact.
- [ ] CSV contains only permitted scoped recap rows.
- [ ] UI date controls display `dd-mm-yyyy`.
- [ ] New context returns `401`, `403`, `405`, or `200` as appropriate.
- [ ] No new context request writes module data.
- [ ] New assignments endpoint returns scoped seeded assignments for daily,
      weekly, and monthly ranges.
- [ ] ISO query dates and `dd-mm-yyyy` display fields refer to the same dates.
- [ ] Role, agent, and division filters cannot expand server scope.
- [ ] Assignment responses omit email, notes, actor IDs, SQL, paths, and logs.
- [ ] GET remains read-only and compatible.
- [ ] POST requires exact Super Admin, `shifts.manage`, and CSRF and creates
      only one validated manual assignment in a disposable/staging database.
- [ ] PATCH requires exact Super Admin, explicit `shifts.manage`, CSRF, scoped
      record access, and a disposable/staging validation environment.
- [x] DELETE requires exact Super Admin, explicit `shifts.manage`, CSRF, scoped
      access, and disposable-database validation; React has no delete caller.

## Validation

```bash
php tests/php-api-foundation.php
php tests/php-api-contract.php
php tests/shift-assignment-api-contract.php
php tests/shift-assignment-assignments-api-contract.php
php tests/shift-assignment-create-api-contract.php
php tests/shift-assignment-update-api-contract.php
php tests/shift-assignment-delete-api-contract.php
find api tests public/api/v1 -name '*.php' -exec php -l {} \;
```

Authenticated role and database checks require a disposable database and
fixture accounts. Never use production shift records for mutation tests.
