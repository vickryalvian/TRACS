# TRACS Security Audit Checklist

Documentation review date: 2026-06-06
Review basis: static implementation inspection. Production configuration and end-to-end penetration testing remain deployment responsibilities.

Status:

- `Implemented`: control exists in code.
- `Verify`: control exists but must be tested in the target environment.
- `Needs Improvement`: known gap or incomplete hardening.
- `Planned`: not implemented.

## Authentication

| Check | Status | Evidence / required verification |
| --- | --- | --- |
| Generic login failure | Implemented | Invalid user/password and lockout paths avoid account enumeration. |
| Unknown-user timing defense | Implemented | Dummy password hash is verified when no account matches. |
| Failed-attempt tracking | Implemented | `tracs_login_attempts` tracks normalized identifier hash and IP. |
| Temporary lockout | Implemented | `TRACS_LOGIN_MAX_FAILED_ATTEMPTS`, window, and lock duration are configurable. |
| CAPTCHA escalation | Implemented | Internal challenge fallback; Turnstile when provider and keys are configured. |
| Mandatory TOTP 2FA | Implemented | Password creates pending auth only; full session follows setup/verification. |
| 2FA failure lock | Implemented | Account counters and temporary lock are present. |
| 2FA secret encryption | Implemented | Production must set and preserve `TRACS_2FA_SECRET_KEY`. |
| Super Admin 2FA reset | Implemented | Server-side role restriction and audit events. |
| Password policy | Implemented | Minimum length, obvious-password rejection, and same-password prevention. |
| Breached-password check | Planned | No outbound breach-password service is connected. |

## Session And CSRF

- [ ] Verify session ID changes after password verification.
- [ ] Verify session ID changes again after successful 2FA.
- [ ] Verify pending-2FA sessions cannot open pages, APIs, or exports.
- [ ] Verify idle timeout uses `TRACS_SESSION_IDLE_TIMEOUT_MINUTES` and never exceeds 2880 minutes.
- [ ] Verify cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` over HTTPS.
- [ ] Verify forwarded HTTPS/client-IP headers are trusted only from `TRACS_TRUSTED_PROXIES`.
- [ ] Verify every mutating form/API supplies a valid CSRF token.
- [ ] Verify logout is POST plus CSRF and destroys the session.

## Roles, Permissions, And Object Access

- [ ] Every protected page must include the auth guard and call a page permission helper before loading sensitive data.
- [ ] Every API must use `_bootstrap.php` and appear in its permission map or perform an explicit equivalent check.
- [ ] Object-detail and attachment endpoints must enforce ownership/visibility, not only module permission.
- [ ] Restricted page discovery should return generic 404 where implemented; APIs should return 401/403 as appropriate.
- [ ] Test Super Admin, Admin, Supervisor, Agent, Intern, and Viewer/Auditor defaults.
- [ ] Confirm Supervisor user actions stay within intended division scope.
- [ ] Confirm Intern sees only dashboard, checklist, and own-task surfaces by default.
- [ ] Confirm only Super Admin can reset another user's 2FA or change the most sensitive role/settings controls.
- [ ] Confirm the last active Super Admin cannot be disabled or demoted.

## Input, SQL, And Output

- [ ] Use prepared statements for all user-controlled values.
- [ ] Allowlist dynamic SQL identifiers, sort expressions, and enum-like values.
- [ ] Escape server-rendered content with `esc()`/`htmlspecialchars()`.
- [ ] Do not insert untrusted HTML into the DOM.
- [ ] Keep database/stack details in server logs; return generic client errors.
- [ ] Review legacy page-local SQL when changing Finance, Domains, or MoM.

## Upload Security

| Upload | Status | Controls to verify |
| --- | --- | --- |
| Avatars | Implemented | MIME/image validation, controlled path/name, script execution denied. |
| Case attachments | Implemented | JPEG/PNG/WebP, 5 MB limit, GD re-encode, thumbnails, permission-checked serving. |
| Shift attachments | Implemented | Image MIME/size processing, thumbnails, report permission check. |
| MoM screenshots | Implemented | Base64/content MIME validation, generated filename, 5 MB limit, authenticated serving endpoint, direct folder denial. |
| Avatars | Implemented | Image MIME/content validation, generated filename, size controls; intentionally direct-public as images only. |

- [ ] Confirm GD is installed in production.
- [ ] Confirm upload directories are writable but the repository is not.
- [ ] Confirm PHP and executable extensions cannot run under uploads.
- [ ] Confirm direct `/uploads/case_attachments/`, `/uploads/shift_report_attachments/`, and `/uploads/mom/` requests return `403/404`.
- [ ] Confirm direct attachment URLs cannot bypass application checks.
- [ ] Confirm delete operations remove metadata and files without crossing record ownership.
- [ ] Scan uploads and monitor unexpected extensions or rapid volume growth.

## Notification Security

- [ ] Notification creation must validate target module permission.
- [ ] Dedupe keys must prevent repeated scheduler triggers.
- [ ] `notifications-list.php`, mark-read, claim, and push-status endpoints must require full auth.
- [ ] A user must only read/update their own notification records.
- [ ] Browser notification text must remain escaped/plain text.
- [ ] Service-worker click URLs must remain same-origin or strictly allowlisted.
- [ ] Production cron must run under a non-privileged deployment user and write logs outside the web root.
- [ ] Notification logs must not contain passwords, TOTP codes/secrets, sessions, CSRF tokens, or attachment contents.
- [ ] Infrastructure notifications remain **Planned** until a real backend emits permission-checked events.

## Error Logging And Sensitive Files

- [ ] Set `APP_ENV=production` and `display_errors=Off`.
- [ ] Keep detailed PHP/MySQL errors in restricted logs.
- [ ] Confirm `.env`, `config/`, `core/`, `modules/`, `logs/`, backup trees, Markdown, SQL, and dotfiles are not web-accessible.
- [ ] Do not use `chmod 777`; keep deployment ownership and `750/640` runtime modes aligned with `deploy.sh`.
- [ ] Remove unnecessary backup artifacts from production releases.
- [ ] Rotate web, PHP-FPM, application, notification-worker, auth, and database logs.
- [ ] Alert on repeated login/2FA failures, permission denials, suspicious access, 5xx spikes, worker failure, and disk pressure.
- [ ] Confirm public responses never include `SQLSTATE`, raw MySQL errors, stack traces, absolute server paths, `var_dump`, `print_r`, or `phpinfo`.
- [ ] Remove tracked `.env`, runtime logs, caches, and uploaded data from the Git index and rotate any credential that was committed.

## Super Admin Server Health & Logs

- [ ] Exact `super_admin` role can open `/server-health.php`.
- [ ] Admin, Supervisor, Agent, Intern, and unauthenticated users are blocked from page and API direct URLs.
- [ ] `/api/server-health.php` accepts GET only, rejects every query/path parameter, and returns structured JSON.
- [ ] Path traversal such as `path=../../`, command-like values, and arbitrary filesystem requests return `400`.
- [ ] Refresh is session-rate-limited and audit logged.
- [ ] CPU, RAM, disk, free storage, project/uploads/logs/database/uptime values display or safely show `Unavailable`.
- [ ] Backup size remains unavailable when `700/600` backup permissions block PHP.
- [ ] Deployment time/version/commit contains no path, username, host, credential, or token.
- [ ] Error entries are sanitized and never expose raw SQL, stack traces, paths, IPs, credentials, cookies, or authorization values.
- [ ] Healthy is below `70%`, Warning is `70-84.9%`, and Critical is `85%+`.

## Production Hardening

- [ ] HTTPS redirect and HSTS after HTTPS is stable.
- [ ] `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, and CSP headers present.
- [ ] MySQL bound to localhost/private network and never public.
- [ ] UFW/cloud firewall permits only required ports.
- [ ] SSH key-only, no root login, restricted users/IPs, Fail2ban enabled.
- [ ] Automatic security upgrades enabled.
- [ ] Database, `.env`, uploads, server config, cron, and deployment version backed up.
- [ ] Restore test completed.

## High-Risk Regression Tests

1. Login, CAPTCHA escalation, lockout, 2FA setup/verify/reset, logout, and idle timeout.
2. Direct URL and API access for every role.
3. Case/shift/MoM attachment upload, view, delete, and cross-user denial.
4. Task assignment to another user, checklist/reminder sync, and notification visibility.
5. Notification worker concurrency/dedupe and browser permission denied/granted paths.
6. Domain Price view/manage/approve separation and Intern assignment isolation.
7. TV Mode role gate and API access.
8. Exposed-file checks against the deployed web server.

See `docs/SECURITY_AUDIT_2FA.md` for the focused 2FA implementation audit and `VPS_SECURITY_CONFIGURATION.md` for server controls.
