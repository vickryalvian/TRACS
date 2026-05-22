# TRACS Security Audit Checklist

Audit date: 2026-05-22  
Scope: authentication, brute-force protection, session security, 2FA, broken access control, IDOR, SQL injection, XSS, CSRF, upload abuse, exposed files, error leakage, headers, cookies, and VPS deployment assumptions.

Status key:
- Passed: reviewed and acceptable for production use.
- Fixed: issue found and corrected in this audit.
- Needs Improvement: safe for current flow, but should be strengthened in a future hardening pass.
- Pending: depends on deployment or operational configuration outside the application repository.

## Authentication And Brute-Force Review

| Area checked | Status | Notes | Recommendation |
|---|---|---|---|
| Login CSRF protection | Passed | Login POST verifies the CSRF token before credential checks. | Keep all future login-adjacent POST handlers behind `verify_csrf()`. |
| Generic failed login response | Passed | Invalid user, wrong password, inactive accounts, and lockouts avoid user enumeration. | Keep login messages generic and record detail only in auth logs. |
| Password verification timing | Passed | A dummy password hash is used when no matching account exists. | Keep dummy hash updated with the current password hashing algorithm. |
| Failed login tracking | Passed | Failed attempts are tracked by identifier hash and IP in the login-attempt table. | Monitor repeated lock events and alert on credential-stuffing patterns. |
| Brute-force lockout | Passed | Temporary lockout is applied after configured thresholds. | Tune `TRACS_AUTH_MAX_ATTEMPTS`, lock duration, and window duration for production traffic. |
| CAPTCHA escalation | Passed | CAPTCHA is required after repeated failures, with Turnstile support when configured. | Use Cloudflare Turnstile in production and keep the internal CAPTCHA as fallback. |
| 2FA enforcement | Passed | Successful password verification always routes to setup or verification before a full session is granted. | Keep 2FA mandatory for all accounts. |
| 2FA failed attempt lockout | Passed | TOTP failures increment account-level counters and can temporarily lock verification. | Monitor `two_factor_lock` events. |
| 2FA reset recovery | Passed | Super Admin recovery reset is preserved and logged. | Keep 2FA reset restricted to Super Admin only. |
| 2FA setup session timing in UI | Fixed | The setup page previously displayed a setup-session expiry time. This has been removed. | Do not expose session duration or expiry timing in UI copy. |
| Session fixation defense | Passed | Session ID is regenerated after password verification and after 2FA completion. | Keep regeneration tied to privilege/authentication state changes. |
| Idle session timeout | Passed | Idle timeout is enforced in the background by page/API bootstrap code. | Do not show session countdowns or expiry times in the UI. |
| Cookie flags | Fixed | Session startup now sets `HttpOnly`, `SameSite=Lax`, strict/use-only-cookie settings, and `Secure` when HTTPS is detected. | Terminate TLS correctly and configure trusted proxy settings if behind a reverse proxy. |
| Password reset security | Passed | Password reset is admin-generated, permission checked, creates a one-time visible temporary password, and logs the action. | Require out-of-band delivery of temporary passwords and force users to change them quickly by procedure. |
| Password policy | Passed | Minimum length, same-password prevention, generated temporary passwords, and obvious-password rejection are present. | Consider a breached-password check in a future release if outbound API usage is acceptable. |

## Breach And Application Attack Review

| Area checked | Status | Notes | Recommendation |
|---|---|---|---|
| Direct URL access to restricted pages | Fixed | Authenticated pages now require explicit page permissions before data is loaded. | Keep adding page-level permission gates for every new restricted view. |
| IDOR on MoM detail URLs | Fixed | Invalid or unauthorized MoM IDs return a generic 404 before data is rendered. | Use object-access helpers whenever a URL accepts a record ID. |
| API permission checks | Fixed | Shared API bootstrap now enforces permission maps and helper checks for restricted endpoints. | Register every new API endpoint in the bootstrap map or add an equivalent local permission check. |
| Export permission checks | Fixed | Export helpers now validate full authentication, active account state, and export/module permissions. | Keep reports/export permission separate from normal view permission. |
| Admin/Super Admin boundaries | Fixed | Domain Price permissions were added to the central permission catalog; Super Admin-only 2FA reset remains preserved. | Review role defaults after every permission catalog change. |
| Permission-safe denial behavior | Fixed | Restricted pages use the generic 404 renderer where direct access could reveal feature or record existence. | Prefer 404 for object/feature discovery risk and 403 for API authorization failures. |
| SQL injection | Passed | Reviewed dynamic SQL paths; user-provided values are prepared/cast/allowlisted. Reminder and ticker reads were moved to prepared statements. | Keep dynamic identifiers/order expressions allowlisted only. |
| XSS | Passed | Server-rendered user data is escaped and JSON responses are encoded. | Keep using `esc()`/`htmlspecialchars()` for HTML and avoid inline HTML from untrusted data. |
| CSRF | Passed | Forms use CSRF inputs and mutating APIs verify CSRF through bootstrap/global fetch headers. | Never add mutating endpoints outside `_bootstrap.php` without CSRF protection. |
| File upload abuse | Fixed | MoM screenshots now validate base64, detected image MIME, declared MIME, size, and extension. Upload directories block executable file types. | Keep uploaded content limited to image MIME types and never allow PHP execution under uploads. |
| Avatar upload abuse | Passed | Avatar upload validates MIME and image dimensions and writes controlled filenames. | Keep avatar public paths restricted to `/uploads/avatars/` image extensions. |
| Exposed backup/config files | Fixed | `.htaccess` now blocks dotfiles, sensitive extensions, backup-named public assets, internal `public/modules`, and executable uploads. | Set webroot to `public/` and mirror these deny rules in Nginx if Apache is not used. |
| Public `.env` protection | Fixed | Root deny rules block `.env`; VPS docs require `.env` outside public webroot with strict permissions. | Validate `.env` is not reachable after each deploy. |
| Debug/error leakage | Fixed | API DB errors now log server-side details and return generic client errors. Currency API debug display was removed. | Keep `APP_ENV=production` and `display_errors=Off` on VPS. |
| Test/debug API switches | Fixed | Holiday API test switches are limited to local/dev mode or settings-manage users. | Do not leave public debug toggles enabled in production. |
| Security headers | Fixed | App and Apache configs now set `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP frame/base/object limits, and HTTPS-only HSTS. | Add equivalent Nginx headers when deploying behind Nginx. |
| Insecure cookies | Fixed | Session cookies are hardened at session start. | Confirm `Secure` is present over HTTPS in the browser and proxy headers are trusted only from known proxies. |
| Public backup/config files | Fixed | Public backup-named CSS/JS and backup/archive extensions are denied by rewrite rules. | Remove backup files from production releases when possible. |
| VPS firewall, SSH, database, backup, and monitoring | Pending | Covered in `VPS_SECURITY_CONFIGURATION.md`; enforcement depends on the production server. | Complete the VPS checklist before go-live and repeat after server changes. |

## Files Changed In This Audit

| File | Why it changed |
|---|---|
| `.htaccess` | Added production security headers and deny rules for sensitive directories, backup/config files, and executable uploads when the repository root is exposed. |
| `public/.htaccess` | Added safe 404 handling, blocked direct access to internal `public/modules`, denied executable uploads and backup-named assets, and expanded security headers. |
| `public/uploads/.htaccess` | Added upload-directory hardening: no indexes, no executable script types, PHP engine off when supported, and `nosniff`. |
| `core/security/csrf.php` | Centralized security headers and hardened session cookie/runtime settings without changing visible app flow. |
| `core/access_control.php` | Added reusable page-permission helpers that log denied access and render generic 404s. |
| `core/user_management.php` | Added Domain Price permissions to the permission catalog and role defaults so access control can be managed consistently. |
| `public/index.php` | Added dashboard permission validation before dashboard data is shown. |
| `public/cases.php` | Added cases view permission validation before case data is shown. |
| `public/reminders.php` | Added reminders view permission validation before reminder data is shown. |
| `public/checklist.php` | Added checklist view permission validation before checklist data is shown. |
| `public/shift-reports.php` | Added reports view permission validation before report data is shown. |
| `public/activity.php` | Added activity-log permission validation before audit log data is shown. |
| `public/cancellation_feedback.php` | Added cancellation-feedback view permission validation before feedback data is shown. |
| `public/finance.php` | Added finance view permission validation before finance data is shown. |
| `public/domains.php` | Added domain view/manage permission validation, including safer permission response for POST actions. |
| `public/infrastructure-pulse.php` | Added dashboard permission validation before infrastructure dashboard data is shown. |
| `public/mom.php` | Added MoM page permission validation and generic 404 behavior for invalid/unauthorized MoM IDs. |
| `public/domain_price_crosscheck.php` | Added Domain Price page permission validation through shared access control. |
| `public/profile.php` | Added profile self-view permission validation before profile data is shown. |
| `public/user-management.php` | Replaced raw forbidden output with permission-safe 404 behavior while preserving bootstrap recovery access and Super Admin 2FA reset. |
| `public/intern-management.php` | Replaced raw forbidden output with permission-safe 404 behavior. |
| `public/monitoring.php` | Replaced raw forbidden output with permission-safe 404 behavior. |
| `public/tv-mode.php` | Added role validation before TV-mode data is shown. |
| `public/two-factor-setup.php` | Removed visible setup-session expiry timing from the UI. |
| `public/api/_bootstrap.php` | Enforced full-authentication state, idle timeout, account status refresh, CSRF, and endpoint permission checks for APIs. |
| `public/api/_export_helpers.php` | Enforced full-authentication state, account status refresh, and export-specific permission checks. |
| `public/api/currency.php` | Removed hardcoded display-errors debug mode. |
| `public/api/holiday-indonesia.php` | Restricted test/debug query switches to local/dev mode or settings-manage users. |
| `public/api/domain-price-workflow.php` | Switched local permission checks to shared API permission helpers. |
| `public/api/domain-price-task.php` | Switched local manage/approve checks to shared any-permission helper. |
| `public/api/domain-price-recalculate.php` | Switched local manage/approve checks to shared any-permission helper. |
| `public/api/domain-price-matrix.php` | Switched local permission checks to shared API permission helpers. |
| `public/api/bt-create.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/bt-update.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/bt-delete.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/case-create.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/reminder-create.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/reminder-get.php` | Replaced interpolated ID lookup with a prepared statement. |
| `public/api/domain-create.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/task-create.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/finance-create.php` | Replaced DB error detail in client response with server-side logging and generic error. |
| `public/api/ticker-list.php` | Replaced interpolated user lookup with a prepared statement. |
| `public/api/mom-action.php` | Replaced legacy exception detail in client response with server-side logging and generic error. |
| `public/api/user-avatar.php` | Kept validation feedback but made unexpected avatar-update exceptions generic and logged server-side. |
| `public/modules/mom/controller.php` | Hardened MoM screenshot upload validation for MIME, image content, size, extension, and saved permissions. |

## Follow-Up Audit Checklist

- [ ] Confirm production webroot points to `public/`, not the repository root.
- [ ] Confirm `.env`, `config/`, `core/`, `modules/`, `logs/`, `backups/`, and Markdown files are not web-accessible.
- [ ] Confirm every new page includes `auth_check.php` plus a page permission gate before data loading.
- [ ] Confirm every new API includes `_bootstrap.php` and is mapped to required permission(s), or has an explicit local permission check.
- [ ] Confirm every new record-detail URL checks object access and returns generic 404 for missing or unauthorized records.
- [ ] Confirm every mutating form/API uses CSRF.
- [ ] Confirm every upload path validates file type by content and blocks script execution.
- [ ] Confirm `APP_ENV=production`, PHP `display_errors=Off`, HTTPS is enabled, and session cookies are `Secure`.
- [ ] Confirm Super Admin 2FA reset works and remains logged.
- [ ] Review auth logs weekly for `login_lock`, `captcha_challenge`, `two_factor_lock`, `permission_denied`, and `suspicious_access_attempt`.
