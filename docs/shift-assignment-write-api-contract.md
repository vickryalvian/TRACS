# Shift Assignment Write API Contract Plan

## Phase 13 Boundary

This document plans future Shift Assignment mutations. Phase 13 does not add a
write route, change the React preview, seed permissions, alter the database, or
modify real schedules.

Current authority remains:

- `public/shifting-assignment.php` and its legacy API;
- `modules/shifting-assignment/ShiftingAssignmentService.php`;
- the existing `shifts.manage`, `shifts.settings`,
  `shifts.monthly_templates`, and `shifts.export` permissions;
- the read-only v1 context and assignments endpoints.

The React preview remains an exact `super_admin` plus `shifts.view` read-only
pilot. `public/calendar.php` remains the zero-mistake visual reference.

## Contract Status

| Contract | Proposed method and path | Status |
| --- | --- | --- |
| Create assignment | `POST /api/v1/shift-assignment/assignments.php` | Implemented in Phase 14 under controlled pilot gate |
| Update assignment | `PATCH /api/v1/shift-assignment/assignment.php?id=<id>` | Implemented; controlled React pilot |
| Delete assignment | `DELETE /api/v1/shift-assignment/assignment.php?id=<id>` | Implemented backend-only in Phase 21; React UI blocked |
| Generate monthly template preview | `POST /api/v1/shift-assignment/templates/preview.php` | Implemented non-mutating Phase 28 preview contract |
| Commit monthly template generation | `POST /api/v1/shift-assignment/templates/commit.php` | Planned confirmed bulk mutation |
| Copy schedule preview | `POST /api/v1/shift-assignment/templates/copy-preview.php` | Planned non-mutating copy preview |
| Copy schedule commit | `POST /api/v1/shift-assignment/templates/copy-commit.php` | Planned confirmed copy mutation |
| Legacy generate placeholder | `POST /api/v1/shift-assignment/templates/generate.php` | Superseded naming; no route exists |
| Legacy copy placeholder | `POST /api/v1/shift-assignment/templates/copy.php` | Superseded naming; no route exists |
| Apply monthly template | `POST /api/v1/shift-assignment/templates/{id}/apply.php` | Deferred; commit contract must decide whether this remains separate |
| Assign overtime | `POST /api/v1/shift-assignment/overtime.php` | Planned facade over normal assignment validation |
| Resolve warning | `POST /api/v1/shift-assignment/warnings/{key}/resolve.php` | Planned only for dismissible warnings |
| CSV export | `GET /api/v1/shift-assignment/export.php` | Planned read/export contract; no CSRF |
| Bulk update | `PATCH /api/v1/shift-assignment/assignments/bulk.php` | Deferred until single-record writes are proven |

Paths containing `{id}` or `{key}` describe the target contract. The eventual
thin PHP route layout may use an equivalent validated query/path adapter if the
current server cannot route dynamic PHP filenames safely.

## Permission Transition

The database currently defines only:

- `shifts.view`
- `shifts.manage`
- `shifts.settings`
- `shifts.monthly_templates`
- `shifts.export`

The granular permissions below are target contracts and do not exist yet:

| Future permission | Future capability | Current compatibility gate |
| --- | --- | --- |
| `shifts.create` | Create assignment or overtime assignment | `shifts.manage` |
| `shifts.update` | Edit, resize, confirm, or change status | `shifts.manage` |
| `shifts.delete` | Delete/soft-delete an assignment | No current assignment-delete behavior |
| `shifts.template.generate` | Historical umbrella for template generation | `shifts.monthly_templates` or `shifts.settings` |
| `shifts.template.copy` | Historical umbrella for copy last week or duplicate template | `shifts.manage` or `shifts.monthly_templates` |
| `shifts.template.preview` | Preview a generated template without mutation | Exact `super_admin` plus `shifts.manage` until seeded |
| `shifts.template.commit` | Commit a generated template batch | Exact `super_admin` plus `shifts.manage` until seeded |
| `shifts.template.copy_preview` | Preview copy/paste schedule output without mutation | Exact `super_admin` plus `shifts.manage` until seeded |
| `shifts.template.copy_commit` | Commit a copied schedule batch | Exact `super_admin` plus `shifts.manage` until seeded |
| `shifts.overtime.create` | Create overtime/holiday coverage assignment | `shifts.manage` |
| `shifts.warning.resolve` | Dismiss an allowed warning | `shifts.manage` |
| `shifts.export` | Export scoped data | Existing permission |
| `shifts.audit.view` | View assignment history/audit detail | Currently covered by `shifts.view` |

Do not make a v1 write depend on a permission key that has not been seeded. A
later permission migration must include paired `up.sql` and `down.sql`, role
grant verification, and compatibility tests. Until that migration is approved,
an implemented endpoint must use the current gate and document the future
granular mapping.

Suggested initial role intent, subject to explicit approval:

| Role | Intended write scope |
| --- | --- |
| Super Admin | All approved permissions and global scope |
| Admin | Create/update/template/overtime/export; delete only if separately approved |
| Supervisor | Create/update/template copy within assigned division; no global settings |
| Agent | Read own schedule; no assignment writes by default |
| Intern | Read own schedule; no assignment writes by default |

Role names never grant a write by themselves. PHP must check the exact
permission and then enforce object, agent, and division scope.

## Phase 14 Controlled Create Adoption

Phase 14 implements only:

```text
POST /api/v1/shift-assignment/assignments.php
```

The same file retains the Phase 7 GET behavior. `PATCH`, `DELETE`, template,
copy, overtime, warning-resolution, export-v1, and bulk routes remain absent.

`shifts.create` is not present in the current permission catalog. The temporary
controlled pilot therefore requires all of:

1. fully authenticated active session;
2. valid `X-CSRF-Token`;
3. an explicit `shifts.manage` assignment on the user's role;
4. exact `super_admin` role.

The future `shifts.create` migration remains required before broader role
access. The context endpoint advertises create capability only when the current
user meets this temporary gate.

The JSON body accepts required `agent_id`, `assignment_date`, `shift_type`,
`start_time`, and `end_time`, plus optional `shift_template_id` or
`template_id`, `break_minutes`, `status`, and `notes`.

Unknown fields are rejected. Client-controlled `source`, `is_overtime`, role,
division, creator, approver, and audit fields are not accepted. Source is
forced to `manual`; overtime, holiday, approval, division, duration, and
cross-day state are calculated by the existing service.

`24:00` is accepted as an API display boundary and normalized to the existing
next-day `00:00` storage behavior. Create status is limited to `assigned` or
`confirmed`.

The existing service remains authoritative for active-agent scope, overlap,
availability, duration, template/type/status, holiday coverage, overtime,
approval, notification, jumpshift warnings, and assignment audit records.

Phase 14 also writes a correlated activity audit summary through the Phase 5
helper. It excludes notes and sensitive identity fields. Authentication,
permission, exact-role, and invalid-CSRF denials use the existing security
audit path. Audit storage remains best-effort where an optional legacy audit
table is unavailable; no schema is added.

## Phase 15 Disposable Integration Evidence

Run the guarded integration workflow with:

```bash
TRACS_ENV=test \
TRACS_ALLOW_MUTATION_TESTS=1 \
TRACS_TEST_DB_NAME=tracs_phase15_test \
php tests/shift-assignment-create-api-integration.php
```

The runner refuses to start unless the environment is exactly `test`, mutation
tests are explicitly enabled, and the target database name contains `test`,
`local`, `dev`, or `disposable`.

It creates a temporary database, clones schema only from local Compose MySQL,
seeds dedicated fixtures, invokes the real v1 route with isolated session and
CSRF state, verifies create/read/overlap/audits, and drops the database in a
`finally` block.

The authenticated success path was genuinely exercised against MySQL. No
production database or existing assignment row was used or modified.

The test exposed that normal `tracs_user_can()` gives Super Admin implicit
catalog-wide access. The controlled endpoint now additionally checks an
explicit role-permission assignment for `shifts.manage`. Normal TRACS
permission semantics elsewhere are unchanged.

React create UI remains blocked until approved staging browser evidence and a
separate UI activation review.

## Phase 16 Controlled React Adoption

The direct-URL React preview now exposes create only when the context returns
`allowed_actions.create_assignment=true`. The PHP page still requires
`shifts.view` and exact Super Admin access; the POST route independently
requires exact `super_admin`, explicit `shifts.manage`, authentication, and
valid CSRF.

The frontend accepts only the implemented API fields, sends `assignment_date`
as ISO after displaying `dd-mm-yyyy`, supports Shift 1/2/3 presets including
the `24:00` boundary, disables duplicate submission, maps `422` field errors,
keeps the modal open on failure, refreshes GET after success, and warns when
the created row may be outside current filters.

The CSRF token remains in React memory from the protected context response and
is sent using that response's header name. It is not written to local or
session storage. Frontend checks are usability controls only; PHP remains the
authority.

This activation does not approve update, delete, templates, copy/paste,
overtime-specific controls, broad role access, navigation exposure, or
production replacement.

## Phase 17 Browser Validation Result

The controlled create contract was genuinely exercised through the React UI
against `tracs_phase17_test`. The test used the real login and mandatory 2FA
setup flow, exact Super Admin plus explicit `shifts.manage`, and fixture agent
ID `9733`.

Shift 3 on `2026-07-13` created successfully with the `16:00-24:00` display
boundary, appeared through the read API after applying the matching daily
range, and produced both assignment and activity audit records. Repeating the
same create returned the safe overlap conflict without a second row.

Non-Super Admin access was concealed, and removing explicit `shifts.manage`
from the disposable Super Admin role hid the create entry point. The temporary
container and disposable database were then removed.

This evidence clears the create UI's disposable-browser gate. It does not
approve update/delete/template/copy contracts or production replacement.

## Common Security Contract

Every future mutation must:

1. Accept only its documented HTTP method and return `405` with `Allow`
   otherwise.
2. Require a fully authenticated, active session with completed 2FA.
3. Reject missing authentication with `401`.
4. Require its exact current or future permission and reject denial with `403`.
5. Verify object/division/agent scope before reading before-values or writing.
6. Require CSRF from the context response using
   `X-CSRF-Token: <token>`.
7. Reject missing or invalid CSRF with the standard JSON envelope and `403`.
8. Parse a JSON object and reject malformed bodies with `400`.
9. Return field validation failures with `422`.
10. Return state, overlap, version, or idempotency conflicts with `409`.
11. Use prepared queries and a service-owned transaction for linked writes.
12. Write the required audit record before reporting success.
13. Return sanitized errors without SQL, stack traces, paths, credentials,
    environment values, session identifiers, or CSRF tokens.

Frontend permission flags are presentation hints only. Hiding a button cannot
replace any server-side check.

## Standard Envelopes

Success:

```json
{
  "success": true,
  "message": "Assignment created.",
  "data": {
    "assignment": {},
    "warnings": []
  },
  "errors": [],
  "meta": {
    "request_id": "..."
  }
}
```

Validation failure:

```json
{
  "success": false,
  "message": "Validation failed.",
  "data": {},
  "errors": [
    {
      "field": "agent_id",
      "message": "Agent is required."
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

Recommended statuses:

- `201` create succeeded.
- `200` update/action/export metadata succeeded.
- `400` malformed JSON or request.
- `401` missing, expired, or incomplete authentication.
- `403` permission, scope, or CSRF denial.
- `404` missing resource or intentional concealment.
- `405` unsupported method.
- `409` overlap, stale version, duplicate execution, or state conflict.
- `422` field/business validation.
- `500` sanitized unexpected failure with private correlated logging.

## Assignment Create

```text
POST /api/v1/shift-assignment/assignments.php
```

Required permission: future `shifts.create`; the Phase 14 compatibility gate is
exact `super_admin` plus `shifts.manage`.

Proposed JSON:

```json
{
  "agent_id": 21,
  "date": "2026-07-01",
  "shift_type": "regular_shift",
  "shift_template_id": 1,
  "start_time": "00:00",
  "end_time": "08:00",
  "break_minutes": 0,
  "status": "assigned",
  "notes": "",
  "client_request_id": "..."
}
```

Validation:

- `agent_id` is a positive active user inside the caller's scheduling scope.
- `date` is strict ISO `YYYY-MM-DD`; UI conversion from `dd-mm-yyyy` occurs
  before the request.
- `shift_type`, status, and template use server allowlists.
- start and end are strict 24-hour times; equal times are invalid.
- Shift 3 `16:00-24:00` is represented internally as a cross-day midnight end.
- break is non-negative, below gross duration, and within the current limit.
- calculated duration respects division workload settings.
- new records cannot begin as active or completed.
- overlapping counted assignments are rejected.
- unavailable agents are rejected according to current behavior.
- rest below 480 minutes returns a jumpshift warning; it is not silently lost.
- weekly totals, holiday coverage, overtime, approval, notifications, and
  English day-name conventions remain service-owned.
- source defaults to `manual`; clients cannot claim template/system provenance.
- dummy reset or automatic data replacement is never accepted.

The endpoint should accept an idempotency/client request ID before production
activation so a retry cannot create duplicate real assignments.

## Assignment Update

```text
PATCH /api/v1/shift-assignment/assignment.php?id=<id>
```

Required permission: future `shifts.update`; current gate `shifts.manage`.

Phase 18 implements this route under the temporary pilot gate: exact
`super_admin`, explicit role assignment for `shifts.manage`, authenticated
session, and mutation CSRF. It accepts at least one allowlisted field and
merges omitted fields with the scoped current record before invoking the
existing service. Unsupported source, creator, approver, division override,
audit, and overtime flag fields are rejected.

The current pilot request uses the same field rules as create:

```json
{
  "agent_id": 21,
  "assignment_date": "2026-07-01",
  "shift_type": "regular_shift",
  "start_time": "08:00",
  "end_time": "16:00",
  "status": "confirmed",
  "status": "confirmed"
}
```

PHP loads the scoped record, captures the before snapshot, validates the
complete resulting resource, and updates holiday coverage and notifications
consistently. A future production-replacement phase must add an explicit
concurrency/version contract; Phase 18 does not claim stale-write protection.
PATCH must not permit
unlisted fields such as creator IDs, approver IDs, source, audit fields, or
division overrides.

Status, confirmation, and resize may later receive narrower action endpoints,
but they must not bypass the same validation, permission, scope, concurrency,
and audit rules.

## Assignment Delete

```text
DELETE /api/v1/shift-assignment/assignment.php?id=<id>
```

Phase 21 implements this backend-only contract for exact `super_admin` plus
explicit `shifts.manage`. It requires CSRF, scoped record access, and a required
before-delete assignment audit. The current schema has no soft-delete columns,
so the endpoint performs a transaction-protected hard delete. Template-owned
assignments return `409`.

## Delete UI Safety Gate

Phase 25 activates Delete only inside the direct-URL controlled React preview.
The pilot must continue to satisfy every item:

1. Render Delete only when the server capability confirms exact `super_admin`,
   `shifts.view`, and explicit `shifts.manage`. Frontend visibility never
   replaces backend enforcement.
2. Show agent name, `dd-mm-yyyy` assignment date, shift type, start/end time,
   status, and available role/division context in the confirmation.
3. State plainly that the action is a hard delete because no soft-delete
   schema exists.
4. Require the operator to type exactly `DELETE`. Pasting or typing the agent
   name/date alone is not sufficient for the first destructive pilot.
5. Keep the destructive submit disabled until the typed value matches and
   disable the complete dialog while the request is pending.
6. Prevent duplicate submission, keep the dialog open on failure, refresh the
   current assignment query after success, and show success only after the API
   confirms deletion.
7. Handle `401`, `403`, `404`, `405`, `409`, `422`, network, and unexpected
   errors with safe messages. A `409` must explain that template-owned records
   require the template/copy workflow.
8. Do not optimistically remove the row. If the request fails, the assignment
   remains visible.
9. Require fresh authenticated browser evidence against a disposable database
   before UI activation.

### Manual Restoration

There is no restore endpoint. Restoration is an audited administrative
procedure, never an automatic UI undo.

The authoritative restore source is the `before_snapshot` from the
`assignment_audit_logs` row where `action='deleted'`. It contains the full
pre-delete `shift_assignments` row. The API activity audit is intentionally a
safer summary and is not sufficient for exact restoration.

Before restoring:

1. Work only in an approved staging/admin database session.
2. Record the delete request/audit IDs and export the snapshot.
3. Verify the user, division, template, monthly-template, and approver IDs still
   exist.
4. Check that the original assignment ID is unused and that restoring the time
   range will not create an overlap.
5. Back up `shift_assignments`, `assignment_audit_logs`, and affected warning,
   holiday, notification, and template-link tables.

Exact restoration uses a reviewed transaction and an explicit INSERT mapping
all current `shift_assignments` columns from the snapshot. Reusing the original
ID is allowed only when it remains unused and dependent references have been
reviewed. The restoration must add a separate audited
`shift_assignment.restore` event with the delete audit ID and reason. Commit
only after the restored row appears through the scoped GET API and all
constraints are valid; otherwise roll back.

The existing POST create API may be used only for a logical replacement of a
simple manual assignment. It creates a new ID and cannot faithfully restore all
source, approval, creator/updater, monthly-template, or historical metadata.
The operator must label and audit that result as a replacement, not an exact
restore.

If the full before snapshot is unavailable, malformed, or references missing
records, restoration is blocked and active Delete UI must remain disabled.

### Phase 23 Restoration Drill Result

`tracs_phase23_test` proved exact assignment-row restoration. The drill reused
the original ID, mapped every current `shift_assignments` column from the full
before snapshot, verified scoped GET visibility and key-field equality,
prevented duplicates, and wrote a separate `shift_assignment.restore` activity
audit tied to the delete audit.

The snapshot is sufficient for the assignment row itself. It does not contain
linked `shift_warnings` rows removed by the delete transaction, nor dependent
rows removed by foreign-key cascade such as holiday coverage links. Exact
assignment restoration therefore does not yet equal complete operational-state
restoration.

**Delete UI remains blocked.** Before activation, preserve dependent rows,
capture and restore their snapshots transactionally, or prove they can be
safely regenerated with explicit audit and no lost operator state. Fresh
authenticated disposable-browser evidence is also required after the UI
exists.

### Future Soft Delete Proposal

A later migration may propose nullable `deleted_at`, nullable `deleted_by`,
optional `delete_reason`, GET filtering, restore behavior, indexes, and audit
integration. It must include reviewed `up.sql` and `down.sql`, legacy-page
compatibility, performance checks, and tested restoration. Phase 22 creates no
migration or schema change.

## Templates And Copy

Template generation and copy operations are bulk mutations even when called
"preview". The current monthly preview may mark a draft as previewed.

Every future bulk flow requires:

1. A non-committing preview response with counts, conflicts, warnings, target
   range, and a short-lived confirmation token.
2. A second explicit confirmation request carrying that token.
3. Idempotency protection.
4. A transaction boundary or a documented per-item partial-success model.
5. Created/skipped IDs and reasons in the response.
6. Audit records for the parent action and affected assignments.
7. No overwrite of seeded real schedules and no automatic dummy reset.

Phase 27 narrows the future v1 contract into explicit preview-before-commit
routes:

```text
POST /api/v1/shift-assignment/templates/preview.php
POST /api/v1/shift-assignment/templates/commit.php
POST /api/v1/shift-assignment/templates/copy-preview.php
POST /api/v1/shift-assignment/templates/copy-commit.php
```

`templates/preview.php` is implemented as a non-mutating in-memory preview.
`templates/copy-preview.php` must follow the same rule when approved. Preview
routes may not update draft state, create rows, reserve assignments, archive
templates, or write generated links. `templates/commit.php` and
`templates/copy-commit.php` are the only planned mutating bulk routes, and they
must re-check permissions, CSRF, scope, preview freshness, conflicts, weekly
hours, jumpshift/rest warnings, holiday/overtime advisories, and real-schedule
overwrite risk immediately before writing.

Phase 28 preview accepts a scoped weekly-rotation/date pattern and selected
agents, requires exact Super Admin plus explicit `shifts.manage`, and returns
preview items, conflicts, warnings, and blocked items without writing
assignments, templates, warnings, dependents, or assignment audits. Disposable
validation in `tracs_phase28_test` proved table counts did not change after a
valid preview.

The detailed canonical contract is:

- `docs/shift-assignment-template-api-contract.md`

The current schema already contains `shift_assignments.source`,
`shift_assignments.monthly_template_id`, `shift_monthly_templates`, and
`shift_monthly_template_items.generated_assignment_id`. Those fields support
template ownership and rollback targeting, but the current
`assignment_audit_logs.action` enum does not contain the proposed granular
template action names. A future implementation must either use the approved
general activity audit for parent action summaries or include reviewed
`up.sql` and `down.sql` to expand the enum.

## Overtime

```text
POST /api/v1/shift-assignment/overtime.php
```

Required permission: future `shifts.overtime.create`; current gate
`shifts.manage`.

Overtime is still an assignment. This endpoint must delegate to the same
assignment service and validation rather than create parallel write logic. It
must validate assignment type flags, approval state, holiday coverage, weekly
thresholds, overlap, availability, and minimum rest. The response must identify
pending approval and warnings without exposing private agent fields.

## Warning Resolution

```text
POST /api/v1/shift-assignment/warnings/{key}/resolve.php
```

Required permission: future `shifts.warning.resolve`; current gate
`shifts.manage`.

Only allowlisted dismissible warning types may be resolved. The warning key,
optional assignment/user reference, date, and note must remain scoped and
validated. Structural conflicts must not be hidden by changing frontend state
alone. Recalculation may reopen a warning when the underlying schedule changes.

## Export

```text
GET /api/v1/shift-assignment/export.php
```

Export remains a read action requiring `shifts.export` plus the same data scope
as the assignments API. GET does not require CSRF. Date/filter validation,
headers, filename, CSV escaping, UTF-8 behavior, and sensitive-field
allowlisting require contract tests before replacing the legacy export.

## Audit And Observability

Every mutation audit must include, where supported:

- actor user ID;
- safe actor display/email reference only under the existing audit policy;
- action type;
- assignment/template/warning target;
- before snapshot for update/delete/status actions;
- after snapshot for create/update actions;
- timestamp;
- API request ID;
- safe IP/user-agent metadata if the existing logger supports it;
- success/failure outcome where appropriate.

Never log session IDs, CSRF tokens, passwords, 2FA secrets, raw request headers,
or database credentials. Audit failure policy must be explicit: security- or
compliance-critical writes should fail closed rather than report success
without the required audit.

## React Write Behavior

The implemented and tested create and update contracts are active only in the
controlled preview. Edit visibility comes from the exact-Super-Admin plus
explicit-`shifts.manage` server capability. Delete/Template/Copy controls
remain absent, and the pilot banner continues to identify the legacy page as
production authority.

The edit modal uses only safe fields returned by the read API. It does not
invent or overwrite notes, role, division, overtime, source, creator, or
approver fields. Only changed fields are sent. Custom assignments represented
with inherited template ID `0` are normalized to no template.

Future forms must:

- derive visibility from permission metadata but rely on PHP enforcement;
- show a saving state and prevent duplicate submission;
- send the context-provided CSRF header;
- keep the modal open on failure;
- map field errors and focus the first invalid control;
- show success toast only after confirmed API success;
- keep modal-contained toast width within the modal;
- warn about unsaved changes when dirty;
- avoid optimistic mutation until rollback semantics are proven;
- refresh the read assignments contract after success;
- handle `401`, `403`, `409`, and `422` distinctly.

Phase 20 consolidates that handling in a shared frontend mutation utility. Both
Create and Edit retain backend authority, keep failed forms open, focus the
first invalid control, disable the full form during save, and distinguish a
successful mutation followed by a failed refresh. No endpoint or permission
contract changes in this phase.

## Implementation Order And Gates

Recommended future batches:

1. Permission compatibility decision and disposable-database fixtures.
2. Create assignment endpoint with CSRF, scope, transaction, audit, and tests.
3. Disabled internal React create form, then limited activation.
4. Update assignment endpoint with concurrency protection.
5. Status/confirm/resize parity.
6. Overtime and warning resolution.
7. Template preview/confirm and copy.
8. Export parity.
9. Delete only after a separately approved retention design.
10. Bulk update only after single-record production evidence.

Each endpoint is implemented and reviewed alone. It needs unit/contract,
integration, role/scope, CSRF, audit, idempotency, rollback, and manual browser
evidence before its React control becomes active.

Phase 21 implements DELETE on the existing single-assignment route. The schema
has no soft-delete field, so it uses a hard delete inside a transaction after
writing a before snapshot. The assignment audit foreign key becomes `NULL` on
delete while retaining the snapshot; the API activity audit retains the target
ID. The delete fails closed and rolls back if required assignment-audit storage
is unavailable. Template/monthly-template assignments are protected with
`409`, and linked warning rows are cleaned up. React Delete UI remains blocked.

## Dependent Record Delete/Restore Safety Matrix

Phase 24 extends the required delete audit's top-level assignment snapshot with
a reserved `_dependents` object. Existing assignment fields remain unchanged,
so the Phase 23 exact-restore mapping stays compatible.

| Dependency | Delete behavior | Class | Evidence and recommendation |
| --- | --- | --- | --- |
| `shift_warnings` | Explicitly deleted by service | B. Safe restorable | Full rows, including resolution state and IDs, restored in `tracs_phase24_test` |
| `holiday_coverage_assignments` | FK `ON DELETE CASCADE` | B. Safe restorable | Full row captured before cascade and restored after assignment |
| `assignment_audit_logs` | FK `ON DELETE SET NULL` | A. Safe retained | Delete snapshot survives with assignment ID in JSON |
| `tracs_user_activity_logs` | Logical target ID | A. Safe retained | Delete/restore events remain queryable |
| Notifications, triggers, logs | Logical module/entity ID | A. Safe retained | Disposable drill verified notification count unchanged |
| `shift_monthly_template_items` | FK `ON DELETE SET NULL` | D. Must block | Service rejects any live template-item link, including inconsistent source flags |
| Assignment notes, overtime/holiday flags, approval/source data | Assignment columns | B. Safe restorable | Phase 23 restores every assignment column |
| Workload, holiday, jumpshift, weekly outputs | Computed | C. Safe to regenerate | Scoped GET recomputes after restoration |
| Holidays, availability, workload settings | No assignment child relation | A. Safe retained | No assignment FK or delete query |
| Reminder/task tables with `assignment_id` | Task Management assignment IDs | E. Not applicable | Model creates and consumes `tracs_task_assignments.id` |
| Shift reports, attachments/comments, Dashboard/Ops/Calendar reads | No child record found | E / C | No persisted dependency; reads recompute |

The DELETE transaction still fails before mutation when required audit storage
is unavailable. No schema change or production restore endpoint is introduced.

### Phase 24 Drill Result

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 \
php tests/shift-assignment-dependent-restore-drill.php
```

The drill exactly restored a resolved warning and cascaded holiday coverage
row, including IDs, timestamps, text, and status. Notifications and audit rows
were retained, scoped GET returned one restored assignment, no duplicate child
rows remained, and `tracs_phase24_test` was dropped.

The backend dependency gate permitted the separately approved Phase 25
controlled React Delete UI pilot.

### Phase 25 Controlled Delete UI Result

Delete is visible only when the context returns
`allowed_actions.delete_assignment=true`, requiring exact `super_admin` plus
explicit `shifts.manage`; the page still requires `shifts.view`. React sends
the in-memory CSRF token to the existing DELETE endpoint.

The modal shows agent, date, shift/time, type, division, status, and dependency
context. Submit stays disabled until exact `DELETE`. It discloses hard-delete
risk, does not optimistically remove the row, remains open on failure, handles
template-link `409`, and refreshes the current query after success.

Authenticated disposable-browser validation on June 16, 2026 used
`tracs_phase25_test`. Create/Edit remained functional; cancel retained the
assignment; exact confirmation deleted it and refreshed to zero records. A
manual-source assignment with a live template-item link returned safe `409`
and remained present. Audits and dependent snapshot keys were verified, no
console errors appeared, and the temporary app/database were removed.

This permits only the controlled direct-URL pilot. Production navigation,
legacy replacement, template/copy actions, and production hard deletion remain
unapproved.

### Phase 26 Delete Pilot Hardening Result

The pilot now states that audit-backed restoration is a controlled manual
recovery procedure rather than an instant undo. The modal includes assignment
ID and all safe identity fields available from the read contract, announces
confirmation state accessibly, and requires byte-exact `DELETE` without
trimming or case normalization.

Disposable API and browser gates passed Shift 1 to Shift 2 to Shift 3,
overlap rejection, hard delete, exact dependent restoration, protected
template-link `409`, post-delete Create/Edit, permission hiding, audit
retention, GET visibility, and duplicate prevention. This does not approve
template generation, copy/paste, production navigation, or replacement.

## Data And Rollback Safety

- No write test may run against production data.
- Use a disposable MySQL database and fixture users/schedules.
- Back up affected tables before an approved production pilot.
- Prefer transactions for linked assignment/holiday/template writes.
- Bulk operations require preview and explicit confirmation.
- Destructive behavior prefers soft delete only when the existing schema and
  restoration contract support it.
- Every future migration includes `up.sql`, `down.sql`, verification queries,
  backup instructions, and tested rollback.
- Rollout remains role-gated or feature-flagged with the legacy page available.
