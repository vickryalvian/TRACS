# TRACS — MoM (Minutes of Meeting)

MoM is the TRACS meeting operations module. It turns meeting schedules, discussions, decisions, and action items into operational records linked to reminders, cases, ticker events, ops-status windows, screenshots, and history/export.

## Current Status

| Area | Status | Notes |
| --- | --- | --- |
| Meeting creation | Implemented | Create title, type, objective, participants, meeting time, and meeting URL. |
| Meeting schedule | Implemented | `meeting_at` is stored on `tracs_moms`; scheduled meetings create reminders and ops-status entries. |
| Meeting lifecycle | Implemented | `upcoming`, `ongoing`, `completed`, `cancelled`; due meetings may auto-start in controller flow. |
| Agenda | Implemented | Add/update/delete agenda items and mark completed/skipped. |
| Discussion notes | Implemented | Add and delete notes with note types. |
| Decisions | Implemented | Add/delete decisions with owner/status fields in schema. |
| Action items | Implemented | Add/update/delete/complete action items with priority, assignee text, due date. |
| Reminder integration | Implemented | Meeting schedules and action items can create records in `tracs_reminders`. |
| Ops window integration | Implemented | Meeting schedule/start/complete/cancel updates `ops_status` when linked. |
| Ticker integration | Implemented | Meeting events can create ticker events. |
| Case links | Implemented | Link/unlink cases; linked cases can be updated from MoM flow. |
| Case creation from action | Implemented in controller/API flow | Creates operational cases from action items where current UI/API invokes it. |
| Screenshots | Implemented | Screenshot metadata is stored in `tracs_mom_screenshots`; UI supports upload/lightbox/delete. |
| Meeting history/export | Implemented | Recent completed/cancelled meetings and CSV export. |
| Case suggestions | Implemented | Weekly suggestions use unresolved/recent cases. |

## Files

| File | Purpose |
| --- | --- |
| [public/mom.php](/tracs/public/mom.php) | Main MoM page. |
| [public/assets/mom-styles.css](/tracs/public/assets/mom-styles.css) | MoM-specific styling. |
| [public/assets/mom-functions.js](/tracs/public/assets/mom-functions.js) | MoM frontend behavior. |
| [public/api/api_mom.php](/tracs/public/api/api_mom.php) | Current JSON API for MoM operations. |
| [public/api/mom-action.php](/tracs/public/api/mom-action.php) | Legacy/compatibility API shape. |
| [modules/mom/controller.php](/tracs/modules/mom/controller.php) | Bridge include. |
| [public/modules/mom/controller.php](/tracs/public/modules/mom/controller.php) | Main MoM controller. |
| [config/schema/moms.sql](/tracs/config/schema/moms.sql) | Focused MoM schema reference. |
| [config/install.sql](/tracs/config/install.sql) | Fresh-install schema containing MoM tables. |

## Database Tables

| Table | Purpose |
| --- | --- |
| `tracs_moms` | Meeting header, lifecycle, schedule, URL, summary, linked reminder/ops status. |
| `tracs_mom_agenda` | Agenda items. |
| `tracs_mom_notes` | Discussion notes, decisions-as-notes, insights, risks. |
| `tracs_mom_decisions` | Structured decisions. |
| `tracs_mom_actions` | Action items with assignee text, due date, priority, linked reminder/case. |
| `tracs_mom_case_links` | Many-to-many links between meetings and cases. |
| `tracs_mom_screenshots` | Screenshot attachment metadata. |
| `tracs_mom_audit_log` | MoM-specific audit trail. |

## Current MoM Flow

1. User opens `mom.php`.
2. If MoM tables are missing, the page shows an installation warning.
3. User creates a meeting with title, type, schedule, URL, objective, participants, and optional suggested case links.
4. Controller stores the meeting as `upcoming`.
5. Controller creates a scheduled reminder in `tracs_reminders`.
6. Controller creates an `ops_status` row and ticker event for operational visibility.
7. At meeting time or when manually started, status becomes `ongoing`; the scheduled reminder is completed.
8. During the meeting, users add agenda items, notes, decisions, actions, linked cases, and screenshots.
9. Action items can create reminders and/or operational cases.
10. Completion stores `completed_at`, deactivates/updates the ops-status row, completes the scheduled reminder, and emits ticker/activity records.
11. Completed/cancelled meetings appear in history and can be exported.

## Meeting Types

| Type | Intended use |
| --- | --- |
| `weekly` | Recurring operations review, usually with unresolved case suggestions. |
| `training` | SOP, incident review, and internal learning. |
| `coordination` | Cross-team or division coordination. |
| `urgent` | Escalation or high-risk operational event. |

## Reminder / Ticker / Ops Integration

- Meeting schedule creates a reminder titled `MOM: <title>`.
- Urgent meetings use higher priority/severity signals.
- Starting/completing/cancelling meetings updates linked reminder and ops-status rows when IDs are available.
- Ticker events are created for scheduled, started, completed, cancelled, and auto-started meetings.
- Action item reminders link back to `tracs_mom_actions.linked_reminder_id`.

## Case Integration

Implemented:

- Link existing cases to a meeting.
- Show related cases in the MoM context.
- Suggest unresolved cases for weekly meeting preparation.
- Create a case from an action item when the API/UI path is used.
- Update linked case status/notes from MoM flow when invoked.

Planned/improvement:

- Stronger case timeline showing MoM decisions/actions inside the case detail view.
- More explicit UI for “suggest case from selected discussion text”.
- Better two-way badges from case rows back to linked MoM records.

## Screenshots / Attachments

Implemented now:

- Screenshot upload UI and lightbox are present.
- Metadata is stored in `tracs_mom_screenshots`.
- Screenshots can be associated with general meeting context and displayed on the MoM page.

Operational note:

- Confirm upload directory and permissions during deployment. Keep uploads outside Git if production users add real evidence files.

## Setup

### Fresh install

Run [config/install.sql](/tracs/config/install.sql). It includes the MoM schema.

### Existing install

Back up the database, then apply current schema/migrations in chronological order. The focused reference is [config/schema/moms.sql](/tracs/config/schema/moms.sql), but production upgrades should follow migration discipline rather than manually pasting fragments into a live DB.

### Verify

- `/mom.php` loads without the missing-table warning.
- A meeting can be created with a meeting time.
- A scheduled reminder appears in reminders.
- Starting/completing/cancelling updates meeting lifecycle.
- Agenda, note, decision, and action forms save.
- Case linking works.
- Screenshot upload works in the deployment environment.
- CSV export works for authorized users.

## Implemented Now

- Meeting schedule and lifecycle.
- Reminder creation from meeting schedule and action item.
- Ops-status and ticker integration.
- Agenda, notes, decisions, actions.
- Meeting history and export.
- Linked cases and weekly suggestions.
- Screenshot UI/storage metadata.

## Planned / Improvement

- Consolidate `api_mom.php` and `mom-action.php` into one response style after confirming no callers depend on the legacy shape.
- Replace stale “run `config/mom_database_schema.sql`” error text with current installer/migration guidance.
- Add richer participant model if free-text participants become insufficient.
- Add stronger audit/evidence export for ISO 9001 management review.
- Add action assignment to actual TRACS users rather than only assignee text, if needed.
- Improve screenshot storage configuration and production retention rules.

## Legacy Notes

Older MoM documentation referred to `mom-migration.sql`, `modules/mom/mom.css`, `modules/mom/mom.js`, and `/uploads/mom/` as if MoM were a separate drop-in package. The current integrated TRACS repository uses `public/assets/mom-styles.css`, `public/assets/mom-functions.js`, `config/install.sql`, and the integrated `public/mom.php` flow. Keep old package docs under `MOM README/` as historical reference only.
