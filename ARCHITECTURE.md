# TRACS — Architecture

## Overview

TRACS is a vanilla PHP operational dashboard. It uses server-rendered pages under `/public`, authenticated JSON endpoints under `/public/api`, business controllers/models under `/modules`, shared helpers under `/core`, and a MySQL/MariaDB schema managed from `config/install.sql` plus dated migrations.

```text
Browser
  -> public/*.php page
      -> core/security/csrf.php
      -> config/database.php
      -> public/auth/auth_check.php
      -> modules/* controller/model
      -> public/includes/header.php
      -> page HTML
      -> public/includes/footer.php

Browser JS
  -> public/api/*.php
      -> public/api/_bootstrap.php
      -> module controller/model
      -> JSON or CSV response
```

## Folder And File Structure

| Path | Purpose |
| --- | --- |
| [public](/tracs/public) | Web root and page entry points. |
| [public/api](/tracs/public/api) | Authenticated API/export endpoints. |
| [public/auth](/tracs/public/auth) | Login, logout, auth guard. |
| [public/includes](/tracs/public/includes) | Header/sidebar/ticker, footer/modals/scripts, theme bootstrap, page helpers. |
| [public/assets](/tracs/public/assets) | `tracs.css`, `tracs.js`, MoM assets, TV mode assets, logo. |
| [modules](/tracs/modules) | Controllers/models for operational modules. |
| [core](/tracs/core) | CSRF, user management, creator tracking. |
| [config](/tracs/config) | Database config, installer, schema modules, migrations, archive. |
| [Dockerfile](/tracs/Dockerfile) | PHP Apache image for local/container deployment. |
| [docker-compose.yml](/tracs/docker-compose.yml) | Local app + MySQL stack. |

## Authentication And Permissions

- Login form: [public/login.php](/tracs/public/login.php).
- Login handler: [public/auth/login.php](/tracs/public/auth/login.php).
- Auth guard: [public/auth/auth_check.php](/tracs/public/auth/auth_check.php).
- User/role/permission helpers: [core/user_management.php](/tracs/core/user_management.php).
- CSRF helpers: [core/security/csrf.php](/tracs/core/security/csrf.php).

User-management schema adds roles, permissions, divisions, intern profiles, user activity logs, and password reset tokens. Navigation in `header.php` is role/permission aware: monitoring, TV mode, and user management are only visible to eligible users.

## Main Pages And Modules

| Page | Module/controller | Notes |
| --- | --- | --- |
| `index.php` | case, reminder, checklist, ticker, activity, ops status, shift reports, MoM | Main dashboard; also includes currency converter widget. |
| `cases.php` | `modules/case` | Case list, CRUD modal, search/filter/export, next-check tracking. |
| `reminders.php` | `modules/reminder` | Reminder list, CRUD modal, due state, completion. |
| `checklist.php` | `modules/checklist` | Checklist tasks, progress, linked task-assignment sync. |
| `shift-reports.php` | `modules/shift-reports` | Handover reports and activity snapshots. |
| `monitoring.php` / `tasks.php` | `modules/task-management` | Task assignment, assignee progress, review/monitoring. |
| `mom.php` | `modules/mom` -> `public/modules/mom/controller.php` | Meeting schedule, agenda, notes, decisions, actions, cases, reminders, screenshots, history. |
| `domains.php` | page-local SQL plus ticker integration | Domain transfers, expiry/transfer tracking, activity feed. |
| `finance.php` | finance/balance transfer flows | Balance transfer log, filters, export. |
| `cancellation_feedback.php` | `modules/cancellation-feedback` | Cancellation intelligence, multi-select values, export. |
| `user-management.php` | `modules/user-management` | Users, roles, permissions, divisions, activity. |
| `intern-management.php` | user-management | Intern profiles and intern-focused admin views. |
| `profile.php` | user helpers/preferences | Profile, security, theme/preferences. |
| `activity.php` | `modules/activity-log` | Audit/activity browser. |
| `tv-mode.php` | `public/api/tv-mode-summary.php` | Role-gated wall display. |

## Frontend Assets

- [public/assets/tracs.css](/tracs/public/assets/tracs.css): main design system and page styling.
- [public/assets/tracs.js](/tracs/public/assets/tracs.js): shared modal, toast, CRUD, theme, export, and utility behavior.
- [public/assets/mom-styles.css](/tracs/public/assets/mom-styles.css): MoM-specific styles loaded for dashboard and MoM pages.
- [public/assets/mom-functions.js](/tracs/public/assets/mom-functions.js): MoM interactions.
- [public/assets/tv-mode.css](/tracs/public/assets/tv-mode.css) and [public/assets/tv-mode.js](/tracs/public/assets/tv-mode.js): wall-display experience.

The header loads Google Fonts, lucide, flatpickr, the theme bootstrap, and cache-busted asset links using `filemtime`.

## Database And Config

- DB connection: [config/database.php](/tracs/config/database.php).
- Fresh install: [config/install.sql](/tracs/config/install.sql).
- Active schema fragments: [config/schema](/tracs/config/schema).
- Existing-install migrations: [config/migrations](/tracs/config/migrations).
- Archived/superseded SQL: [config/archive](/tracs/config/archive).

`database.php` reads `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, and `DB_NAME` from PHP `$_ENV`, then sets `utf8mb4` and `Asia/Jakarta` time. Docker config ensures `$_ENV` is populated.

## Important Tables

| Area | Tables |
| --- | --- |
| Users/Auth | `tracs_users`, `tracs_roles`, `tracs_permissions`, `tracs_role_permissions`, `tracs_divisions`, `user_intern_profiles` |
| Cases/Reminders/Checklist | `tracs_cases`, `tracs_reminders`, `tracs_side_tasks`, `tracs_side_task_logs` |
| Task Management | `tracs_tasks`, `tracs_task_assignments`, `tracs_task_logs`, `tracs_task_reviews`, `tracs_task_reminders` |
| MoM | `tracs_moms`, `tracs_mom_agenda`, `tracs_mom_notes`, `tracs_mom_decisions`, `tracs_mom_actions`, `tracs_mom_case_links`, `tracs_mom_screenshots`, `tracs_mom_audit_log` |
| Shift Reports | `tracs_shift_reports`, `tracs_shift_activities` |
| Alerts/Activity | `tracs_ticker_messages`, `tracs_ticker_events`, `tracs_activity_logs`, `tracs_user_activity_logs`, `ops_status` |
| Finance/Domains | `tracs_finance_transfers`, `balance_transfers`, `tracs_domains`, `domain_transfers`, `activity_feed` |
| Feedback/Utility | `tracs_cancellation_feedback`, `tracs_currency_history`, `tracs_user_preferences` |

## Operational Flow

1. User logs in and the session stores user identity.
2. Page includes DB/auth/controllers and prepares formatted data.
3. Header renders ticker, sidebar, theme/user menus, and role-aware navigation.
4. Page body renders compact panels, tables, forms, and action buttons.
5. Footer renders shared modals and loads JS.
6. JS posts to `/api/*.php`; `_bootstrap.php` checks auth and CSRF.
7. Controllers update tables, log activity, and optionally create ticker events, reminders, checklist items, or ops-status records.

## Cross-Module Integrations

- Ticker aggregates critical cases, reminders, domain/finance/shift/MoM signals, custom ticker messages, and ticker events.
- Task management creates linked checklist tasks and reminders for assignees.
- MoM schedules create reminders and ops-status rows; action items can create reminders or cases; meetings can link and update cases.
- Shift reports collect operational snapshots from checklist, reminders, cases, domains, finance, meetings, and ticker signals.
- User management permissions control task monitoring, user management, and TV mode visibility.

## Docker Architecture

`docker-compose.yml` runs:

- `app`: PHP 8.2 Apache, document root `/var/www/html/public`, source mounted at `/var/www/html`, logs mounted to `./logs`, environment variables passed for DB.
- `db`: MySQL 8.0, healthcheck, persistent `tracs_db` volume, first-run import of `config/install.sql`.

`Dockerfile` enables `mysqli`, `rewrite`, and `headers`, configures Apache for `/public`, and sets PHP `variables_order=EGPCS` so Docker environment variables reach `$_ENV`.

## ISO 9001 And Measurement Direction

TRACS is moving toward more measurable operational management:

- Task assignments and reviews provide accountability evidence.
- Shift reports and activity logs provide traceability.
- Cancellation feedback provides customer-loss intelligence.
- MoM decisions/actions provide decision and follow-up records.
- Future measurement work should add KPI/achievement tracking, SLA summaries, exportable evidence, and possibly a dedicated measurement page or subdomain.

Keep measurement features additive and auditable. Prefer new reporting tables/views and exports over disruptive rewrites of core operational flows.
