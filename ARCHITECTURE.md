# TRACS - Architecture

## Overview

TRACS is a modular, server-rendered PHP operations application. Pages under `public/` assemble data from module controllers/models, shared includes render the application shell, and authenticated APIs handle mutations and exports.

```text
Browser
  -> public/*.php
     -> hardened session + auth guard
     -> page permission check
     -> modules/* controller/model
     -> public/includes/header.php
     -> page HTML
     -> public/includes/footer.php

Browser JavaScript
  -> public/api/*.php
     -> public/api/_bootstrap.php
     -> full-auth, account, permission, and CSRF checks
     -> module/controller or focused endpoint logic
     -> JSON, CSV, or permission-checked image response
```

## Runtime Structure

| Path | Responsibility |
| --- | --- |
| `public/` | Web root and page routes. |
| `public/api/` | Authenticated JSON, CSV, and attachment endpoints. |
| `public/auth/` | Login, logout, and full-auth guard. |
| `public/includes/` | Shared header/sidebar/ticker, footer/modals/scripts, theme bootstrap, and page helpers. |
| `public/assets/` | Shared CSS/JS plus module-specific assets. |
| `public/uploads/` | Runtime image storage. Avatars are intentional public images; case, shift, and MoM evidence is served through authenticated endpoints. |
| `public/cache/` | PHP-writable generated cache. |
| `modules/` | Business controllers and models. |
| `core/` | Access control, CSRF/session hardening, user management, creator tracking, notifications, shift configuration, and build signature. |
| `config/` | Database connection, fresh installer, focused schemas, and dated migrations. |
| `bin/tracs-notification-worker.php` | CLI scheduler for due reminders, meetings, and shift-handover notifications. |
| `logs/` | Private application/PHP logs; never a web route. |
| `storage/deployment/` | Private deploy metadata read by Super Admin monitoring. |

Only `public/` is a browser document root. Even inside `public/`, `includes/`, `modules/`, API bootstrap/helper files, and protected upload folders are internal and denied by Nginx/Apache plus PHP direct-access guards.

## Authentication And Session Layer

- `public/login.php` renders login; `public/auth/login.php` verifies credentials.
- Login uses generic failures, a dummy hash for unknown accounts, failed-attempt tracking, CAPTCHA escalation, and temporary lockouts.
- Password success creates only a pending-2FA session.
- `public/two-factor-setup.php` and `public/two-factor-verify.php` complete mandatory TOTP authentication.
- Session IDs regenerate after password verification and after successful 2FA.
- Protected pages, APIs, and exports require a full auth state and refresh the idle timer.
- Session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` when HTTPS is detected.

## Roles And Permissions

System roles are Super Admin, Admin, Supervisor / Leader, Agent, Intern, and Viewer / Auditor. Effective access comes from permission records, not role labels alone.

- Super Admin receives the full permission catalog and owns sensitive recovery actions such as another user's 2FA reset.
- Admin receives all default permissions except role deletion and sensitive settings management.
- Supervisor has team operations, task monitoring/review, reports, domains, MoM, and selected user controls.
- Agent has normal operational create/update permissions but no User Management.
- Intern has dashboard, checklist, and own-task access by default.
- Viewer / Auditor has read-oriented access.

`core/user_management.php` is the source of truth for the catalog and default role mappings. `public/includes/header.php` derives navigation visibility from permissions.

Server Health & Logs is deliberately role-based rather than permission-based: only the exact `super_admin` role can discover the menu, open `public/server-health.php`, or call `public/api/server-health.php`. Admin, Supervisor, Agent, Intern, Viewer, unauthenticated, and pending-2FA sessions are blocked.

## Server Health And Logs

`core/server_monitoring.php` reads a strict fixed metric set. It accepts no path or command input, does not follow symlinks, bounds folder scans by time and entry count, and never scans the whole server filesystem. Linux CPU/RAM/uptime use fixed `/proc` files; disk uses PHP filesystem functions; database size/version use fixed queries; Nginx version is parsed only from the server software value; deployment version/time/commit come from `storage/deployment/deployment.meta`.

Recent `logs/error.log` entries are aggressively sanitized before JSON output. SQL/stack/credential details are replaced, and paths, IPs, URLs, and email addresses are redacted. If the log or a server metric is not safely readable, the API reports `Unavailable`.

## Dashboard Layer

`public/index.php` is the operational landing page.

- Uses the restored five-item `dashboard-stat-strip`; the grouped dashboard stat-card experiment is not current.
- Cases open the same shared ticket modal used from `cases.php`.
- Operations summary slider includes Infrastructure Pulse and Shift Summary.
- Main workspace includes Task Monitoring, Shift Handover, and Currency Converter.
- The notification bell combines stored notifications, time-sensitive alerts, and workload summaries.

## Cases

Files: `modules/case/*`, `public/cases.php`, `public/api/case-*.php`, and the shared ticket modal in `public/includes/footer.php`.

- Statuses: `active`, `pending`, `in_progress`, `stuck`, `on_hold`, `completed`.
- Dashboard and case-list rows call `openCaseTicket()` and use `case-get.php`.
- Ticket timeline: Created, Assigned, In Progress, Waiting, Resolved.
- Resolve is the right-side primary footer action. Close remains in the header. Edit/Delete are under the icon-only More trigger.
- Case attachments accept JPEG, PNG, and WebP up to 5 MB, are re-encoded with GD, receive thumbnails, and are served through `case-attachment.php`.

## Task Monitoring

The dashboard widget and the full assignment page are related but separate surfaces.

Dashboard tabs:

1. `Checklist and Reminder`
2. `Assignments`
3. `Activity`

There is no separate Reminder dashboard tab. Reminder List is embedded in the combined tab. `public/reminders.php` remains the full reminder page.

The full task assignment route is `monitoring.php`; `tasks.php` includes it as a compatibility entry. Task creation can target users, roles, or divisions. Each assignment creates a linked checklist item, creates a linked reminder when a due date exists, queues assignment/reminder notifications, and maintains assignment logs/review state.

## Shift Summary And Handover

Files: `modules/shift-reports/*`, `public/shift-reports.php`, and `public/api/shift-*.php`.

- Statuses: Active, On Hold, Resolved.
- Active items are handover work; on-hold items provide monitoring context; resolved items remain visible for context.
- Dashboard reminders begin 30 minutes before the configured shift change.
- Notification scheduler creates shift reminders in the final 15 minutes when actionable handover remains.
- Shift report image attachments are stored in `shift_report_attachments` and served through the permission-checked attachment API.

## MoM And Schedule Relationship

`public/mom.php` uses the bridge in `modules/mom/controller.php` to the integrated controller under `public/modules/mom/`.

- Meetings store schedule and lifecycle state.
- Scheduled meetings create reminders, ops-status windows, and ticker events.
- Agenda, notes, decisions, actions, screenshots, cases, and history remain part of the MoM page.
- Action items can create reminders or cases.
- MoM reminders appear through the normal reminder data flow and can surface in the dashboard Reminder List.

See `README_MOM.md` for current behavior. Files under `MOM README/` are legacy package documentation.

## Notifications

`core/notifications.php` owns schema tolerance, permission checks, dedupe keys, in-app records, browser push state, logs, and scheduled trigger generation.

Implemented triggers:

- New case
- Reminder created, due in 15/10 minutes, and due now
- Task assigned
- Meeting starts in 15/10 minutes or now
- Shift handover in 15 minutes

`public/assets/tracs.js` polls `notifications-list.php` every 60 seconds and uses `public/tracs-sw.js` for browser notification clicks. Production should run `bin/tracs-notification-worker.php` every minute from cron. Infrastructure notifications are not connected because monitoring is still a prototype.

## Infrastructure Pulse

**Partially Implemented.**

- Full page: `public/infrastructure-pulse.php`
- Shared mock/API-ready store: `public/assets/infrastructure-pulse-data.js`
- Rendering: `public/assets/infrastructure-pulse.js`
- Dashboard mini widget: `public/index.php`
- TV widget: `public/tv-mode.php`

All three views share session-only mock telemetry. Backend probes, persistent server registry, monitoring result tables, incidents, cache, and streaming endpoints are planned.

## Domain Price Crosscheck

- Canonical route: `public/domain-price-crosscheck.php`
- Legacy redirect: `public/domain_price_crosscheck.php` (308)
- Logic: `modules/domain-price-crosscheck/*`
- Assets: `public/assets/domain-price-crosscheck.*`

The page is under `Tasks & Monitoring` and includes monthly snapshots, exchange-rate handling, gTLD/ccTLD matrices, registrar source and extension management, intelligence summaries, website price adjustment, action buckets, notes/follow-ups, approval locking, task assignment, CSV export, and an operational audit trail.

See `docs/DOMAIN_PRICE_CROSSCHECK.md` and `docs/DOMAIN_PRICE_CROSSCHECK_ARCHITECTURE.md`.

## Domain Transfer, Finance, Users, And TV Mode

- Domain Transfer Log: `public/domains.php`; retained legacy tables include `domain_transfers` and `activity_feed`.
- Finance: `public/finance.php`; retained legacy storage includes `balance_transfers`.
- User Management: `public/user-management.php`, `public/intern-management.php`, and `modules/user-management/*`.
- Settings are under the avatar menu and route to `profile.php?section=preferences`.
- TV Mode: `public/tv-mode.php`, role-gated to Super Admin/Admin/Supervisor, with responsive compact/narrow/4K modes and data from `public/api/tv-mode-summary.php`.

## Database Overview

| Area | Important tables |
| --- | --- |
| Auth/users | `tracs_users`, `tracs_login_attempts`, `tracs_auth_events`, `tracs_roles`, `tracs_permissions`, `tracs_role_permissions`, `tracs_divisions`, `user_intern_profiles` |
| Cases | `tracs_cases`, `case_attachments` |
| Checklist/reminders/tasks | `tracs_side_tasks`, `tracs_side_task_logs`, `tracs_reminders`, `tracs_tasks`, `tracs_task_assignments`, `tracs_task_logs`, `tracs_task_reviews`, `tracs_task_reminders` |
| Shift | `tracs_shift_reports`, `tracs_shift_activities`, `shift_report_attachments` |
| MoM | `tracs_moms` and `tracs_mom_*` tables |
| Notifications/activity | `tracs_notifications`, `tracs_notification_triggers`, `tracs_notification_logs`, `tracs_ticker_messages`, `tracs_ticker_events`, `tracs_activity_logs`, `tracs_user_activity_logs`, `ops_status` |
| Domain price | `domain_price_months`, `domain_price_tlds`, `domain_price_sources`, `domain_price_entries`, `domain_price_summaries`, `domain_price_audit_logs`, `domain_price_tld_notes`, `domain_price_task_links` |

Use `config/install.sql` for a fresh database and dated files in `config/migrations/` for existing installs.

## Frontend Design System

- Primary tokens and components live in `public/assets/tracs.css`.
- Font stack is `Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif`.
- Prefer compact panels, consistent gaps/padding, contextual icons, readable completed states, and blue only for active/highlighted state.
- Use shared modal, toast, loading, error, dropdown, and theme helpers before adding module-specific copies.

## Refactor Frontend Direction

The current application remains server-rendered PHP during the incremental
refactor. New React modules follow the Calendar pilot pattern: authenticated PHP
shell, explicit React root, module-specific manifest assets, same-origin PHP
APIs, and source-owned business rules.

Tailwind must not load Preflight into hybrid pages. React bundles use prefixed
utilities, separate output, root-scoped handwritten CSS, and semantic theme
tokens mapped to the existing TRACS CSS variables. Existing Calendar uses the
`cal:` prefix and remains unchanged until a dedicated migration is approved.

The future consolidated source root is `frontend/`, with shared UI/pattern
components and independent module entries. PHP loads hashed assets through a
shared allowlisted Vite manifest helper. Existing PHP rendering remains the
fallback until characterization, permission, API, visual, and smoke checks pass.

See:

- `docs/react-tailwind-architecture.md`
- `docs/frontend-migration-plan.md`
- `docs/php-api-architecture-plan.md`
- `docs/tailwind-design-system-plan.md`
- `docs/design-token-map.md`

## Deployment And Docker

- Production target: Ubuntu 24.04-class VPS with Nginx or Apache, PHP-FPM, MySQL/MariaDB, HTTPS, cron, backups, and monitoring.
- Docker is **Local Development Only**. Compose runs PHP 8.2 Apache on port 8080 and MySQL 8 on host port 3307.
- The current Docker image enables `mysqli`, rewrite, and headers, but not GD. Attachment image processing needs a Dockerfile follow-up before Docker can exercise the complete upload flow.
- Never expose the repository root or MySQL publicly.
