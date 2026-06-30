# TRACS User Management — Audit & Remediation Report

**Date:** 2026-06-30
**Scope:** User creation, activation, authentication, login/logout, 2FA, removal,
lifecycle integrity, and email/username reuse. No unrelated modules were changed.
**Environment reproduced on:** local Docker stack (`tracs_app` + `tracs_db`,
MySQL 8.0, `STRICT_TRANS_TABLES`).

---

## 1. Root Cause Analysis — 404 After Login

**Symptom:** After a new user is set up and signs in, they receive HTTP 404.

**Reproduced:** A fresh `agent` user authenticated successfully (`302 → /index.php`)
but `GET /index.php` returned **HTTP 404**.

**Root cause:** The post-login landing page is `index.php`, whose guard is
`tracs_require_page_permission($conn, 'dashboard.view')`. Page guards in TRACS
intentionally return **404** (not 403) for unauthorized accounts
(`core/access_control.php → tracs_abort_404`). The live `tracs_role_permissions`
data was missing `dashboard.view` for every non–super-admin role:

| Role | had `dashboard.view` before fix |
| --- | --- |
| super_admin | n/a — bypasses all checks in `tracs_user_can` |
| admin | ❌ |
| supervisor | ❌ |
| agent | ❌ |
| viewer | ❌ |
| intern | ✅ |

Only `super_admin` could open the dashboard (it short-circuits to `true` in
`tracs_user_can`). Every other role landed on `/index.php` and hit a hard 404.

**How the data drifted:** `dashboard.view` was granted to these roles by the
original `2026_05_17_user_management.sql` migration, but the role-permission
matrix UI (`UserManagementModel::replaceRolePermissions`) lets an admin overwrite
a role's full permission set. A later edit dropped `dashboard.view`. The
source-of-truth default map (`tracs_default_role_permissions`) also omitted
`dashboard.view` for `supervisor`, so any resync re-introduced the gap.

**Permanent fix (two layers, root cause + defense in depth):**

1. **Data + source-of-truth repair** — `dashboard.view` re-granted to `admin`,
   `supervisor`, `agent`, `viewer`, `intern`, and added to the `supervisor`
   default map in `core/user_management.php` so it can never silently drift out
   again on resync.
2. **Resilient landing** — `tracs_auth_resolve_safe_landing()` now verifies the
   resolved landing is actually permitted for the user before redirecting, and
   falls back through accessible pages to `profile.php` (every account holds
   `profile.view_own`). A successful login can **never** dead-end in a 404 again,
   even if role permissions are later misconfigured through the UI.

---

## 2. Root Cause Analysis — User Recreation Conflict (Email/Username Reuse)

**Symptom:** After deleting a user, creating a new user with the same email
fails with a conflict.

**Two compounding root causes were found:**

**(a) Removal was outright broken on un-migrated databases.** `removeUser()`
set `status = 'removed'`, but the `tracs_users.status` ENUM did not contain
`'removed'` (the `2026_06_30_user_removed_status.sql` migration had not been
applied). Under `STRICT_TRANS_TABLES`, the `UPDATE` failed with
`Data truncated for column 'status'`, so removal threw and the account was never
removed. Reproduced live.

**(b) Removal never released the unique identifiers.** `tracs_users` has
`UNIQUE(email)` and `UNIQUE(username)`. A soft-deleted row kept the original
email/username, so both the application checks (`emailExists`/`usernameExists`)
and the database UNIQUE constraints blocked recreation with the same values.

**Permanent fix — archive + identity release:**

`UserManagementController::removeUser()` now calls the new
`UserManagementModel::archiveUserForRemoval()`, which in one statement:

- sets `status = 'removed'`, `is_active = 0` (revokes sign-in),
- copies the original email/username into new `archived_email` /
  `archived_username` columns (immutable history),
- rewrites the live `email`/`username` to id-tied tombstones
  (`removed+{id}@removed.tracs.invalid`, `removed_{id}`) that are guaranteed
  unique and release the original values for reuse,
- records `removed_at` / `removed_by`.

`emailExists`/`usernameExists` now ignore `removed` rows, and `listUsers`
excludes `removed` accounts by default, so they disappear from User Management.
Because the user **row is preserved** (never deleted) and history references it
by immutable integer id, all historical records stay linked to the original
identity, and a recreated account always gets a **new id** — it never inherits
the deleted account's history.

---

## 3. Files Modified

| File | Change |
| --- | --- |
| `config/migrations/2026_06_30_user_removal_release.sql` | **New.** Ensures `removed` status enum, adds `archived_email`/`archived_username`/`removed_at`/`removed_by`, re-grants `dashboard.view` to operational roles. |
| `modules/user-management/model.php` | `archiveUserForRemoval()` added; `emailExists`/`usernameExists` ignore `removed`; `listUsers` hides `removed` by default. |
| `modules/user-management/controller.php` | `removeUser()` uses `archiveUserForRemoval()`; clearer message. |
| `core/user_management.php` | `supervisor` default map now includes `dashboard.view`. |
| `core/security/auth_hardening.php` | `tracs_auth_landing_permission()` + `tracs_auth_resolve_safe_landing()`; wired into `tracs_auth_complete_full_login()`. |

## 4. Database Tables Affected

- `tracs_users` — `status` enum gains `removed`; new columns `archived_email`,
  `archived_username`, `removed_at`, `removed_by`.
- `tracs_role_permissions` — `dashboard.view` granted to operational roles.
- No other table is altered. No data is deleted by removal.

## 5. Migrations Added / Modified

- **Added:** `config/migrations/2026_06_30_user_removal_release.sql` (idempotent,
  re-runnable; supersedes the partial `2026_06_30_user_removed_status.sql` by also
  ensuring the enum).

## 6. Risks Identified

- **Removed users vanish from the UI by design.** Audit/forensic access to a
  removed identity is via the `archived_*` columns and `tracs_user_activity_logs`
  (the `remove_user` event stores the full before-snapshot).
- **Removal is terminal** (one-way archive). Reactivating a removed account is
  intentionally not offered, because its email/username may already be reused by
  a newer account. Recreation is the supported path.
- **No server-side session table.** Sessions are validated per request; a removed
  account fails `tracs_user_can_login()`, so `auth_check.php` tears down any live
  session on the next request. There is no separate API-token store to revoke.

## 7. Validation Results

All runs against the live Docker stack. Test accounts created and cleaned up.

**Controller/model lifecycle harness — 23/23 PASS:** create → remove → recreate
(same email + same username), tombstone + archive correctness, `removed_at`/
`removed_by`, history (case still bound to original id), audit log retained with
original email, new account gets a new id, exactly one active row owns the email,
removed user fails `can_login`, removed user hidden from User Management.

**2FA harness — 10/10 PASS:** pre-2FA login allowed, enable→configured, live TOTP
verifies, wrong code rejected, secret decrypts, admin reset returns account to
normal sign-in.

**HTTP flows:**
- TEST 1 (all 5 roles): login → `/index.php` **200** → logout (POST) → **302
  /login.php** → post-logout access bounced to login → relogin → **200**.
- 404 fix: every role lands on `/index.php` **200** (was 404 for agent).
- Resilient landing: a role stripped of `dashboard.view` lands on `/cases.php`
  **200** instead of 404.
- TEST 2/8 (2FA end-to-end): password → **302 /two-factor-verify.php** → GET
  **200** → live TOTP → **302 /index.php** → **200**.

**Integrity sweep (TEST 9/10):** 0 null emails/usernames, 0 duplicate active
emails/usernames, 0 orphaned cases, 0 dangling `role_id`.

| Test | Result |
| --- | --- |
| 1 Create→Login→Logout→Login | ✅ |
| 2 Create→Enable 2FA→Login | ✅ |
| 3 Remove→Recreate same email | ✅ |
| 4 Remove→Recreate same username | ✅ |
| 5 Multiple users, different roles | ✅ |
| 6 Delete user with historical activity | ✅ |
| 7 Delete user with active sessions | ✅ (invalidated next request) |
| 8 Delete user with 2FA enabled | ✅ |
| 9 No orphaned records | ✅ |
| 10 No DB integrity errors | ✅ |
| 11 Case history remains | ✅ |
| 12 Audit logs remain | ✅ |
| 13 Reports remain accurate | ✅ (row preserved, no cascade) |
| 14 Historical records still reference original deleted account | ✅ |
| 15 Measurements/KPI/ISO records intact | ✅ (row preserved, no cascade) |

## 8. Recommended Long-Term User Lifecycle Strategy

`active → (suspended/inactive) → removed (archived)`. Removal is a non-destructive
archive that revokes access and releases reusable identifiers while keeping the
identity row immutable for traceability. Recreation, not reactivation, is the
path to re-onboard a former email holder.

## 9. Historical Data Preservation Strategy

The user row is **never deleted**. All business records (cases, MoM, shift
reports, audit/activity logs, reporting, measurements) reference users by
immutable integer id, so every reference stays valid and resolves to the original
person's `name` (kept intact) plus the `archived_*` identity. ISO 9001
traceability is preserved.

## 10. Email Reuse Strategy

On removal, the email and username are released by tombstoning the live columns
(id-tied, collision-proof) and archiving the originals. Application existence
checks and the DB UNIQUE constraints then allow the same email/username on a new
account (new id). Verified through both the controller create path and direct DB
assertions.

## 11. Database Integrity Review

- UNIQUE(`email`)/UNIQUE(`username`) retained; never violated because removed
  rows carry tombstones.
- No cascading deletes are introduced; removal touches only `tracs_users`.
- FKs (`role_id`, `division_id`) remain valid; no orphans created.
- Idempotent, re-runnable migration; safe on fresh installs and existing DBs.

---

## Deployment

```bash
# On the VPS, after pulling this branch:
REPO_DIR=/srv/tracs-repository WEB_ROOT=/var/www/tracs \
  ./deploy.sh deploy --with-migration config/migrations/2026_06_30_user_removal_release.sql --yes
```

The migration is idempotent; re-running it is safe.
