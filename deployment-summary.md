# TRACS Deployment Summary

Status: Deployed successfully
Completed: 2026-06-29 08:54 WIB
Domain: https://tracs.vickry.id

## Deployed — Website Screenshot Widget (2026-07-01 ~01:45 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`).

Adds the full-width "Website Screenshot" dashboard widget from branch
`feat/dashboard-website-screenshot-widget`. Operators enter a domain, URL, or
IP and receive a rendered PNG with view (full size) / download / clipboard-copy
actions; submitting a new target replaces the previous image. Backed by the
PageFleets API (`https://api.pagefleets.com/api/v1/screenshot`), proxied
server-side so the bearer key never reaches the browser.

What was applied:

- Code (file-copy deploy; prior versions backed up under
  `backups/screenshot-widget-20260701-014140/`): new
  `public/api/screenshot-capture.php`; modified `public/api/_bootstrap.php`
  (GET + `dashboard.view` maps), `public/index.php`, `public/assets/tracs.js`,
  `public/assets/tracs.css`, `.env.example`.
- Secret: `PAGEFLEETS_API_KEY` added to `/opt/tracs/config/.env` (`.env` backed
  up to `config/.env.bak-screenshot-*`). PHP reads `.env` per request, so no
  reload was required; opcache validates by timestamp.

Verification on production:

- `php -l` clean for all deployed PHP; deployed copies grep-verified to contain
  the feature.
- PHP env loader exposes the key (`len=56`).
- Direct PageFleets call with the live key returns **HTTP 200**, a valid
  `1280×800` PNG.
- TRACS endpoint reachable over HTTPS: unauthenticated request returns a clean
  `application/json` **401** (auth/routing wiring correct; no 404/500, no PHP
  errors logged).
- Remaining user-side check: an authenticated browser capture
  (`input → generate → preview → download/copy`).

The branch remains pushed for review/PR; production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — User Lifecycle Fix (2026-06-30 ~19:55 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`).

Fixes the post-login 404 and the email/username reuse conflict from branch
`feat/user-mgmt-auth-domain-ui-improvements`.

What was applied:

- Code (file-copy deploy, matching the server's manual-deploy workflow; prior
  versions backed up under `backups/user-lifecycle-fix-20260630-195538/`):
  `core/security/auth_hardening.php`, `core/user_management.php`,
  `modules/user-management/controller.php`, `modules/user-management/model.php`.
- Migration `config/migrations/2026_06_30_user_removal_release.sql` applied to
  `vickryid_tracs_alpha` (MariaDB): added `archived_email`, `archived_username`,
  `removed_at`, `removed_by`; ensured `removed` status; granted `dashboard.view`
  to admin/supervisor/agent/viewer/intern.
- `php8.3-fpm` reloaded to clear opcache.

Verification on production:

- Lifecycle harness: **23/23 passed** (create → remove → recreate same
  email/username, history + audit preserved, no orphans).
- HTTPS end-to-end: a fresh agent signs in at `https://tracs.vickry.id` and the
  dashboard (`/index.php`) returns **HTTP 200** (was 404 before the fix).
- Integrity sweep: 0 null emails, 0 duplicate active emails, 0 orphan cases.
- All test accounts removed; no residue.

Production tracks the working tree via file-copy deploys (not a `main` pull), so
the change is live without a `main` merge. The branch remains pushed for review/PR.
See `docs/USER_LIFECYCLE_REMEDIATION.md`.

## 1. Deployment Path

- Application path: `/opt/tracs`
- Branch deployed: `main`
- Commit deployed: `7037c97`
- Public web root: `/opt/tracs/public`

## 2. Runtime Detected

- OS: Ubuntu 24.04.4 LTS
- Web server: Nginx 1.24.0
- PHP runtime: PHP 8.3-FPM
- Database: MariaDB 10.11.14
- Node runtime: upgraded to Node.js 20.20.2 for Vite/Tailwind build compatibility
- Frontend build: `npm run build:calendar`

## 3. Database Configuration Performed

- Database: `vickryid_tracs_alpha`
- Application user: `tracs_app`
- Credentials stored on server in `/opt/tracs/config/.env`
- PHP-FPM `www` pool configured with the TRACS production environment values from `/opt/tracs/config/.env`

## 4. SQL Imported Or Migrations Executed

- Imported fresh schema/data from `/opt/tracs/config/install.sql`
- Database verification showed 40 tables
- Seeded admin user count: 1

## 5. Services Used

- `nginx`: active
- `php8.3-fpm`: active
- `mysql`/`mariadb`: active
- `tracs-notification-worker.timer`: active, runs `/opt/tracs/bin/tracs-notification-worker.php` every minute

## 6. SSL Status

- SSL active via Let's Encrypt
- Certificate path: `/etc/letsencrypt/live/tracs.vickry.id/fullchain.pem`
- Certificate expiry: 2026-09-27
- Certbot auto-renewal timer is active

## 7. Nginx Status

- Nginx config test passed
- HTTP redirects to HTTPS
- HTTPS serves TRACS from `/opt/tracs/public`
- Private paths, env/sql/log files, backups, protected uploads, and helper APIs are denied by Nginx rules

## 8. Warnings

- TRACS code reads database credentials from `$_ENV`; PHP-FPM required explicit pool-level environment configuration.
- Certbot modified the Nginx site to add the HTTPS server and HTTP redirect.
- Default seeded login exists from the installer and should be changed immediately after first login.

## 9. Manual Follow-Up Required

- Log in at `https://tracs.vickry.id/login.php`
- Default seeded login from the repository docs: `admin@tracs.local` / `password`
- Change the default password immediately
- Complete or confirm 2FA setup for production users

## Verification

- `http://tracs.vickry.id/` returns `301` to HTTPS
- `https://tracs.vickry.id/` returns `302` to `/login.php`
- `https://tracs.vickry.id/login.php` loads the TRACS Sign In page
- Database connection is working through PHP-FPM
- Notification worker ran successfully with `status=ok`
