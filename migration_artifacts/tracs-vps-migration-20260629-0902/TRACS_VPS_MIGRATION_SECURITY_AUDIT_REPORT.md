# TRACS VPS Migration, Security Audit, Permission Review, and Post-Migration Report

Date: 2026-06-29  
Timezone: Asia/Jakarta / WIB  
VPS: 103.82.93.75  
Production database: `vickryid_tracs_alpha`  
Production app root: `/opt/tracs`  
Production web root: `/opt/tracs/public`  
Local source database: `tracs_db` in Docker container `tracs_db`

## Migration Summary

| Item | Result |
| --- | --- |
| Start time | 2026-06-29 09:02:16 WIB |
| Production backup time | 2026-06-29 09:09 WIB |
| Import start | 2026-06-29 09:18:16 WIB |
| Import end | 2026-06-29 09:20:18 WIB |
| Final status | Success, with authenticated UI validation pending admin password/TOTP |
| Source engine | MySQL 8.0.46 |
| Destination engine | MariaDB 10.11.14 |
| Source charset/collation | `utf8mb4`, mixed `utf8mb4_unicode_ci` and `utf8mb4_0900_ai_ci` |
| Destination charset/collation | `utf8mb4`, `utf8mb4_general_ci` server default |
| Compatibility action | Replaced unsupported MySQL 8 `utf8mb4_0900_*` collations with `utf8mb4_unicode_ci` in the MariaDB-compatible dump |
| Final database size | 6.297 MB |
| Tables migrated | 77 |
| Foreign keys | 51 |
| Triggers | 0 |
| Views | 2 |
| Routines | 1 |
| Indexes | 395 |
| Source rows | 6,689 exact rows at source count time |
| Destination row comparison | 77/77 tables present; live destination had +5 log/audit rows after app resumed |

## Backup Summary

Production backups were created before import:

`/root/backups/tracs/2026-06-29-0909/`

Files:

- `full.sql`
- `schema-only.sql`
- `data-only.sql`
- `SHA256SUMS`

Backup restore verification:

- Restored `full.sql` into temporary database `tracs_backup_verify_202606290909`.
- Verified 40 production pre-migration tables restored.
- Dropped the temporary verification database after successful restore.

Backup checksums:

```text
9240dcc5273d7628e2e4541ef724646cfe22b68e9390d33fdb9daaba11b5c0fb  data-only.sql
a0526cc19d9f8ba7f6dc8d85e2fd79108a06685a40bab8aa666549f7602d14f0  full.sql
1220a65af17db8b331cf9a70e1d90c14a9a2563430e9a65d1aca137d75149a93  schema-only.sql
```

## Export And Transfer

Local dump files:

- `migration_artifacts/tracs-vps-migration-20260629-0902/local-full.mysql8.sql`
- `migration_artifacts/tracs-vps-migration-20260629-0902/local-full.mariadb-compatible.sql`

Local checksums:

```text
c1f4faff26dbc920f132e608d381342149c8e6610fe30823d200dd9f0dc45a2c  local-full.mysql8.sql
046aa84bcd4908308f68873e9c7823a18ce8ea6be2326d63222be3adafa32a38  local-full.mariadb-compatible.sql
```

Transfer destination:

`/tmp/tracs-migration-20260629-0902/local-full.mariadb-compatible.sql`

Transfer verification:

- Local size: 1,380,586 bytes.
- Remote size: 1,380,586 bytes.
- Local SHA-256: `046aa84bcd4908308f68873e9c7823a18ce8ea6be2326d63222be3adafa32a38`.
- Remote SHA-256: `046aa84bcd4908308f68873e9c7823a18ce8ea6be2326d63222be3adafa32a38`.

## Import Verification

Safety checks:

- Transferred dump restored successfully into temporary MariaDB database `tracs_import_verify_202606290902`.
- Verification import produced 77 tables, 51 foreign keys, 2 views, 1 routine, 0 triggers.
- `php8.3-fpm` was stopped during the production replacement window to prevent app writes.
- Production database was recreated and imported from the verified dump.
- `php8.3-fpm` was restarted and confirmed active.

Final destination object counts:

```text
tables    77
fks       51
triggers  0
views     2
routines  1
indexes   395
size_mb   6.297
```

Row-count comparison:

- 77 source tables matched 77 destination tables.
- No missing tables.
- No extra tables.
- Three tables changed after app resume because live login/notification activity added rows:
  - `tracs_auth_events`: 171 -> 172
  - `tracs_login_attempts`: 11 -> 12
  - `tracs_notification_logs`: 48 -> 51

## Application Validation

Completed:

- `/login.php` returns HTTP 200.
- `/index.php` returns HTTP 302 to `/login.php` for unauthenticated requests.
- `tracs.js` returns HTTP 200 with `application/javascript`.
- `tracs.css` returns HTTP 200 with `text/css`.
- `config/database.php` now loads `config/env.php` directly and connects to `vickryid_tracs_alpha`.
- PHP syntax checks passed for:
  - `/opt/tracs/config/database.php`
  - `/opt/tracs/public/index.php`
  - `/opt/tracs/public/login.php`
- `admin@tracs.local` exists, is active, has role `admin`, and has confirmed 2FA enabled.
- Notification worker recovered after DB grant adjustment and logged `status=ok created=0`.

Pending:

- Full browser login with `admin@tracs.local` requires the current admin password and current 2FA code, or explicit approval to reset admin credentials.
- Authenticated dashboard, reports, user-management, and API workflow validation remains pending for the same reason.

## UI Fixes Applied

Files changed locally and deployed to production:

- `config/database.php`
- `public/assets/tracs.js`
- `public/assets/tracs.css`

Fixes:

- `config/database.php` now loads `config/env.php` itself so CLI, dashboard, and auth paths consistently read `/opt/tracs/config/.env`.
- Login toast routing now creates a dedicated `.toast-dock--login` inside `.login-shell`, inserted immediately above `.login-card`.
- Login toast CSS overrides the global fixed top-right toast placement so login notifications stay above the sign-in box.
- Shift handover dashboard panel header now wraps title, metadata, and actions to prevent overlap in the narrow dashboard column.

Verification:

- `node --check public/assets/tracs.js` passed locally.
- PHP lint passed locally and on production.
- Production login page loaded updated assets with version `1782700877`.
- Static checks confirm login toast routing and shift-handover wrapping CSS are present.

## Security Findings

### Critical

| Finding | Impact | Fix applied | Remaining action |
| --- | --- | --- | --- |
| VPS SSH password was shared in the task context. | Anyone with access to the conversation/history could attempt VPS login until the password is rotated. | No password rotation was performed because that can lock out users if not coordinated. | Rotate VPS user passwords immediately. Prefer SSH keys and disable password auth after confirming key access. |

### High

| Finding | Impact | Fix applied | Remaining action |
| --- | --- | --- | --- |
| SSH `PermitRootLogin` was `yes`. | Root account could be targeted directly. | Created `/etc/ssh/sshd_config.d/99-tracs-hardening.conf`, set `PermitRootLogin no`, validated with `sshd -t`, reloaded SSH. | Confirm root password is rotated even though direct root SSH is disabled. |
| SSH `PasswordAuthentication` remains `yes`. | Password brute-force and credential reuse risk remains. | Fail2ban is active; `MaxAuthTries` reduced to 3 and `LoginGraceTime` reduced to 30. | Disable password auth after confirming key-based access for required admins. |
| Automated probes attempted to fetch `.env`, `.git/config`, config files, and backups. | Public secret/config exposure would be severe if Nginx rules failed. | Existing Nginx deny rules blocked these requests. App backups and `.git` were additionally made inaccessible to `www-data`. | Continue monitoring logs and keep deny rules under change control. |

### Medium

| Finding | Impact | Fix applied | Remaining action |
| --- | --- | --- | --- |
| App DB user originally had `ALL PRIVILEGES` on the production schema. | Compromise of app credentials would allow unrestricted schema operations. | Reduced to schema-scoped privileges: `SELECT`, `INSERT`, `UPDATE`, `DELETE`, `CREATE`, `INDEX`, `ALTER`, `CREATE TEMPORARY TABLES`, `EXECUTE`, `SHOW VIEW`. | Long-term code improvement: remove runtime schema mutation checks, then remove `CREATE`/`ALTER`/`INDEX`. |
| First DB hardening pass removed needed DDL privileges. | Notification worker logged schema unavailable errors. | Restored only required schema-scoped DDL privileges; worker then logged `status=ok`. | Track this as a code-level hardening backlog item. |
| `config/database.php` depended on external env bootstrap order. | CLI or page include-order changes could connect to the wrong DB fallback. | `database.php` now directly includes `config/env.php`. | Keep this in repo and deployment baseline. |
| App tree and historical app backups were broadly readable by group before hardening. | Increased blast radius if web user or another service account is compromised. | Tightened ownership and modes across `/opt/tracs`. | Continue reviewing writable paths after future deployments. |

### Low

| Finding | Impact | Fix applied | Remaining action |
| --- | --- | --- | --- |
| MySQL 8 dump used collations unsupported by MariaDB. | Import would fail on `utf8mb4_0900_*`. | Created MariaDB-compatible dump with `utf8mb4_unicode_ci`. | Prefer MariaDB-compatible collation in schema going forward if production remains MariaDB. |
| Production worker service reports `inactive` after run. | Could be expected for one-shot scheduling, but should be confirmed. | Restarted manually and observed successful log entry. | Confirm intended scheduling mechanism: systemd timer, supervisor, or cron. |

## Permission Changes

Changed on production:

| Path | Final state | Reason |
| --- | --- | --- |
| `/root/backups` | `700 root:root` | Restrict production database backups. |
| `/root/backups/tracs` | `700 root:root` | Restrict production database backups. |
| `/root/backups/tracs/2026-06-29-0909` | `700 root:root` | Restrict production database backups. |
| `/root/backups/tracs/2026-06-29-0909/*.sql` | `600 root:root` | Prevent backup disclosure. |
| `/opt/tracs` | `750 vickry:www-data` | App owner can manage; web group can traverse. |
| `/opt/tracs/config/.env` | `640 vickry:www-data` | Keep secrets non-world-readable while allowing PHP-FPM read. |
| `/opt/tracs/config/database.php` | `640 vickry:www-data` | Config is non-world-readable. |
| `/opt/tracs/logs` | `770 vickry:www-data` | Runtime writable by owner/web group only. |
| `/opt/tracs/storage` | `770 vickry:www-data` | Runtime writable by owner/web group only. |
| `/opt/tracs/public/cache` | `770 vickry:www-data` | Runtime cache writable by owner/web group only. |
| `/opt/tracs/public/uploads` | `770 vickry:www-data` | Upload runtime writable by owner/web group only. |
| `/opt/tracs/public` | `755 vickry:www-data` | Public document root readable by Nginx/PHP. |
| `/opt/tracs/public/assets` | dirs `755`, files `644` | Static assets publicly readable. |
| `/opt/tracs/.git` | `vickry:vickry`, `go-rwx` | Prevent web group and world access to Git metadata. |
| `/opt/tracs/backups` | `700 vickry:vickry` | Prevent web group and world access to app backups. |
| `/opt/tracs/backup` | `700 vickry:vickry` | Prevent web group and world access to app backups. |

System files checked:

- `/etc/nginx/sites-available/tracs`: `644 root:root`
- `/etc/systemd/system/tracs-notification-worker.service`: `644 root:root`

## Credentials Review

### PASSWORDS / ACCESS THAT MUST BE UPDATED

| Credential/access | Risk level | Why rotation is recommended | Priority |
| --- | --- | --- | --- |
| VPS root password | Critical | Root login was previously permitted and root credentials may exist outside managed vaults. | Immediate |
| VPS `vickry` password | Critical | Password was provided in task context and SSH password auth remains enabled. | Immediate |
| Any other VPS user passwords | High | Password auth remains enabled; shared or reused credentials are high risk. | High |
| Database root/admin password | High | Root DB access can fully control production data. Rotate after migration and store in vault. | High |
| Database `tracs_app` password | High | Present in production `.env`; compromise allows app-level DB access. | High |
| SSH passwords | Critical | Same reason as VPS password exposure and password-auth brute-force risk. | Immediate |
| API keys | Medium | No API key values were printed, but any `.env` secrets should be rotated after an access-sharing event. | Medium |
| SMTP credentials | Medium | No SMTP keys were observed in the visible env key list, but rotate if configured elsewhere. | Medium |
| Third-party service credentials | Medium | Rotate any keys in deployment configs or external service dashboards. | Medium |
| GitHub deploy credentials | High | If deploy keys/tokens exist on the VPS or in CI, rotate after production access sharing. | High |
| `APP_URL` / app config values | Low | Not secret by itself, but validate expected production domain. | Low |
| Admin web account `admin@tracs.local` | High | Account is active with 2FA enabled; current password is not the default seed. Rotate after validating access. | High |

Observed `.env` secret-bearing keys:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `APP_URL`

## Final Verification Checklist

| Check | Status | Notes |
| --- | --- | --- |
| Database operational | Pass | `config/database.php` connects to `vickryid_tracs_alpha`. |
| Application public login page operational | Pass | `/login.php` returns HTTP 200. |
| Unauthenticated dashboard guard | Pass | `/index.php` returns HTTP 302 to `/login.php`. |
| Backups verified | Pass | Pre-migration full backup restored into temporary DB. |
| Import verified | Pass | Temporary MariaDB restore succeeded before production import. |
| Object counts verified | Pass | 77 tables, 51 FKs, 395 indexes, 2 views, 1 routine. |
| Row counts compared | Pass with expected live drift | Only log/audit tables gained rows after app resume. |
| Permissions verified | Pass | Sensitive dirs locked; runtime dirs writable only where required. |
| Security audit completed | Pass | DB, Linux, app paths, firewall, SSH, logs reviewed. |
| Authenticated login test | Pending | Needs current admin password and 2FA code, or explicit reset approval. |
| Dashboard visual validation | Partial | CSS fix deployed; authenticated visual check pending credentials. |
| Remaining risks | Present | SSH password auth still enabled; credential rotation pending. |

## Files Modified

Local repository:

- `config/database.php`
- `public/assets/tracs.js`
- `public/assets/tracs.css`
- `migration_artifacts/tracs-vps-migration-20260629-0902/TRACS_VPS_MIGRATION_SECURITY_AUDIT_REPORT.md`

Production:

- `/etc/ssh/sshd_config.d/99-tracs-hardening.conf`
- `/opt/tracs/config/database.php`
- `/opt/tracs/public/assets/tracs.js`
- `/opt/tracs/public/assets/tracs.css`
- Database grants for `tracs_app`@`localhost`
- File and directory permissions listed above

## Redacted Command Log

Passwords and secret values are intentionally redacted.

```bash
pwd
rg --files -g '.env*' -g 'docker-compose*' -g 'package.json' -g 'composer.json' -g 'artisan' -g 'config/database.php' -g '*.sql' -g 'README*'
git status --short --branch
date '+%Y-%m-%d %H:%M:%S %Z'
sed -n '1,220p' config/database.php
sed -n '1,220p' docker-compose.yml
node -e '<redacted env key printer>'
which mysql || true
which mysqldump || true
which mariadb || true
which sshpass || true
which ssh || true
which rsync || true
docker ps --format 'table {{.Names}}\t{{.Image}}\t{{.Status}}\t{{.Ports}}'
which expect || true
python3 - <<'PY' '<pexpect availability check>' PY
ls -ld . logs backups backup config frontend api
find . -maxdepth 2 -type d \( -name storage -o -name uploads -o -name cache -o -name logs -o -name backups \) -print
ssh vickry@103.82.93.75 '<read-only OS/db/app discovery>'
sudo mysql -NBe 'SELECT VERSION(), @@character_set_server, @@collation_server, @@default_storage_engine;'
sudo mysql -NBe 'SHOW DATABASES;'
sudo mysql -NBe 'SELECT user,host,plugin FROM mysql.user ORDER BY user,host;'
sudo sed -n '1,220p' /etc/nginx/sites-available/tracs
sudo find /opt/tracs -maxdepth 2 -printf '%M %u %g %p\n'
docker exec tracs_db mysql -uroot -p'<redacted>' -NBe '<source preflight metadata queries>'
sudo mysql -NBe '<destination preflight metadata queries>'
sudo mysqldump --single-transaction --routines --triggers --events --hex-blob vickryid_tracs_alpha > /root/backups/tracs/2026-06-29-0909/full.sql
sudo mysqldump --single-transaction --no-data --routines --triggers --events vickryid_tracs_alpha > /root/backups/tracs/2026-06-29-0909/schema-only.sql
sudo mysqldump --single-transaction --no-create-info --skip-triggers --hex-blob vickryid_tracs_alpha > /root/backups/tracs/2026-06-29-0909/data-only.sql
sudo sha256sum /root/backups/tracs/2026-06-29-0909/*.sql > /root/backups/tracs/2026-06-29-0909/SHA256SUMS
sudo mysql -e 'DROP DATABASE IF EXISTS tracs_backup_verify_202606290909; CREATE DATABASE tracs_backup_verify_202606290909 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
sudo mysql tracs_backup_verify_202606290909 < /root/backups/tracs/2026-06-29-0909/full.sql
sudo mysql -e 'DROP DATABASE tracs_backup_verify_202606290909;'
docker exec tracs_db mysqldump -uroot -p'<redacted>' --single-transaction --routines --triggers --events --hex-blob --default-character-set=utf8mb4 --no-tablespaces tracs_db > local-full.mysql8.sql
perl -pe 's/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g; s/utf8mb4_0900_as_ci/utf8mb4_unicode_ci/g' local-full.mysql8.sql > local-full.mariadb-compatible.sql
shasum -a 256 local-full.mysql8.sql local-full.mariadb-compatible.sql > SHA256SUMS.local
docker exec tracs_db mysql -uroot -p'<redacted>' -e 'DROP DATABASE IF EXISTS tracs_local_dump_verify; CREATE DATABASE tracs_local_dump_verify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
docker exec -i tracs_db mysql -uroot -p'<redacted>' tracs_local_dump_verify < local-full.mariadb-compatible.sql
docker exec tracs_db mysql -uroot -p'<redacted>' -e 'DROP DATABASE tracs_local_dump_verify;'
scp local-full.mariadb-compatible.sql vickry@103.82.93.75:/tmp/tracs-migration-20260629-0902/
sha256sum /tmp/tracs-migration-20260629-0902/local-full.mariadb-compatible.sql
wc -c /tmp/tracs-migration-20260629-0902/local-full.mariadb-compatible.sql
sudo mysql -e 'DROP DATABASE IF EXISTS tracs_import_verify_202606290902; CREATE DATABASE tracs_import_verify_202606290902 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
sudo mysql tracs_import_verify_202606290902 < /tmp/tracs-migration-20260629-0902/local-full.mariadb-compatible.sql
sudo mysql -e 'DROP DATABASE tracs_import_verify_202606290902;'
systemctl stop php8.3-fpm
mysql -e 'DROP DATABASE IF EXISTS vickryid_tracs_alpha; CREATE DATABASE vickryid_tracs_alpha CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
mysql vickryid_tracs_alpha < /tmp/tracs-migration-20260629-0902/local-full.mariadb-compatible.sql
systemctl start php8.3-fpm
systemctl is-active php8.3-fpm
mysql -N vickryid_tracs_alpha < /tmp/tracs-migration-20260629-0902/row-counts.sql > /tmp/tracs-migration-20260629-0902/dest-row-counts.tsv
scp vickry@103.82.93.75:/tmp/tracs-migration-20260629-0902/dest-row-counts.tsv migration_artifacts/tracs-vps-migration-20260629-0902/dest-row-counts.tsv
sudo mysql -NBe '<database security audit queries>'
sshd -T | egrep '^(permitrootlogin|passwordauthentication|pubkeyauthentication|x11forwarding|maxauthtries|logingracetime) '
ufw status verbose
systemctl is-active fail2ban
fail2ban-client status
ss -tulpn
systemctl list-units --type=service --state=running --no-pager
cat > /etc/ssh/sshd_config.d/99-tracs-hardening.conf
sshd -t
systemctl reload ssh
mysql -e 'REVOKE ALL PRIVILEGES, GRANT OPTION FROM tracs_app@localhost; GRANT ... ON vickryid_tracs_alpha.* TO tracs_app@localhost; FLUSH PRIVILEGES;'
chown -R vickry:www-data /opt/tracs
find /opt/tracs -type d -exec chmod 750 {} +
find /opt/tracs -type f -exec chmod 640 {} +
chmod -R 770 /opt/tracs/logs /opt/tracs/storage /opt/tracs/public/cache /opt/tracs/public/uploads
chown -R vickry:vickry /opt/tracs/.git /opt/tracs/backups /opt/tracs/backup
chmod -R go-rwx /opt/tracs/.git /opt/tracs/backups /opt/tracs/backup
chmod 755 /opt/tracs/public
find /opt/tracs/public/assets -type d -exec chmod 755 {} +
find /opt/tracs/public/assets -type f -exec chmod 644 {} +
chmod 700 /root/backups /root/backups/tracs /root/backups/tracs/2026-06-29-0909
find /root/backups/tracs/2026-06-29-0909 -type f -exec chmod 600 {} +
sed -n '1,260p' public/login.php
sed -n '1,260p' public/index.php
sed -n '<relevant ranges>' public/assets/tracs.js public/assets/tracs.css public/assets/tracs-spacing.css
apply_patch config/database.php
apply_patch public/assets/tracs.js
apply_patch public/assets/tracs.css
node --check public/assets/tracs.js
docker exec tracs_app php -l /var/www/html/config/database.php
docker exec tracs_app php -l /var/www/html/public/login.php
docker exec tracs_app php -l /var/www/html/public/index.php
scp config/database.php public/assets/tracs.js public/assets/tracs.css vickry@103.82.93.75:/tmp/tracs-ui-fix-20260629/
sudo cp /tmp/tracs-ui-fix-20260629/database.php /opt/tracs/config/database.php
sudo cp /tmp/tracs-ui-fix-20260629/tracs.js /opt/tracs/public/assets/tracs.js
sudo cp /tmp/tracs-ui-fix-20260629/tracs.css /opt/tracs/public/assets/tracs.css
sudo chown vickry:www-data /opt/tracs/config/database.php /opt/tracs/public/assets/tracs.js /opt/tracs/public/assets/tracs.css
sudo chmod 640 /opt/tracs/config/database.php
sudo chmod 644 /opt/tracs/public/assets/tracs.js /opt/tracs/public/assets/tracs.css
curl -k -I https://tracs.vickry.id/login.php
curl -k -I https://tracs.vickry.id/index.php
curl -k -I https://tracs.vickry.id/assets/tracs.js
curl -k -I https://tracs.vickry.id/assets/tracs.css
mysql -e 'GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, CREATE TEMPORARY TABLES, EXECUTE, SHOW VIEW ON vickryid_tracs_alpha.* TO tracs_app@localhost; FLUSH PRIVILEGES;'
systemctl restart tracs-notification-worker.service
tail -20 /opt/tracs/logs/notification-worker.log
stat -c '%A %U %G %n' '<audited production paths>'
apply_patch migration_artifacts/tracs-vps-migration-20260629-0902/TRACS_VPS_MIGRATION_SECURITY_AUDIT_REPORT.md
```

Notes:

- Several early SSH/SQL probes failed because local shell or Expect expanded remote variables before execution. Those failed probes were read-only or no-op; production changes were applied only after backup verification.
- The intentionally redacted command log omits plain-text passwords and secret values.
