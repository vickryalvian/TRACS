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

Phase 30 did not implement the commit endpoint. Phase 32 implements it as a
controlled backend-only route because template commit is a bulk write.

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

## Phase 32 Commit Implementation

Phase 32 implements:

```text
POST /api/v1/shift-assignment/templates/commit.php
```

The endpoint requires authenticated session, CSRF, exact `super_admin`, and
explicit `shifts.manage`. If `shifts.template.commit` is seeded later, the
route requires that explicit permission too.

Current Phase 32 behavior:

- accepts `preview_payload` because preview storage/signing does not exist yet;
- never trusts preview items blindly and recomputes preview server-side;
- enforces exact `APPLY TEMPLATE`;
- supports only `conflict_policy = block`;
- performs final conflict re-check before creating assignments;
- blocks overlap/existing-assignment conflicts with `409`;
- creates assignments through the existing Shift Assignment service;
- stores created rows with `source=monthly_template`;
- captures created assignment IDs in the response and audit;
- returns rollback targeting as `created_assignment_ids`;
- adds no React commit UI and no copy endpoints.

Known schema limitation: there is still no `template_batch_id`. Rollback for
Phase 32 targets the created assignment IDs from response/audit. A future
first-class batch migration remains recommended before broader rollout.

Phase 32 disposable validation used `tracs_phase32_test` and verified Shift 1,
Shift 2, Shift 3 `16:00-24:00`, exact confirmation rejection, conflict `409`
with no created rows, GET visibility, audit created IDs, and rollback cleanup
that removed only committed assignment IDs.

## Copy Schedule Preview

```text
POST /api/v1/shift-assignment/templates/copy-preview.php
```

Purpose: generate a side-effect-free preview of copying existing assignments
from a source date range to a target date range. This route is not implemented
in Phase 38.

Required future request shape:

```json
{
  "source_start_date": "2026-07-01",
  "source_end_date": "2026-07-07",
  "target_start_date": "2026-08-01",
  "target_end_date": "2026-08-07",
  "scope": {
    "agent_ids": [],
    "role_ids": [],
    "division_ids": []
  },
  "options": {
    "include_holidays": true,
    "include_warnings": true,
    "strict_conflict_check": true
  }
}
```

Required response shape:

```json
{
  "success": true,
  "message": "Copy schedule preview generated.",
  "data": {
    "source_range": {},
    "target_range": {},
    "items": [],
    "summary": {
      "source_assignments": 0,
      "preview_assignments": 0,
      "agents": 0,
      "warnings": 0,
      "conflicts": 0,
      "blocked_items": 0
    },
    "warnings": [],
    "conflicts": [],
    "blocked_items": []
  },
  "errors": [],
  "meta": {
    "request_id": "..."
  }
}
```

This endpoint must be non-mutating. The target range must match the source
range length unless a future transform rule is explicitly approved and tested.

Date validation rules:

- `source_start_date`, `source_end_date`, `target_start_date`, and
  `target_end_date` are required.
- Source and target ranges must be valid ISO `YYYY-MM-DD` dates internally.
- UI display remains `dd-mm-yyyy`.
- Source and target range lengths must match unless a later transform policy is
  approved.
- Safe maximum range is 31 days by default, or 35 days only with explicit
  implementation evidence.
- Source and target must not be the exact same range.
- Source assignments must exist for the requested scope.
- Target real schedules must never be overwritten silently.

Source-to-target transformation rules:

1. Preserve the agent when still active and in scope.
2. Preserve shift type where the target shift definition is valid.
3. Preserve start/end time, including Shift 3 `16:00-24:00`.
4. Preserve role/division only when supported by the current schema and scope.
5. Preserve notes only when intentionally approved because notes may be stale.
6. Recalculate the assignment date by offset from `source_start_date` to
   `target_start_date`.
7. Recalculate day-of-week labels for the target date.
8. Recalculate holiday and overtime advisories for the target date.
9. Recalculate jumpshift/rest warnings against target-neighbor assignments.
10. Recalculate weekly hours for the target week.
11. Do not copy audit IDs.
12. Do not copy old assignment IDs.
13. Do not copy deleted/restored state.
14. Do not copy template batch metadata unless future schema supports it.
15. Do not mutate source assignments.

Non-mutating preview guarantee:

- no assignment inserts;
- no assignment updates;
- no assignment deletes;
- no monthly template rows;
- no monthly template item rows;
- no draft rows or draft status changes;
- no assignment audit state changes;
- no warning row changes;
- no holiday coverage row changes;
- no notification row changes;
- no source assignment mutation.

Copy-preview conflicts:

- target assignment overlap;
- target existing real assignment conflict;
- inactive or missing agent;
- missing shift definition;
- locked/protected target assignment;
- invalid source assignment;
- unsupported source/target range transform;
- unsafe template-owned target conflict.

Copy-preview warnings:

- holiday assignment advisory;
- overtime advisory;
- weekly hours above/below target;
- jumpshift/rest-time warning;
- source assignment notes may be stale;
- source assignment belongs to an old template batch if future schema supports
  batch identity.

Blocked items represent source assignments that cannot be safely transformed
into target preview rows. They must include enough safe details for the user to
understand the reason without exposing raw SQL, server paths, or stack traces.

Permissions and CSRF:

- requires an authenticated session;
- requires `X-CSRF-Token` because preview is a POST;
- requires exact `super_admin` during the pilot;
- requires explicit `shifts.manage`;
- should require future `shifts.template.copy_preview` after a reviewed
  permission migration;
- if `shifts.template.copy_preview` is not seeded, do not create it in
  Phase 38. Future permission seeding must include `up.sql` and `down.sql`.

Future React Copy Preview UI flow:

1. Button label: `Copy Schedule Preview`.
2. Visible only to exact `super_admin` plus `shifts.manage` during the pilot.
3. Step 1 selects source range, target range, and optional scope/filter.
4. Step 2 shows source summary, target preview rows, warnings, conflicts, and
   blocked items.
5. The modal must say: `Preview only - this will not create or modify assignments.`
6. No commit/apply copy button is allowed until copy-commit exists and passes a
   separate disposable/browser validation phase.

Future copy-commit relationship:

- copy-commit must be a separate endpoint;
- it must require a later exact confirmation phrase such as `APPLY COPY`;
- it must revalidate source and target server-side;
- it must re-check conflicts immediately before writing;
- it must audit created IDs and support rollback targeting;
- it must not be implemented in Phase 38.

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

## Phase 31 Disposable DB Gate

Before `templates/commit.php` is implemented, the disposable DB validation
environment must pass:

- `php tests/disposable-db-preflight.php` with `TRACS_ENV=test` and
  `TRACS_ALLOW_MUTATION_TESTS=1`;
- exact delete restoration drill;
- dependent restoration drill;
- non-mutating template preview integration;
- Phase 30 commit contract gate.

If Docker is unavailable, a local MySQL fallback may be used only with explicit
non-production connection variables, a safely marked disposable target
database, and the same cleanup guarantees. Commit remains blocked while this
gate is red.

## Implementation Gate

Before any commit or copy endpoint is implemented:

- prove no production data is touched;
- decide whether preview state is persisted or signed;
- decide audit storage for parent template actions;
- confirm rollback evidence for generated assignments and dependents;
- run disposable database integration before exposing any React UI.

## Phase 32 Template Commit API

`POST /api/v1/shift-assignment/templates/commit.php` is implemented as a
backend-only controlled bulk-write endpoint. It requires authenticated session,
CSRF, exact Super Admin, explicit `shifts.manage`, and exact
`APPLY TEMPLATE`. If `shifts.template.commit` is seeded later, the route also
requires that granular permission.

The endpoint accepts `preview_payload`, not trusted preview rows. It recomputes
the preview server-side, revalidates agents, dates, shifts, warnings, and
conflicts, and supports only `conflict_policy=block`. Any blocking conflict
returns `409` and writes no template assignments.

Because the current schema has no `template_batch_id`, rollback targeting is
based on the created assignment ids returned in the response and written to
`tracs_user_activity_logs`. React commit UI remains blocked until those ids are
also proven through authenticated disposable-browser validation.

## Phase 33 Commit Hardening Evidence

Phase 33 strengthens the disposable validation around `templates/commit.php`:

- exact confirmation rejects lowercase, case variants, leading/trailing
  spaces, double spaces, and `APPLY-TEMPLATE`;
- unsupported conflict policies such as `overwrite` return `422`;
- preview remains non-mutating after the commit endpoint exists;
- a race drill previews a valid payload, inserts a conflicting assignment
  before commit, then confirms commit returns `409` and creates no template
  rows;
- rollback targeting deletes only returned `created_assignment_ids` and keeps
  unrelated baseline assignments intact;
- commit audit includes generated count, created ids, and rollback ids;
- test-only rollback cleanup audit records the ids used by the disposable
  cleanup path.

These checks do not add React commit UI, copy endpoints, schema changes,
Calendar changes, navigation changes, or legacy Shift Assignment changes.
The `template_batch_id` schema limitation remains documented for future bulk
rollback ergonomics.

## Phase 34 React Commit UI Safety Gate

No active Apply Template UI is implemented in Phase 34. The existing Template
Preview UI remains preview-only, and React must not call
`/api/v1/shift-assignment/templates/commit.php`.

The future modal or wizard flow is:

1. Step 1 - Configure Preview: choose date range, pattern, scoped agents, and
   Shift 1/2/3 presets, then generate preview only.
2. Step 2 - Review Preview: show summary, preview rows, warnings, conflicts,
   and `blocked_items`. Commit is unavailable when conflicts or blocked items
   exist.
3. Step 3 - Commit Review: show assignment count, affected range, affected
   agents, accepted advisory warnings, zero-conflict status, rollback evidence,
   and require exact typed confirmation `APPLY TEMPLATE`.
4. Step 4 - Commit Result: show created assignment count, created assignment
   IDs or safe rollback reference, audit evidence, and a clear note that
   rollback is manual/admin-controlled.

Future confirmation UX must reject lowercase, case variations, leading or
trailing spaces, double spaces, punctuation variants, and any text other than
exact `APPLY TEMPLATE`. The commit control must remain disabled until the
phrase is exact, must show `Applying...` while in flight, must prevent double
submit, and must keep the modal open on errors.

Future commit must be unavailable when:

- preview has not succeeded;
- preview has conflicts;
- preview has blocked_items;
- preview result is stale;
- required permissions or CSRF token are missing;
- the user is not exact Super Admin during pilot;
- the confirmation phrase is not exact;
- the commit endpoint returns `409`;
- the API client detects an unsafe or malformed response.

Future `commitTemplatePreview(payload)` behavior is documented only. It will
POST to `/api/v1/shift-assignment/templates/commit.php`, send CSRF, handle
success, `401`, `403`, `405`, `409`, `422`, network errors, and unexpected safe
errors, and never trust client preview rows as authoritative. Success must
refresh assignments and display created assignment IDs or rollback evidence.

Criteria before Phase 35 active React Apply Template UI pilot:

- keep exact Super Admin plus `shifts.manage`;
- keep `conflict_policy=block`;
- run authenticated disposable-browser validation;
- prove no copy/paste endpoints or UI are introduced;
- show rollback evidence after success without exposing sensitive details.

## Phase 35 Apply Template UI Pilot

The React preview now includes a controlled Apply Template step inside the
existing Template Preview modal. It remains direct-preview only: users must
generate a successful non-mutating preview first, then review summary,
warnings, conflicts, blocked items, and rollback evidence before applying.

Apply is visible only in the authenticated preview pilot and is usable only
when the Shift Assignment context grants `allowed_actions.apply_template`.
During this pilot that maps to exact Super Admin plus explicit `shifts.manage`;
the backend commit route still re-enforces auth, CSRF, exact role, permission,
confirmation, and final conflict checks.

The UI disables Apply when preview is absent, loading, stale, missing CSRF,
missing permission, has conflicts, has `blocked_items`, or the typed
confirmation is not exactly `APPLY TEMPLATE`. Lowercase, leading/trailing
spaces, double spaces, punctuation variants, and case variations stay disabled
client-side and remain rejected server-side.

On backend `409`, the modal stays open, displays returned conflicts and blocked
items, marks the preview stale, and requires regeneration. On success, the
modal displays created count, request id, created assignment IDs or rollback
reference, and refreshes the current assignments request without optimistic
rows. There is no rollback button and no copy/paste UI in this phase.

## Phase 36 Apply Template UI Hardening

Phase 36 keeps the same backend route and hardens the React Apply Template UI
states. The confirmation field now has an explicit accessible label and help
text, stale/error states announce with alert semantics, and the Apply button is
linked to its disabled reason when the preview is stale, conflicted, missing
CSRF/capability, or lacks exact `APPLY TEMPLATE`.

Disposable API/workflow validation still passes for preview, commit,
rollback targeting, and race-conflict checks. Live authenticated browser
click-through could not be completed in this environment because the in-app
browser control failed before page navigation with a tool metadata error and
standalone Playwright is not installed. This is a blocker for copy-preview and
copy-commit phases; do not proceed until authenticated browser evidence exists.
Phase 36 is blocked by browser tooling for live authenticated click-through.

## Phase 37 Authenticated Browser Validation Gate

Phase 37 restores live browser evidence using a dev-only Playwright/Chrome
path because the in-app browser still fails before navigation with missing
`sandboxPolicy` metadata. The browser command is:

```bash
TRACS_ENV=test TRACS_ALLOW_MUTATION_TESTS=1 TRACS_TEST_DB_NAME=tracs_phase37_test npm run test:e2e:shift-template-apply --prefix frontend
```

The command uses `public/__test/shift-assignment-auth-session.php`, which only
establishes a session under `TRACS_ENV=test`,
`TRACS_ALLOW_MUTATION_TESTS=1`, and a disposable-safe database name. It does
not weaken production authentication.

The authenticated browser click-through against `tracs_phase37_test` validates
Template Preview, exact `APPLY TEMPLATE` confirmation, commit success,
created count, request id, rollback ids/reference, assignment refresh,
`shift_assignment.template.commit` audit evidence, rollback targeting,
unrelated-assignment retention, conflict-disabled Apply behavior, no copy
endpoint calls, no copy/paste UI, no rollback UI, and clean console/network
capture. The live browser pass also found a legacy unsaved-change overlay
intercept; the Template Preview form now uses `data-unsaved-ignore` because
the React modal owns dirty-form protection.

With Phase 37 passing, Phase 38 copy-preview may proceed from the
authenticated browser-validation gate, but copy-preview and copy-commit still
require their own explicit phase approval, contracts, disposable evidence, and
no production navigation exposure.

## Phase 39 Copy Schedule Preview API

Phase 39 implements the non-mutating Copy Schedule Preview API:

```text
POST /api/v1/shift-assignment/templates/copy-preview.php
```

The public wrapper is:

```text
public/api/v1/shift-assignment/templates/copy-preview.php
```

Request and response schemas remain the Phase 38 contract. The endpoint
requires an authenticated session, CSRF, exact `super_admin`, and
`shifts.manage` during the pilot. If `shifts.template.copy_preview` is seeded
later, the route also requires that permission. No migration is added in Phase
39.

Implementation behavior:

- validates `source_start_date`, `source_end_date`, `target_start_date`, and
  `target_end_date`;
- rejects invalid dates, same source/target range, mismatched range lengths,
  ranges above 35 days, unsupported `role_ids`, and invalid scope arrays;
- copies source assignments into in-memory preview rows only;
- preserves active agent, shift type, shift template, start/end time, Shift 3
  `16:00-24:00`, division, break minutes, duration, and safe source
  references;
- computes target dates by offset from source start to target start;
- recalculates target day-of-week labels, holiday/overtime advisories,
  jumpshift/rest warnings, weekly-hour warnings, and target overlap conflicts;
- returns `source_range`, `target_range`, `items`, `summary`, `warnings`,
  `conflicts`, and `blocked_items`;
- uses negative preview IDs and separate `source_assignment_id` references so
  source assignment IDs are never reused as target IDs.

The endpoint is side-effect free. Phase 39 tests prove the route does not
change persisted counts for assignments, warnings, holiday coverage, monthly
templates, monthly template items, assignment audit logs, or activity logs.
It does not create commit-style audit rows.

Still absent after Phase 39:

- `templates/copy-commit.php`;
- React copy/paste UI;
- active Copy Schedule button;
- rollback UI;
- schema changes;
- Calendar, legacy Shift Assignment, or production navigation changes.

Criteria before React Copy Preview UI:

- keep copy-preview response stable through another frontend contract pass;
- add a controlled preview-only React modal with no copy-commit/apply button;
- rerun authenticated browser validation and confirm no copy-commit calls.

Criteria before copy-commit:

- define exact `APPLY COPY` confirmation in an implementation phase;
- revalidate source/target server-side at commit time;
- re-check target conflicts immediately before writing;
- audit created assignment IDs;
- prove rollback targeting in disposable DB;
- complete authenticated browser validation after UI activation.

## Phase 40 Copy Schedule Preview UI

Phase 40 adds the controlled React Copy Schedule Preview UI inside
`shift-assignment-react-preview.php` only. It calls the existing non-mutating
route:

```text
POST /api/v1/shift-assignment/templates/copy-preview.php
```

The UI is gated by the server-issued `allowed_actions.copy_preview` capability,
which remains exact `super_admin` plus `shifts.manage` during the pilot. The
request sends the documented CSRF header and uses `dd-mm-yyyy` display dates
converted to ISO payload dates.

The modal collects `source_start_date`, `source_end_date`,
`target_start_date`, and `target_end_date`. Frontend validation blocks invalid
dates, same source/target range, mismatched range length, and ranges above 35
days before the API call where practical. Backend validation remains
authoritative.

The result view shows source range, target range, summary, preview items,
warnings, conflicts, and blocked items. It repeats:
`Preview only - this will not create or modify assignments.`

Strict Phase 40 limits:

- no `templates/copy-commit.php`;
- no `APPLY COPY`, Apply Copy, Commit Copy, Paste Schedule, or copied-schedule
  save/generate control;
- no rollback UI;
- no optimistic assignment insertion;
- no assignment refresh caused by copy preview;
- no schema, Calendar, legacy page, or production navigation change.

Criteria before copy-commit remain unchanged: exact `APPLY COPY`,
server-side source/target revalidation, final conflict re-check, audit of
created assignment IDs, rollback targeting, disposable DB evidence, and
authenticated browser evidence after any copy-apply UI activation.

## Phase 41 Copy Preview UI Hardening

Phase 41 keeps the copy workflow preview-only and hardens the React modal:

- date inputs expose help/error wiring with `aria-invalid` and inline
  `role="alert"` messages;
- client-side validation covers missing dates, same source/target range,
  mismatched range length, and ranges above 35 days;
- changing date options after a successful preview marks the displayed result
  stale and tells the user to generate a new copy preview;
- API validation, authorization, session, method, and network errors remain
  safe modal/toast messages;
- browser regression validates source/target rendering, Shift 1/2/3 preview
  including `16:00-24:00`, conflict/blocked display, no persisted count
  changes, no `copy-commit.php` call, and no Apply Copy/Commit Copy/Paste
  Schedule UI.

Phase 41 adds no copy-commit endpoint, no copy mutation UI, no rollback UI, no
schema change, no Calendar change, no legacy page replacement, and no
production navigation exposure.

## Phase 42 Copy Commit Contract Gate

Phase 42 is documentation and safety gating only. It defines future Copy
Schedule Commit behavior and intentionally adds no endpoint, service,
repository, schema change, Apply Copy UI, Paste Schedule UI, rollback UI, or
production navigation exposure.

Future route, not implemented in Phase 42:

```text
POST /api/v1/shift-assignment/templates/copy-commit.php
```

### Commit Confirmation

Future Copy Commit must require the exact confirmation phrase:

```text
APPLY COPY
```

The phrase is case sensitive and whitespace sensitive. It must use exact match
only: no fuzzy match, no localization variant, and no alternate phrase.

Valid:

- `APPLY COPY`

Invalid examples:

- `apply copy`
- `Apply Copy`
- ` APPLY COPY`
- `APPLY COPY `
- `APPLY  COPY`
- `APPLY-COPY`
- `CONFIRM`

### Preview-To-Commit Integrity

Future Copy Commit must never trust browser preview results, never trust preview counts, never trust preview IDs, and never trust client-provided transformed assignment rows. It must recompute or revalidate the copy preview server-side from the submitted source range, target range, scope filters, conflict policy, and current permission state before any insert occurs.

Commit must re-check:

- source range;
- target range;
- source/target length match;
- scope filters;
- conflict policy;
- authenticated actor and permission state;
- source assignments still exist and remain eligible;
- target assignments still do not conflict.

Only `conflict_policy=block` is allowed until a later approved phase defines a
safer policy.

### Final Conflict Re-Check

Immediately before writing, future Copy Commit must rerun conflict detection,
blocked-item detection, and assignment validation. If conflicts or blocked
items exist, the endpoint must return `409`, create no assignments, write no
partial batch, and fail closed.

### Copy Batch Behavior And Schema Limitation

Future Copy Commit must be atomic and all-or-nothing. It must run inside a
transaction and roll back the entire batch on any validation, audit, or insert
failure. Partial copy batches are not allowed.

Current schema limitation: there is no `template_batch_id` or `copy_batch_id`.
Until a reviewed migration exists, rollback targeting must rely on
`created_assignment_ids` returned by the commit response and stored in audit.
This is acceptable only if disposable tests prove rollback deletes exactly
those IDs and leaves unrelated/baseline assignments untouched.

### Audit And Rollback Requirements

Future Copy Commit audit must include:

- request id;
- actor user id;
- action such as `shift_assignment.copy.commit`;
- source range;
- target range;
- scope filters;
- generated count;
- created assignment IDs;
- rollback targeting data;
- timestamp;
- success/failure and conflicts where applicable.

Audit must not include sensitive values, credentials, raw SQL, server paths, or
unredacted internal logs. If commit audit cannot be written, commit must fail
closed before creating assignments.

Rollback must target only the `created_assignment_ids` returned by commit and
recorded in audit. It must never affect unrelated assignments, baseline
records, existing real schedules, or source assignments. Rollback targeting
must be disposable-test verified before Apply Copy UI activation.

### Security And Future UI Requirements

Future Copy Commit must enforce authenticated session, CSRF, exact
`super_admin` during the pilot, `shifts.manage`, and future
`shifts.template.copy_commit` if a reviewed permission migration seeds it.
Backend validation remains authoritative.

Future Apply Copy UI must require a successful copy preview, zero conflicts,
zero blocked items, a non-stale preview, valid CSRF, allowed server-issued
capability, and exact `APPLY COPY`. It must display rollback reference after
success, but Phase 42 adds no rollback UI.

Non-goals for Phase 42:

- no copy-commit endpoint;
- no Apply Copy, Commit Copy, or Paste Schedule UI;
- no rollback UI;
- no copy mutation service or repository;
- no hidden feature flag that activates copy commit;
- no schema change;
- no Calendar, legacy Shift Assignment, or production navigation change.
