# TRACS Deployment Summary

Status: Deployed successfully
Completed: 2026-06-29 08:54 WIB
Domain: https://tracs.vickry.id

## Deployed — Task Monitoring CRUD + Assign UX + Checklist Live-Sync + Drag Board (2026-07-01 ~22:40 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `e980ef3` (full branch state).

Ships Parts 1/2/4 of the Task Monitoring + Checklist work, plus the previously
committed-but-undeployed Workflow Board drag & drop (its frontend shares
tracs.js/css, so it went out in the same pass):

- **Part 1** Task item CRUD parity (`monitoring.php`): Edit / Reassign / Delete
  added beside Details + Update + history. `updateTask` cascades to linked
  checklist items + reminders; `deleteTask` cascades (assignments, checklist,
  reminders, logs); `addAssignees` reassigns, skipping existing. Creator-or-
  monitor gated.
- **Part 2** Create Task modal: grouped "Task details" + highlighted "Assign to"
  section, per-field icons, required markers, and an opt-in searchable
  dropdown (`data-searchable`) on the shared tracs-select.
- **Part 4** Checklist real-time sync: `checklist-sync.php` returns a cheap
  change signature; `checklist.php` polls every 15s and swaps a fresh
  server-rendered fragment only when idle (no pending toggle / open menu /
  modal / focused input) — drift-free, no manual refresh.
- **Workflow Board drag & drop** (`cases.php`): pointer-based, board_order
  column (self-healing), `case-reorder.php`, `case-status.php` relaxed to
  `cases.view`. Backend verified; drag *feel* still needs a browser eyeball.

What was applied:

- 16 app files (file-copy; backup `/opt/tracs/backups/fullparts-20260701-223946/`):
  `core/creator_tracking.php`, `modules/case/{controller,model}.php`,
  `modules/checklist/{controller,model}.php`,
  `modules/task-management/{controller,model}.php`,
  `public/api/{_bootstrap,case-reorder,case-status,checklist-sync}.php`,
  `public/{cases,checklist,monitoring}.php`, `public/assets/tracs.{js,css}`.
  Ownership `vickry:www-data`; `php8.3-fpm` reloaded.
- DB: `board_order` column added to `tracs_cases` via self-healing helper (no
  other schema change).

Verification on production:

- All 16 files sha256 local↔prod match; `php -l` clean; `board_order` present.
- Checklist global visibility holds (two ids → identical list); signature
  endpoint returns; task-management `updateTask/reassign/deleteTask` present.
- Full task CRUD lifecycle tested over authenticated HTTP on staging (local
  docker): create→edit→reassign→delete with zero orphans.
- HTTP: `/login.php` 200; `/checklist.php` `/monitoring.php` `/cases.php`
  `/index.php` 302→login; `checklist-sync` + `case-reorder` 401 unauth. No 500s.

Deployed via key auth (`~/.ssh/tracs_deploy_ed25519`); no password used.

## Deployed — Checklist Global Visibility Fix (2026-07-01 ~22:14 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `1142bd5`.

Production-critical bug: the Operational Checklist was not shared. Every read in
`modules/checklist/model.php` scoped by `WHERE t.user_id = ?`, so a checklist
item created by one user was invisible to everyone else (permissions were
already open, but the query filtered by owner). Fixed by removing owner scoping
from all reads (list / by-id / incomplete / logs) and from the status update;
delete stays creator-only (`task-delete.php` via `created_by`), and note logs
still record which user acted.

What was applied:

- Single file (file-copy deploy; prior version backed up under
  `/opt/tracs/backups/checklist-visibility-20260701-221440/`):
  `modules/checklist/model.php`. Ownership preserved; `php8.3-fpm` reloaded.

Verification on production:

- sha256 local ↔ prod match.
- Real prod DB: two different user ids return the **identical** 146-item list
  (was owner-scoped before).
- `/checklist.php` and `/index.php` → 302 to login (healthy), `/login.php` →
  200, `/api/task-toggle.php` unauth → 401. No 500s.

NOT deployed in this pass (committed + pushed, awaiting verification/approval):
the Trello-like Workflow Board drag & drop (`5448956`) — it carries a DB
migration (`board_order`) and a case-status permission relaxation and has not
been browser-verified, so it was deliberately held back from this deploy.

Deployed via key auth (`~/.ssh/tracs_deploy_ed25519`); no password used.

## Deployed — Full Custom Error-Page Suite (2026-07-01 ~16:30 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`.

Audit found only `public/404.php` (with the Dobby mascot) existed; every
other status code (400/401/403/405/408/419/429/500/502/503) had no page and
no server-level routing at all, and production's nginx `try_files` fallback
silently rewrote every unmatched path to the dashboard instead of ever
returning a real 404 (`curl` confirmed a mistyped URL 302'd to `/login.php`).

What was applied:

- New `public/includes/error_page_render.php` — single shared render
  function extracted from `404.php`'s existing markup/CSS, used by every
  page so they can't drift out of sync with each other.
- `public/404.php` refactored to call the shared renderer (output
  unchanged); new `public/400.php`, `401.php`, `403.php`, `405.php`,
  `408.php`, `419.php`, `429.php`, `500.php` built on the same pattern.
- `public/502.html`, `public/503.html` — static (no PHP) fallback pages for
  the two codes that mean "PHP-FPM itself is unreachable," so nginx can
  serve them without needing a live backend.
- `public/.htaccess` — added matching `ErrorDocument` lines (for local
  Apache dev parity only; production runs nginx, which never reads
  `.htaccess`).
- **Production nginx** (`/etc/nginx/sites-available/tracs`, backed up
  first as `tracs.bak-error-pages-20260701161721`): added `error_page`
  directives for all 10 codes, and changed
  `location / { try_files $uri $uri/ /index.php?$query_string; }` to
  `try_files $uri $uri/ =404;` so genuinely unknown paths now correctly
  404 instead of falling through to the dashboard/login redirect.
  `nginx -t` passed before each reload.
- **Regression caught and fixed during verification:** initially also added
  `fastcgi_intercept_errors on;` to the `.php` location block per the
  original plan, but this caused an infinite-loop condition — each error
  page sets its own matching `http_response_code()`, so nginx re-triggered
  `error_page` on the response and fell back to its own generic (but still
  info-safe) error page instead of ours, for every code including the
  previously-working 404. Removed that one line (kept everything else) and
  reloaded; re-verified every code's response body afterward, not just its
  status code.
- **Known nginx limitation, not a bug:** with `fastcgi_intercept_errors`
  off, direct hits to `/408.php` do render correctly. (Earlier, with that
  directive on, nginx dropped 408 connections silently — a documented nginx
  quirk that treats upstream-emitted 408 as an already-dead client
  connection.)

Verification on production:

- Unknown path → **404** (previously 302 to `/login.php`).
- Every one of `400/401/403/404/405/408/419/429/500`.php and
  `502/503`.html returns its correct status code **and** its response body
  contains the Dobby mascot / correct copy (checked body content, not just
  status — the intercept_errors regression above returned the right status
  with the wrong body).
- Known real pages/API unaffected: `/login.php` → 200, `/assets/tracs.css`
  → 200, `/api/server-health.php` → 401 (unchanged).
- No `X-Powered-By`/version leakage on any error page; `display_errors`
  confirmed `Off` server-wide.
- 404.php and dobby-404.png were already byte-identical between local and
  production before this deploy (sha256 verified) — no drift there.

Deployed via key auth (`~/.ssh/tracs_deploy_ed25519`), file-copy for
`public/`, direct edit for the nginx vhost. No password used.

## Deployed — Dashboard Restructure & Multi-Region Screenshot (2026-07-01 ~11:20 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `eb010b3`.

Reworks the dashboard Website Screenshot widget and utility-row layout:

- Screenshot region defaults to **All regions**; each region captures in
  parallel and renders as a card in a modal (reuses the `case-image-modal`
  frame/backdrop). `session_write_close()` added in `screenshot-capture.php` so
  concurrent per-region requests don't serialize behind the PHP session lock.
- Website Screenshot moved under the Cases panel (left column); Cases list
  capped to ~7 rows with inline scroll.
- **Activity** moved out of Task Monitoring into a standalone Recent Activity
  widget beside a now half-width Currency Converter (equal-height row).
- Fixed collapsed Currency dropdowns (flex layout for the enhanced selects).
- Added a small Dobby easter-egg filler in the workspace.

What was applied:

- Code (file-copy deploy; prior versions backed up under
  `/opt/tracs/backups/dashboard-20260701-111534/`): `public/index.php`,
  `public/includes/footer.php`, `public/assets/tracs.css`,
  `public/assets/tracs.js`, `public/api/screenshot-capture.php`. Ownership
  restored to `vickry:www-data`; `php8.3-fpm` reloaded.
- **PAGEFLEETS_API_KEY web-path fix:** the browser reported "Screenshot service
  is not configured" because `.env` is `-rw-r----- vickry:vickry` and the
  `www-data` FPM user cannot read it, so `config/env.php`'s `loadEnv()` no-ops on
  web requests. DB/2FA vars work only via FPM pool `env[]` directives, and
  `PAGEFLEETS_API_KEY` was missing there. Added
  `env[PAGEFLEETS_API_KEY]` to `/etc/php/8.3/fpm/pool.d/www.conf` (backup:
  `www.conf.bak-pagefleets-20260701-112036`), validated `php-fpm8.3 -t`,
  reloaded FPM.

Verification on production:

- All 5 deployed files sha256-match the local committed versions.
- FPM web path exposes the key (`env_len=56`, `getenv_len=56`) via a temporary
  web-served probe (created and deleted immediately; value never printed).
- Live key returns **HTTP 200** from PageFleets; TRACS endpoint over HTTPS
  returns clean `401` unauthenticated (auth/routing correct, no 404/500).
- Remaining user-side check: an authenticated **All regions** browser capture
  rendering real images per region.

Deployed via key auth (`~/.ssh/tracs_deploy_ed25519`, authorized on the host);
no password used. Branch pushed to GitHub for review/PR.

## Deployed — Task Monitoring & MoM Permission Revision (2026-07-01 ~09:40 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`).

Revises authorization for Checklist, Reminder, Assignment, and MoM from
branch `feat/task-monitoring-mom-permission-revision`. Checklist
create/update open to every authenticated user; delete now checks
`created_by` instead of `user_id` (task-assignment-linked checklist items
can have a different assignee than creator). Reminders are fully public
(view/create/update); delete stays owner-only. MoM view/create/update
opened to every authenticated user across the record and all sub-objects
(agenda, notes, decisions, actions, screenshots, case links), with a new
self-healing `updated_by` audit column; MoM deletion stays creator-only.
Task Monitoring assignment visibility was already correctly private
(non-monitor users scoped to their own assignments) — verified, no code
change needed there.

What was applied:

- Code (file-copy deploy; prior versions backed up under
  `backups/task-monitoring-mom-permission-revision-20260701-023642/`):
  `core/access_control.php`, `modules/reminder/model.php`,
  `public/api/reminder-get.php`, `reminder-toggle.php`,
  `reminder-update.php`, `task-delete.php`, `task-toggle.php`,
  `task-update.php`, `public/checklist.php`, `public/index.php`,
  `public/mom.php`, `public/modules/mom/controller.php`.
- Migration `config/migrations/2026_07_01_task_monitoring_mom_permission_revision.sql`
  applied to `vickryid_tracs_alpha` (MariaDB): grants
  `checklist.manage`, `reminders.view`, `reminders.manage`, `moms.view`,
  `moms.manage` to the `intern` and `viewer` roles (both were previously
  missing or view-only on these three modules).
- `php8.3-fpm` reloaded to clear opcache.

Verification on production:

- `php -l` clean for all 12 deployed PHP files.
- Role-permission grants confirmed live for `intern` and `viewer`
  (all 5 permission keys present).
- `nginx`, `php8.3-fpm`, `mysql` all active; no PHP fatal errors in
  `php8.3-fpm.log` after reload; no new nginx errors beyond a pre-existing,
  unrelated static-asset rule.
- `login.php` returns `200`; `checklist.php`, `mom.php`, `reminders.php`
  return `302` to login when unauthenticated (expected, no 404/500).
- Pre-deploy drift check: production copies of `core/access_control.php`
  and `public/index.php` differed only by CRLF line endings from the
  `main` baseline (byte-identical content) — no production-only changes
  were overwritten.
- Local pre-deploy regression pass (Docker `tracs_db`/`tracs_app`, real
  DB, real users): 15/15 checks passed — cross-user checklist/MoM update
  allowed, cross-user delete blocked, `created_by` immutable, `updated_by`
  correctly attributed, reminder view/update public, non-monitor task
  assignment visibility unchanged.

The branch remains pushed for review/PR; production tracks the working
tree via file-copy deploy (not a `main` pull).

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
