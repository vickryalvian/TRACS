# TRACS - MoM (Minutes of Meeting)

MoM converts scheduled meetings, decisions, notes, and action items into operational records linked to reminders, cases, ticker events, ops-status windows, screenshots, and history.

## Current Role

MoM remains a dedicated page at `mom.php`. It has not been replaced by Task Monitoring.

- The MoM page manages meeting schedule and lifecycle.
- Meeting and action reminders use `tracs_reminders`.
- Those reminders can appear in the dashboard Reminder List inside `Checklist and Reminder`.
- The standalone `reminders.php` page remains the complete reminder list.
- Weekly case suggestions help prepare unresolved operational topics.

## Implementation Status

| Area | Status | Current behavior |
| --- | --- | --- |
| Meeting creation/schedule | Implemented | Title, type, objective, participants, meeting time, and URL. |
| Lifecycle | Implemented | `upcoming`, `ongoing`, `completed`, `cancelled`; due meetings may auto-start in controller flow. |
| Agenda and notes | Implemented | Agenda status, typed notes, add/delete flows. |
| Decisions | Implemented | Structured decision records. |
| Action items | Implemented | Priority, free-text assignee, due date, status, reminder/case links. |
| Reminder integration | Implemented | Meeting schedule and action items can create reminders. |
| Ops/ticker integration | Implemented | Schedule/start/complete/cancel updates operational signals. |
| Case integration | Implemented | Link/unlink cases, suggest cases, create cases from action flow where invoked. |
| Screenshots | Implemented | Upload metadata, lightbox, and delete flow. |
| History/export | Implemented | Completed/cancelled history and CSV export. |
| User-backed action assignee | Planned | Action ownership is still free-text rather than a user foreign key. |
| Unified MoM API style | In Progress | Current `api_mom.php` and legacy `mom-action.php` response styles coexist. |

## Main Files

| File | Purpose |
| --- | --- |
| `public/mom.php` | Integrated MoM workspace. |
| `public/modules/mom/controller.php` | Main controller. |
| `modules/mom/controller.php` | Bridge include used by the integrated app. |
| `public/api/api_mom.php` | Current JSON API. |
| `public/api/mom-action.php` | Legacy/compatibility API. |
| `public/assets/mom-styles.css` | MoM styling. |
| `public/assets/mom-functions.js` | MoM browser behavior. |
| `config/schema/moms.sql` | Focused schema reference. |
| `config/install.sql` | Fresh-install schema. |

## Data Model

| Table | Purpose |
| --- | --- |
| `tracs_moms` | Meeting header, schedule, lifecycle, linked reminder and ops status. |
| `tracs_mom_agenda` | Agenda items. |
| `tracs_mom_notes` | Discussion notes, insights, risks, and note-based decisions. |
| `tracs_mom_decisions` | Structured decisions. |
| `tracs_mom_actions` | Action items with free-text assignee, due date, priority, and links. |
| `tracs_mom_case_links` | Meeting-to-case links. |
| `tracs_mom_screenshots` | Screenshot metadata. |
| `tracs_mom_audit_log` | MoM-specific audit trail. |

## Operational Flow

1. Create a meeting with schedule, URL, objective, participants, and optional case suggestions.
2. TRACS stores the meeting as `upcoming`.
3. The controller creates a scheduled reminder, ops-status row, and ticker event when available.
4. At meeting time or manual start, status becomes `ongoing` and the scheduled reminder is completed.
5. Add agenda items, notes, decisions, actions, case links, and screenshots.
6. Convert actions to reminders or cases where the UI/API path supports it.
7. Complete or cancel the meeting; linked operational signals are updated.
8. Review the meeting in history or export it.

## Meeting Types

| Type | Intended use |
| --- | --- |
| `weekly` | Operations review with unresolved case suggestions. |
| `training` | SOP, incident review, or internal learning. |
| `coordination` | Cross-team coordination. |
| `urgent` | Escalation or high-risk event. |

## Setup

Fresh installs use `config/install.sql`. Existing installations should back up the database and apply dated migrations in order.

Verify:

- `mom.php` loads without a schema warning.
- Meeting create/start/complete/cancel works.
- Meeting reminders appear in `reminders.php` and the dashboard Reminder List when active.
- Agenda, note, decision, and action forms save.
- Case linking and action-to-case flow work.
- Screenshot upload directory is writable and scripts cannot execute there.
- CSV export respects permissions.

## Current Follow-Up

- Replace stale installer messages that reference removed standalone MoM schema files.
- Decide whether action assignees should link to `tracs_users`.
- Consolidate legacy and current API response shapes after caller review.
- Define screenshot retention and backup policy.
- Add stronger audit/evidence exports for management review.

## Legacy Documentation

Files under `MOM README/` describe the original drop-in package and contain historical filenames, paths, status claims, and installation steps. Preserve them as historical reference. Use this file for the current integrated implementation.
