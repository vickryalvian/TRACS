# TRACS VPS Security Configuration

Production baseline for Ubuntu 24.04 LTS. TRACS can run behind Nginx or Apache, but the recommended VPS shape is Nginx + PHP-FPM + MySQL/MariaDB. Docker is documented for local development only.

## Required Services

| Service | Production baseline |
| --- | --- |
| OS | Ubuntu 24.04 LTS with current security updates. |
| Web | Nginx, or Apache 2.4 with equivalent deny/header rules. |
| PHP | PHP 8.2+ PHP-FPM with `mysqli`, `curl`, `gd`, `mbstring`, `json`, `openssl`, and sessions. |
| Database | MySQL 8+ or MariaDB 10.6+, localhost/private bind only. |
| TLS | Let's Encrypt or another trusted CA with automatic renewal. |
| Firewall | UFW plus cloud firewall where available. |
| Abuse protection | Fail2ban for SSH and relevant web/auth patterns. |
| Updates | `unattended-upgrades` for security updates. |
| Scheduler | Cron for `bin/tracs-notification-worker.php`. |

## Network Exposure

| Port | Exposure | Purpose |
| --- | --- | --- |
| `22/tcp` | Trusted admin IPs where possible | SSH. |
| `80/tcp` | Public | Redirect to HTTPS and ACME. |
| `443/tcp` | Public | TRACS HTTPS. |
| `3306/tcp` | Local/private only | MySQL/MariaDB. Never public. |
| PHP-FPM socket | Local only | Web server to PHP. |

## Initial Server Hardening

```bash
sudo apt update
sudo apt full-upgrade -y
sudo apt install -y nginx mysql-server php-fpm php-mysql php-curl php-gd php-mbstring php-xml php-opcache unzip ufw fail2ban unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades
```

- Create a non-root deploy user.
- Install and verify SSH keys before disabling password login.
- Set `PermitRootLogin no` and `PasswordAuthentication no`.
- Restrict SSH with `AllowUsers`/`AllowGroups` and firewall source rules.
- Enable and review Fail2ban jails.
- Reboot after kernel/security updates when required.

## UFW Baseline

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow from <admin-ip> to any port 22 proto tcp
sudo ufw enable
sudo ufw status verbose
```

Do not open MySQL publicly. If SSH cannot be IP-restricted, use key-only auth, Fail2ban, and conservative rate limits.

## Deployment Layout

- Deploy the repository outside a publicly browsable parent.
- Set web root to `/path/to/tracs/public`.
- Keep `config/`, `core/`, `modules/`, `logs/`, backups, SQL, Markdown, and environment files outside URL access.
- Keep only runtime upload/cache/log directories writable.
- Do not run PHP-FPM or deployment commands as root.

Recommended ownership:

```text
deploy-user:www-data  application files
www-data:www-data     runtime directories only where writes are required
```

Baseline permissions:

```bash
sudo find /path/to/tracs -type d -exec chmod 755 {} \;
sudo find /path/to/tracs -type f -exec chmod 644 {} \;
sudo chmod 640 /path/to/tracs/.env
sudo chmod 750 /path/to/tracs/deploy.sh
```

`deploy.sh` then applies the scoped exceptions below and verifies them after sync:

| Path | Owner:group | Directories | Files | Access reason |
| --- | --- | ---: | ---: | --- |
| Application source | `deploy-user:www-data` | `755` | `644` | PHP reads source; web user cannot modify it |
| `.env`, `config/.env`, `config/database.php` | `deploy-user:www-data` | parent baseline | `640` | PHP reads private configuration |
| `public/uploads` and declared upload subfolders | `www-data:www-data` | `750` | `640` | PHP writes validated uploads |
| `public/cache` and `public/cache/holidays` | `www-data:www-data` | `750` | `640` | PHP writes generated holiday cache |
| `logs` | `www-data:www-data` | `750` | `640` | PHP/application logs |
| `storage`, `storage/deployment` | `deploy-user:www-data` | `750` | `640` | PHP reads deployment metadata but cannot write it |
| External backup root | `deploy-user:deploy-group` | `700` | `600` | Deployment-only backup access |
| `deploy.sh` | `deploy-user:www-data` | n/a | `750` | Restricted execution |

No folder or file may use mode `777`. Runtime folders use owner-write access rather than broad group/world write access.

## Environment

`config/database.php` reads database values from PHP `$_ENV`. Ensure PHP-FPM receives the variables; a root `.env` file is not automatically parsed by the application.

```dotenv
APP_ENV=production

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=tracs
DB_USER=tracs_app
DB_PASS=replace-with-a-long-random-password

TRACS_LOGIN_MAX_FAILED_ATTEMPTS=5
TRACS_LOGIN_WINDOW_MINUTES=15
TRACS_LOGIN_LOCK_MINUTES=15
TRACS_LOGIN_CAPTCHA_AFTER=3
TRACS_SESSION_IDLE_TIMEOUT_MINUTES=2880

TRACS_2FA_SECRET_KEY=base64:replace-with-a-stable-32-byte-key
TRACS_2FA_ISSUER=TRACS
TRACS_2FA_TIMEOUT_MINUTES=10
TRACS_2FA_MAX_FAILED_ATTEMPTS=5
TRACS_2FA_LOCK_MINUTES=15
TRACS_2FA_VALID_WINDOW_STEPS=1

TRACS_TRUSTED_PROXIES=127.0.0.1
```

Optional Turnstile:

```dotenv
TRACS_CAPTCHA_PROVIDER=turnstile
TRACS_TURNSTILE_SITE_KEY=...
TRACS_TURNSTILE_SECRET_KEY=...
```

Do not rotate `TRACS_2FA_SECRET_KEY` without a migration plan for existing encrypted TOTP secrets.

## Nginx Baseline

Use [docs/nginx-tracs.conf.example](docs/nginx-tracs.conf.example) as the reviewed template. It sets `public/` as root, disables directory listing, limits request bodies, denies hidden/sensitive/backup files, blocks direct `includes/`, `modules/`, and API helpers, denies all protected uploads, permits only generated avatar image names, and places upload deny rules before the PHP handler.

Adjust the PHP-FPM socket to the installed version. If Apache is used, set `DocumentRoot` to `public/`, enable rewrite/headers, and preserve the repository/public `.htaccess` protections.

## PHP-FPM

- `display_errors=Off`
- `log_errors=On`
- Set a restricted `error_log`.
- Set upload/body limits above the application's per-file limit only as needed.
- Keep OPcache enabled.
- Expose required environment variables to the FPM pool or service manager.
- Restart PHP-FPM after configuration changes.

## Database

- Bind to `127.0.0.1` or a private interface.
- Run `mysql_secure_installation` or equivalent hardening.
- Create a dedicated TRACS database/user with privileges only on that database.
- Disable remote database root login.
- Import `config/install.sql` for fresh installs or apply dated migrations after backup.
- Encrypt off-server database backups.
- Monitor connection errors, slow queries, and disk capacity.

## Notification Worker

Run once per minute:

```cron
* * * * * /usr/bin/php /path/to/tracs/bin/tracs-notification-worker.php >> /path/to/tracs/logs/notification-worker.log 2>&1
```

- Run as the deploy/application user, not root.
- Keep the log outside public access and rotate it.
- Alert when the worker stops, repeatedly fails, or creates unexpected volumes.
- Verify the PHP CLI receives the same DB and security environment as PHP-FPM.

## Upload Security

- Accept only application-supported image MIME types.
- Confirm GD is installed so case/shift uploads can be decoded and re-encoded.
- Deny script execution under every upload directory.
- Use controlled stored filenames; never execute or include user uploads.
- Serve protected case/shift images through their API endpoints.
- Back up uploads separately from source code.
- Monitor unexpected extension, file count, and disk growth.

## TLS And Proxying

- Redirect all HTTP traffic to HTTPS.
- Enable certificate renewal and expiry alerts.
- Enable HSTS only after HTTPS is stable.
- If Cloudflare/load balancer is used, set `TRACS_TRUSTED_PROXIES` only to actual proxy addresses/ranges.
- Verify the browser session cookie is `Secure`, `HttpOnly`, and `SameSite=Lax`.

## Backups

Back up:

- Database
- deployment environment/secrets
- `public/uploads`
- web server and PHP-FPM configuration
- cron/systemd units
- deployment commit/version

Use encrypted off-server storage, retention such as 7 daily/4 weekly/6-12 monthly, restricted permissions, and quarterly restore tests.

## Logging And Monitoring

Rotate and monitor:

- Nginx/Apache access and error logs
- PHP-FPM logs
- TRACS application logs
- authentication/security events
- notification worker log
- database error/slow logs
- Fail2ban and SSH auth logs
- backup and certificate-renewal logs

Alert on repeated login/2FA failures, permission denials, 5xx spikes, PHP fatals, DB failures, worker failure, backup failure, certificate expiry, disk above 80%, and unexpected uploads.

### Server Health & Logs

The in-app route is `/server-health.php`; data comes from `/api/server-health.php`. Both require a fully authenticated exact `super_admin` role. Admin, Supervisor, Agent, Intern, Viewer, pending-2FA, and unauthenticated users are blocked. The API rejects every query parameter, including `path=../../`, accepts no command input, returns JSON only, and rate-limits refreshes to once every five seconds per session.

Allowed metrics:

| Metric | Safe source |
| --- | --- |
| CPU usage | Two bounded reads from `/proc/stat` |
| RAM usage | `/proc/meminfo` |
| Disk used/free | PHP `disk_total_space()` / `disk_free_space()` on the TRACS root |
| TRACS/uploads/logs size | Bounded non-symlink traversal of fixed project paths |
| Backups size | Fixed local `backups/` or default `/var/backups/tracs`; unavailable if private permissions prevent reading |
| Database size | Fixed `information_schema` aggregate for the current database |
| Uptime | `/proc/uptime` |
| PHP version | `PHP_VERSION` |
| MySQL/MariaDB version | Fixed `SELECT VERSION()` with version-only parsing |
| Nginx version | Version-only parsing from server software, otherwise unavailable |
| Last deploy/version/commit | `storage/deployment/deployment.meta` written by `deploy.sh` |
| Error log summary | Fixed `logs/error.log`, bounded tail, aggressive redaction |

Monitored paths:

| Label | Fixed path relationship |
| --- | --- |
| TRACS folder | `WEB_ROOT` |
| Uploads | `WEB_ROOT/public/uploads` |
| Logs | `WEB_ROOT/logs` |
| Deployment metadata | `WEB_ROOT/storage/deployment/deployment.meta` |
| Backups | `BACKUP_DIR` default `/var/backups/tracs`; normally unavailable to PHP because it remains `700/600` |

Thresholds are Healthy below `70%`, Warning from `70%` through `84.9%`, and Critical at `85%` or above. Folder size progress is relative to the TRACS filesystem capacity, not an upload quota.

Security limitations:

- Linux `/proc` metrics are unavailable on hosts without readable `/proc`.
- Nginx version is unavailable when the server software value is hidden.
- Private external backups should normally remain unavailable to PHP. Do not grant access merely to populate the card.
- The error panel is not a raw log viewer. SQL, stack, credential, path, IP, URL, and email details are redacted.
- Folder scans stop after the fixed time/entry budget and return `Unavailable` rather than hanging.

## Post-Deploy Verification

- [ ] Login, mandatory 2FA, logout, and idle timeout work.
- [ ] Dashboard and stat strip load without stale assets.
- [ ] Database connection and migrations are correct.
- [ ] Cases open the shared ticket and Resolve works by permission.
- [ ] Case and shift uploads work and cannot execute scripts.
- [ ] Task assignment creates linked checklist/reminder records as expected.
- [ ] Notification worker creates due reminders without duplicates.
- [ ] Browser permission granted/denied paths both remain usable.
- [ ] Shift handover reminder timing is correct.
- [ ] Domain Price canonical and legacy redirect routes work.
- [ ] Infrastructure Pulse clearly displays mock/prototype state.
- [ ] TV Mode loads in normal, fullscreen, smaller viewport, and dark mode.
- [ ] Restricted pages/APIs fail correctly for lower-privilege roles.
- [ ] Super Admin can open `/server-health.php` and refresh `/api/server-health.php`.
- [ ] Admin, Supervisor, Agent, Intern, Viewer, and unauthenticated requests cannot open either monitoring route.
- [ ] Path traversal such as `/api/server-health.php?path=../../` returns `400` and no filesystem data.
- [ ] Rapid monitoring refresh returns `429`.
- [ ] Missing metrics render `Unavailable` without changing folder permissions.
- [ ] Sanitized logs contain no filesystem path, SQL text, credentials, token, cookie, internal IP, or stack trace.
- [ ] `.env`, SQL, Markdown, logs, backups, and repository internals return 403/404.
- [ ] Backup and restore procedures have been tested.
