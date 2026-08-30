# TRACS Abuse Reports

## Purpose

Abuse Reports is the operational queue for receiving, triaging, investigating,
and closing abuse complaints. The canonical page is
`public/abuse-reports.php`; records use references such as
`TRACS-AR-000006`.

The module supports phishing, malware, spam, copyright, illegal-content,
general abuse-complaint, and other report types. It stores the report, audit
timeline, notes, evidence, SLA data, assignment, ticket metadata, nameserver
snapshots, and relationships to other TRACS records.

## Access Control

| Capability | Requirement |
| --- | --- |
| Open the page, list records, view details, and download evidence | `abuse_reports.view` |
| Create, update, assign, move, reorder, add notes, and upload evidence | `abuse_reports.manage` |
| Delete a report | Supervisor-tier role or above |

The delete endpoint is deliberately stricter than its bootstrap permission.
It checks `tracs_user_can_delete_abuse_reports()` before deletion, so a
misconfigured permission grant cannot let an Agent or Intern delete reports.
The sidebar item is visible only when the current user can view Abuse Reports.

All API routes use the shared API bootstrap for authenticated-session,
account-state, HTTP-method, permission, and CSRF enforcement. Never expose a
write endpoint outside that bootstrap.

## Workflow

Stored statuses are:

| Stored status | UI stage | Meaning |
| --- | --- | --- |
| `incoming` | Incoming | Newly received and awaiting triage. |
| `investigating` | Investigating | Active internal investigation. |
| `waiting_external` | Waiting External | Waiting for an external response. |
| `action_taken` | Action Required | Follow-up action is required. |
| `resolved` | Resolved | Operational work is resolved. |
| `closed` | Resolved | Final closed state; grouped with Resolved on the board. |

`action_required` is a UI-stage alias and normalizes to the stored
`action_taken` value.

Default SLA deadlines are calculated at creation when no explicit SLA is
provided:

| Priority | Default SLA |
| --- | --- |
| Critical | 4 hours |
| High | 8 hours |
| Medium | 24 hours |
| Low | 72 hours |

Waiting External supports a 24-hour or 48-hour waiting window. Open reports
become visually urgent when action is required, the waiting deadline is
overdue, or the SLA is exceeded. Resolved and Closed reports are not treated
as urgent.

## User Interface Contract

### Board view

- Columns represent Incoming, Investigating, Waiting External, Action Required,
  and Resolved.
- Authorized users can drag cards between columns; the server persists status
  and board order.
- On desktop, the board is constrained to the available workspace height and
  each column scrolls vertically without growing the whole page. Mobile keeps
  normal page scrolling and a bounded column list.
- Priority and status chips follow the compact Cases badge geometry, including
  severity dots and shared radius/typography tokens.
- Column headers use one count badge only. Cards and list rows default to newest
  activity first, falling back from last activity to updated and created time.
- Board cards may show explicit text actions such as **Investigate** or
  **Resolve**. These labels must remain visible because they change workflow
  state.
- A card click opens the lightweight preview. **Open record** or a card
  double-click opens the full editor.

### List view

- The report title is primary. The compact `#000006` reference and target are
  secondary metadata.
- Priority, Status, Assignee, and Reporter look like quiet text. Their native
  dropdown opens only after the value is clicked.
- Status is the only workflow-state control in a table row. Do not add
  unlabeled or icon-only status-transition actions to the Actions column.
- The Actions column contains only the pencil control labeled
  **Edit report details**. It expands the inline editor for Title, Type,
  Affected Domain, Reporter, and Notes.
- Priority, Status, Assignee, and Reporter changes save immediately through
  `abuse-report-update.php`; they are final saves, not drafts or autosaved
  previews.
- Clicking or changing an inline control must not open the record preview. The
  delegated handlers cancel any scheduled preview before saving.
- Clicking the non-interactive part of a row opens the lightweight preview;
  double-clicking opens the full record.
- Evidence and Actions headers and cells remain centered.

### Preview and full editor

- Preview contains the reference, title, type, priority, status, target, last
  activity, and SLA due time. It does not load the full tabbed editor.
- The full editor contains Overview, Timeline, Evidence, Notes, Relationships,
  and Actions tabs.
- Advanced starts collapsed and remembers its open/closed state in
  `sessionStorage` for the browser session.
- Empty optional fields remain hidden until the user selects the corresponding
  **Add field** control.
- Creating one report closes the editor after a successful save. Multiple
  records should be created with Bulk mode; there is no Save and Add Another
  flow.
- Saving an existing report refreshes the same full editor with server data.
- The delete button is shown only to users allowed to delete and requires
  confirmation. Deletion is permanent.

## Save and Unsaved-Change Behavior

The full editor uses the shared `TRACSUnsavedChanges` guard.

- Opening or reloading a report establishes the saved baseline.
- A successful save refreshes the form with the server response and marks the
  new values as saved.
- A failed save reports an error and leaves the edited form dirty.
- Closing a dirty full editor invokes the shared unsaved-changes confirmation.
- Reset reloads the stored record, or clears a new record back to defaults.
- Status quick actions are blocked while the full editor has unsaved changes.
- `saveInFlight` prevents duplicate full-form submissions while a request is
  active.
- Inline table saves do not participate in the full-editor dirty state; each
  changed value is sent immediately and the row is rerendered from the server
  response.

There is no local-storage or session-storage draft recovery for report content.
Only the Advanced accordion preference is stored for the session.

## API Surface

| Endpoint | Method | Permission | Purpose |
| --- | --- | --- | --- |
| `api/abuse-report-get.php` | GET or POST | `abuse_reports.view` | Fetch one report with timeline, notes, and evidence. |
| `api/abuse-report-create.php` | POST | `abuse_reports.manage` | Create one report and optional evidence. |
| `api/abuse-report-update.php` | POST | `abuse_reports.manage` | Partial or full report update. |
| `api/abuse-report-status.php` | POST | `abuse_reports.manage` | Explicit status update. |
| `api/abuse-report-reorder.php` | POST | `abuse_reports.manage` | Persist board ordering. |
| `api/abuse-report-note.php` | POST | `abuse_reports.manage` | Add a report note. |
| `api/abuse-report-evidence-upload.php` | POST | `abuse_reports.manage` | Upload one or more evidence files. |
| `api/abuse-report-evidence.php` | GET | `abuse_reports.view` | Permission-checked evidence download. |
| `api/abuse-report-delete.php` | POST | Supervisor or above | Permanently delete the report and child data. |

Create, update, status, evidence, and delete operations run inside database
transactions. API failures return JSON errors. The full editor preserves its
current edits and shows an error toast; failed inline updates rerender the
stored server state rather than assuming success.

## Evidence

Evidence is stored under `public/uploads/abuse_report_evidence/` with random
server filenames and database metadata. Direct directory access is denied;
downloads go through the permission-checked evidence endpoint.

- Maximum size: 10 MB per file.
- Supported categories: screenshot, email, header, log, document, image, other.
- Supported content includes JPEG, PNG, WebP, GIF, PDF, text, CSV, EML, ZIP,
  DOC, DOCX, and permitted binary evidence.
- MIME type is detected server-side. Image content is validated as readable.
- A failed multi-file upload rolls back database work and removes files already
  stored by that request.
- Permanent report deletion removes events, notes, evidence rows, the report,
  and stored evidence files.

Production must keep `public/uploads/abuse_report_evidence` writable by the PHP
runtime and blocked from direct web access.

## Data Model

| Table | Responsibility |
| --- | --- |
| `tracs_abuse_reports` | Main record, workflow, SLA, assignment, ticket, target, and relationship data. |
| `tracs_abuse_report_events` | Field changes and workflow audit timeline. |
| `tracs_abuse_report_notes` | User-authored notes and attribution. |
| `tracs_abuse_report_evidence` | Evidence metadata and storage references. |

New IDs receive a unique `TRACS-AR-` number padded to six digits. Important
record changes write timeline events. Creation, updates, status moves, evidence,
and deletion also integrate with the shared activity/ticker/notification
systems where applicable.

Fresh installs use `config/schema/abuse_reports.sql`. Existing installations
must run `config/migrations/2026_08_04_abuse_reports.sql` after taking a database
backup.

## Source Map

| Path | Responsibility |
| --- | --- |
| `public/abuse-reports.php` | Permission-gated page shell and server-provided datasets. |
| `public/assets/abuse-reports.js` | Board/list state, preview/editor behavior, saves, filters, and interactions. |
| `public/assets/abuse-reports.css` | Module-specific board, table, preview, and editor styles. |
| `modules/abuse-report/controller.php` | Thin controller over the model. |
| `modules/abuse-report/model.php` | Validation, persistence, formatting, audit events, and summaries. |
| `public/api/abuse-report-*.php` | Permission-checked API endpoints. |
| `config/schema/abuse_reports.sql` | Fresh-install schema. |
| `config/migrations/2026_08_04_abuse_reports.sql` | Existing-install migration and permissions. |
| `tests/abuse-report-preview-delete-flow.php` | Focused UI/API/delete contract checks. |

## Verification

Run the focused checks after changing the module:

```bash
php tests/abuse-report-preview-delete-flow.php
php tests/unsaved-changes-submit-flow.php
node --check public/assets/abuse-reports.js
```

Also complete the Abuse Reports section in
`docs/manual-smoke-checklist.md`. Test view-only, manage, and deletion roles;
network failure during save; rapid edits; browser back/refresh with dirty data;
and two sessions editing the same record. The module currently has no
optimistic-lock/version conflict detection, so last successful write wins when
two sessions edit the same record.

## Regression Invariants

- Table dropdowns appear only when their quiet value is clicked.
- Inline table edits never open the preview or full editor.
- Table workflow changes use only the Status control.
- The Actions column contains only Edit report details.
- Board workflow actions use explicit text, not ambiguous icons.
- Failed saves preserve dirty state and entered values.
- Successful saves clear dirty state only after the server responds.
- Evidence is never served directly from the upload directory.
- Delete remains restricted to Supervisor or above.
