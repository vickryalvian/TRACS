# TRACS - AI Memory & Project Rules

> **READ THIS FIRST.** This file is durable project memory for future AI agents and developers. Verify implementation before changing documentation or behavior.

## Project Identity

- **Name:** TRACS Operational Dashboard
- **Purpose:** Compact operational control panel for support, CS, legal, and infrastructure-monitoring workflows.
- **Stack:** Vanilla PHP 8, MySQL/MariaDB, Apache or Nginx/PHP-FPM, vanilla JavaScript, and CSS.
- **Web root:** `public/`, never the repository root.
- **Timezone:** `Asia/Jakarta` / WIB.
- **Runtime style:** Server-rendered pages plus authenticated JSON/CSV APIs. There is no Composer, npm, SPA router, or frontend build step.

## Multi-Machine Git Workflow

This repo is actively developed from more than one machine (PC and MacBook). The
GitHub remote (`origin`) is the single source of truth, not either local working
tree. To avoid divergent or lost work:

1. **At the start of every session**, before editing anything: `git fetch origin`,
   then compare local `HEAD` against `origin/<current-branch>`. If local is
   behind, `git pull --rebase origin <branch>` before making changes. If
   uncommitted changes already exist in the working tree, surface them to the
   user rather than assuming they are stale or safe to discard.
2. **Never force-push** a shared branch (e.g.
   `feat/user-mgmt-auth-domain-ui-improvements`, `main`) unless the user
   explicitly asks for it. A force-push from one machine can silently discard
   unpushed commits made on the other machine.
3. **At natural stopping points** — end of a task, before a long pause, or
   whenever the user is likely to switch machines — commit and push completed
   work rather than leaving it only on local disk. If a session ends with
   unpushed commits, say so explicitly.
4. Production (`/opt/tracs` on the VPS) is deployed via manual file-copy, not a
   `git pull` of `main` — see `deployment-summary.md` and
   `docs/USER_LIFECYCLE_REMEDIATION.md`. Do not assume pushing to GitHub alone
   updates production.

## Current Direction

- Keep the interface clean, compact, operational-first, and low-noise.
- The dashboard uses the restored five-item stat strip. Do not restore the rejected grouped dashboard stat-card experiment.
- Keep widget gaps, internal padding, row heights, and column balance consistent.
- Use blue only for active, selected, or highlighted states.
- Keep completed items readable; do not fade them until operational context is lost.
- Use context-relevant icons, especially for holidays and special-day notices.
- Preserve light and dark mode behavior.
- Use this font stack:

```css
font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Fira Sans", "Droid Sans", "Helvetica Neue", sans-serif;
```

## Dashboard Decisions

- Main dashboard areas are Cases, Task Monitoring, Shift Handover, Currency Converter, and the Infrastructure Pulse summary.
- Dashboard case rows and `cases.php` rows open the same shared ticket-detail modal.
- Task Monitoring has exactly these dashboard tabs:
  - `Checklist and Reminder`
  - `Assignments`
  - `Activity`
- Checklist and Reminder are merged. Do not recreate a separate dashboard Reminder tab unless explicitly requested.
- Reminder List belongs inside the combined tab. The standalone `reminders.php` page remains an implemented full-list page.
- Assigned tasks create linked checklist items and, when due dates exist, reminders. New assignments can therefore appear in the combined tab and Assignments tab.

## User Lifecycle Rules

- Page guards return **404** (not 403) for unauthorized accounts. Every role that
  lands on the dashboard must hold `dashboard.view`; the login flow falls back to
  an accessible page (ultimately `profile.php`) so a successful login never 404s.
  Do not remove `dashboard.view` from operational roles.
- User removal is a **non-destructive archive**, never a hard delete. It marks the
  account `removed`, sets `is_active = 0`, preserves the original identity in
  `archived_email`/`archived_username`, and releases the live `email`/`username`
  as id-tied tombstones so they can be reused by a new account.
- The identity row is never deleted. History references users by immutable id, so
  case history, audit logs, reporting, measurements, and ISO 9001 traceability
  stay intact. A recreated account always gets a new id and never inherits history.
- `emailExists`/`usernameExists` ignore `removed` rows; `listUsers` hides `removed`
  by default. Removal is terminal (recreate, do not reactivate).
- See `docs/USER_LIFECYCLE_REMEDIATION.md` and migration
  `2026_06_30_user_removal_release.sql`.

## Case Ticket Rules

- Keep `Resolve` as the primary footer action on the right.
- Keep the only Close control in the ticket header.
- Edit and Delete currently live under the icon-only More trigger in the header.
- The progress timeline is implemented with Created, Assigned, In Progress, Waiting, and Resolved steps.
- Keep the line thin and blue-to-cyan, limit animation to the filled/current portion, use smaller passed dots, and emphasize the current dot.
- Resolved timelines stop animating.

## Shift Handover Rules

- Statuses are `active`, `on_hold`, and `resolved`.
- Active items need handover; on-hold items remain monitoring context; resolved items remain visible as informational context.
- Do not imply that every case or activity must become a handover item.
- The dashboard reminder starts 30 minutes before shift change and escalates visually near handover.
- Scheduled browser/in-app shift notification creation currently occurs within the final 15 minutes and requires the notification worker or an active dashboard request.

## Shift Assignment Rules

- Main CS shifts are Shift 1 `00:00-08:00`, Shift 2 `08:00-16:00`, and Shift 3 `16:00-24:00`.
- Shift 3 stores `00:00` as the next-day end time with `is_cross_day=1`; `24:00` is display-only.
- The default CS monthly schedule uses `weekly_matrix` settings with canonical English `Week 1`-`Week 5` and `Monday`-`Sunday` values; Week 5 repeats the Week 4 pattern.
- Actual dated assignments cover every calendar date in each generated month.
- Run `php bin/seed-default-shift-schedule.php --apply` for the current month through December, `--start=YYYY-MM --end=YYYY-MM` for an explicit range, or `--month=YYYY-MM` for one month.
- Reruns only replace rows owned by `default_cs_monthly_shift_v1`; manual assignments and sensitive existing user fields are not overwritten.
- The seed removes only the three explicitly identified June 2026 dummy rows before conflict validation; unknown manual, leave, off-day, and special assignments remain protected.
- Timeline modes share the same dated-assignment endpoint. Daily uses one date, Weekly uses Monday-Sunday, and Monthly fetches the complete calendar month.
- Assignment range overlap is half-open (`start < range_end` and `end > range_start`) so Shift 3 ending at midnight does not leak into or conflict with the next day's Shift 1.
- The canonical Customer Support-equivalent role is currently `Agent`; Vickry remains `Super Admin` and is still eligible for shift assignment.

## Infrastructure Pulse

- **Partially Implemented:** full page, dashboard mini widget, and TV Mode widget share `public/assets/infrastructure-pulse-data.js`.
- Current telemetry and server-registry changes are mock/session-only. No backend ping worker, monitoring tables, Redis, SSE, or WebSocket feed is implemented.
- `tv-mode.php` includes an Infrastructure Pulse widget; there is no separate Infrastructure-only TV route.
- Do not document mock incidents as live infrastructure alerts.

## Domain Price Crosscheck

- Canonical route: `domain-price-crosscheck.php`.
- Legacy `domain_price_crosscheck.php` only performs a 308 redirect.
- Navigation is under `Tasks & Monitoring` as `Domain Pricing Crosscheck`.
- Current UI includes Overview, Price Matrix, Intelligence Summary, ccTLD Check, Website Price Adjustment, Action Buckets, Notes & Follow-ups, Audit Trail, registrar source management, and domain-extension management.
- Keep layouts compact, preserve sticky matrix context, and do not describe planned registrar APIs/imports as implemented.

## Navigation And Settings

- Top-level navigation includes Dashboard, Case Management, Reminders, Shift Reports, Infrastructure Pulse, Meetings/MoM, Feedback, Ticker/Alerts, Activity Log, role-gated TV Mode, and role-gated User Management.
- `Tasks & Monitoring` contains Case / Task Monitoring, Finance, Domain Transfer Log, Domain Pricing Crosscheck, and Checklist according to permissions.
- Settings, profile, and password changes are accessed through the avatar/profile menu.

## Notifications

- Implemented notification types include new case, reminder creation/due timing, task assignment, meeting timing, and shift-handover reminder.
- The Attention Center polls every 60 seconds. Browser notifications use the Notification API plus `public/tracs-sw.js` after user permission.
- The scheduler is `bin/tracs-notification-worker.php`; production should run it from cron.
- Critical shared request errors can remain visible until manually dismissed.
- Infrastructure alerts are **Planned** until a real monitoring backend creates them.

## Security And Runtime Invariants

1. Public pages start the hardened session, include `public/auth/auth_check.php`, and apply page permission checks before loading sensitive data.
2. APIs use `public/api/_bootstrap.php`; mutating requests require CSRF.
3. Full authentication requires password verification plus TOTP 2FA.
4. Session IDs regenerate after password verification and successful 2FA.
5. Use prepared statements, `esc()`, and existing access-control helpers.
6. Uploads must be content-validated, re-encoded where implemented, and served through permission-checked endpoints.
7. Keep `public/includes/header.php` as the shared shell and `footer.php` as the shared modal/script owner.
8. Preserve creator/build signature files and text unchanged.
9. Production permissions are source `755/644`, secrets `640`, runtime folders/files `750/640` owned by `www-data`, deployment metadata `750/640` owned by the deploy user and readable by `www-data`, backups `700/600`, and `deploy.sh` `750`. Never use `777`.
10. `server-health.php` and `api/server-health.php` are exact-role `super_admin` only. Do not replace this with a broadly assignable permission.
11. Server monitoring accepts no path or command input. Keep metrics fixed, scans bounded, logs sanitized, and unavailable states preferable to weaker permissions.
12. Protected case, shift, and MoM evidence is served through authenticated APIs. Only avatar images are intentionally direct-public under `public/uploads`.

## Documentation Rules

1. Update existing documentation before creating new files.
2. Create a new Markdown file only when it removes real complexity and does not duplicate existing content.
3. Use actual routes, modules, schema names, and current behavior.
4. Label incomplete work as `Planned`, `In Progress`, `Partially Implemented`, or `Legacy`.
5. Preserve every existing signature block and signature string exactly.
6. Keep historical package documents under `MOM README/` as legacy reference; use `README_MOM.md` for current MoM behavior.
7. Do not edit backup-tree documentation as if it were current.

## Known Constraints

- External CDNs are used for fonts, Lucide, and Flatpickr.
- Docker is for local development; its current image installs `mysqli` but not GD, so case/shift image processing requires a Dockerfile follow-up.
- `config/database.php` reads `$_ENV`; PHP-FPM/Apache must expose deployment environment values.
- Some intentionally retained legacy tables are unprefixed: `balance_transfers`, `domain_transfers`, `activity_feed`, and `ops_status`.
- MoM still has current and legacy API shapes.
- The worktree may contain intentional user edits and backup folders. Never revert unrelated changes.
