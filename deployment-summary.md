# TRACS Deployment Summary

Status: Deployed successfully
Completed: 2026-06-29 08:54 WIB
Domain: https://tracs.vickry.id

## Pending Deploy — User Lifecycle Fix (2026-06-30)

Branch `feat/user-mgmt-auth-domain-ui-improvements` fixes the post-login 404 and
the email/username reuse conflict. Production (installed from `install.sql`) has
both defects until this is merged to `main` and the migration is applied. Run on
the VPS after merging:

```bash
cd /opt/tracs
git fetch origin && git checkout main && git pull
REPO_DIR=/opt/tracs WEB_ROOT=/opt/tracs \
  ./deploy.sh deploy --with-migration config/migrations/2026_06_30_user_removal_release.sql --yes
```

The migration is idempotent. After it runs, verify a non–super-admin user can log
in (lands on the dashboard, no 404) and that a removed user's email can be reused.
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
