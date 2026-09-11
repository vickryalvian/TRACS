# Clients / Calendar rework

## Audit and architecture

`public/clients.php` is an authenticated PHP shell for `frontend/src/modules/clients/main.jsx`. The old React page combined a statistics strip, an attention list, an automatically selected inspection panel, and inline forms. Its API lives in `public/api/v1/client-portfolio/`, backed by `modules/client-portfolio/controller.php` and `model.php`.

The existing client model has clients, contacts, services, addons, renewal history, billing records, follow-ups, and activity logs. Follow-ups already reference `tracs_reminders` through `reminder_id`, but previously also read their copied title/date/status independently. That link is retained; no second reminder store is introduced.

`public/calendar.php` mounts `assets/react/calendar/CalendarApp.jsx`. `/api/calendar/events.php` calls `CalendarService`, which aggregates reminders, tasks, meetings, cases, shifts, holidays, manual schedules, and other existing sources. Manual schedules use `calendar_events`; client activities continue to use the existing reminder source rather than creating manual calendar copies.

Reused patterns:

- `public/domain-transfer.php`: compact filter toolbar, `panel`, `panel-head`, table and record count.
- `public/mom.php` and `public/assets/mom-functions.js`: one expanded preview row at a time, inline content beneath its source row, chevron and `aria-expanded` state.
- TRACS `modal-overlay`, `modal`, `modal-head`, `modal-body`, `modal-close`: shared React wrapper with focus containment, Escape, focus restoration, and viewport sizing.
- Calendar's `MonthMiniCalendar`, `MonthView`, event badges, `EventDetailPanel`, date utilities, API client, and data hook.
- Existing Clients statistics, form controls, buttons, service/addon/renewal/billing actions, and theme tokens.

## Delivered behavior

The statistics remain above a simple client table. Search, service and renewal filters stay prominent; other filters are disclosed on demand. Client details expand inline without navigation. Client creation, profile editing, and operational record entry open in standard modals. Expanded details retain contacts, services/addons, billing, tax information, notes, reminders, activity, and renewal history.

The mini calendar defaults to the current month and shares event data with its full-month popup. Both support month navigation and event inspection; activity and pending/done filters are available. The adjacent list can show the selected month or the current week. The popup keeps the user on Clients.

Scheduled invoice records create invoice reminders. A tax invoice date creates a tax reminder, and service renewal dates create renewal reminders. Renewing a service reschedules its pending renewal reminder, or creates the next reminder if none is pending. Quotations, payment follow-ups, meetings, documents, and general follow-ups can be added explicitly.

## Using Clients and Calendar

1. Open **Clients** and find a client using search, service, or renewal filters. Open the additional filters when you need to narrow the list further.
2. Select **Add Client** to create a client. Initial service and billing details are optional and save together with the client. Select a row's **View More** to expand its details; **Edit Client** changes its profile.
3. In expanded details, select **Add Record / Reminder** to record service, billing, or follow-up work. For a scheduled follow-up, enter a title, activity type, and due date/time. Use quotation, payment, meeting, document, or general follow-up activities for work that is not generated from an invoice or renewal.
4. Use **Client Calendar** to inspect dates and events. **View More** opens the full calendar without leaving Clients. Filter by activity and Pending, Done, or All statuses. **Upcoming** shows the selected month or the current week; it is not an all-time overdue queue.
5. Open an event, or select **View / Edit** in a client's Reminders table, to change its details or complete it. The same client event appears in the main Calendar. Changes refresh other open Clients and Calendar views. Users without management permission can inspect events but cannot edit them.

Completing a reminder records completion of the task. Update the billing record separately to mark an invoice paid or a tax invoice sent. A billing cycle does not generate a recurring monthly reminder series. Scheduled upcoming invoices, explicit tax-invoice dates, and service renewal dates create the corresponding reminders; renewal actions move the pending renewal reminder to the new date.

## One source of truth

`ClientReminderRecords::query()` reads mutable fields from the linked `tracs_reminders` row. The existing follow-up retains its client/service/billing/type relationships. Clients details and list signals use that reader, as does Calendar's client collector. The generic reminder collector excludes linked records to avoid duplicate calendar entries.

Client activities expose structured `client_id`, follow-up/reminder IDs, service/billing IDs, activity type, and client name. Visibility follows client ownership and `clients.view_all`; mutations require `clients.manage` at the API and verify client access before changing a follow-up. Client creation with initial service/billing and reminder writes is transactional.

Editing or completing a reminder in either Calendar view updates the linked reminder through the existing client actions API. Changes refresh the local views and invalidate other open Calendar/Clients views through storage notifications, focus, and visibility refresh. The shared calendar cache also clears across years on refresh.

Existing unlinked follow-ups remain readable, including historical completion timestamps. Scheduling one attaches a reminder. Archived/deleted linked reminders are not resurrected as pending client activities.

## Files changed

- `frontend/src/modules/clients/main.jsx`, `styles.css`, new `ClientCalendar.jsx`.
- `modules/client-portfolio/model.php`, `controller.php`, new `ClientReminderRecords.php`.
- `modules/calendar/CalendarService.php`.
- `public/api/v1/client-portfolio/actions.php`.
- `assets/react/calendar/CalendarApp.jsx`, `api/calendarApi.js`, `hooks/useCalendarData.js`, `utils/events.js`.
- `assets/react/calendar/components/CalendarToolbar.jsx`, `EventDetailPanel.jsx`, `MonthMiniCalendar.jsx`, `MonthView.jsx`, new `TracsModal.jsx`.
- `config/migrations/2026_09_10_client_calendar.sql`.
- `tests/client-calendar-integration.php`, `tests/client-calendar-browser.cjs`.
- Rebuilt Calendar and React bundles/manifests under `public/assets/calendar-dist/` and `public/assets/react-dist/`.

The PHP page wrappers, Domain Transfer, MoM, navigation, and global theme source were not changed by this work. Pre-existing changes in screenshot capture, abuse reports, and the global theme remain intact.

## Database and deployment

The rerunnable migration widens `tracs_client_followups.action_type` from its existing enum to `VARCHAR(80)` so quotation and additional activity types can be stored without another schema change. Existing values and rows are preserved. No new table or duplicate reminder relationship is added.

Applied to the local Docker database and verified as `varchar(80)`; follow-up row count remained zero. Apply the migration on any other environment before using the new activity types, and deploy the rebuilt assets with their manifests. Production deployment was subsequently authorized and completed as release `f9afddd`; see [deployment summary](../deployment-summary.md) for backup and verification details.

## Validation

Run from the repository root after installing the root and frontend dependencies:

```sh
npm run build:calendar
(cd frontend && npm run build:preview)
php tests/client-calendar-integration.php
node tests/client-calendar-browser.cjs
```

The integration test requires a local MySQL server and permission to create and drop its disposable test database. The browser test uses the frontend Playwright dependency and a Chrome installation. See the [Calendar regression checklist](calendar-reference-regression-checklist.md) for manual checks.


- Both Vite production builds pass, including all existing React entry points.
- Modified PHP files pass syntax checks; `git diff --check` passes.
- Isolated MySQL integration tests use copied schemas and synthetic users only, then remove the disposable database. Checks cover grouped creation, invoice/tax/quotation/renewal events, canonical edits in both directions, completion/reopening, client ownership, no duplicate events, transaction rollback, invalid dates, search/service filters, renewal rescheduling, legacy follow-up scheduling, and migration reruns.
- Playwright tests use actual compiled assets and TRACS CSS with mocked API records. Checks cover empty state, Add Client modal, inline expansion, quotation creation, full Calendar modal, month navigation, editing, main Calendar completion, cross-tab refresh, search, mobile overflow, expanded-row visibility after horizontal scrolling, and modal viewport fit.
- Desktop and 390px mobile screenshots were inspected. Browser artifacts are written to `/tmp/tracs-clients-validation/`.

Known pre-existing issue: Calendar's domain-expiration collector uses the SQL alias `div`, which fails on the local MySQL server. That unchanged collector reports unavailable independently of client activities. This was observed during integration tests and is outside this rework. Browser checks used API fixtures rather than a live authenticated user session.
