# TRACS — Architecture

## Stack
- **Backend:** PHP 8.0+ (no framework, vanilla MVC)
- **Database:** MySQL/MariaDB via MySQLi
- **Frontend:** Vanilla JS + CSS custom design system
- **Fonts:** Inter (UI) + JetBrains Mono (data/code)

## Folder Structure
```
tracs/
├── api/                    JSON REST API layer
├── auth/                   Session auth (login/logout/guard)
├── config/                 DB config + SQL schema
├── modules/                Business logic
│   ├── case/               Model + Controller
│   ├── reminder/           Model + Controller
│   ├── checklist/          Model + Controller
│   ├── alert-ticker/       Model + Controller
│   └── activity-log/       Model + Controller
└── public/                 DOCUMENT ROOT
    ├── assets/             tracs.css (design system) + tracs.js (all JS)
    └── includes/           header.php, footer.php, page_helpers.php
```

## Auth Flow
```
Browser → public/page.php
  └→ require auth/auth_check.php
       ├ session not set → redirect login.php
       └ session ok → continue
           └→ page renders with header.php + content + footer.php

Login POST → auth/login.php
  ├ validate → query tracs_users
  ├ password_verify()
  ├ success → set $_SESSION[user_id, user_email] → redirect index.php
  └ fail → set $_SESSION[login_error] → redirect login.php
```

## Request Flow (API)
```
JS fetch('../api/endpoint.php', {method:'POST', body: JSON})
  └→ api/_bootstrap.php
       ├ session check → 401 if not logged in
       ├ parse JSON body → $body[]
       └ execute SQL → ok(data) or fail(msg)
```

## Page Render Flow
```
public/page.php
  1. session_start()
  2. require auth_check.php
  3. require controllers
  4. fetch & format data
  5. set $page_title, $active_page, $ticker_items, $critical_count
  6. include includes/header.php   → outputs <html><head><body><sidebar><main>
  7. echo page HTML
  8. include includes/footer.php   → outputs modals + <script> + </body></html>
```

## JS Architecture (tracs.js)
Single file, no build step, no dependencies.
- `toast(msg, type)` — notification system
- `openModal(id)` / `closeModal(id)` — modal manager
- `confirm(msg, cb)` — confirm dialog
- `api(url, data)` — fetch wrapper returning JSON
- `saveCase/Reminder/Task()` — form validation + API call
- `openEditCase/Reminder/Task(id)` — populate modal from data-* attributes
- `deleteCase/Reminder/Task(id)` — confirm + API delete + DOM remove
- `toggleReminder/Task(id, checked)` — optimistic UI update
- `addTickerMsg()` / `deleteTickerMsg(id)` — ticker management
- `_updateProgress()` — recalculates checklist progress bar

## Data Attributes Pattern
All list rows store their data in HTML data-* attributes for instant edit:
```html
<div class="case-row" data-cid="5"
     data-title="Case Title"
     data-status="active"
     data-priority="critical"
     data-next="2025-06-01T09:00"
     data-notes="Notes here">
```
JS reads these on editCase(id) → populates modal fields — zero extra API calls.

## Database Tables
| Table | Purpose |
|---|---|
| tracs_users | Auth accounts |
| tracs_cases | Legal/operational cases |
| tracs_reminders | Time-based alerts |
| tracs_side_tasks | Checklist items |
| tracs_side_task_logs | Task notes/history |
| tracs_activity_logs | Audit trail |
| tracs_ticker_messages | Custom announcements |
| tracs_finance_transfers | Balance transfer log |
| tracs_domains | Domain expiry tracking |

## Design System
CSS file: `public/assets/tracs.css`

### Color Tokens (CSS vars)
```css
--bg, --bg2          → Page backgrounds
--s1–s4              → Surface layers (cards, panels)
--bd1–bd4            → Border shades
--tx1–tx4            → Text shades
--blue               → Primary action
--red                → Critical/danger/overdue
--amber              → Warning/high priority
--green              → Success/active/completed
--purple             → Stuck status
--cyan               → Info/due today
```

### Component Classes
- `.panel` + `.panel-head` + `.panel-title` — card containers
- `.stat-card.{color}` + `.stat-glow` — metric cards
- `.case-row` + `.case-bar.{priority}` — case list items
- `.rem-row` — reminder rows
- `.task-row` — checklist rows
- `.badge.b-{status}` — status/priority pills
- `.btn`, `.btn-primary`, `.btn-ghost`, `.btn-danger`, `.btn-icon` — buttons
- `.modal-overlay` + `.modal` — dialog system
- `.toast-dock` + `.toast.{type}` — notifications
- `.form-group`, `.form-row`, `.form-input`, `.form-select` — forms

## Ticker System
Auto-generated alerts (from DB data) + custom user messages merged in `AlertTickerController::formatAlertsForTicker()`.
Custom messages stored in `tracs_ticker_messages`. Managed via ⚙ MANAGE button in ticker bar.

## Activity Log
Every create/update/delete in any module calls `logAct()` in api/_bootstrap.php which calls `ActivityLogController::logActivity()` → inserts into `tracs_activity_logs`.
