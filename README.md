# TRACS — Operational Dashboard

TRACS is a compact operational control panel for support, legal, CS, and monitoring workflows. It is a vanilla PHP 8 + MySQL/MariaDB application with a `public/` web root, mandatory 2FA, permission-aware pages/APIs, a shared design system, notifications, and wall-display TV Mode.

## Current Features

| Area | What exists now |
| --- | --- |
| Dashboard | Restored five-item stat strip, Cases, Task Monitoring, Shift Handover, Currency Converter, Infrastructure Pulse summary, ticker, and Attention Center. |
| Cases | CRUD, `in_progress` status, filters/search/export, shared ticket detail, progress timeline, Resolve action, and image attachments. |
| Reminders | Full reminder page plus Reminder List inside the dashboard `Checklist and Reminder` tab. |
| Task Monitoring | Dashboard tabs are `Checklist and Reminder`, `Assignments`, and `Activity`; full assignment/review workflow is at `monitoring.php`. |
| Shift Reports | Active/On Hold/Resolved handover context, dashboard shift reminder, activity snapshots, exports, and image attachments. |
| Shift Assignment | Exact-date scheduling, workload/coverage warnings, monthly templates, and the idempotent default CS agent/schedule seed. |
| MoM | Scheduled meetings, meeting lifecycle, agenda, notes, decisions, action items, reminders, case links, screenshots, history/export. |
| Finance | Balance transfer logging, filtering, CSV export, currency conversion support. |
| Domains | Domain Transfer Log and Domain Pricing Crosscheck under `Tasks & Monitoring`. |
| Cancellation Feedback | Cancellation intake, multi-value reasons/services, retention intelligence, filters, export. |
| User Management | Users, roles, permissions, divisions, intern profiles, user audit activity, profile/preferences. |
| Notifications | In-app center, optional browser notifications, due scheduling, dedupe/logging, and a CLI worker. |
| Infrastructure Pulse | **Partially Implemented:** full page, dashboard widget, and TV widget use shared mock/session telemetry; backend probes are planned. |
| TV Mode | Role-gated operational wall display with compact/narrow/4K responsive states and Infrastructure Pulse widget. |
| Domain Price Crosscheck | Canonical route `domain-price-crosscheck.php`; matrix, intelligence, ccTLD, adjustments, action buckets, notes, audit, source/extension management, approval, tasks, and export. |

## Tech Stack

- PHP 8.2+ recommended.
- Apache with `mod_rewrite`/`headers`, or Nginx + PHP-FPM.
- MySQL 8.0 or MariaDB 10.3+.
- MySQLi, sessions, JSON, GD, cURL, mbstring, OpenSSL, and file upload support.
- Vanilla JavaScript in [public/assets/tracs.js](/tracs/public/assets/tracs.js) plus MoM/TV helpers.
- CSS custom-property design system in [public/assets/tracs.css](/tracs/public/assets/tracs.css).
- External browser assets: Google Fonts, lucide, flatpickr CDN.

## Build Signature

TRACS includes a subtle first-deployment authorship marker for copyright, support traceability, and deployment history. The build owner is recorded as Vickry in HTML metadata, [public/manifest.json](/tracs/public/manifest.json), retained asset comments, admin-only system build information, and [docs/TRACS_SIGNATURE.md](/tracs/docs/TRACS_SIGNATURE.md). It is intentionally not exposed as a visible watermark or public-facing brand treatment.

## Quick Start

### Docker - Local Development Only

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

The current Docker image installs `mysqli` but not GD. Core pages work, but case/shift image processing needs a Dockerfile follow-up before attachment flows can be tested completely in Docker.

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

## Documentation Index

| Document | Purpose |
| --- | --- |
| [ARCHITECTURE.md](ARCHITECTURE.md) | Runtime, modules, data flow, permissions, and deployment shape. |
| [AI_MEMORY.md](AI_MEMORY.md) | Durable product decisions and rules for future AI/developer work. |
| [HANDOFF.md](HANDOFF.md) | Current state, regression hotspots, and continuation checks. |
| [TASKS.md](TASKS.md) | Completed, in-progress, partial, planned, and legacy work. |
| [README_MOM.md](README_MOM.md) | Current integrated MoM behavior. |
| [SECURITY_AUDIT_CHECKLIST.md](SECURITY_AUDIT_CHECKLIST.md) | Application security controls and verification checklist. |
| [VPS_SECURITY_CONFIGURATION.md](VPS_SECURITY_CONFIGURATION.md) | Ubuntu 24.04 production deployment baseline. |
| [docs/API_SECURITY_INVENTORY.md](docs/API_SECURITY_INVENTORY.md) | Endpoint methods, authentication, permissions, CSRF, and hardening actions. |
| [docs/nginx-tracs.conf.example](docs/nginx-tracs.conf.example) | Production Nginx deny rules and PHP-FPM baseline. |
| [config/README.md](config/README.md) | Installer, schemas, and migration guidance. |
| [docs/DOMAIN_PRICE_CROSSCHECK.md](docs/DOMAIN_PRICE_CROSSCHECK.md) | Domain Price user/operations guide. |
| [docs/INFRASTRUCTURE_PULSE.md](docs/INFRASTRUCTURE_PULSE.md) | Current prototype scope and usage. |
| [docs/SECURITY_AUDIT_2FA.md](docs/SECURITY_AUDIT_2FA.md) | Focused mandatory-2FA audit. |
| [docs/TRACS_SIGNATURE.md](docs/TRACS_SIGNATURE.md) | Preserved build authorship/deployment marker. |
| [TESTING.md](TESTING.md) | Pre-refactor testing baseline, priorities, tools, and future CI direction. |
| [ROLLBACK.md](ROLLBACK.md) | Local, branch, commit, deployment, and database rollback procedures. |
| [REFACTOR_ROADMAP.md](REFACTOR_ROADMAP.md) | Full-system React, Tailwind, PHP API, and MySQL migration direction. |
| [docs/manual-smoke-checklist.md](docs/manual-smoke-checklist.md) | Manual smoke coverage for critical TRACS pages and workflows. |
| [docs/permission-api-contract-checklist.md](docs/permission-api-contract-checklist.md) | Role, object-scope, CSRF, API, export, upload, and monitoring contracts. |
| [docs/calendar-reference-regression-checklist.md](docs/calendar-reference-regression-checklist.md) | Zero-mistake Calendar reference regression checklist. |

Files under `MOM README/` are historical package documentation. Files under backup trees are not current documentation.

## Folder Overview

```text
tracs/
  bin/                    notification scheduler CLI
  config/                 database config, installer, schema modules, migrations, archive
  core/                   security, access, notifications, creator tracking, users/permissions
  modules/                PHP business controllers/models
  public/                 web root
    api/                  authenticated JSON/CSV endpoints
    assets/               TRACS CSS/JS, MoM CSS/JS, TV mode CSS/JS, logo
    auth/                 login/logout/auth guard used by public pages
    includes/             header/footer, theme bootstrap, page helpers
    cache/                PHP-writable generated holiday cache
    uploads/              PHP-writable images; only avatars are direct-public
  logs/                   private PHP/application logs
  storage/deployment/     private, read-only deployment metadata for monitoring
  backup/docs-config/     documentation/config backups created before doc audits
  backups/                older task-specific file backups
```

Production deployment excludes development backup trees, `.git`, `.cursor`, `graphify-out`, local logs, cache contents, uploads, and secrets. Nginx must use `public/` as its root and deny direct requests to `includes/`, `modules/`, API helpers, protected uploads, backup-like filenames, shell scripts, SQL, logs, and documentation.

## Database Setup

Fresh installs should run [config/install.sql](/tracs/config/install.sql). Existing installs should run dated files in [config/migrations](/tracs/config/migrations) in chronological order after a database backup.

Current active schema includes core `tracs_` tables plus legacy names still used by PHP:

- `tracs_users`, `tracs_login_attempts`, `tracs_auth_events`, `tracs_roles`, `tracs_permissions`, `tracs_role_permissions`, `tracs_divisions`, `user_intern_profiles`
- `tracs_cases`, `case_attachments`, `tracs_reminders`, `tracs_side_tasks`, `tracs_side_task_logs`
- `tracs_tasks`, `tracs_task_assignments`, `tracs_task_logs`, `tracs_task_reviews`, `tracs_task_reminders`
- `tracs_shift_reports`, `tracs_shift_activities`, `shift_report_attachments`
- `shift_assignment_types`, `shift_templates`, `shift_assignments`, `shift_monthly_templates`, `shift_monthly_template_items`, `shift_workload_settings`, `shift_coverage_rules`, `shift_warnings`
- `tracs_moms`, `tracs_mom_*`
- `tracs_notifications`, `tracs_notification_triggers`, `tracs_notification_logs`
- `tracs_activity_logs`, `tracs_user_activity_logs`, `tracs_ticker_messages`, `tracs_ticker_events`, `ops_status`
- `tracs_finance_transfers`, `balance_transfers`
- `tracs_domains`, `domain_transfers`, `activity_feed`
- `tracs_cancellation_feedback`, `tracs_currency_history`, `tracs_user_preferences`
- `domain_price_months`, `domain_price_tlds`, `domain_price_sources`, `domain_price_entries`, `domain_price_summaries`, `domain_price_audit_logs`, `domain_price_tld_notes`, `domain_price_task_links`

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

# Run notification scheduling once
php bin/tracs-notification-worker.php

# Preview and apply the default CS schedule from the current month through year-end
php bin/seed-default-shift-schedule.php
php bin/seed-default-shift-schedule.php --apply

# Reconcile an explicit range
php bin/seed-default-shift-schedule.php --start=2026-06 --end=2026-12 --apply
```

## Production Deployment Script

[`deploy.sh`](deploy.sh) deploys TRACS to Ubuntu 24.04 with Nginx, PHP-FPM, and MySQL. `WEB_ROOT` is the application root; Nginx must point to `WEB_ROOT/public`. The script requires a clean Git branch, validates PHP and Nginx, creates timestamped application/database backups, preserves production config and runtime data, applies and verifies scoped permissions, writes secret-free deployment metadata, reloads services, and checks `/login.php`.

Configure MySQL client authentication with `MYSQL_LOGIN_PATH`, a mode-600 `MYSQL_DEFAULTS_FILE`, or the deploy user's `~/.my.cnf`. Do not place a database password in the command line. The deploy user needs `sudo` rights for ownership changes and service reloads.

```bash
# Make executable after cloning
chmod +x deploy.sh

# Preflight only
REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs \
  MYSQL_LOGIN_PATH=tracs-deploy DOMAIN=tracs.example.com \
  ./deploy.sh check

# Show the deployment without changing files or services
REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs \
  MYSQL_LOGIN_PATH=tracs-deploy DOMAIN=tracs.example.com \
  ./deploy.sh deploy --dry-run --yes

# Normal deployment
REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs \
  MYSQL_LOGIN_PATH=tracs-deploy DOMAIN=tracs.example.com \
  ./deploy.sh deploy

# Deploy and run one reviewed migration
REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs \
  MYSQL_LOGIN_PATH=tracs-deploy DOMAIN=tracs.example.com \
  ./deploy.sh deploy --with-migration config/migrations/2026_05_27_case_in_progress_status.sql

# Restore application code from a backup ID
REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs \
  ./deploy.sh rollback 20260607-153000
```

TRACS has no migration ledger, so migrations are never auto-discovered or replayed. Rollback restores application files while preserving current `.env`/database config, uploads, cache, and logs. Database restoration remains a separate manual operation using the selected backup's `database.sql.gz`.

Permission summary:

| Path | Owner:group | Mode | Purpose |
| --- | --- | --- | --- |
| Source directories/files | `deploy-user:www-data` | `755` / `644` | PHP-readable, not web-writable |
| `.env`, `config/.env`, `config/database.php` | `deploy-user:www-data` | `640` | Private configuration |
| `public/uploads`, `public/cache`, `logs` and declared subfolders | `www-data:www-data` | `750` / `640` | Intentional PHP runtime writes |
| `storage/deployment` | `deploy-user:www-data` | `750` / `640` | PHP-readable deployment metadata, not PHP-writable |
| External backup root | `deploy-user:deploy-group` | `700`; files `600` | Private backup storage |
| `deploy.sh` | `deploy-user:www-data` | `750` | Restricted deployment execution |

No deployed path uses `777`. See [VPS_SECURITY_CONFIGURATION.md](VPS_SECURITY_CONFIGURATION.md) for the exact Nginx and permission model and [docs/API_SECURITY_INVENTORY.md](docs/API_SECURITY_INVENTORY.md) for endpoint exposure.

## Server Health & Logs

`server-health.php` is visible and directly accessible only to the exact `super_admin` role. Its JSON source is `api/server-health.php`, which requires full login, rejects query/path input, rate-limits refreshes, and returns only whitelisted metrics. It reports CPU, RAM, disk/free space, bounded TRACS/upload/log/backup sizes, database size, uptime, safe version values, deployment metadata, and sanitized recent application errors. Missing permissions or host capabilities produce `Unavailable`; deployment must not weaken private folder permissions to make a metric work.

## Deployment Checklist

- Back up application files and database.
- Set production DB credentials outside source control.
- Import installer or run migrations.
- Set Apache/Nginx document root to `/public`.
- Disable PHP display errors in production.
- Install the reviewed Nginx site based on `docs/nginx-tracs.conf.example`.
- Enable HTTPS.
- Change default admin password.
- Install PHP GD before testing case and shift image attachments.
- Run `config/migrations/2026_05_21_login_hardening.sql` on existing databases to enable failed-login counters and authentication security logs.
- Run `config/migrations/2026_05_21_mandatory_2fa.sql` on existing databases to add mandatory TOTP 2FA fields. Existing users will be forced to set up 2FA on their next successful password login.
- Configure login protection as needed with `TRACS_LOGIN_MAX_FAILED_ATTEMPTS`, `TRACS_LOGIN_WINDOW_MINUTES`, `TRACS_LOGIN_LOCK_MINUTES`, `TRACS_LOGIN_CAPTCHA_AFTER`, `TRACS_SESSION_IDLE_TIMEOUT_MINUTES`, and `TRACS_TRUSTED_PROXIES`. `TRACS_SESSION_IDLE_TIMEOUT_MINUTES` defaults to 2880 and is capped at 2880 minutes / 48 hours.
- Configure 2FA as needed with `TRACS_2FA_ISSUER`, `TRACS_2FA_TIMEOUT_MINUTES`, `TRACS_2FA_MAX_FAILED_ATTEMPTS`, `TRACS_2FA_LOCK_MINUTES`, `TRACS_2FA_VALID_WINDOW_STEPS`, and `TRACS_2FA_SECRET_KEY`. Production deployments should set `TRACS_2FA_SECRET_KEY` to a long random value and keep it stable across deploys.
- Optional CAPTCHA: set `TRACS_CAPTCHA_PROVIDER=turnstile`, `TRACS_TURNSTILE_SITE_KEY`, and `TRACS_TURNSTILE_SECRET_KEY`. If unset, TRACS uses an internal challenge only after suspicious login behavior.
- Run `bin/tracs-notification-worker.php` every minute from cron and rotate its log.
- Verify login, 2FA, case ticket/resolve, reminder/checklist, task assignment sync, shift statuses, MoM, notifications, permissions, and exports.
- Confirm `public/uploads/case_attachments`, `public/uploads/shift_report_attachments`, and `public/uploads/mom` are writable when image evidence is used. Protected case, shift, and MoM images must be served through their API endpoints.
- Confirm protected case, shift, and MoM uploads return `403/404` when requested directly; avatars are the only intended direct-public upload.
- Verify Super Admin can open Server Health & Logs, while Admin, Supervisor, Agent, Intern, and unauthenticated sessions cannot open either the page or API.
- Test TV Mode at normal, fullscreen, smaller, and dark-mode viewports.
- Confirm Infrastructure Pulse is visibly identified as mock/prototype telemetry until a backend exists.
- Preserve [docs/TRACS_SIGNATURE.md](/tracs/docs/TRACS_SIGNATURE.md) and the build-signature metadata when packaging first-deployment artifacts.
- Keep [AI_MEMORY.md](/tracs/AI_MEMORY.md), [ARCHITECTURE.md](/tracs/ARCHITECTURE.md), and [HANDOFF.md](/tracs/HANDOFF.md) with the deployment package.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| DB connection fails in Docker | Rebuild after the Dockerfile `variables_order=EGPCS` config, confirm `db` service is healthy, and inspect `docker compose logs db`. |
| Login redirects repeatedly | Confirm sessions are writable and `public/auth/auth_check.php` is included from a public page after `tracs_start_session()`. |
| Login shows CAPTCHA or lockout | Failed credentials crossed the configured threshold. Wait for `TRACS_LOGIN_LOCK_MINUTES`, solve the challenge, or review User Management → Login Security as an admin/supervisor. |
| Login forces 2FA setup | This is expected after the mandatory 2FA migration until the user scans the QR code and confirms a TOTP code. |
| 2FA code is rejected | Confirm device time sync, `TRACS_2FA_ISSUER`, and that `TRACS_2FA_SECRET_KEY` has not changed since setup. |
| 401 on API calls | User session expired or CSRF token missing/invalid for mutating requests. Reload the page and retry. |
| MoM says tables are not installed | Import current `config/install.sql` or run the MoM schema/migrations on existing DB. |
| User/monitoring pages return Forbidden | Run user-management and task-management migrations, then assign appropriate role permissions. |
| Notifications do not become due | Run `php bin/tracs-notification-worker.php`, then configure one-minute cron and verify CLI DB environment values. |
| Case/shift image upload fails | Confirm GD is installed and upload directories are writable. The current Dockerfile does not install GD. |
| Domain Price old URL is used | Use `domain-price-crosscheck.php`; the underscore route is redirect-only. |
| Assets appear stale | CSS/JS links use filemtime query strings; hard refresh or verify files are mounted into the container. |

## Project Notes

TRACS intentionally remains a no-framework PHP application. Preserve the compact operational layout, restored dashboard stat strip, shared modals, permission-aware navigation, and light/dark consistency unless a redesign is explicitly scoped.

- Dashboard Task Monitoring tabs are `Checklist and Reminder`, `Assignments`, and `Activity`; there is no separate dashboard Reminder tab.
- Domain Transfer Log and Domain Pricing Crosscheck are under `Tasks & Monitoring`.
- Settings are accessed through the avatar menu.
- Infrastructure Pulse is partially implemented and uses mock/session data across its page, dashboard widget, and TV widget.
- The primary UI font stack is `Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif`.

### Shared Frontend UX

- `public/assets/tracs.js` owns global toast, loading-button, friendly-error, lazy-initialization, and Shift Summary reminder helpers. Extend these helpers instead of adding module-specific duplicates.
- Use `showToast(message, type, options)` for new code. The legacy `showToast(type, title, message, options)` and `toast(message, type, duration)` signatures remain supported.
- Use `setButtonLoading`, `resetButtonLoading`, or `withLoadingState` for important actions. Native POST forms receive shared double-submit protection automatically.
- Modal actions should use `context: 'modal'`; page actions default to the page dock. Critical request errors remain visible until dismissed.
- The dashboard Shift Summary reminder uses server time, current shift end time, and current-user submission state. It updates every 30 seconds without a page refresh.

## Login And 2FA Hardening Notes

- Failed login attempts are tracked by normalized email/username input and client IP in `tracs_login_attempts`.
- Defaults: CAPTCHA after 3 failed attempts, temporary lock after 5 failed attempts within 15 minutes, 15-minute lock, and 48-hour idle session timeout.
- Login errors are intentionally generic. The assistance message, “If you are having trouble logging in, please contact your administrator for further assistance.”, is only shown after relevant trouble states such as repeated failures, CAPTCHA, lockout, failed 2FA, or expired pending 2FA sessions.
- Mandatory 2FA uses TOTP authenticator apps. Password verification creates only a temporary pending state; the full authenticated session is created after setup or verification succeeds.
- Session IDs are regenerated after password verification and again after successful 2FA. Protected pages and APIs require the full authenticated state, not merely a password-verified pending session.
- Fully authenticated sessions refresh their idle timer on valid protected page, API, and export activity. Session timing stays in the background and is not displayed in the UI. Users are signed out only after inactivity exceeds the configured idle timeout, capped at 48 hours.
- 2FA secrets are encrypted before storage. The setup QR code and manual key are shown only during initial setup; existing secrets are never shown again.
- Super Admin users can reset another user's 2FA from User Management. The reset clears the stored secret, marks setup required, and writes user and auth audit events.
- Logout is protected by POST + CSRF. Authentication events are recorded in `tracs_auth_events` without passwords, session IDs, CAPTCHA secrets, or CSRF tokens.
- Behind a reverse proxy, set `TRACS_TRUSTED_PROXIES` before trusting `X-Forwarded-For` or Cloudflare client-IP headers. Example: `TRACS_TRUSTED_PROXIES=127.0.0.1,10.0.0.0/8`.
- Rollback: restore backed-up PHP/CSS/docs files, restore the database backup taken before the 2FA migration or clear the new `tracs_users.two_factor_*` columns only after exporting audit history, and keep `TRACS_2FA_SECRET_KEY` unchanged if any encrypted secrets remain.
- Testing checklist: existing and new users are forced through setup, users cannot open dashboard/API/export routes before 2FA, invalid 2FA does not create a session, valid 2FA logs in, 2FA lockout works, Super Admin reset works, non-Super Admin reset is blocked, login error UI is compact/centered, assistance text appears only on trouble states, logout destroys the session, and light/dark/mobile layouts remain clean.

See [docs/SECURITY_AUDIT_2FA.md](/tracs/docs/SECURITY_AUDIT_2FA.md) for the implementation audit, known limitations, and detailed verification checklist.
