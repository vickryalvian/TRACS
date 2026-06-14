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

## Planned Assignment Read Contract

Future, not implemented in Phase 6:

```text
GET /api/v1/shift-assignment/assignments.php
```

Proposed query:

- `start_date` and `end_date` as ISO dates
- `view=daily|weekly|monthly`
- `agent_id`
- `division_id`
- `assignment_type`
- `status`
- `holiday_only`
- `q`

It must reuse current scope and business services, return only allowlisted
assignment fields, preserve seeded real schedules, and keep recap/warnings as
separately testable resource sections or endpoints.

Future writes are not approved in this phase:

```text
POST   /api/v1/shift-assignment/assignments.php
PATCH  /api/v1/shift-assignment/assignments/{id}.php
DELETE /api/v1/shift-assignment/assignments/{id}.php
```

Before a write endpoint is added, define its exact permission, object scope,
CSRF, validation, transaction, conflict, audit, notification, and rollback
contract. DELETE needs separate approval because the current module has no
assignment-delete action.

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

## Validation

```bash
php tests/php-api-foundation.php
php tests/php-api-contract.php
php tests/shift-assignment-api-contract.php
find api tests public/api/v1 -name '*.php' -exec php -l {} \;
```

Authenticated role and database checks require a disposable database and
fixture accounts. Never use production shift records for mutation tests.
