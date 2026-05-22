# TRACS — Operational Dashboard

TRACS is a high-density operational control panel for support, legal, and CS operations teams. It is a vanilla PHP 8 + MySQL/MariaDB application with a `/public` web root, session authentication, a shared design system, JSON APIs, and operational modules for cases, reminders, checklists, shift handover, MoM, task monitoring, finance, domains, cancellation feedback, user management, and wall-display TV mode.

## Current Features

| Area | What exists now |
| --- | --- |
| Dashboard | Operational stats, active cases, reminders, checklist progress, shift/MoM signals, activity feed, ops status, currency converter, ticker alerts. |
| Cases | Case CRUD, priority/status filters, search, export, next-check tracking, dashboard/ticker integration. |
| Reminders | Reminder CRUD, due/overdue/today states, completion, ticker/dashboard integration. |
| Checklist | Personal/operational tasks, progress tracking, linked task-management assignments when present. |
| Tasks / Monitoring | Role-aware task assignment, assignee progress, review flow, checklist/reminder sync, monitoring page for privileged users. |
| Shift Reports | Shift handover reports, today-by-shift dashboard, activity snapshots, exports. |
| MoM | Scheduled meetings, meeting lifecycle, agenda, notes, decisions, action items, reminders, case links, screenshots, history/export. |
| Finance | Balance transfer logging, filtering, CSV export, currency conversion support. |
| Domains | Domain transfer and expiry tracking, activity feed, ticker integration, export. |
| Cancellation Feedback | Cancellation intake, multi-value reasons/services, retention intelligence, filters, export. |
| User Management | Users, roles, permissions, divisions, intern profiles, user audit activity, profile/preferences. |
| TV Mode | Read-only operational wall display for super admin/admin/supervisor roles. |
| Domain Price Crosscheck | Monthly comparison panel for domain registrar costs vs selling prices. Accessed from Domains → Crosscheck Pricing; Task Management integration remains for assignment/workflow. See [DOMAIN_PRICE_CROSSCHECK.md](file:///Users/ulfahanifah/Documents/tracs/docs/DOMAIN_PRICE_CROSSCHECK.md), [DOMAIN_PRICE_CROSSCHECK_ARCHITECTURE.md](file:///Users/ulfahanifah/Documents/tracs/docs/DOMAIN_PRICE_CROSSCHECK_ARCHITECTURE.md), and [DOMAIN_PRICE_CROSSCHECK_AI_MEMORY.md](file:///Users/ulfahanifah/Documents/tracs/docs/DOMAIN_PRICE_CROSSCHECK_AI_MEMORY.md). |

## Tech Stack

- PHP 8.2 recommended, PHP 8.0+ minimum.
- Apache with `mod_rewrite` and `headers`.
- MySQL 8.0 or MariaDB 10.3+.
- MySQLi, sessions, JSON, file upload support for MoM screenshots.
- Vanilla JavaScript in [public/assets/tracs.js](/tracs/public/assets/tracs.js) plus MoM/TV helpers.
- CSS custom-property design system in [public/assets/tracs.css](/tracs/public/assets/tracs.css).
- External browser assets: Google Fonts, lucide, flatpickr CDN.

## Build Signature

TRACS includes a subtle first-deployment authorship marker for copyright, support traceability, and deployment history. The build owner is recorded as Vickry in HTML metadata, [public/manifest.json](/tracs/public/manifest.json), retained asset comments, admin-only system build information, and [docs/TRACS_SIGNATURE.md](/tracs/docs/TRACS_SIGNATURE.md). It is intentionally not exposed as a visible watermark or public-facing brand treatment.

## Quick Start

### Docker

```bash
docker compose up -d --build
docker compose logs -f app
```

Open `http://localhost:8080`.

Docker uses:

| Service | Port | Notes |
| --- | --- | --- |
| `app` | `8080:80` | PHP 8.2 Apache, document root `/var/www/html/public`. |
| `db` | `3307:3306` | MySQL 8.0 with `tracs_db`, user `tracs`, password `tracs_secret`. |

The database volume is named `tracs_db`. On first startup only, MySQL imports [config/install.sql](/tracs/config/install.sql).

### Local LAMP/LEMP

1. Create a database.
2. Import [config/install.sql](/tracs/config/install.sql).
3. Configure DB credentials in [config/database.php](/tracs/config/database.php) or provide `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` through the PHP runtime environment.
4. Point the web server document root to [public](/tracs/public).
5. Visit `/login.php`.

Default seeded login:

| Email | Password |
| --- | --- |
| `admin@tracs.local` | `password` |

Change this immediately after first login.

## Folder Overview

```text
tracs/
  config/                 database config, installer, schema modules, migrations, archive
  core/                   CSRF, creator tracking, user/role/permission helpers
  modules/                PHP business controllers/models
  public/                 web root
    api/                  authenticated JSON/CSV endpoints
    assets/               TRACS CSS/JS, MoM CSS/JS, TV mode CSS/JS, logo
    auth/                 login/logout/auth guard used by public pages
    includes/             header/footer, theme bootstrap, page helpers
  backup/docs-config/     documentation/config backups created before doc audits
  backups/                older task-specific file backups
  logs/                   Apache logs when running Docker with the current compose file
```

## Database Setup

Fresh installs should run [config/install.sql](/tracs/config/install.sql). Existing installs should run dated files in [config/migrations](/tracs/config/migrations) in chronological order after a database backup.

Current active schema includes core `tracs_` tables plus legacy names still used by PHP:

- `tracs_users`, `tracs_login_attempts`, `tracs_auth_events`, `tracs_roles`, `tracs_permissions`, `tracs_role_permissions`, `tracs_divisions`, `user_intern_profiles`
- `tracs_cases`, `tracs_reminders`, `tracs_side_tasks`, `tracs_side_task_logs`
- `tracs_tasks`, `tracs_task_assignments`, `tracs_task_logs`, `tracs_task_reviews`, `tracs_task_reminders`
- `tracs_shift_reports`, `tracs_shift_activities`
- `tracs_moms`, `tracs_mom_*`
- `tracs_activity_logs`, `tracs_user_activity_logs`, `tracs_ticker_messages`, `tracs_ticker_events`, `ops_status`
- `tracs_finance_transfers`, `balance_transfers`
- `tracs_domains`, `domain_transfers`, `activity_feed`
- `tracs_cancellation_feedback`, `tracs_currency_history`, `tracs_user_preferences`

Do not drop unprefixed legacy tables until the PHP references are migrated.

## Common Commands

```bash
# Start Docker stack
docker compose up -d --build

# Stop Docker stack
docker compose down

# Follow app logs
docker compose logs -f app

# Connect to Docker MySQL
docker compose exec db mysql -utracs -ptracs_secret tracs_db

# Fresh DB reset, destructive
docker compose down -v
docker compose up -d --build
```

## Deployment Checklist

- Back up application files and database.
- Set production DB credentials outside source control.
- Import installer or run migrations.
- Set Apache/Nginx document root to `/public`.
- Disable PHP display errors in production.
- Enable HTTPS.
- Change default admin password.
- Run `config/migrations/2026_05_21_login_hardening.sql` on existing databases to enable failed-login counters and authentication security logs.
- Configure login protection as needed with `TRACS_LOGIN_MAX_FAILED_ATTEMPTS`, `TRACS_LOGIN_WINDOW_MINUTES`, `TRACS_LOGIN_LOCK_MINUTES`, `TRACS_LOGIN_CAPTCHA_AFTER`, `TRACS_SESSION_IDLE_TIMEOUT_MINUTES`, and `TRACS_TRUSTED_PROXIES`.
- Optional CAPTCHA: set `TRACS_CAPTCHA_PROVIDER=turnstile`, `TRACS_TURNSTILE_SITE_KEY`, and `TRACS_TURNSTILE_SECRET_KEY`. If unset, TRACS uses an internal challenge only after suspicious login behavior.
- Verify login, case create/update, reminder create/toggle, checklist create/toggle, MoM create, user permissions, and exports.
- Confirm `public/uploads/mom` or equivalent upload path is writable if MoM screenshots are used.
- Preserve [docs/TRACS_SIGNATURE.md](/tracs/docs/TRACS_SIGNATURE.md) and the build-signature metadata when packaging first-deployment artifacts.
- Keep [AI_MEMORY.md](/tracs/AI_MEMORY.md), [ARCHITECTURE.md](/tracs/ARCHITECTURE.md), and [HANDOFF.md](/tracs/HANDOFF.md) with the deployment package.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| DB connection fails in Docker | Rebuild after the Dockerfile `variables_order=EGPCS` config, confirm `db` service is healthy, and inspect `docker compose logs db`. |
| Login redirects repeatedly | Confirm sessions are writable and `public/auth/auth_check.php` is included from a public page after `tracs_start_session()`. |
| Login shows CAPTCHA or lockout | Failed credentials crossed the configured threshold. Wait for `TRACS_LOGIN_LOCK_MINUTES`, solve the challenge, or review User Management → Login Security as an admin/supervisor. |
| 401 on API calls | User session expired or CSRF token missing/invalid for mutating requests. Reload the page and retry. |
| MoM says tables are not installed | Import current `config/install.sql` or run the MoM schema/migrations on existing DB. |
| User/monitoring pages return Forbidden | Run user-management and task-management migrations, then assign appropriate role permissions. |
| Assets appear stale | CSS/JS links use filemtime query strings; hard refresh or verify files are mounted into the container. |

## Project Notes

TRACS intentionally remains a no-framework PHP application. Preserve the TRACS identity, sidebar/ticker/dashboard layout, shared modals, and the compact operational design language unless a future redesign is planned as its own project.

## Login Hardening Notes

- Failed login attempts are tracked by normalized email/username input and client IP in `tracs_login_attempts`.
- Defaults: CAPTCHA after 3 failed attempts, temporary lock after 5 failed attempts within 15 minutes, 15-minute lock, and 60-minute idle session timeout.
- Login errors are intentionally generic and include the safe assistance message: “If you are having trouble logging in, please contact your administrator for further assistance.”
- Logout is protected by POST + CSRF. Authentication events are recorded in `tracs_auth_events` without passwords, session IDs, CAPTCHA secrets, or CSRF tokens.
- Behind a reverse proxy, set `TRACS_TRUSTED_PROXIES` before trusting `X-Forwarded-For` or Cloudflare client-IP headers. Example: `TRACS_TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8`.
- Rollback: restore backed-up PHP/CSS/docs files, drop `tracs_login_attempts` and `tracs_auth_events` only after exporting them if audit history is needed, and remove the optional CAPTCHA/session/proxy environment variables.
- Testing checklist: normal login, wrong password increments attempts, CAPTCHA after threshold, wrong CAPTCHA blocks, correct CAPTCHA plus valid credentials logs in, lockout activates/expires, successful login resets attempts, logout destroys session, role-based pages still route correctly, and login page remains responsive in light/dark themes.
