# TRACS Mandatory 2FA Security Audit

Date: 2026-05-21

## Security Audit Summary

TRACS now requires TOTP-based 2FA for every role after successful password verification. Password success creates only a temporary pending-2FA session. Full authentication is created only after 2FA setup or verification succeeds. Protected pages, JSON APIs, and CSV exports now reject sessions that are not fully authenticated.

2FA setup displays a QR code and manual key only during setup. The confirmed secret is encrypted before storage and is never shown again. 2FA verification is server-side, accepts only a small configurable time window, rate-limits failures, temporarily locks the user after repeated failures, and logs setup, success, failure, reset, lock, and suspicious access events.

## Vulnerabilities Or Gaps Found

- Fixed: Password-only login previously created a full authenticated session immediately after `password_verify()`.
- Fixed: API/export bootstraps previously checked `user_id` rather than the new full-authenticated state.
- Fixed: Login assistance text was always visible on a clean login page.
- Fixed: Login error alert was full-width and visually loose.
- Fixed: `public/index.php` enabled PHP display errors directly.
- Fixed: Root `.htaccess` only denied `.env`; if a deployment accidentally pointed document root above `/public`, config, logs, backups, and SQL files were easier to expose.
- Fixed: Ops status mutation API had no backend role gate beyond being logged in.
- Remaining medium risk: Several older module actions use owner-scoped checks but not always granular module permissions. They should be reviewed module by module before adding cross-team administration features.
- Remaining medium risk: Some legacy pages still create compatibility tables from runtime PHP. Fresh install schema should eventually be consolidated so runtime DDL is unnecessary.

## Modified Files

- `.htaccess`
- `README.md`
- `ARCHITECTURE.md`
- `config/README.md`
- `config/install.sql`
- `config/schema/auth.sql`
- `config/migrations/2026_05_21_mandatory_2fa.sql`
- `core/security/auth_hardening.php`
- `core/user_management.php`
- `modules/user-management/controller.php`
- `modules/user-management/model.php`
- `public/auth/auth_check.php`
- `public/auth/login.php`
- `public/api/_bootstrap.php`
- `public/api/_export_helpers.php`
- `public/api/ops-status.php`
- `public/assets/tracs.css`
- `public/includes/user-management-card.php`
- `public/index.php`
- `public/login.php`
- `public/two-factor-setup.php`
- `public/two-factor-verify.php`
- `public/user-management.php`

Backups were written under `backups/codex-mandatory-2fa-20260521-135027/`.

## Database Migration

Run:

```sql
SOURCE config/migrations/2026_05_21_mandatory_2fa.sql;
```

The migration adds `tracs_users.two_factor_*` columns and indexes. Existing users are marked as requiring setup. Take a database backup first.

## New Login Flow

1. User submits username/email and password.
2. Login validates CSRF, CAPTCHA state when required, lockout state, account status, and password.
3. On password success, TRACS regenerates the session ID and stores only pending 2FA state.
4. If the user has no confirmed 2FA secret or was reset, TRACS redirects to mandatory setup.
5. If 2FA is already confirmed, TRACS redirects to verification.
6. On valid TOTP, TRACS regenerates the session ID again, stores `tracs_auth_state = full`, updates login timestamps, and opens the landing page.
7. Direct dashboard/API/export access before 2FA is blocked and logged.
8. Full sessions refresh their idle timer on valid protected page, API, and export activity. Session timing is not displayed in the UI, and sessions expire only after inactivity exceeds the configured idle timeout, capped at 48 hours.

## Super Admin 2FA Reset

Super Admin can open User Management, choose a user action menu, and select `Reset 2FA`. A confirmation modal explains that the user must set up 2FA again on next login. The reset clears the stored secret, disables current 2FA, marks setup required, clears 2FA failure state, and logs both user-management and authentication events.

Non-Super Admin reset attempts are blocked server-side even if a request is forged.

## Permission And Access Control Audit Result

- Protected pages include `public/auth/auth_check.php`, which now requires full auth.
- JSON APIs include `_bootstrap.php`, which now requires full auth and CSRF for mutations.
- CSV exports include `_export_helpers.php`, which now requires full auth and idle-session enforcement.
- Protected pages, APIs, and exports refresh the same idle timer after successful full-auth checks. TV mode polling counts as valid activity and keeps an active wall display signed in; inactive sessions expire after the capped idle timeout.
- User-management mutating actions validate permissions server-side.
- Super Admin-only 2FA reset is enforced in the controller, not only in the UI.
- Permission denied events are logged through `tracs_auth_events`.
- IDOR-sensitive MoM, case, report, feedback, and balance-transfer paths use owner-aware helpers where already wired.

## Testing Checklist

- Existing user with no 2FA is forced to set up 2FA: verified locally with Docker app/database.
- New user is forced to set up 2FA: covered by default schema state; pending manual create-user browser test.
- User cannot skip 2FA setup: verified with direct dashboard redirect and API 401 while pending.
- User cannot access dashboard before completing 2FA: verified locally with `index.php` redirect to setup.
- Valid password plus invalid 2FA cannot login: verified locally on setup POST.
- Valid password plus valid 2FA can login: verified locally on setup and verification POST.
- 2FA rate limit and temporary lock work: failure counter path verified; full lock threshold pending manual repeated-attempt test.
- Super Admin can reset user 2FA: verified by controller-level test against local DB.
- Non-Super Admin cannot reset user 2FA: verified by controller-level test against local DB.
- Login error message is centered and compact: CSS updated; pending visual browser screenshot.
- Login assistance only appears on relevant trouble states: verified clean page, first failure, and repeated-failure behavior locally.
- Light/dark/mobile layouts: CSS updated; pending visual browser check.
- Logs do not include password, 2FA code, 2FA secret, session ID, CSRF token, or CAPTCHA secret: implemented by not logging those values and existing scrubber.

## Rollback Notes

1. Restore backed-up files from `backups/codex-mandatory-2fa-20260521-135027/`.
2. Restore the database backup taken before `2026_05_21_mandatory_2fa.sql`.
3. If database restore is not possible, keep `TRACS_2FA_SECRET_KEY` stable until encrypted secrets are no longer needed, then remove or ignore the `two_factor_*` columns.
4. Re-test password login, logout, role-gated pages, APIs, and exports after rollback.
