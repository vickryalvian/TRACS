# TRACS VPS Security Configuration

This checklist is the recommended production baseline for deploying TRACS on a VPS. It assumes a Linux server running PHP-FPM, a web server, and MySQL/MariaDB.

## Required Services

| Service | Recommended production role |
|---|---|
| Web server | Apache 2.4 with `.htaccess` enabled, or Nginx with equivalent deny/header rules. |
| PHP | PHP 8.2+ with PHP-FPM, `mysqli`, `curl`, `gd`, `mbstring`, `json`, `openssl`, and session support. |
| Database | MySQL 8+ or MariaDB 10.6+ bound to localhost or a private network only. |
| TLS | Let’s Encrypt or another trusted certificate authority with automatic renewal. |
| Firewall | UFW, firewalld, nftables, or cloud firewall rules. |
| Monitoring | System logs, web logs, PHP-FPM logs, TRACS auth/application logs, disk/CPU/RAM, backups, and certificate expiry. |

## Ports

| Port | Exposure | Purpose |
|---|---|---|
| `22/tcp` | Restricted to admin IPs when possible | SSH administration. |
| `80/tcp` | Public | HTTP redirect to HTTPS and ACME renewal. |
| `443/tcp` | Public | HTTPS application traffic. |
| `3306/tcp` | Private only, preferably localhost | MySQL/MariaDB. Do not expose publicly. |
| PHP-FPM socket/port | Local only | Web server to PHP-FPM. |

## First Deploy Checklist

- [ ] Create a dedicated Linux user for TRACS deployment.
- [ ] Set the webroot to `/path/to/tracs/public`.
- [ ] Keep repository root, `config/`, `core/`, `modules/`, `logs/`, `backups/`, and `.env` outside public URL access.
- [ ] Install PHP 8.2+ and required extensions.
- [ ] Create a dedicated database and least-privilege database user.
- [ ] Import schema and migrations.
- [ ] Create `.env` with production values and strict permissions.
- [ ] Set `APP_ENV=production`.
- [ ] Configure HTTPS and redirect all HTTP traffic to HTTPS.
- [ ] Confirm security headers are present on HTTPS responses.
- [ ] Confirm session cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS.
- [ ] Confirm `/uploads/*.php`, backup-named assets, `.env`, Markdown, SQL, logs, and dotfiles return 403 or 404.
- [ ] Confirm Super Admin can log in, complete 2FA, and reset another user’s 2FA for recovery.
- [ ] Confirm normal users cannot access admin/user-management/domain-price/finance/report exports by direct URL unless permitted.
- [ ] Configure backups, log rotation, monitoring, and alerting before production traffic starts.

## File And Folder Permissions

Recommended ownership:

```text
deploy-user:www-data  repository files
www-data:www-data     runtime writable directories only when required
```

Recommended permissions:

```text
find /path/to/tracs -type d -exec chmod 755 {} \;
find /path/to/tracs -type f -exec chmod 644 {} \;
chmod 640 /path/to/tracs/.env
chmod -R 750 /path/to/tracs/logs
chmod -R 750 /path/to/tracs/backups
chmod -R 755 /path/to/tracs/public/uploads
```

Writable directories should be as narrow as possible:
- `public/uploads/avatars`
- `public/uploads/mom`
- `public/cache/holidays` if the TV holiday cache is used
- `logs` if TRACS/PHP writes application logs there

Do not make the full repository writable by the web server user.

## Environment Variables

Required or recommended production values:

```dotenv
APP_ENV=production

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=tracs
DB_USER=tracs_app
DB_PASS=change-this-long-random-password

TRACS_2FA_SECRET_KEY=base64:replace-with-32-byte-random-key
TRACS_2FA_ISSUER=TRACS
TRACS_2FA_TIMEOUT_MINUTES=10
TRACS_2FA_MAX_FAILED_ATTEMPTS=5
TRACS_2FA_LOCK_MINUTES=15
TRACS_2FA_VALID_WINDOW_STEPS=1

TRACS_AUTH_MAX_ATTEMPTS=5
TRACS_AUTH_CAPTCHA_AFTER=3
TRACS_AUTH_LOCK_MINUTES=15
TRACS_AUTH_WINDOW_MINUTES=15
TRACS_AUTH_IDLE_TIMEOUT_MINUTES=480

TRACS_TRUSTED_PROXIES=127.0.0.1
TRACS_LOGIN_HELP_CONTACT=security@example.com
```

Optional when using Cloudflare Turnstile:

```dotenv
TRACS_TURNSTILE_SITE_KEY=...
TRACS_TURNSTILE_SECRET_KEY=...
```

Generate a strong 2FA encryption key once and keep it stable. Rotating `TRACS_2FA_SECRET_KEY` without a migration plan can invalidate stored TOTP secrets.

## Web Server Rules

Apache:
- Set `DocumentRoot` to `/path/to/tracs/public`.
- Enable `AllowOverride All` or at least the override classes required for rewrite/header/access rules.
- Enable `mod_rewrite` and `mod_headers`.
- Keep the repository-root `.htaccess` in place as a secondary safety net.

Nginx:
- Set `root /path/to/tracs/public;`.
- Deny dotfiles, `.env`, `.sql`, `.log`, `.md`, `.bak`, `.backup`, `.old`, `.orig`, `.save`, `.swp`, `.ini`, `.toml`, `.lock`.
- Deny internal folders if the root is ever changed: `/config/`, `/core/`, `/modules/`, `/logs/`, `/backups/`.
- Deny `public/modules` direct access.
- Deny script execution inside `/uploads/`.
- Add equivalent security headers:

```nginx
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "SAMEORIGIN" always;
add_header Referrer-Policy "same-origin" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()" always;
add_header Content-Security-Policy "frame-ancestors 'self'; base-uri 'self'; object-src 'none'" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
```

## Database Security

- Bind MySQL/MariaDB to `127.0.0.1` or a private interface only.
- Do not expose `3306/tcp` publicly.
- Create a dedicated TRACS database user.
- Grant only needed privileges on the TRACS database.
- Use a long random database password stored only in `.env` and the password manager.
- Disable remote root login for MySQL/MariaDB.
- Back up with a dedicated backup user if possible.
- Encrypt database backups before moving them off-server.

## Firewall Baseline

Example UFW baseline:

```bash
ufw default deny incoming
ufw default allow outgoing
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow from <admin-ip> to any port 22 proto tcp
ufw enable
ufw status verbose
```

If SSH must be public, use key-only authentication, Fail2ban, and rate limits. Never expose MySQL publicly.

## SSH Hardening

- [ ] Use SSH keys, not passwords.
- [ ] Disable root login: `PermitRootLogin no`.
- [ ] Disable password login after keys are confirmed: `PasswordAuthentication no`.
- [ ] Restrict SSH users with `AllowUsers` or `AllowGroups`.
- [ ] Limit SSH to trusted IPs in firewall rules when possible.
- [ ] Install and enable Fail2ban or equivalent.
- [ ] Keep the OS patched.
- [ ] Use a non-root deploy user with `sudo` only where needed.
- [ ] Rotate keys when staff changes.

## HTTPS And Cookies

- Use HTTPS for all production traffic.
- Redirect HTTP to HTTPS.
- Enable HSTS after confirming HTTPS is stable.
- If behind Cloudflare, a load balancer, or a reverse proxy, configure `TRACS_TRUSTED_PROXIES` so the app trusts forwarded HTTPS headers only from known proxy IPs.
- Verify in the browser that the PHP session cookie is `Secure`, `HttpOnly`, and `SameSite=Lax`.

## Upload Security

- Keep upload directories under `public/uploads`.
- Allow only expected image types for avatars and MoM screenshots.
- Keep `public/uploads/.htaccess` and `public/uploads/avatars/.htaccess`.
- Deny PHP and executable script extensions in web server config.
- Do not allow user-controlled filenames to become executable paths.
- Periodically scan uploads for unexpected extensions.

## Backup Strategy

- Back up the database at least daily.
- Back up `.env`, uploaded files, and deployment metadata separately from the code repository.
- Encrypt backups before storing them off-server.
- Keep at least one off-server backup location.
- Use retention such as daily for 7 days, weekly for 4 weeks, monthly for 6-12 months.
- Test restore procedures at least quarterly.
- Restrict backup directory permissions to deployment/admin users only.
- Do not store public web-accessible backups under `public/`.

Suggested backup contents:
- Database dump
- `.env`
- `public/uploads`
- Deployment version/commit hash
- Web server virtual host config
- Cron/automation config

## Log Rotation And Monitoring

Rotate and retain:
- Web server access/error logs
- PHP-FPM error logs
- TRACS application logs
- TRACS auth/security events
- Database slow/error logs
- Backup job logs

Monitor and alert on:
- Repeated `login_failed`, `login_lock`, `captcha_challenge`
- Repeated `two_factor_failed`, `two_factor_lock`
- `permission_denied` and `suspicious_access_attempt`
- 5xx spikes
- PHP fatal errors
- Disk usage above 80%
- Memory/CPU saturation
- Database connection failures
- Backup failures
- Certificate expiry within 14 days
- Unexpected files in `public/uploads`

## Future Audit Checklist

- [ ] Run `php -l` against changed PHP files.
- [ ] Verify login, 2FA setup, 2FA verification, logout, and idle timeout.
- [ ] Verify Super Admin 2FA reset.
- [ ] Verify direct URL access for each restricted page with a lower-privilege account.
- [ ] Verify API endpoints return 401 unauthenticated, 403 unauthorized, and no sensitive DB details.
- [ ] Verify invalid object IDs return generic 404 where appropriate.
- [ ] Verify uploads reject executable files and non-image content.
- [ ] Verify backup/config files cannot be fetched over HTTP.
- [ ] Verify HTTPS headers and cookie flags after every web server change.
