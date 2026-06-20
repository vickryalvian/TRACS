# Shift Assignment Template API Contract Plan

## Phase 27/28 Boundary

Phase 27 is planning only. It defines a preview-before-commit bulk scheduling
direction, but it does not add template endpoints, copy/paste endpoints, React
template UI, backend write routes, schema migrations, navigation links,
Calendar changes, or legacy page changes.

Phase 28 implements only the first endpoint:

```text
POST /api/v1/shift-assignment/templates/preview.php
```

The Phase 28 endpoint is side-effect free. It does not create template drafts,
change draft status, create assignments, write audit rows, or persist preview
state. Commit, copy-preview, copy-commit, and React template UI remain
unimplemented.

The existing Create/Edit/Delete pilot remains limited to the direct-URL React
preview and exact Super Admin plus `shifts.manage`. `public/calendar.php`
remains the visual reference implementation.

## Current Template Behavior

Legacy Shift Assignment template behavior currently lives behind
`public/api/shifting-assignment.php` and
`modules/shifting-assignment/ShiftingAssignmentService.php`.

Current legacy actions include:

- `copy_last_week`
- `preview_monthly_template`
- `save_monthly_template`
- `duplicate_monthly_template`
- `apply_monthly_template`
- `archive_monthly_template`

Important characterization: the legacy monthly preview is not reliably
side-effect free. When previewing an existing draft template, the service may
update `shift_monthly_templates.status` from `draft` to `previewed`. The future
v1 preview contracts below must therefore be new non-mutating preview contracts,
not thin wrappers around the legacy preview action.

## Schema Investigation

| Table / field | Current role | Phase 27 finding |
| --- | --- | --- |
| `shift_assignments.source` | Records assignment provenance | Already supports `manual`, `monthly_template`, `copy`, and `replacement`. |
| `shift_assignments.monthly_template_id` | Links generated assignments to a monthly template | Present and indexed; useful for template ownership and rollback targeting. |
| `shift_monthly_templates` | Stores monthly template metadata | Present with `draft`, `previewed`, `applied`, and `archived` statuses. |
| `shift_monthly_template_items` | Stores generated template rows | Present with `generated_assignment_id`, which links committed template rows to live assignments. |
| `shift_templates` | Stores reusable shift patterns | Present and active-state aware. |
| `shift_warnings` | Stores warning state | Present; warnings may also be recalculated from assignment data. |
| `holiday_coverage_assignments` | Links holiday coverage to assignments | Present and cascades from assignment deletes. |
| `assignment_audit_logs.action` | Assignment-level audit enum | Currently supports `template_applied` but not the proposed granular template preview/copy action names. |
| `tracs_user_activity_logs` | General activity audit | Available for broader activity summaries where assignment enum changes are not yet approved. |

No schema migration is made in Phase 27. Future granular permissions or audit
action expansion must include reviewed `up.sql` and `down.sql`.

## Future Permissions

Target permissions:

- `shifts.view`
- `shifts.manage`
- `shifts.template.preview`
- `shifts.template.commit`
- `shifts.template.copy_preview`
- `shifts.template.copy_commit`

These granular template permissions are not seeded yet. Until an approved
permission migration exists, any future implementation must remain behind
exact `super_admin` plus explicit `shifts.manage` and must document that
compatibility gate in the route contract.

## Required API Envelope

Every future template response must use the standard TRACS v1 envelope:

```json
{
  "success": true,
  "message": "Request completed successfully.",
  "data": {},
  "errors": [],
  "meta": {
    "request_id": "..."
  }
}
```

Validation errors must use field-level objects:

```json
{
  "success": false,
  "message": "Validation failed.",
  "data": {},
  "errors": [
    {
      "field": "start_date",
      "message": "Start date is required."
    }
  ],
  "meta": {
    "request_id": "..."
  }
}
```

## Template Generation Preview

```text
POST /api/v1/shift-assignment/templates/preview.php
```

Purpose: generate a non-mutating preview of assignments that would be created
for a selected date range or month.

Phase 28 implementation accepts a `weekly_rotation` pattern with explicit
`date` items or `day_of_week` items and selected in-scope agents. It returns
in-memory preview items, overlap conflicts, blocked items, advisory warnings,
holiday/overtime flags, and workload-derived warnings. It requires
authentication, mutation CSRF, exact `super_admin`, and explicit
`shifts.manage`.

Required security:

- authenticated active session;
- completed 2FA where applicable;
- CSRF via `X-CSRF-Token`;
- future `shifts.template.preview`, or exact `super_admin` plus `shifts.manage`
  until that permission exists;
- server-side division, agent, and object scope.

Required request fields:

- `start_date`
- `end_date`
- `pattern_source`
- selected agents or a scoped group/filter selection;
- shift pattern details or selected template pattern.

The endpoint must not insert, update, delete, archive, apply, or mark a draft
as previewed. If the implementation needs to reuse legacy monthly-template
logic, it must call only pure generation/calculation helpers or first extract a
side-effect-free service path.

Preview response data should include:

```json
{
  "preview_id": "optional-if-supported-later",
  "range": {},
  "items": [],
  "summary": {
    "total_assignments": 0,
    "agents": 0,
    "warnings": 0,
    "conflicts": 0
  },
  "warnings": [],
  "conflicts": [],
  "blocked_items": []
}
```

## Template Generation Commit

```text
POST /api/v1/shift-assignment/templates/commit.php
```

Purpose: commit a previously reviewed template preview.

Required request fields:

- `preview_id` or signed preview payload;
- optional `preview_payload` only if a signed payload approach is approved;
- exact typed confirmation phrase: `APPLY TEMPLATE`;
- `options.conflict_policy`, initially `block`;
- `options.allow_warnings`, initially `false`;
- CSRF token.

Required request shape:

```json
{
  "preview_id": "optional-if-supported",
  "preview_payload": {},
  "confirmation": "APPLY TEMPLATE",
  "options": {
    "conflict_policy": "block",
    "allow_warnings": false
  }
}
```

## Phase 30 Commit Safety Gate

Phase 30 does not implement the commit endpoint. It hardens the future
contract because template commit is a bulk write.

Preview-to-commit integrity requirements (`preview-to-commit integrity`):

- Never trust client preview items blindly.
- Commit must recompute or revalidate preview server-side.
- Commit must re-check authenticated session, exact `super_admin` plus
  explicit `shifts.manage`, future `shifts.template.commit` if migrated, and
  `X-CSRF-Token`.
- Commit must revalidate date range, selected agents, caller scope, shift
  templates, Shift 3 `16:00-24:00`, and all item fields.
- Commit must re-check overlaps, jumpshift/rest warnings, weekly hours,
  holiday/overtime warnings, inactive agents, and missing shifts immediately
  before writing.
- If a stored `preview_id` exists later, commit must verify preview freshness.
- If no stored preview exists, signed preview payload is only a transport hint;
  the server must still revalidate against current database state.
- If data changed after preview and conflicts now exist, commit must block.

Required confirmation behavior:

- User must type `APPLY TEMPLATE` exactly.
- Whitespace or case variations must be rejected.
- The UI must show assignment count, warnings, conflicts, and blocked items
  before enabling commit.
- Commit must remain disabled if blocking conflicts exist.

Default conflict policy:

- `conflict_policy = block`
- Existing assignment overlap blocks commit.
- Inactive or missing agent blocks commit.
- Missing shift blocks commit.
- Template-owned target conflict blocks commit until a safer policy is
  separately approved.
- Warnings such as holiday, jumpshift, overtime risk, or weekly-hour variance
  are advisory only when they do not conflict with real data and when
  `allow_warnings` is explicitly approved.

Commit must:

- revalidate the preview freshness and caller scope;
- re-check conflicts immediately before writing;
- write only assignment fields supported by the current schema;
- use `source=monthly_template` when template ownership is known;
- link `monthly_template_id` and `shift_monthly_template_items.generated_assignment_id`
  when the monthly-template tables are used;
- return created assignment IDs, skipped items, blocked items, warnings, and
  conflicts;
- write audit evidence before reporting success.

Future commit audit must include actor user id, `shift_assignment.template.commit`,
request ID, preview/reference ID if available, date range, selected agents,
generated assignment count, created assignment ids, warnings, conflicts,
skipped or blocked items, confirmation result, before/after summary,
rollback reference, timestamp, and success/failure.

Rollback and restoration requirements:

- Bulk commit must be reversible.
- Preferred strategy: monthly template ownership plus `source=monthly_template`,
  `monthly_template_id`, and `shift_monthly_template_items.generated_assignment_id`
  identify committed rows.
- Current schema does not contain `template_batch_id`; rollback can target a
  monthly template, but not an arbitrary preview batch unless the commit audit
  stores the full created assignment ID list.
- If no monthly template record is used, the commit audit must store every
  created assignment ID and enough after-snapshot data to reverse the batch.
- If neither ownership nor complete audit evidence is available, template
  commit must remain blocked.

Future migration recommendation only:

- add `template_batch_id` or `source_batch_id` if arbitrary preview commits
  need first-class rollback grouping;
- add `generated_by`, `generated_at`, `source_template_id`, and optional
  `rollback_status` only after review;
- include `up.sql` and `down.sql`;
- do not add this migration in Phase 30.

## Copy Schedule Preview

```text
POST /api/v1/shift-assignment/templates/copy-preview.php
```

Purpose: preview copying assignments from one date range to another.

Required request fields:

- `source_start_date`
- `source_end_date`
- `target_start_date`
- `target_end_date`
- selected agents, roles, or divisions if applicable.

This endpoint must be non-mutating. The target range must match the source
range length unless a future transform rule is explicitly approved and tested.

Preview must identify:

- assignments that would be copied;
- assignments that would be skipped;
- existing target conflicts;
- inactive or out-of-scope agents;
- jumpshift/rest warnings;
- weekly-hour warnings;
- holiday and overtime advisories.

## Copy Schedule Commit

```text
POST /api/v1/shift-assignment/templates/copy-commit.php
```

Purpose: commit a previously reviewed copy preview.

Required request fields:

- `preview_id` or signed preview payload;
- typed or tokenized confirmation;
- CSRF token.

Commit must re-run validation, block or skip conflicts according to the
approved policy, write copied rows with `source=copy` where supported, and log
created IDs plus skipped/blocked items.

## Validation Rules

Future preview and commit routes must validate:

- `start_date`, `end_date`, source dates, and target dates as backend-safe ISO
  dates; UI display remains `dd-mm-yyyy`;
- valid, ordered date ranges;
- a safe range maximum, initially 31 or 35 days unless explicitly approved;
- matching source and target lengths for copy unless transform logic is
  approved;
- active selected agents inside caller scope;
- active shift type and shift template references;
- strict `HH:MM` start and end times;
- Shift 3 `16:00-24:00` and cross-day midnight storage compatibility;
- no overlapping counted assignment;
- jumpshift/rest time below eight hours;
- weekly target, under-target, overtime-risk, and max-hour warnings;
- holiday and overtime indicators;
- inactive agents, protected records, and locked/template-owned rows;
- no silent overwrite of real assignments.

## Conflict Policy

Default policy: block conflicting writes.

Advisory warnings may be allowed only when they do not conflict with real data.
Existing real assignments must never be overwritten silently. Any future
conflict-resolution option must be a separate explicit contract with typed
confirmation, audit evidence, and disposable-browser validation.

## Audit Requirements

Future actions should be auditable as:

- `shift_assignment.template.preview`
- `shift_assignment.template.commit`
- `shift_assignment.template.copy_preview`
- `shift_assignment.template.copy_commit`

Each commit audit must include actor ID, request ID, source range, target
range, affected agents, created assignment IDs, skipped/blocked items,
warnings, conflicts, before/after summary, timestamp, and success/failure.

Current `assignment_audit_logs.action` does not contain those exact action
names. A future implementation must either use an approved general activity
audit for parent action summaries or include an `up.sql`/`down.sql` migration
to expand the enum. Phase 27 makes no migration.

## Rollback And Restoration

Preview needs no rollback because it must not mutate data.

Commit must store enough evidence to reverse created records. If committed
rows are linked through `source`, `monthly_template_id`, and
`generated_assignment_id`, rollback can target generated records without
touching real manual schedules.

Bulk rollback must not rely on casual hard delete. It needs a controlled,
audited restoration or reversal procedure, with before snapshots for generated
assignments and their dependent warning/holiday coverage rows. Future bulk
operations remain blocked from production until that drill passes on a
disposable database.

## Future React UI

The future React template workflow must be a wizard or modal flow:

1. Template Generator or Copy Schedule action appears only from server-issued
   capabilities.
2. Step 1 selects pattern/source and date range.
3. Step 2 previews generated/copied rows without writing.
4. Step 3 reviews warnings, conflicts, blocked rows, and weekly-hour results.
5. Step 4 requires explicit confirmation before commit.
6. Success refreshes the current assignments query.
7. Errors keep the modal open and use TRACS toast behavior.

There must be no direct bulk write button, no optimistic bulk mutation, and no
frontend-only permission reliance.

## Phase 28 Disposable Evidence

`tests/shift-assignment-template-preview-integration.php` validates the route
against `tracs_phase28_test`. It covers unauthenticated, missing CSRF, invalid
CSRF, non-Super-Admin, invalid payload, valid Shift 1/2/3 preview, overlap
conflict output, warning output, and no persisted table-count changes across:

- `shift_assignments`
- `shift_warnings`
- `holiday_coverage_assignments`
- `shift_monthly_templates`
- `shift_monthly_template_items`
- `assignment_audit_logs`

The disposable database is dropped after the run.

## Phase 29 React Preview UI Pilot

Phase 29 adds the first React UI surface for template generation, limited to
preview only. The Shift Assignment preview exposes "Preview Template" only when
the backend context grants `allowed_actions.preview_template` for exact Super
Admin plus `shifts.manage`.

The UI contract is:

- collect a bounded date range and simple supported weekly-rotation pattern;
- display dates as `dd-mm-yyyy` and submit backend-safe ISO dates;
- support Shift 1 `00:00-08:00`, Shift 2 `08:00-16:00`, and Shift 3
  `16:00-24:00`;
- send the existing CSRF token in the configured header;
- render returned preview items, summary, warnings, conflicts, and
  `blocked_items`;
- keep the modal open for validation, network, permission, and conflict
  errors;
- never show commit/apply/save/copy controls in this phase.

This UI is not evidence that commit is safe. Commit implementation still
requires a separate API contract, confirmation token/freshness decision,
rollback evidence, audit design, and disposable database validation.

## Implementation Gate

Before any commit or copy endpoint is implemented:

- prove no production data is touched;
- decide whether preview state is persisted or signed;
- decide audit storage for parent template actions;
- confirm rollback evidence for generated assignments and dependents;
- run disposable database integration before exposing any React UI.
