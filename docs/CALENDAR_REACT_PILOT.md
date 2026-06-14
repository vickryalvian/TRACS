# TRACS Calendar React Pilot

## Architecture

`public/calendar.php` keeps the existing authenticated PHP shell, sidebar, theme,
CSRF meta tag, ticker, and global helpers. It loads a Vite manifest from
`public/assets/calendar-dist/.vite/manifest.json` and mounts React at
`#calendar-react-root`.

The frontend lives in `assets/react/calendar/`. Tailwind CSS v4 is used without
Preflight, so no global reset or utility output changes existing PHP pages.
Calendar colors, radii, typography, spacing, borders, and shadows map to the
current TRACS CSS variables in `styles.css`.

`modules/calendar/CalendarService.php` is the normalization boundary. It reads
installed source tables dynamically, applies user/role scope, and converts rows
to one event contract. Existing records are never copied into `calendar_events`.

## API Endpoints

- `GET /api/calendar/events.php?start=YYYY-MM-DD&end=YYYY-MM-DD`
- `GET /api/calendar/metadata.php`
- `POST /api/calendar/create.php`
- `POST /api/calendar/update.php`
- `POST /api/calendar/delete.php`

State-changing calls use the existing `X-CSRF-Token` fetch bridge and authenticated
API bootstrap. Super Admin, Admin, and Supervisor roles can manage manual
schedules; Supervisor writes are scoped to their division. Reminder and checklist
completion is routed through the existing source-module APIs, so current TRACS
permissions, activity logging, ticker events, and task synchronization remain
authoritative.

## Event Contract

Every source is normalized to:

```json
{
  "id": "case_123",
  "source": "cases",
  "source_id": 123,
  "type": "case",
  "title": "Follow up customer ticket",
  "date": "2026-06-14",
  "end_date": null,
  "start_time": "09:00",
  "end_time": null,
  "status": "overdue",
  "priority": "high",
  "assignee": { "id": 5, "name": "Agent Name" },
  "division": { "id": 2, "name": "Customer Support" },
  "notes": "",
  "meta": {
    "url": "cases.php?case_id=123",
    "actions": ["open_case"]
  }
}
```

Dates remain ISO in the API and React state. UI formatting is always
`dd-mm-yyyy`. Backend date handling uses `Asia/Jakarta`.

## Data Sources

The collector supports cases, reminders, checklist reset/deadline dates, MoM
meetings and actions, shift assignments, overtime, public holidays, maintenance
notifications, domain expirations, optional birthday columns, internship dates,
and manual Calendar schedules. Missing optional tables or columns produce an
empty source instead of PHP warnings.

## Build

```bash
npm install
npm run build:calendar
```

The output is isolated under `public/assets/calendar-dist/`. Future React + PHP
pilots can follow the same pattern: existing PHP shell, small normalized API,
scoped Tailwind entry without Preflight, manifest-loaded React bundle, and
source-owned writes. The pilot emits ES2022 JavaScript for the modern-browser
refactor target.
