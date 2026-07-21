# TRACS Deployment Summary

Status: Deployed successfully (remediated)
Completed: 2026-07-15 19:13 WIB
Domain: https://tracs.vickry.id

## Deployed — MoM History 24-Hour Visibility Bug Fix (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `feat/dashboard-quick-tools-widgets`, commit `d460197`.
1 file (`mom.php`), deployed via `scp` + `sha256sum` drift-check/verify, backed up to `/opt/tracs/backups/mom-history-filter-fix-20260721-162423/` before overwrite. No migration.

### Changes Deployed
User reported meeting records "missing" from `mom.php`. Investigation on the production VPS (read-only: row counts, `AUTO_INCREMENT` gap check, audit log, nginx/php-fpm logs, DB backups) confirmed no data was ever lost — `tracs_moms` held all 11 records ever created, IDs 1-11 contiguous, no deletions. Root cause was a display bug: `mom_recent_history()` (`mom.php`) only showed `completed`/`cancelled` meetings if `completed_at`/`cancelled_at`/`updated_at` was within the last 24 hours, so every meeting older than a day — including the two real weekly Friday CS meetings among a batch of dev/test entries — silently dropped out of the page and the total count. Fix: renamed to `mom_is_history()` and removed the 24-hour cutoff, keeping only the `completed`/`cancelled` status check, so history shows every past meeting (existing `usort` already sorts newest-first).

### Verification
- Pre-deploy drift-check: `mom.php` matched the previous deploy (`77a1e48`) exactly, no drift.
- `php -l` clean locally and on the server.
- Post-deploy `sha256sum` matches local exactly.
- `php8.3-fpm` reloaded cleanly (`active`).
- `https://tracs.vickry.id/mom.php` → `302` (expected login redirect, unauthenticated GET).
- **Not** verified: an authenticated browser walkthrough confirming "Meeting jumat" and "Meeting Mingguan CS" now render in the history list. Local Docker verification wasn't available (daemon unresponsive on this machine). A manual check on next login is recommended.

## Deployed — Cases Grid Row Explicit Height Fix (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `feat/dashboard-quick-tools-widgets`, commit `f4a913e`.
1 file (`tracs.css`), deployed via `scp` + `sha256sum` drift-check/verify, backed up to `/opt/tracs/backups/cases-grid-row-height-fix-20260721-155511/` before overwrite. No migration.

### Changes Deployed
Second attempt at the Cases scroll fix — the previous one (`8ef7ff2`) capped only `.dashboard-case-panel`'s own height, missing that `.col-left` actually stacks Infrastructure Pulse *above* Cases (not Cases alone), so `.col-left`'s total natural height could still exceed `.dashboard-workspace`'s, re-poisoning `.dash-grid`'s auto row-height calculation the same way — confirmed live via a follow-up screenshot showing the list still rendering uncapped.

Root cause: `.dash-grid`'s row had no explicit height, so it was sized from the max natural (unstretched) height of every item in it, including whatever `.col-left` contains. Fix: give the row an explicit `--dashboard-row1-height` (the `.dashboard-workspace` stack's genuinely bounded total — two `clamp(430px,48vh,540px)` panels + Dobby + two row-gaps), independent of either column's content. `.col-left` now has a real external height ceiling (`overflow:hidden`); Infrastructure Pulse stays `flex:0 0 auto`; `.dashboard-case-panel` flexes to fill the remainder, with `.dashboard-case-list` clipping/scrolling internally regardless of case count. The `<960px` mobile breakpoint (single-column stacked layout) resets `grid-template-rows` back to `auto auto` and `overflow:visible`, since that layout already intentionally lets Cases flow without internal scrolling.

### Verification
- Pre-deploy drift-check: `tracs.css` matched the previous deploy (`87561d3`) exactly, no drift.
- Post-deploy `sha256sum` matches local exactly.
- `https://tracs.vickry.id/` → `200`.
- **Not** verified: an authenticated browser walkthrough confirming the Cases list now genuinely clips at the fixed row height with both columns' bottom edges aligned. Given this is the second attempt at the same bug (the first shipped without catching the Infrastructure Pulse interaction), a manual visual check is strongly recommended before considering this closed.

## Deployed — Cases Scroll Regression Fix & Currency Rate Header Move (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `feat/dashboard-quick-tools-widgets`, commit `8ef7ff2`.
3 files, deployed via `scp` + `sha256sum` drift-check/verify, backed up to `/opt/tracs/backups/cases-scroll-currency-header-fix-20260721-154009/` before overwrite. No migration.

### Changes Deployed
1. **Regression fix**: the previous deploy (`578d8e4`) removed the Cases widget's hardcoded 8-item cap but left `.dashboard-case-panel` with only a `min-height` floor and no real ceiling. With 21 cases, the panel's own intrinsic height ballooned to fit every row, which fed back into the CSS Grid row's auto-sizing and defeated `.dashboard-case-list`'s internal scroll entirely — the full list rendered inline instead of clipping, making the widget (and page) excessively tall, as reported by the user from a live screenshot. Fixed by giving `.dashboard-case-panel` an explicit `height`/`max-height` matching the *actually bounded* `.dashboard-workspace` stack beside it (two `clamp(430px,48vh,540px)` `.task-monitoring-panel` instances + the Dobby filler + two row-gaps) via `calc()`, so the list scrolls internally regardless of case count.
2. **Currency Converter**: moved the realtime rate indicator from the widget body into the panel-head's `.panel-right` (matching the Cases panel-head's existing total/counter/All/Add convention), per user feedback that the previous placement wasn't visible/obvious as a header-level update. Compacted to a single line (pair + rate); exact updated time moved to a `title` tooltip to fit the header's single-row height.

### Verification
- Pre-deploy drift-check: all 3 files matched the previous deploy (`9c7a5e8`) exactly, no drift.
- Post-deploy `sha256sum` of all 3 files matches local exactly.
- `php -l` clean on `index.php`; `php8.3-fpm` reloaded cleanly, no errors in the journal since deploy.
- `https://tracs.vickry.id/` → `200`.
- **Not** verified: an authenticated browser walkthrough confirming the Cases list now actually clips/scrolls at the intended height and the currency rate renders correctly in the header — shipped on static review only (JS syntax check, CSS brace balance, PHP lint, CSS custom-property scope check). Given this fixes a regression the user caught live, a manual visual check on next login is strongly recommended.

## Deployed — Cases Widget Adaptive Layout & No-Reload Gap Closures (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `feat/dashboard-quick-tools-widgets`, commit `578d8e4`.
3 files, deployed via `scp` + `sha256sum` drift-check/verify, backed up to `/opt/tracs/backups/cases-widget-noreload-audit-20260721-151912/` before overwrite. No migration in this pass.

### Changes Deployed
1. **Cases widget**: removed the hardcoded `array_slice($dashboard_cases, 0, 8)` cap (and the on-hold backfill loop it required) — the widget now renders every dashboard-visible case and relies on the existing scrollable `.dashboard-case-list` to stay bounded instead of an arbitrary item limit. "View all cases" footer is now unconditional (was previously only shown when the old cap caused overflow), giving a true sticky footer. `.panel-head` pinned to `flex: 0 0 auto` so it can't be squeezed.
2. **No-reload audit found two real gaps** left from the earlier no-reload pass: `deleteCase()` updated `cases.php`'s board state but never touched the dashboard's Cases widget, so deleting a case from the dashboard showed a success toast while the row silently stayed on screen until manual reload — now swaps `.dashboard-case-panel` when present. Shift Handover's `editHandoverSummary()` and `resolveShiftReport()` still called a full `location.reload()` — both now use the same `tracsRefreshTaskMonitoringPanel('#dashboard-pane-shift-handover')` pattern the sibling shift-item-edit function already used. Checklist, Reminder, and the Quick Tools widgets (Screenshot/Currency) were already reload-free — audited, no changes needed.

### Verification
- Pre-deploy drift-check: all 3 files matched the previous deploy (`dfb9468`) exactly, no drift.
- Post-deploy `sha256sum` of all 3 files matches local exactly.
- `php -l` clean on `index.php`; `php8.3-fpm` reloaded cleanly, no errors in the journal since deploy.
- `https://tracs.vickry.id/` → `200`, `/login.php` → `200`.
- **Not** verified: an authenticated browser walkthrough of the adaptive Cases scroll behavior or the delete/resolve/edit-summary flows — shipped on static review only (JS syntax check, CSS brace balance, PHP lint, cross-references to confirm no other call sites still reference the removed variables/functions). Recommend a manual pass covering: adding enough cases to trigger internal scroll, deleting a case from the dashboard widget, and resolving/editing a shift handover summary from the dashboard tab.

## Deployed — Currency Converter Workflow Prioritization (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `feat/dashboard-quick-tools-widgets`, commit `cbadbcc`.
3 files, deployed via `scp` + `sha256sum` drift-check/verify, backed up to `/opt/tracs/backups/currency-widget-refinement-20260721-145217/` before overwrite. No migration in this pass (frontend/layout only).

### Changes Deployed
1. Reordered the Currency Converter widget so the conversion form (currency selectors, amount, Convert button, result) sits at the top instead of below informational cards — matches the input → convert → result workflow.
2. Realtime rate dropped from a bordered card to a compact single-line inline indicator (pair, rate, updated time) directly below the conversion result.
3. "Last Converted" lost its redundant rate text (already shown by the realtime line) — now just amount, currency pair, and relative timestamp.
4. Reduced `.currency-body` vertical spacing for a tighter, more compact widget consistent with the rest of the dashboard.

### Verification
- Pre-deploy drift-check: all 3 files matched the previous deploy (`3530c9a`) exactly, no drift.
- Post-deploy `sha256sum` of all 3 files matches local exactly.
- `php -l` clean on `index.php`; `php8.3-fpm` reloaded cleanly, no errors in the journal since deploy.
- `https://tracs.vickry.id/` → `200`, `/login.php` → `200`.
- **Not** verified: an authenticated browser walkthrough — local Docker is still not exercised this session (the earlier disk-space issue was resolved mid-session; a live pass is still recommended). Shipped on static review only (JS syntax check, CSS brace balance, PHP lint, ID cross-checks).

## Deployed — Website Screenshot Widget Hierarchy Refinement (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `feat/dashboard-quick-tools-widgets`, commit `e74fdd4`.
3 files, deployed via `scp` + `sha256sum` drift-check/verify, backed up to `/opt/tracs/backups/screenshot-widget-refinement-20260721-141121/` before overwrite. No migration in this pass (frontend/layout only).

### Changes Deployed
1. Moved the URL input, Capture button, and region selector to the top of the Website Screenshot widget (above history) so starting a new capture never requires scrolling past prior results.
2. Simplified history cards to thumbnail, status badge, region, and timestamp only — resolution, size, and full URL moved out of the compact grid.
3. History is now a uniform grid of equally-sized cards (larger thumbnail, hover/focus lift animation) instead of an enlarged "latest" card plus a smaller strip.
4. Clicking any history card opens the same two-column detail modal a fresh capture uses (full URL, region, timestamp, resolution, size, capture duration, Download/Open Full Size/Copy Image URL/Capture Again) — reuses the already-fetched history row, no extra request.

### Verification
- Pre-deploy drift-check: all 3 files matched the previous deploy (`c130758`) exactly, no drift.
- Post-deploy `sha256sum` of all 3 files matches local exactly.
- `php -l` clean on `index.php`; `php8.3-fpm` reloaded cleanly, no errors in the journal since deploy.
- `https://tracs.vickry.id/` → `200`, `/login.php` → `200`.
- **Not** verified: an authenticated browser walkthrough — local Docker remains unusable this session (disk space has since dropped further, to ~491MB free / 96% used, independent of this work). Shipped on static review only (JS syntax check, CSS brace balance, PHP lint, ID cross-checks). Recommend a manual pass once the disk-space issue is resolved.

## Deployed — Dashboard Quick Tools: Website Screenshot & Currency Converter Overhaul (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `feat/dashboard-quick-tools-widgets`, commit `dbe9802` (also carries the previously-committed, not-yet-deployed `fix/cases-status-access-audit` case-status-permission fix, commit `9e4831f`, since both landed in the same `tracs.js`/`index.php` file-copy).

### Changes Deployed
1. **Website Screenshot widget**: persisted capture history (new `screenshot_history` table + disk-backed storage under `public/uploads/screenshot_history/`, mirroring the shift-attachment pattern) shows the latest capture or a proper empty state instead of a blank panel on load; redesigned two-column preview modal (image + URL/region/time/resolution/size); region dropdown now populated live from PageFleets `GET /api/v1/regions` instead of a hardcoded 2-region list — the API actually serves 3 (`id-1`, `us-1`, `sg-1`/Singapore was missing).
2. **Currency Converter widget**: realtime USD/IDR rate card (auto-refreshing every 5 min, server-cached to avoid hammering Frankfurter), "Last Converted" summary, and a compact recent-history list replace the previously bare converter form. Formalized the ad hoc `tracs_currency_history` table (previously `CREATE TABLE IF NOT EXISTS`'d inline on every conversion) into a proper migration.
3. **Section rename**: "Dashboard Widgets" → "Quick Tools" (avoids overlapping with the adjacent Task Monitoring panel's language).
4. **Layout fix**: Cases panel now fills its full grid-stretched height instead of a fixed clamp, so it no longer falls visibly short of the taller Quick-Tools-plus-Task-Monitoring stack beside it.
5. **Code cleanup**: removed dead/duplicate currency module files never wired to any route (`modules/currency/{controller,model,view}.php`, `public/api/currency-converter.php`); fixed a `_bootstrap.php` gap where `screenshot-capture.php` had no registered method/permission entry.

### Verification
- Pre-deploy drift-check: of the 6 modified/deleted-from files already live, 4 matched the expected prior-commit baseline (`9e4831f`) exactly; `tracs.js`/`index.php` matched one commit further back (`2b4c271`) — expected, since the case-status-permission fix was committed this session but never previously deployed, not unexplained drift.
- Backed up all 10 touched/removed files to `/opt/tracs/backups/dashboard-quick-tools-20260721-114454/` before overwrite.
- Migration applied to `vickryid_tracs_alpha`: created `screenshot_history` (2 indexes); `tracs_currency_history` already existed from prior ad hoc use (1261 rows) — `CREATE TABLE IF NOT EXISTS` was a no-op there, so the new `created_at` index was added separately via `ALTER TABLE ... ADD INDEX IF NOT EXISTS`.
- `php -l` clean on all 10 deployed PHP files; dead files confirmed removed.
- Post-deploy `sha256sum` of all 12 deployed files matches local exactly.
- `php8.3-fpm` reloaded; `nginx`/`php8.3-fpm` both active; no new errors in `nginx` error log or `php8.3-fpm` journal since deploy (only pre-existing unrelated bot/scanner noise).
- `https://tracs.vickry.id/` → `200`, `/login.php` → `200`, `/api/screenshot-regions.php` → `401`, `/api/currency-rate.php` → `401` (auth-gated as expected, no 404/500).
- **Not** verified: an authenticated browser walkthrough of either widget. Local Docker was broken (containerd I/O errors, low host disk space) for the entire session, so this shipped on static review only (PHP lint, JS syntax check, CSS brace balance, ID cross-checks between markup and JS) — the user explicitly chose to skip live verification and deploy as-is. Recommend an authenticated pass on both widgets (capture flow, region dropdown, currency rate/history) at the next opportunity.

## Deployed — No-Reload Save Flow Audit (2026-07-21)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `fix/intern-user-creation-audit`, commit `492e2c3`.
6 files, deployed via `scp` + `sha256sum` drift-check/verify, backed up to `/opt/tracs/backups/no-reload-audit-20260721-034024/` before overwrite.

### Changes Deployed
1. **Eliminated page-reload-after-save across the dashboard, User Management, Monitoring, and Domain Price Crosscheck**:
   - New `tracsSwapFragment`/`tracsFetchDocument` helpers in `tracs.js` refetch the current URL and swap only the affected DOM section instead of reloading.
   - Case, Reminder, Task, Shift Handover, and Ticker saves on the dashboard now patch their widgets in place.
   - Ops-status marquee slider extracted out of its `DOMContentLoaded` closure into module scope so it can be safely re-bound after a save without stacking duplicate listeners.
   - Fixed `bindModalAjaxForms` (used by `user-management.php` and `monitoring.php`) comparing raw redirect strings instead of resolved pathnames — the old check silently forced a real reload on every save for these two pages. Auto-generated temporary passwords still force a real navigation via a `force_navigate` flag, since that reveal is a show-once session-flash banner.
   - `domain-price-crosscheck.js` matrix/notes/task-assignment saves and the `finance.php`/`domain-transfer.php` month filter converted to the same fragment-refresh pattern.
   - Also picked up the previously-committed, not-yet-deployed intern-field validation fix (`ee48853`) on `user-management.php`.

### Verification
- Pre-deploy drift-check: 5 of 6 files matched the expected prior-commit baseline exactly; `user-management.php` matched one commit further back (`ee48853` not yet deployed) — expected, not unexplained drift.
- Post-deploy `sha256sum` of all 6 files matches local exactly.
- `php -l` clean on all 4 PHP files.
- `php8.3-fpm` reloaded successfully.
- `https://tracs.vickry.id/` → `302`, `/login.php` → `200`, `/user-management.php` → `302` (auth redirect, expected), `/assets/tracs.js` → `200`.
- Save-flow fixes were live-tested against a local Docker replica (not prod) before this deploy — see branch history for details.

## Deployed — Hotfix: Minutes of Meeting UI/API Permission Restoration (2026-07-15)

Status: **Remediated in production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`).
Changes: Restored readable permissions (`644`) on `public/assets/mom-styles.css` and `public/api/api_mom.php` on both local workspace and VPS.

### Changes Deployed
1. **Hotfix: Stylesheet & API Permission Fix**:
   - Fixed file permissions of `public/assets/mom-styles.css` and `public/api/api_mom.php` from `600` (restricted) to `644` (readable by `www-data`).
   - Resolves layout issues (stacked columns) and broken AJAX/API features on `mom.php` due to HTTP `403 Forbidden` errors returned to the client and php-fpm respectively.

## Deployed — Real-time Auto-Save Workflows, Calendar & Shift Assignment UI Parity (2026-07-15)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `codex/dashboard-tabs-calendar-checkbox-audit`.
41 files (32 modified + 9 new).
Deployed via secure rsync, file-permissions hardening, and php-fpm reload.

### Changes Deployed

1. **Real-Time Auto-Save Workflows & Collaboration**:
   - Added real-time auto-save logic to `cancellation_feedback.php`, `domain-transfer.php`, `finance.php`, `mom.php`, and their respective APIs.
   - Built a dedicated helper script `public/assets/feedback-autosave.js` for form auto-save and sync.
   - Added `_realtime_payloads.php` to handle active payload caching.
   - Implemented real-time collaboration/editing detection using the active payloads API (`_realtime_payloads.php`) to show who is currently editing a form, prevent conflicts, and synchronize field changes dynamically.
2. **Calendar & Shift Assignment React/Tailwind Fixes**:
   - Fixed rendering issues and responsive views for the React `MonthMiniCalendar`, `MonthView`, and `WeekView` components.
   - Refined tailwind css styles for the calendar and shift assignment views, ensuring consistency across different screens.
   - Rebuilt the frontend React bundles (`calendar` and `shiftAssignment`) so the production build matches the revised sources (updating Vite manifests and generated bundle chunks).
   - Resolved specific visual issues with domain price crosscheck css, date range picker css, shifting assignment css, and general TRACS main css styles.

### Verification
- `https://tracs.vickry.id/login.php` returns `200`
- `https://tracs.vickry.id/monitoring.php`, `/checklist.php`, `/shift-reports.php` all return `302` (not `500`)


## Deployed — Task Monitoring Sorting, Routing, and Recurrence Update (2026-07-12)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`, `https://tracs.vickry.id`). Branch `fix/monitoring-task-assignment-routing-and-modal`, commit `7755a40`.
Deployed via secure rsync and php-fpm reload.


## Deployed — Checklist History Rework, Task Management Wiring, Monitoring Filter Fix (2026-07-05 ~04:07 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `dcb8a05`.
16 files (12 modified + 4 new).
File backup `/opt/tracs/backups/checklist-taskmgmt-wiring-20260705-040751/` (12 files).
DB backup (pre-migration, full gzip)
`/opt/tracs/backups/db-pre-checklist-taskmgmt-wiring-20260705-040712/tracs-full.sql.gz`.

Three user-requested changes in one deploy:

1. **"Checklist History" reworked into "View All Checklist"**: a bigger
   `modal-lg` popup with Active/History tabs and an Add Task button in the
   toolbar. Active tab lists every checklist item (not just the widget's
   compact scroll), reusing the same `toggleTask`/`openEditTask`/`deleteTask`
   wiring via shared `data-tid` so it stays in sync with the dashboard widget
   automatically — no separate state to manage. History tab's description
   text now names the actual item instead of a generic "Task marked
   complete". Checklist items also gained click/drag/paste screenshot
   upload, backed by a new `tracs_checklist_attachments` table.
2. **Checklist ↔ Task Management wiring**: checklist items created from the
   dashboard widget now also create a self-assigned Task Assignment
   (`tracs_tasks` + `tracs_task_assignments`), mirroring the existing reverse
   flow where assigning a task already creates a linked checklist item.
   Toggle/edit/delete all propagate to the linked assignment; deleting a
   checklist item also removes the assignment and the task if it was the
   only assignee. User explicitly chose this deeper integration over a
   read-only alternative after being asked directly, since it's a bigger,
   less reversible change (touches the already-live task-management sync
   logic).
3. **Fix**: monitoring.php's "More filters" popover was anchored `left:0` on
   a trigger sitting near the right edge of the filter row, so it overflowed
   off-screen (cut off, overlapping the Apply button) — screenshotted by the
   user. Right-anchored it instead. It also didn't close on an outside
   click; added it to the app's existing shared popup-close mechanism
   (`TRACS_POPUP_DETAILS_SELECTOR`) rather than writing a one-off handler.

Caught and fixed a real testing mistake during local verification: nested
`begin_transaction()` calls implicitly commit the outer transaction in
MySQL/MariaDB, so an early "rolled back" verification actually left a real
row in the dev DB. Traced it, cleaned it up by exact id+title match (never
positional), and re-verified correctly afterward — flagged here since it's
a pattern worth remembering for any future test harness in this codebase.

Migration verified on prod in a controlled run after deploy (DB backed up
first, not wrapped in a redundant outer transaction this time): the checklist
→ task-assignment lifecycle (create → mirror created → permission check →
delete cascade) ran for real against prod, then was cleaned up via the
production `deleteTaskFromChecklist()` cascade itself plus one explicit
delete-by-exact-id for the checklist row. Final sweep confirms 0 stray rows
and 0 rows in the new attachments table.

Deploy steps: full DB dump → schema dependency check (`linked_assignment_id`
column, `tracs_tasks`/`tracs_task_assignments` tables already present on
prod from the earlier Task Assignment deploy) → drift-check against
`fb4812c` baseline (all 12 modified files matched, 0 drift) → back up prod
files → `scp` 16 files → `chown vickry:www-data` → `php -l` all 14 PHP files
(clean) → sha256 local↔prod verified for all 16 → `systemctl reload
php8.3-fpm`.

Verification:
- `https://tracs.vickry.id/login.php` returns `200`
- `https://tracs.vickry.id/monitoring.php`, `/checklist.php`,
  `/shift-reports.php` all return `302` (not `500`)
- `https://tracs.vickry.id/api/checklist-attachment.php`,
  `/api/checklist-history.php`, `/api/checklist-list.php` all return `401`
  (routes + auth gate working)

## Deployed — Consistent Click/Drag/Paste Image Upload Across Modals (2026-07-05 ~02:27 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `7fd75fa`.
15 files (13 modified + 2 new).
File backup `/opt/tracs/backups/upload-consistency-20260705-022703/` (13 files).
DB backup (pre-migration, full gzip)
`/opt/tracs/backups/db-pre-upload-consistency-20260705-022505/tracs-full.sql.gz`.

User-requested audit: every screenshot/photo upload surface in the app
should support the same click, drag-drop, and paste interaction — not just
the shift handover modal from the previous deploy.

- **Case modal**: added paste-to-upload (already had click + drag-drop).
- **MoM screenshots** (`mom.php` sidebar card): was single-file, click-only,
  immediate-upload. Refactored into `uploadMOMScreenshotFile`/`Files`, added
  multi-file select, drag-drop, and paste (gated to the card's existing
  edit-mode toggle).
- **Add Task Assignment modal** (`monitoring.php`): had no attachment support
  at all. Added a full click/drag/paste screenshot uploader backed by a new
  `tracs_task_attachments` table + `task-attachment-lib.php` (mirrors the
  proven shift-attachment pipeline, kept as an independent file rather than a
  shared refactor to avoid touching the already-deployed shift code), a new
  `tracs_can_view_task()` permission check (monitor access, or the task's
  creator/assigner/assignee), and a serving endpoint
  (`api/task-attachment.php`, permission gated via `api_require_any_permission`
  since the map-based gate is AND-only). Screenshots render in the task
  detail panel.
- One shared `tracsHandleImagePaste` in `tracs.js` now covers every modal
  (shift summary, shift item, case, Add Task) instead of one-off listeners.
- Also finished the previous deploy's shift-handover polish: shared
  "shift notes" screenshot section (attaches to the handover, not a specific
  case), summary now required / items optional, fixed a real double-spacing
  bug in the item card (wasn't a flex container), and fixed a broken
  "edit summary" prompt that always no-opped (used the overridden synchronous
  `window.prompt` instead of the async `tracsPrompt`).

**Drift note**: `mom.php`, `mom-functions.js`, and `mom-styles.css` showed
drift against the last-deployed baseline before this push. Investigated by
diffing prod's live copies against git — the direction was "prod is behind"
(missing already-committed UI polish: sticky header, section group labels,
discussion-note clamping, the Timeline card), not "prod has unique work."
Confirmed safe to overwrite; this deploy also catches prod up on that
already-approved-but-undeployed work.

Migration verified on prod in a controlled run after deploy (DB backed up
first): `tracs_task_attachments` created with expected columns,
`tracs_can_view_task()` correctly allows the task's owner/assignee/monitor
and denies unrelated users (tested against both real prod data and outsider
accounts), no rows mutated.

Deploy steps: full DB dump → drift-check against `630d96f` baseline (3 files
flagged, investigated and confirmed safe) → back up prod files → `scp` 15
files → `chown vickry:www-data` → `php -l` all 11 PHP files (clean) → sha256
local↔prod verified for all 15 → `systemctl reload php8.3-fpm`.

Verification:
- `https://tracs.vickry.id/login.php` returns `200`
- `https://tracs.vickry.id/shift-reports.php`, `/monitoring.php`, `/mom.php`
  all return `302` (not `500`)
- `https://tracs.vickry.id/api/task-attachment.php` returns `401`
  (route + auth gate working)

## Deployed — Comprehensive Multi-Item Shift Handover (2026-07-04 ~16:05 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `cb39e24`.
11 files (9 modified + 2 new).
File backup `/opt/tracs/backups/shift-handover-20260704-160357/` (9 files).
DB backup (pre-migration, full gzip)
`/opt/tracs/backups/db-pre-shift-handover-20260704-160242/tracs-full.sql.gz`.

User-requested rework + audit of the shift-handover flow on the dashboard
widget and `shift-reports.php`. Real-world case: an agent ending a shift files
one handover covering **many** cases, but the old modal/model treated one row
as one case, so a multi-case handover became several disconnected "reports".

- **Root cause**: `tracs_shift_reports` conflated the *handover* (one agent,
  one shift, one date) with the *case* (a single item). No parent entity, no
  place for an overall shift summary, and grouping was by shift only (never by
  author).
- **Fix (Option B — parent + child)**: new `tracs_shift_handovers` table
  (agent, shift, date, `summary`, `submitted_at`) + nullable `handover_id` on
  `tracs_shift_reports`, both created at runtime via the existing
  `ensure*Schema` pattern. An idempotent `backfillHandovers()` bundles legacy
  rows into one synthetic handover per `(agent, shift, date)` on first load.
- **API**: `shift-handover-create.php` (one transactional multi-item submit)
  and `shift-handover-update.php` (edit summary); per-item screenshots upload
  in a second pass reusing the existing `shift-update.php` attachment pipeline.
- **UI**: the modal is now a multi-item card list with a shift summary (edit
  mode reuses it for a single item); the dashboard widget and reports page
  group items by shift/date, then per-agent handover block with a summary line.
- **Also**: fixed a fatal placeholder/bind mismatch in the backfill INSERT
  (surfaced as a local 500), and removed currency-converter debug console
  noise + the build-signature console banner.

Migration verified on prod in a controlled run after deploy (DB backed up
first): `tracs_shift_handovers` created, `handover_id` column present, all 10
existing reports backfilled into 10 handovers, **0 orphans**, `getHistory`
reads back with `handover_id` populated.

Deploy steps: full DB dump → drift-check (all 9 modified files matched the
`2271b54` baseline on prod) → back up prod files → `scp` 11 files →
`chown vickry:www-data` → `php -l` all 8 PHP files (clean) → sha256
local↔prod verified for all 11 → `systemctl reload php8.3-fpm`.

Verification:
- `https://tracs.vickry.id/` returns `302` to `/login.php`
- `https://tracs.vickry.id/login.php` returns `200`
- `https://tracs.vickry.id/shift-reports.php` returns `302` (not `500`)
- `https://tracs.vickry.id/api/shift-handover-create.php` returns `401`
  (route + auth gate working)

## Deployed — Collapsed-Rail Nav: Square Active/Hover Indicator, Drop Left Accent Bar (2026-07-04 ~10:40 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `a18a67a`.
1 file: `public/assets/tracs.css`.
Backup `/opt/tracs/backups/nav-square-indicator-20260704-104039/`.

User-requested audit: the active/hover nav-item highlight in the
collapsed sidebar rail looked horizontally stretched instead of a
proportional square around the icon.

- **Root cause**: the active/hover background is painted on `.nav-item`
  itself, which is `width: auto` and fills whatever lane width is
  available -- ~47px in the collapsed rail against a fixed 36px row
  height, a rectangle rather than a square. The icon was already
  exactly centered in the 64px rail by construction (existing comment:
  `gutter + pad + icon/2 == half the rail width`), so only the
  highlight's shape was wrong, not its position.
- **Fix**: added `.nav-icon::before`, a decorative pseudo-element sized
  to a fixed `36x36` (`var(--sb-row-h)`) square, centered via
  `top/left: 50%` + `translate(-50%, -50%)` on `.nav-icon` -- independent
  of the row's own width, so it's always a 1:1 square with equal padding
  on every side regardless of layout. In the collapsed state (including
  while forced-collapsed by the account menu being open), `.nav-item`'s
  own background/border go transparent and the highlight moves onto
  this square instead. Same transition timing (`var(--dur) var(--ease)`),
  same `--sb-radius` token, same color tokens (`--blue-lt`, `--blue-bd`,
  `--s3`, `--bd2`) -- fully reused from the existing design system, no
  new values introduced. Scoped to `@media (min-width: 641px)` only;
  mobile already handles its own icon-only sizing independently and is
  untouched.
- **Follow-up in the same deploy**: removed the pre-existing 3px left
  accent bar on `.nav-item.active::before` per direct feedback after
  the first pass (`- just remove left vertical line accent`) -- it was
  a separate decorative flourish unrelated to the square fix. Mobile's
  own bottom-underline active indicator reused the same `::before` and
  depended on the removed base rule for `content`/`position`/
  `background`, so it's now a fully self-contained declaration instead
  of relying on that shared base.

Verified locally against the docker dev stack before deploying (per
explicit instruction to work locally first and not deploy until fully
verified):

- Computed styles confirm the highlight is exactly `36px x 36px`,
  `8px` radius (`--sb-radius`), across all 11 sidebar nav icons --
  consistent sizing/alignment/spacing check.
- Confirmed via `getComputedStyle` that non-active/non-hover icons stay
  fully transparent, and that the expanded (full pill) state is
  completely unchanged.
- Confirmed the square renders correctly in the "account menu open,
  forced collapse" edge case, not just the plain resting collapsed
  state.
- Zoom levels 0.75x-1.5x and viewport resize (1024x768, mobile 375px):
  ratio stays exactly 1:1 at every level; mobile's own layout is
  untouched (scoped media query) and its bottom-underline indicator
  still renders correctly post-refactor.
- Checked both light and dark theme -- token colors resolve correctly
  in both.
- After removing the accent bar: confirmed `content: none` on
  `.nav-item.active::before` on desktop (fully gone, both collapsed and
  expanded), and confirmed mobile's bottom-underline still renders
  (`content:""`, 2px height, full width) via its own self-contained
  rule.

Verification on production:

- No PHP touched -- static asset, cache-busted via `filemtime()`, no
  `php-fpm` reload needed.
- Drift-check before deploy: prod's `tracs.css` sha256 matched the
  previous deploy's commit (`6d28e8e`) exactly -- no untracked drift.
- Post-deploy sha256
  (`a92bab1e17d059b4765ab9f00fdb8513eb5f761f26fc474ad259ecf3c4e26c57`)
  matches the local working tree byte-for-byte.
- `https://tracs.vickry.id/login.php` → 200; cache-bust version advanced
  to `?v=1783136456`.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Sidebar Profile Rework: Two-Row Name/Position + Collapse-on-Open Menu (2026-07-04 ~09:45 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `6d28e8e`.
2 files: `public/assets/tracs.css`, `public/includes/header.php`.
Backup `/opt/tracs/backups/sidebar-profile-rework-20260704-094526/`.

User-requested rework of the sidebar profile/account-menu flow, on top
of the earlier blank-label/name-display fix:

- **Two-row profile label**: the sidebar row next to the avatar now
  shows the user's name and position stacked (name bold/primary,
  position smaller/muted below it), instead of a single line. Added
  `$_header_position` in `header.php`, resolved as
  `$_header_user['position'] ?: $_header_user['role_name'] ?: tracs_role_fallback_meta($_header_user['role_slug'])['name']`
  so accounts without a `position` value set still show something
  sensible (their role name) rather than a blank second line. New
  markup: `.user-menu-info` wrapper (kept the `nav-label` class so it
  still participates in the existing hover-reveal/opacity system) with
  `.user-menu-name` / `.user-menu-position` children; `.user-menu-row`
  changed from a fixed 36px row to `min-height` + auto so it can grow to
  fit two lines.
- **Collapse-on-open flow**: previously, clicking the avatar opened the
  floating account dropdown while the *full expanded nav* stayed open
  behind it (since focusing the summary via click keeps `:focus-within`
  true) — both panels visible/overlapping at once. Reworked so opening
  the account menu now always collapses the nav rail first. Every
  hover/focus-within expand trigger in the stylesheet
  (`.sidebar-flyout` width, `.nav-label` opacity, `.nav-chevron`
  opacity, `.nav-pin` display) is now guarded with
  `:not(:has(.user-menu-wrap[open]))`, so all of them turn off the
  moment the menu opens, regardless of continued hover/focus, and
  re-evaluate normally (re-expanding if still hovered) once it closes.
  `.user-menu`'s own anchor position changed from
  `calc(var(--sb-w-open) + 10px)` to `calc(var(--sb-w) + 10px)` to match
  — it now always anchors off the *collapsed* rail width, since the rail
  no longer stays expanded while the menu is visible.
- **Animation**: both the nav-collapse (`.sidebar-flyout`'s existing
  `transition: width`) and the menu's own open/close fade (already fixed
  in the previous two deploys) run off the same `[open]` attribute flip,
  so they already animate together with no new transition timing needed.

Verified pre-deploy against the docker dev stack as both
`gagas@idcloudhost.co.id` (operator, position "Customer Support" — real
DB value, confirms the position fallback chain reads real data not just
the role fallback) and `admin@tracs.local` (super_admin):

- Hovered the collapsed rail — two-row name/position renders correctly,
  screenshot-confirmed in dark theme.
- Clicked the profile row — nav fully collapses back to the icon rail
  and the account menu renders anchored beside it, screenshot-confirmed
  (this was the core ask: no more full-nav-plus-menu overlap).
- Clicked the avatar again to close — menu closes and, since the
  summary still had focus, nav re-expanded correctly (expected
  `:focus-within` behavior).
- Re-opened the Tasks & Monitoring accordion afterward to confirm it's
  unaffected (still expands independently; unrelated to the
  `.user-menu-wrap[open]` guard) and still mutually closes the profile
  menu as before.
- Re-checked mobile (375px): unaffected, as expected — mobile's sidebar
  never uses the hover/focus-within expand path this change touches.

Verification on production:

- `php -l` clean on `header.php`.
- Drift-check before deploy: both files' prod sha256 matched the
  previous deploy's commit (`351fb23`) exactly — no untracked drift.
- Post-deploy sha256 matches the local working tree byte-for-byte on
  both files.
- `sudo systemctl reload php8.3-fpm` (opcache cleared); `nginx` and
  `php8.3-fpm` both `active`.
- `https://tracs.vickry.id/login.php` → 200.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Sidebar Profile Row: Blank Label Fix + Show Name Instead of Email (2026-07-04 ~09:27 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `351fb23`.
1 file: `public/includes/header.php`.
Backup `/opt/tracs/backups/profile-name-display-20260704-092738/`.

User reported the username/email next to the sidebar avatar had gone
blank, and asked to show the display name there instead of the email
once fixed.

- **Root cause of the blank label**: `header.php:373` rendered
  `$user_email` directly — a variable each including page must pass in
  itself — while the dropdown header just above it (`.user-menu-head`)
  already used `$_header_user['display_name']`, populated by a DB fetch
  inside `header.php` itself and therefore reliable regardless of what
  the calling page passed in. When `$user_email` wasn't set on a given
  page, the row went blank while the dropdown (built from
  `$_header_user`) still worked — matching the exact symptom reported.
- **Fix**: switched the row's label to the same
  `$_header_user['display_name'] ?? ($_SESSION['user_name'] ?? $user_email ?? 'User')`
  fallback chain already proven correct at the dropdown header, which
  both fixes the blank-label bug at its source (no longer depends on the
  fragile per-page variable) and satisfies the "show name instead of
  email" request in the same change.

Verified pre-deploy against the docker dev stack with two real sessions:

- `admin@tracs.local` (super_admin): sidebar row now reads "Vickry"
  instead of the email, visible in both light and dark theme,
  screenshot-confirmed.
- `gagas@idcloudhost.co.id` (operator/agent): sidebar row reads "Gagas" —
  confirms the fallback isn't admin-specific and works the same for any
  role.

Verification on production:

- `php -l` clean.
- Drift-check before deploy: prod's `header.php` sha256 matched the
  pre-fix commit exactly — no untracked drift.
- Post-deploy sha256 matches the local working tree byte-for-byte.
- `sudo systemctl reload php8.3-fpm` (opcache cleared); `nginx` and
  `php8.3-fpm` both `active`.
- `https://tracs.vickry.id/login.php` → 200.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Mobile Profile-Menu Z-Index Leak + Reduced-Motion Gaps (2026-07-04 ~09:15 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `1f60bcb`.
1 file: `public/assets/tracs.css`.
Backup `/opt/tracs/backups/mobile-zindex-reduced-motion-fix-20260704-091547/`.

Follow-up request after the previous profile-dropdown clipping fix,
asking to double-check open/close smoothness on the accordion (Tasks &
Monitoring / User Management submenus) and the profile dropdown, and to
check for any overlapping CSS.

- **Accordion smoothness**: re-traced the `.nav-submenu-track`
  (`grid-template-rows: 0fr -> 1fr`) + `.nav-submenu` (opacity/visibility,
  delayed-hide) pattern and the `.user-menu` open/close cascade by hand.
  Both already animate symmetrically in both directions (the `:not([open])`
  states correctly fall back to the base rule's per-property
  `transition-delay` rather than snapping instantly, contrary to an
  earlier, incorrect note from initial triage) — confirmed via live
  open/close screenshots at rest on desktop; no change needed there.
- **Real bug found — mobile z-index leak**: at ≤640px the sidebar
  collapses into a horizontal top bar via `.sidebar-flyout { position:
  static }`, but `z-index` has no effect on statically positioned
  elements, so its `z-index: 150` silently stopped applying. Its
  `backdrop-filter` still creates a stacking context regardless of
  `position`, so the entire subtree — including `.user-menu`'s
  `z-index: 6000` profile dropdown — got pinned at the "auto" paint tier.
  `.notif-bell-btn` (the header's notification bell, `position: relative`,
  later in DOM order under `.main`) then won paint-order ties at that same
  tier, so its unread-count badge rendered on top of the open profile
  dropdown. Confirmed precisely via `elementFromPoint` plus toggling
  `position`/`backdrop-filter` independently in devtools before touching
  any code; functionally clicks still worked (the badge only stole taps
  landing exactly on its 16x16px area), but visually the badge floated
  over dropdown text. Fixed by changing `position: static` to
  `position: relative` in the mobile override — identical in-flow layout,
  but restores z-index applicability so the whole sidebar subtree
  properly outranks ordinary page content again.
- **Reduced-motion gap**: `@media (prefers-reduced-motion: reduce)`
  already forced `.sidebar-flyout`, `.nav-submenu`, and `.nav-chevron` to
  `transition-duration: 1ms`, but `.nav-submenu-track` (the accordion's
  actual height-slide) and `.user-menu` (the profile dropdown) were
  missing from that list — under that OS preference the container would
  still animate at full speed while its sibling/parent snapped instantly,
  a real half-instant/half-animated mismatch. Added both to the list.

Verified pre-deploy against the docker dev stack as `admin@tracs.local`:

- Accordion and profile-menu open/close screenshotted at rest in both
  states on desktop — clean, no residual gaps or clipping.
- Reproduced the mobile badge-over-dropdown overlap at 375px width with
  `elementFromPoint`, then confirmed it disappears post-fix (same point
  now resolves to the dropdown's own link) and re-screenshotted to
  confirm visually.
- Re-checked desktop after the mobile-only CSS change — no regression,
  profile menu and accordion both still open/close/mutually-close
  correctly.

Verification on production:

- No PHP touched — static asset, cache-busted via `filemtime()`, no
  `php-fpm` reload needed.
- Drift-check before deploy: prod's `tracs.css` sha256 matched the
  previous deploy's commit (`36d3950`) exactly — no untracked drift.
- Post-deploy sha256
  (`0c448e977042cf0916b7137dce44bbd7e7d6ad1186a6ba4b62d8998c58c1e8ec`)
  matches the local working tree byte-for-byte.
- `https://tracs.vickry.id/login.php` → 200; cache-bust version advanced
  to `?v=1783131354`.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Profile Dropdown Invisible/Unclickable Fix (2026-07-04 ~09:00 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `36d3950`.
1 file: `public/assets/tracs.css`.
Backup `/opt/tracs/backups/profile-menu-clip-fix-20260704-085550/`.

User reported clicking the sidebar profile/avatar row (bottom of the
sidebar, e.g. `admin@tracs.local`) appeared to do nothing — no dropdown
ever showed up.

- **Root cause**: `.sidebar-flyout` (the sidebar's hover-expand panel) has
  `overflow: hidden` for its rail-collapse width animation, and also a
  `backdrop-filter: blur(14px)` glass effect. The `.user-menu` profile
  dropdown uses `position: fixed` specifically so it can escape that
  `overflow: hidden` clip and float over the main content — this worked
  when the CSS comment was written, but `backdrop-filter` on an ancestor
  creates a new containing block for `position: fixed` descendants (same
  as `transform`/`filter`/`will-change`), which put the dropdown back
  inside the clip region. The `<details>` element still toggled `open`
  correctly and the menu existed in the DOM with `opacity:1`/
  `visibility:visible`, but it was invisible and non-interactive —
  `elementFromPoint` over the menu's own bounding rect resolved to the
  dashboard card underneath it, not the menu.
- **Fix**: `.sidebar-flyout:has(.user-menu-wrap[open]) { overflow: visible }`,
  relaxing the clip only while the menu is open. Matches the existing
  `.panel:has(> .panel-head .report-export-menu[open]), .panel:has(.row-action-menu[open])`
  pattern already used elsewhere in this stylesheet for the identical
  problem class.
- Also investigated a suspected open/close animation asymmetry (raised
  during triage) — on closer inspection of the transition cascade, the
  `:not([open])` closing state correctly falls back to the base
  `.user-menu` rule's per-property `transition-delay` (visibility waits
  for the opacity/transform fade to finish before hiding), so open and
  close were already symmetric. No change made there.

Verified pre-deploy against the docker dev stack with two real sessions —
`admin@tracs.local` (super_admin) and `gagas@idcloudhost.co.id` (operator/
agent), both temporarily password-reset for testing:

- Confirmed via `elementFromPoint` + toggling `overflow`/`backdrop-filter`
  independently in devtools that the clip was the actual cause before
  touching any code.
- Post-fix, clicked the real summary element (no JS override) on both
  accounts: menu renders, "Profile / Account" link navigates to
  `profile.php?section=profile` correctly for the super_admin session.
  Confirmed role-based sidebar visibility (Admin group hidden for the
  agent role) and the existing `tasks.php`/`monitoring.php` routing split
  from the 904bbbc fix both still behave correctly.

Verification on production:

- No PHP files touched — `tracs.css` is served as a static asset,
  cache-busted via `filemtime()` query string (`header.php:55`), so no
  `php -l` or `php8.3-fpm` reload was needed.
- Drift-check before deploy: prod's `tracs.css` sha256
  (`2544b313fe38f6480b7f01a4828532e1a318feb117f52e16cf12bfee649be88b`)
  matched the pre-fix commit (`904bbbc`) exactly — no untracked prod drift.
- sha256 of `tracs.css` matches byte-for-byte between the local working
  tree and `/opt/tracs` post-deploy
  (`1c249a94bc7085f47fe797ddf60bd604e2306120dca73f6ff7fa9753bafb8580`).
- `https://tracs.vickry.id/login.php` → 200; response now serves
  `assets/tracs.css?v=1783130162` (new mtime), confirming the cache-bust
  picked up the change.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Task Assignment 404 Fix + Recurring-Task Modal Rework (2026-07-04 ~00:12 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch
`fix/monitoring-task-assignment-routing-and-modal`, commit `904bbbc`.
7 files: `public/monitoring.php`, `public/index.php`,
`public/intern-management.php`, `modules/task-management/controller.php`,
`modules/task-management/model.php`, `public/assets/tracs.css`,
`config/migrations/2026_07_03_task_recurrence_interval.sql` (new, applied).
Backup `/opt/tracs/backups/task-assignment-recurrence-20260704-001128/`.

User reported: assigning a task to someone left that person unable to view
it — clicking through gave a 404. Also flagged the Add Task modal's
"Daily recurring task" checkbox sitting next to "Require review after
completion" as ambiguous, since they read as related options in a
checklist when they're actually unrelated (cadence vs. governance).

- **404 root cause**: `monitoring.php`/`tasks.php` are the same script,
  gated by which URL was requested — `tasks.monitor`-less users must land
  on `tasks.php` or they 404 (correct, existing behavior). But
  `index.php`'s dashboard and `intern-management.php` had every assignment
  link hardcoded to `monitoring.php`, so any assignee without
  `tasks.monitor` (or a `tasks.create`-only user clicking "Add") always
  404'd. Fixed by computing `$task_monitor_base_href` once
  (`tracs_user_can($conn,'tasks.monitor') ? 'monitoring.php' :
  'tasks.php'`) and routing every dashboard link through it, mirroring the
  pattern `header.php`'s nav link already used.
- **Modal rework**: replaced the checkbox with a One-time/Recurring
  segmented toggle in the Add and Edit Task modals, plus a real interval
  picker (quick-pick chips for 1/2/3/5 days or a free-entry day count).
  "Require review" moved out of that row into Task Details, since it's an
  unrelated axis. Edit Task previously couldn't change recurrence at all
  after creation — now it can. Added a Cadence line to the task detail
  panel so the setting is visible without reopening Edit.
- **Recurrence made functional**: `recurrence_type` was written on create
  but nothing ever read it — no cron, no reset job, anywhere in the
  codebase. Added `tracs_tasks.recurrence_interval_days` (migration) and
  `TaskManagementModel::refreshRecurringTasks()`, a lazy on-page-load
  rollover — same pattern this app already uses for overdue-status
  refresh, since there's no system cron. Once a recurring task's `due_at`
  passes, its assignments reset to a fresh `assigned` cycle (progress/
  completion cleared, linked checklist item and reminder reset/rescheduled)
  and `due_at` advances by the interval, self-healing across any missed
  cycles. The prior cycle's outcome is preserved as a `recurrence_reset`
  entry in the task's activity log rather than silently overwritten.
  Wired into both `monitoring.php` and `index.php`'s existing
  `refreshOverdueStatuses()` call site.

**Bug caught during verification, fixed before deploy**: the reset query
tried to set `overdue_seconds = NULL`, but that column is
`NOT NULL DEFAULT 0` — every rollover was silently failing (caught by the
per-task try/catch, rolled back, no visible error). Found via an isolated
dry-run scoped to a throwaway test task (never committed), fixed to
`overdue_seconds = 0`, re-verified the same way before trusting it against
real data.

Verified pre-deploy against the docker dev stack using two real sessions —
`admin@tracs.local` (super_admin) and `test@tracs.local` (agent), both
temporarily password-reset then restored to their original hashes
immediately after:

- Agent hitting `monitoring.php?assignment_id=X` directly → 404 (correct,
  unchanged access control). Agent hitting `tasks.php?assignment_id=X` →
  200, full detail renders. Agent's dashboard link now points at
  `tasks.php?assignment_id=X` instead of the old hardcoded `monitoring.php`
  — this was the actual fix.
- Superadmin created a 3-day-interval recurring task assigned to the
  agent; `recurrence_interval_days=3` persisted correctly; agent's detail
  panel showed "Cadence: Recurring · every 3 day(s)".
- Rollover logic verified via an isolated, always-rolled-back dry run
  scoped to the throwaway test task only — deliberately did **not**
  re-trigger the live shared rollover in dev, since it processes every
  qualifying recurring task system-wide and a real task
  (`Check Transfer Domain`, id 14, created concurrently by the actual user
  during this session) was also overdue-and-recurring at the time; verified
  its `updated_at` was untouched throughout. Test task and its linked
  checklist/reminder rows fully deleted afterward via the app's own
  `delete_task` action (cascade confirmed via DB).

Migration applied on production via `sudo mysql` (root, unix-socket auth)
rather than the `tracs_app` DB user — `tracs_app` correctly lacks
`ALTER ROUTINE`/`CREATE ROUTINE` privileges (least-privilege, as expected),
so the guarded-procedure migration pattern needs the more privileged path
on this host, same as it must have for the original
`2026_05_18_task_management.sql` migration.

Verification on production:

- `php -l` clean on all 5 deployed PHP files.
- Migration applied cleanly; `DESCRIBE tracs_tasks` confirms
  `recurrence_interval_days int(10) unsigned NOT NULL DEFAULT 1`.
- sha256 of all 7 files matches byte-for-byte between the local working
  tree and `/opt/tracs` post-deploy.
- Drift-check before deploy: sha256 of all files-to-be-touched on prod
  matched the branch's parent commit (`e0cb038`) exactly — no untracked
  prod drift.
- `sudo systemctl reload php8.3-fpm` (opcache cleared); `nginx` and
  `php8.3-fpm` both `active`; no new entries in php-fpm/nginx error logs.
- `https://tracs.vickry.id/login.php` → 200; `/monitoring.php`,
  `/index.php`, `/tasks.php` (unauthenticated) → 302 to login, as expected.
- Did **not** trigger `refreshRecurringTasks()` against real data as part
  of prod verification beyond the ordinary page loads above — it will fire
  naturally on the next real page view, which is the intended behavior
  going forward (including for the real `Check Transfer Domain` task,
  which is overdue and due to roll to its next cycle).

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Sidebar Visual Polish: Glass Blur, Group Spacing, Logo Alignment, Fixed Accordion Animation (2026-07-03 ~20:01 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/sidebar-hover-expand`, commits
`d445eea` + `f35ae9d`. 2 files: `public/includes/header.php`,
`public/assets/tracs.css`. Backup
`/opt/tracs/backups/sidebar-polish-20260703-200141/`.

Two rounds of design feedback, deployed together:

- **Glass blur**: `.sidebar-flyout` background now uses `backdrop-filter:
  blur(14px) saturate(150%)` with a translucent `--s1` tint, gated behind
  `@supports` so browsers without backdrop-filter keep the solid
  fallback.
- **Group labels → minimal lines**: removed the uppercase "OVERVIEW" /
  "OPERATIONS" / etc. text entirely; group separators are now a plain
  hairline. Group names moved to `role="group" aria-label="..."` on the
  wrapper so screen readers aren't worse off.
- **Dashboard icon aligned with the TRACS wordmark**: `.sidebar-nav`
  top padding tuned (6px → 10px) so the icon's centre lines up with
  `.page-title-logo` on the dashboard page (verified via
  `getBoundingClientRect`: 2px off).
- **Faster motion**: `--sb-dur` 180ms → 130ms, driving expand/collapse,
  label reveal, chevron rotation, and the submenu accordion from one
  token.
- **More group spacing**: divider height 9px → 18px (half the 36px row
  height, a proportional relationship).
- **Submenu accordion animation was actually broken, not just slow**: it
  animated `max-height: 0 → 320px` regardless of real content, so a short
  submenu (User Management, ~73px) only had visible motion in the last
  ~23% of the transition — at 130ms that's ~30ms, effectively a snap.
  Replaced with the `grid-template-rows: 0fr → 1fr` technique (wrapped
  each submenu's links in a new `.nav-submenu-track` container). Verified
  via `getBoundingClientRect`: Tasks & Monitoring (6 items) resolves to
  207.5px against 208px of real content; User Management (2 items) to
  72.5px against 73px — both animate smoothly regardless of item count
  now, no magic-number tuning needed if items are added later.

Verification: drift-checked clean on both files before deploy (matched the
`cbfc345` baseline exactly); `php -l` clean; post-deploy sha256 matches
local byte-for-byte on both; `login.php` returns **200**; deployed CSS
confirmed to contain `nav-submenu-track`, the `blur(14px)` rule, and the
`18px` group-label height; `nginx`/`php8.3-fpm` active. Pre-deploy verified
live against the docker dev stack as `admin@tracs.local` in both themes:
group spacing, glass blur (confirmed via computed `backdrop-filter`, not
just visual read), logo/icon alignment, and both accordions (measured
open-state track height against real content height for both the 6-item
and 2-item submenu) — no console errors.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Sidebar Bug Fixes: Ticker, Z-Index, Profile Menu, Theme-in-Profile (2026-07-03 ~19:24 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/sidebar-hover-expand`, commit
`cbfc345`. 3 files: `public/includes/header.php`, `public/assets/tracs.css`,
`public/assets/tracs.js`. Backup
`/opt/tracs/backups/sidebar-bugfixes-20260703-192437/`.

User-reported issues, all found to have real, verifiable root causes (not
just visual tweaks):

- **Ticker "LIVE" badge removed** (`.ticker-live`/`.ticker-dot` markup +
  CSS + `@keyframes tdot` deleted; `radarSweep` keyframe kept, it's shared
  with other components).
- **Sidebar/ticker stacking**: sidebar's z-index (was 40 collapsed) sat
  *below* the ticker bar's (100) — raised to 150 so the sidebar can never
  render underneath the ticker at their shared edge.
- **Profile menu "not working"**: root cause was `.sidebar-flyout`'s
  `overflow:hidden` (added for the collapsed-rail clip animation)
  silently clipping `.user-menu`, which was `position:absolute` and
  rendered outside the flyout's own box — it toggled open (attribute-wise)
  but was invisible. Switched to `position:fixed`, anchored past the
  sidebar's expanded edge (confirmed via `getComputedStyle`: opens with
  `opacity:1`/`visibility:visible` and lands fully on-screen).
- **Theme toggle moved into the profile dropdown**: Light/Dark/System are
  now inline options inside `.user-menu`, under a "Theme" section label,
  above Logout. Removed the separate theme-toggle icon + its popup
  (`.theme-menu-wrap`/`.theme-toggle`/`.theme-menu`) and the now-dead JS
  (`tracsOpenThemeMenu`/`tracsCloseThemeMenu`/`tracsToggleThemeMenu`/
  `tracsToggleTheme`); kept `.ic-sun`/`.ic-moon` (still used by TV Mode)
  and `.theme-option` (reused in the new location).
- **Mobile (≤640px) profile menu was unreachable**: the horizontal-bar
  sidebar's nav-icon row and avatar row were fighting over width —
  `overflow-x:auto`'s automatic `min-width:0` let `flex-shrink` crush the
  icon row to ~0px in one attempt, and a leftover `width:100%` inherited
  from the desktop column layout made the avatar row claim the *entire*
  bar in another, pushing the other element off-screen and clipped by
  `overflow:hidden` either way. Fixed by making the icon row the one that
  yields space and scrolls internally (`flex:1; min-width:0`) while the
  avatar row is protected (`flex-shrink:0; width:auto`) and never
  displaced. Also found and removed a stale `@media(max-width:720px)
  .user-menu` rule left over from the old `position:absolute` layout
  (`bottom:calc(100%+8px)` sent the panel off-screen once positioning
  became fixed) — replaced with a correctly-cascaded `≤640px` override
  (placed *after* the base `.user-menu` rule, since equal-specificity
  rules resolve by source order) that drops the panel down from the
  avatar to match the horizontal top-bar layout.

Verification: drift-checked clean on all 3 files before deploy; `php -l`
clean; post-deploy sha256 matches local byte-for-byte on all 3; `login.php`
returns **200**; deployed CSS/JS confirmed to contain the fixes and be free
of the removed dead code; `nginx`/`php8.3-fpm` active. Pre-deploy verified
live against the docker dev stack as `admin@tracs.local`: profile dropdown
opens and is fully visible/interactive (screenshot-confirmed: name, email,
Profile/Settings/Change Password, Theme section with working Light/Dark/
System selection, Logout) at desktop width; mobile bar (375px and 345px)
shows both the scrollable icon row *and* the avatar simultaneously with no
clipping, and the dropdown drops down fully on-screen from there too. No
console errors in either state.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Sidebar Submenu Label Shortened (2026-07-03 ~18:19 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/sidebar-hover-expand`, commit
`b878332`. 1 file: `public/includes/header.php`. Backup
`/opt/tracs/backups/sidebar-label-fix-20260703-181947/`.

Design feedback: "Domain Pricing Crosscheck" ran flush against the
Tasks & Monitoring submenu's right edge with no breathing room. Shortened
to "Domain Pricing" in the `$_task_monitoring_items` array — that label
string is only used in the sidebar nav, so page titles/breadcrumbs on
`domain-price-crosscheck.php` itself are unaffected.

Verification: no drift on prod before deploy; `php -l` clean; post-deploy
sha256 matches local byte-for-byte; `login.php` returns **200**,
unauthenticated `index.php` returns **302** (routing/session intact);
`nginx`/`php8.3-fpm` active. Pre-deploy verified against the docker dev
stack logged in as `admin@tracs.local` — submenu opened, "Domain Pricing"
now has clear trailing space, no console errors.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Sidebar Icon Scale Reduction (2026-07-03 ~15:41 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/sidebar-hover-expand`, commit
`6825688`. 1 file: `public/assets/tracs.css`. Backup
`/opt/tracs/backups/sidebar-icon-scale-20260703-154152/`.

Design feedback: sidebar icons read too large. Dropped the shared
row-geometry tokens (`--sb-icon` 20→16px, `--sb-row-h` 40→36px, `--sb-pad`
14→16px to keep icons centred on the 64px collapsed rail), and scaled the
label font (13→12.5px), active-bar, badge, pin, and submenu-icon sizes to
match proportionally. Also fixed a real inconsistency found while doing
this: the theme-toggle sun/moon icons were pinned to a stray `13px`
override (independent of the nav-item icons, which were 20px) — both now
read `var(--sb-icon)`, so every icon in the rail is the same size.
Verified in-browser post-change: every icon (nav items + theme toggle)
centres at the same x and renders at the same 16px, confirmed via
`getBoundingClientRect()`, not just visual inspection.

Verification: no drift on prod before deploy; post-deploy sha256 matches
local byte-for-byte; `tracs.css`/`login.php` both return **200**; deployed
CSS contains the new `--sb-icon: 16px` token; `nginx`/`php8.3-fpm` active.
Pre-deploy verified against the docker dev stack logged in as
`admin@tracs.local` (collapsed rail, expanded panel, submenu accordion,
light/dark themes) — no console errors.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Sidebar Spacing Refinement (2026-07-03 ~14:15 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/sidebar-hover-expand`, commit
`09d3944`. 1 file: `public/assets/tracs.css`. Backup
`/opt/tracs/backups/sidebar-refine-20260703-141513/`.

Follow-up to the hover-to-expand deploy after design feedback that the
expanded panel didn't match the design system and needed consistent
icon/label margins (referenced Nolito + Intercom sidebars). CSS-only:

- Introduced a shared row-geometry token set on `.sidebar`
  (`--sb-gutter:8px`, `--sb-pad:14px`, `--sb-icon:20px`, `--sb-gap:12px`,
  `--sb-row-h:40px`, `--sb-radius`) so nav items, submenu rows, group
  labels, the user/avatar row and the theme toggle all share one rhythm.
  Verified in-browser: every icon centres at x≈33 (dead-centre of the 64px
  collapsed rail) and every expanded label starts at x=55 — identical
  across nav items, theme toggle, and the 30px avatar row.
- Items are now inset rounded pills (8px side gutters, `--r3` radius)
  instead of full-bleed squares; hover/active read as clean pills matching
  the references. Active keeps the tinted blue fill + a short rounded
  left accent bar.
- Group labels: short centred hairline divider when collapsed, fading to
  uppercase text when expanded (removed the heavier full-width divider
  lines). First visible group carries no leading hairline.
- Notification badge repositioned onto the icon corner (was floating high
  above the row on the old 36px icon slot).
- Expanded width 212→244px for reference-matching roominess.

Verification: no drift on prod before deploy (sha256 matched the prior
`549ef8c` baseline); post-deploy sha256 matches local byte-for-byte;
`tracs.css` and `login.php` both return **200**; deployed CSS contains the
new `--sb-gutter` token; `nginx`/`php8.3-fpm` active. Pre-deploy the change
was verified against the docker dev stack (collapsed + expanded, submenu
accordion, favorites, light/dark themes, geometry measured) with no console
errors; throwaway login user deleted after.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Hover-to-Expand Sidebar Navigation (2026-07-03 ~13:51 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/sidebar-hover-expand`, commit
`549ef8c`. 3 files: `public/includes/header.php`, `public/assets/tracs.css`,
`public/assets/tracs.js`. Backup
`/opt/tracs/backups/sidebar-hover-expand-20260703-135157/`.

Sidebar redesign: stays icon-only (64px) and expands to ~212px on
hover/focus as an absolutely-positioned overlay flyout (the flex layout box
never resizes, so page content never shifts). Items are grouped into
Overview / Operations / Communication / Admin with uppercase labels shown
only while expanded; the active page gets a 3px blue left-edge bar plus
tinted background; a favorites section (localStorage-backed pin toggle, up
to 4 pages) sits above the groups. Tasks & Monitoring / User Management
submenus became inline accordions instead of floating flyouts. The old
JS-computed floating-tooltip mechanism (`bindSidebarTooltips`) was retired
in favor of pure-CSS inline label reveals. Mobile (`≤640px`) keeps the
original icon-only horizontal bar unchanged; hover-expand is gated behind
`(hover: hover) and (pointer: fine)` so touch devices are unaffected.

What was applied:

- Code (file-copy deploy; prior versions backed up as above): the 3 files
  listed. No migrations, no config/env changes.
- Drift-check before deploy: sha256 of all 3 files on prod matched the
  `design/ui-consistency-audit` baseline (commit `4381a89`, the last thing
  deployed) exactly — no untracked prod drift.
- `sudo systemctl reload php8.3-fpm` to clear opcache.

Verification on production:

- `php -l` clean on the deployed `header.php`.
- sha256 of all 3 files matches byte-for-byte between the local working tree
  and `/opt/tracs` post-deploy.
- `https://tracs.vickry.id/login.php` returns **200** and renders; deployed
  `tracs.css`/`tracs.js` both return **200** and contain the new sidebar
  markers (`sb-w-open`, `bindSidebarFavorites`).
- `nginx` and `php8.3-fpm` both `active`; no new entries in php-fpm/nginx
  error logs referencing the deployed files.
- Pre-deploy: verified locally against the docker dev stack (login, hover
  expand, active-state accent bar, pin/favorites, inline submenu accordion,
  light/dark theme tokens, mobile breakpoint) — no console/PHP errors. A
  throwaway test user used for that login was deleted afterward; no existing
  data touched.

Branch remains pushed for review/PR. Production tracks the working tree via
file-copy deploy (not a `main` pull).

## Deployed — Continuous Background ICMP Monitoring + History (2026-07-03 ~11:22–13:02 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, commit
`4381a89`. 7 files: `config/migrations/2026_07_03_infrastructure_monitoring_results.sql`
(new, applied), `core/infrastructure_monitor.php` (new),
`bin/tracs-infrastructure-monitor.php` (new),
`public/api/infrastructure-server-{list,history}.php` (new),
`core/infrastructure_servers.php`, `public/assets/infrastructure-pulse.js`
(updated). Backup `/opt/tracs/backups/infra-continuous-monitor-20260703-112228/`.
`AI_MEMORY.md` and `README.md` were **not** deployed this round — both had
drifted on prod independently of this feature (see below).

User asked whether the tab-driven ICMP check was "okay or needed rework...
like Grafana monitoring." Answer was: it needed rework for that. Built the
real thing:

- New `infrastructure_monitoring_results` table stores every check as a
  historical sample (30-day retention, pruned every worker run via
  `tracs_infra_prune_history()`), replacing the client-fabricated sine-wave
  history that real nodes were silently using for their trend graphs before
  this deploy.
- `core/infrastructure_monitor.php` + `bin/tracs-infrastructure-monitor.php`:
  a cron/systemd-timer worker that checks every real ICMP target whose
  configured interval has elapsed, independent of any browser tab.
  MySQL `GET_LOCK('tracs_infrastructure_monitor', 0)`-guarded so an
  overlapping run is a no-op, mirroring `core/notifications.php`'s existing
  scheduler pattern exactly.
- **Installed as a systemd service + timer** (`tracs-infrastructure-monitor.service`/`.timer`
  in `/etc/systemd/system/`), matching how `tracs-notification-worker` is
  already scheduled on this host — there is no crontab on this server for
  either worker, both run via systemd timers (`OnUnitActiveSec=1min`).
  `systemctl daemon-reload`, `enable`, and `start` run; confirmed active via
  `systemctl list-timers`.
- `public/api/infrastructure-server-list.php` / `-history.php`: new
  session/CSRF/`dashboard.view`-gated read endpoints. The frontend polls
  both every 15s to show the latest persisted state and trend data — the
  same way a Grafana panel polls a datasource — replacing the old
  tab-driven recurring-check timer entirely. The one-off immediate check
  right after adding/editing a real server is unchanged (still useful ahead
  of the next worker tick).
- Documented in `AI_MEMORY.md`'s Infrastructure Pulse section and
  `README.md`'s cron/deploy-checklist (not yet deployed — see below).

**Drift note:** `AI_MEMORY.md` and `README.md` differed from prod
independently of this feature. `AI_MEMORY.md`'s diff was a clean superset
(this branch has an entire "Multi-Machine Git Workflow" section prod
lacks) — low risk, but skipped anyway since it's pure documentation with
zero runtime effect. `README.md`'s diff was messy/reordered (~163 added,
~174 removed, ~39 modified lines) rather than a clean superset, meaning
prod's copy may have content this branch doesn't — did not attempt a
surgical merge given no functional stakes. Both are a known gap for a
future session to reconcile deliberately, not urgent.

Verified on local Docker first: `bin/tracs-infrastructure-monitor.php` run
manually confirmed it only checks servers whose interval has elapsed
(second run immediately after reported `checked=0`), wrote a real history
row, and the frontend's `node.history.latency` reflected real DB samples
(`[27, 36, 29]`) instead of the fabricated wave. Precisely measured the
frontend's poll cadence via `performance.now()` timestamps in the browser
(not the noisy cumulative network log, which had made it look like a
runaway timer from repeated page reloads earlier in the session) — confirmed
exactly ~15000ms between polls, no duplication.

**Verified in production after deploy**: `systemctl list-timers` showed
the new timer active and already fired; `logs/infrastructure-monitor.log`
showed `checked SGP01 (103.250.11.175) -> healthy 16.2ms` from a run with
zero browser tabs open; confirmed the matching row landed in
`infrastructure_monitoring_results`. `php -l` passed on all 5 PHP files,
`php8.3-fpm` reloaded cleanly, post-deploy sha256 matched local on all 7
files, HTTP checks came back as expected (401 unauthenticated on both new
endpoints, 302 login redirect on the page), and the PHP-FPM error log was
checked post-deploy — clean.

## Deployed — Icon-Only Edit/Remove Buttons in Server Registry (2026-07-03 ~10:38 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, commit
`65cd0fd`. 2 files: `public/assets/infrastructure-pulse.css`,
`public/assets/infrastructure-pulse.js`. Backup
`/opt/tracs/backups/infra-icon-buttons-20260703-103829/`.

User feedback: the Edit/Remove buttons in the Server Registry list used
text labels, inconsistent with the icon-only `.btn-icon` pattern used
elsewhere in TRACS (e.g. the modal close button, `.um-row-actions`).
Switched both to icon-only (`.btn btn-ghost btn-icon`, 28×28px, matching the
existing standard), stacked vertically per the user's suggestion — edit on
top, remove below — via `.infra-server-registry__remove { flex-direction:
column }`. Added `title`/`aria-label` on both buttons since the visible text
label is gone. No JS logic changes — click handling is keyed on the
`data-infra-edit-server`/`data-infra-remove-server` attributes, unaffected
by the markup change.

Verified on local Docker: both buttons render at the correct 28px size,
Edit still opens the pre-filled edit form correctly, no console errors.
Drift-checked clean before deploying, post-deploy sha256 matched local on
both files, HTTP checks 200.

## Deployed — Persist Demo-Datacenter Removal + Server Edit Feature (2026-07-03 ~10:28–10:30 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, commit
`3d1b2f0`. 9 files: `config/migrations/2026_07_03_infrastructure_hidden_seeds.sql`
(new, applied), `core/infrastructure_servers.php`,
`public/api/infrastructure-server-delete.php`,
`public/assets/infrastructure-pulse-{data.js,css,js}`, `public/index.php`,
`public/infrastructure-pulse.php`, `public/tv-mode.php` (all updated).
Backup `/opt/tracs/backups/infra-edit-persist-seeds-20260703-102826/`.

User report: deleted all 9 built-in demo datacenters (DCI, IDB, CY1, BCD,
BTI, DR3, SG3, EGH, NDS) via Manage Servers and "it still doesn't update
anything" after a refresh. Confirmed via the prod DB that these were never
persisted — they're hardcoded client-side mock data
(`public/assets/infrastructure-pulse-data.js`'s `DATACENTERS` array),
re-seeded fresh on every page load by design, so removal only ever worked
for the current tab.

- New `infrastructure_hidden_seeds` table (code + hidden_at + hidden_by)
  makes removing one of the 9 seeds durable, matching how removal already
  works for real targets. `infrastructure-server-delete.php` now tries a
  real soft-delete first, then falls back to hiding a seed code (whitelisted
  server-side against the known 9 codes, so ad-hoc "Demo Data" entries added
  through the form can't pollute this table) — one endpoint handles both
  cases. `infrastructure-pulse-data.js`'s `createSnapshot()` filters hidden
  seed codes out of the built-in list before building the store, and dedupes
  by code so a seed that's been edited into a real target doesn't render
  twice.
- New **Edit** action on every Server Registry row (feature request): opens
  the existing Add Server form pre-filled with that server's current values,
  locks the Code field (it's the upsert key server-side — editable would let
  a typo silently create a duplicate entry instead of updating the original),
  and reuses the same create/upsert endpoint on save so no new API surface
  was needed for the "update an existing real server" case. Editing a mock
  seed's method into a real target (icmp/tcp/http with a host) upgrades it to
  a persisted, live-checked server; editing a real target back to Demo Data
  soft-deletes its DB row so it stops being live-checked.

Verified on local Docker before deploying: removed DCI, confirmed the row
landed in `infrastructure_hidden_seeds`, reloaded, confirmed DCI stayed gone
(9→8 nodes); edited SGP01's hostname from `103.250.11.175` to `1.1.1.1`,
confirmed the DB row updated and a fresh live check ran against the new host
automatically; edited CY1 (a seed) into a real ICMP target
(`1.0.0.1`), confirmed it upgraded correctly with no duplicate node in the
store; reverted all test changes back to the clean baseline before
deploying. Drift-checked clean, `php -l` passed on all 5 PHP files,
`php8.3-fpm` reloaded, post-deploy sha256 matched local on all 9 files, HTTP
checks came back as expected, and the PHP-FPM error log was checked
post-deploy — clean.

## Deployed — Production Drift Correction: Full Catch-Up on 22 Un-Deployed Files (2026-07-03 ~09:41–09:54 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, HEAD at
deploy time `d1d1b87` (no new commits — every file deployed here already
existed in git; the gap was purely "committed but never shipped"). Backups
`/opt/tracs/backups/index-css-drift-correction-20260703-094105/` and
`/opt/tracs/backups/full-catchup-20260703-094739/`.

**Read this before the next deploy on this branch — there is a two-part
story here and I got the first part wrong before correcting it.**

**Part 1 — a mistake, corrected.** The previous entry in this log
("Connect Dashboard/TV Widgets...") found `public/index.php` and
`public/assets/infrastructure-pulse.css` didn't match this branch's
last-deployed baseline, and — following the "never revert unrelated
changes" rule — preserved prod's differing content instead of overwriting
it, assuming prod had legitimate independent changes. **That assumption was
wrong.** Checking the actual commits (`2acfa3a`, `4e6686c`, `7973a5e`)
showed prod's "different" content was strictly *older*: a stray
`value="1000000"` Currency Converter prefill that `2acfa3a` had already
removed, a `style="transform:scale(0.8)"` badge hack that `4e6686c` had
already replaced with a real `.badge-sm` class, a raw 🐈‍⬛/🐾 emoji dobby
easter egg that `4e6686c` had already replaced with Lucide icons,
`font-weight: 850`/`650` that `7973a5e` had already normalized to `800`/`600`
for a documented Windows-rendering bug, and missing `data-unsaved-ignore`
attributes that were the actual fix for the false unsaved-changes-banner
incidents logged earlier in this file. The "surgical merge" from the
previous entry kept all of these bugs live on production. Re-deployed the
full, correct branch HEAD for both files here to fix that.

**Part 2 — the same root cause, much bigger than 2 files.** Checking further
found the underlying problem: commits `2acfa3a`, `4e6686c`, and `7973a5e`
were committed to git but **never actually deployed** — no entry for any of
them exists earlier in this log. Scanning every file those three commits
touched against production found 20 more stale files, plus a migration
file that had never been copied to `config/migrations/` on the server (the
migration itself — `2026_07_01_case_board_order.sql` — had already been
applied to the DB directly at some point, confirmed via a read-only
`SHOW COLUMNS` check, so only the file was missing, not the schema):

- `modules/alert-ticker/SmartTickerEngine.php`, `modules/alert-ticker/controller.php`,
  `public/api/ticker-delete.php`, `public/api/ticker-list.php` — and
  `public/api/ticker-feed.php`, which didn't exist on prod **at all**. This
  is the endpoint that powers `7216bdd`'s "shared public feed with live
  sync" ticker feature — production had never had that feature's backend,
  only whatever `header.php` shipped before it.
- `public/api/case-attachment-lib.php`, `case-delete.php`, `case-get.php`,
  `case-resolve.php`, `case-update.php`, `export-cases.php` (696/60/88/62/
  160/110 changed lines respectively — case management on prod was running
  a meaningfully older codebase).
- `public/user-management.php` — **2722 changed lines**, `public/server-health.php`
  — 310 changed lines. Before touching either (super_admin-only, security
  scope on `server-health.php` per `AI_MEMORY.md`), verified the DB schema
  those files expect already existed on prod (`tracs_users.archived_email`,
  `tracs_users.removed_at`, `tracs_user_notes`, `tracs_cases.board_order` —
  all present), so this was purely a stale-code gap, not a schema mismatch
  that would have caused fatal errors.
- `public/login.php`, `public/includes/header.php`,
  `public/assets/{tracs.css,tracs.js,domain-price-crosscheck.css,shifting-assignment.css,tracs-date-range-picker.css,calendar-dist/assets/calendar-CQ8MUmL1.css}`.

Given the scope (a security-sensitive page, thousands of changed lines,
and a previously-undeployed live feature), stopped and got explicit
confirmation before proceeding rather than pushing through
unilaterally — see the conversation for the drift-scope question and
"Full catch-up now" answer.

Deployed all 22 files (21 code files + the migration file), verified DB
schema compatibility first, backed up every existing file before
overwriting, `php -l` passed on all 15 PHP files (both locally and on the
server after copy), `php8.3-fpm` reloaded, post-deploy sha256 matched local
on all 22 files, HTTP checks came back as expected (`login.php` 200,
`user-management.php`/`server-health.php`/`index.php` 302 login redirects,
`ticker-feed.php`/`case-get.php` 401 without auth), and both
`php8.3-fpm` and nginx error logs were checked post-deploy — clean, only
unrelated pre-existing bot-scanning noise already blocked by nginx rules.

**Open question for a future session:** why did `2acfa3a`/`4e6686c`/
`7973a5e` never get deployed despite being committed over 24h ago? Worth
checking whether other commits on this branch have the same gap before
assuming the next `git push` + deploy cycle is fully caught up — the
drift-check step (fetch prod hashes, compare to the last commit *this
session* believes was deployed) only catches drift relative to what this
log claims was deployed, not silent gaps like this one where a commit was
simply never actioned.

## Deployed — Connect Dashboard/TV Widgets to Real Server Data + Reorder Slider (2026-07-03 ~09:31 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, commit
`0b4240a`. 5 files: `core/infrastructure_servers.php`,
`public/assets/infrastructure-pulse.css`, `public/index.php`,
`public/infrastructure-pulse.php`, `public/tv-mode.php`. Backup
`/opt/tracs/backups/infra-widget-connect-20260703-093117/`.

**Drift found and handled before deploying — read this if touching
`public/index.php` or `public/assets/infrastructure-pulse.css` again.**
The pre-deploy drift check (now routine after the false-positive incident
logged 2026-07-02) found prod's live copies of these two files did **not**
match this branch's last-deployed baseline (commit `02d7679`):
- `public/index.php` on prod already had unrelated fixes/tweaks not present
  in this branch's history for that file: `data-unsaved-ignore` attributes
  on the currency/screenshot fields, "All →" arrow link text, a `badge-sm`
  class + keyboard-accessible (`role="button" tabindex="0" onkeydown`) shift
  report cards, and a `🐈‍⬛`/`🐾` emoji dobby easter egg instead of Lucide
  icons.
- `public/assets/infrastructure-pulse.css` on prod had several
  `font-weight` values bumped (800→850, 600→650) beyond what's in this
  branch.
  Per AI_MEMORY's "never revert unrelated changes" rule and the prior
  documented incident, these were **not** overwritten. Instead: fetched
  prod's actual live files via `scp` (not `ssh ... cat`, which contaminates
  output with the login banner), applied only this deploy's specific edits
  on top of that live content (verified with a full `diff` afterward showing
  *only* the intended lines changed), then deployed the merged result. Every
  prod-only difference listed above is still present on production.
  `core/infrastructure_servers.php`, `public/infrastructure-pulse.php`, and
  `public/tv-mode.php` had no drift and were deployed directly from this
  branch.

The actual feature: the dashboard mini-widget and TV Mode widget were
missing persisted real servers (only `infrastructure-pulse.php` itself
loaded them), so they showed a different node count/status than the full
page — e.g. 9 nodes instead of 10 with `CloudVPS-SGP01` missing entirely.
Both now expose `window.TRACS_INFRA_REAL_SERVERS` via the shared
`tracs_infra_server_list_active_for_json()` helper (new in
`core/infrastructure_servers.php`, deduplicating the row-mapping logic that
previously lived only in `infrastructure-pulse.php`). Also reordered the
dashboard's operations-summary slider to show Shift Summary first and
Infrastructure Pulse second (was reversed), updating the two
position-dependent CSS icon-entrance-animation rules that assumed the old
order.

Verified on local Docker before deploying: dashboard widget shows 10 nodes
including `SGP01` with `mode: real`, matches the full page's node count;
Shift Summary confirmed as the default-visible slide (opacity 1) immediately
after page load via computed styles, Infrastructure Pulse slide 2; TV Mode
also confirmed 10 nodes with `SGP01` present. Zero console errors across all
three pages. `php -l` passed on all 4 PHP files, `php8.3-fpm` reloaded,
post-deploy sha256 matched the deployed content on all 5 files (compared
against the merged live copies for the 2 drifted files, and against this
branch directly for the other 3), and HTTP checks came back as expected
(302 login redirects, CSS 200).

## Deployed — Infrastructure Pulse Real Server Registry Persistence (2026-07-03 ~09:12 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, commit
`02d7679`. 8 files: `config/migrations/2026_07_03_infrastructure_servers.sql`
(new, applied), `core/infrastructure_servers.php` (new),
`public/api/infrastructure-server-create.php` (new),
`public/api/infrastructure-server-delete.php` (new),
`public/api/infrastructure-ping.php`, `public/assets/infrastructure-pulse-data.js`,
`public/assets/infrastructure-pulse.js`, `public/infrastructure-pulse.php`
(updated). Backup `/opt/tracs/backups/infra-persistence-20260703-091202/`.

Reported by the user testing the previous deploy: adding a real server (e.g.
`CloudVPS-SGP01`, `103.250.11.175`) worked and got live-checked correctly,
but disappeared on page refresh — the Server Registry had never been
persisted anywhere, only held in an in-memory JS object rebuilt from scratch
on every page load. Mock/demo entries stay intentionally session-only by
design; this only applies to real targets.

- New `infrastructure_servers` table (soft-delete via `deleted_at`, matching
  the non-destructive-removal pattern used elsewhere in TRACS). Migration
  applied via the app's own `config/database.php` connection over SSH
  (no `MYSQL_LOGIN_PATH`/`~/.my.cnf` configured on this host yet), verified
  with a read-only `DESCRIBE` afterward.
- `infrastructure-server-create.php` / `-delete.php`: session + CSRF +
  `dashboard.view`-gated, upsert-by-code (re-adding an existing code
  reactivates/overwrites it, including a previously soft-deleted row),
  server-side re-validates everything the client already validates (host
  format, HTTP method requires `http(s)://`, TCP port range, required
  fields) rather than trusting client-side checks alone.
  Verified 401 unauthenticated, 422 on bad code chars / missing fields /
  injection host / bad URL scheme / bad port.
- `infrastructure-pulse.php` now loads active real servers server-side and
  exposes them as `window.TRACS_INFRA_REAL_SERVERS` (safe JSON encoding,
  `JSON_HEX_*` flags matching the existing ticker-items pattern in
  `header.php`); `infrastructure-pulse.js` hydrates them into the store on
  load, and the periodic ICMP check (from the previous deploy) now also
  writes its result back to the row via `infrastructure-ping.php`'s new
  optional `code` parameter, so a refreshed page shows the last known real
  result immediately, not a reset "Awaiting Backend" pending state.
- Also fixed a real bug found while building this: `tracs_infra_server_upsert`'s
  hand-counted `mysqli` `bind_param()` type strings were misaligned by one
  position, silently int-casting `target_host` (`"103.250.11.175"` stored as
  `103`). Caught by inspecting the DB row after the first local test, not by
  code review. Replaced with an ordered `[type, value]` pair binder
  (`tracs_infra_bind_execute()`) that derives the type string from the pairs
  themselves, removing that whole class of bug for this file.
- Also fixed: real (non-mock) nodes were showing a fabricated `100.000%` 30D
  uptime after a single successful check — a single ping proves current
  reachability, not 30-day history, which TRACS doesn't aggregate yet. Added
  an `uptimeTracked` flag (true only for mock nodes) and a dedicated
  `uptimeText()` helper so real nodes show `--` for uptime specifically,
  independent of the existing pending-state `--` handling.

Verified end-to-end on local Docker before deploying: added
`CloudVPS-SGP01` / `103.250.11.175`, confirmed the DB row (target_host
correct after the bind_param fix, live check result cached), reloaded the
page and confirmed the server survived with its cached status intact,
removed it and confirmed soft-delete (row preserved, `deleted_at` set, does
not reappear on refresh), then re-added it as the final state. Drift-checked
clean before deploying, `php -l` passed on all 5 PHP files, `php8.3-fpm`
reloaded, post-deploy sha256 matched local on all 8 files, and HTTP checks
came back as expected (302 login redirect, all three new/updated API
endpoints correctly 401 without authentication).

**Note for the user:** the `CloudVPS-SGP01` entry added to production before
this deploy was in-memory only and did not carry over (nothing to migrate —
it was never stored anywhere). Add it once more via Manage Servers on
production; from this deploy onward it will persist across refreshes.

## Deployed — Infrastructure Pulse Real ICMP Checks + Pending-Node Stat Fix (2026-07-03 ~08:53 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, commit
`9895ebf`. 4 files: `public/assets/infrastructure-pulse-data.js`,
`public/assets/infrastructure-pulse.js` (updated), `core/infrastructure_ping.php`,
`public/api/infrastructure-ping.php` (new). Backup
`/opt/tracs/backups/infra-ping-feature-20260703-085323/` (pre-existing files
only; the two new files had nothing to back up). The Dockerfile change
(`iputils-ping` for local dev parity) is dev-only and was not deployed here.

Two changes, both requested during manual feature testing on this page:

- **Pending-node stat display fix.** A real "Network Ping" target added but
  never checked showed `0ms` latency and `100.000%` uptime — misleadingly
  implying a measured, healthy result. `infrastructure-pulse.js` now renders
  `--` for latency/loss/uptime while a node's status is `pending`. Also fixed
  a related correctness bug found in the same code path:
  `infrastructure-pulse-data.js`'s `computeSummary()`/`regionSummary()` were
  averaging every node's latency/uptime including unmeasured pending nodes
  (latency=0, uptime=100 defaults), silently skewing the page's "Average
  Latency" and "30D Uptime" stats. Both now exclude pending nodes from those
  averages.
- **Real ICMP checks.** Real "Network Ping" targets no longer stay stuck at
  "Awaiting Backend" forever. `core/infrastructure_ping.php` validates the
  host (IP literal or RFC-1123 hostname only — rejects anything else,
  verified against `; rm -rf /`, `$(whoami)`, backticks, path traversal,
  empty/oversized input, all `422`) and runs the system `ping` binary via
  `proc_open` with an **argv array** (no shell string interpolation is
  possible regardless of host content), bounded packet count/timeout, and a
  hard wall-clock kill switch — mirroring the conservative, fixed-input
  posture already established in `core/server_monitoring.php` for
  `server-health.php`. `public/api/infrastructure-ping.php` gates this behind
  the standard session + CSRF + `dashboard.view` permission bootstrap and
  rate-limits to one check per host per 10 seconds (429 + `Retry-After`).
  `infrastructure-pulse.js` fires an immediate check when a real Network Ping
  server is added, then re-checks on its configured interval (min 30s) while
  the tab stays open. TCP/HTTP methods remain "Awaiting Backend" — only
  Network Ping got a live backend this pass.

Verified against a real internal server (IDCloudHost SGP01,
`103.250.11.175`) before deploying: added via the UI, got back a real
`healthy / 49ms / 0% loss` result with a real timestamp, correctly reflected
across the report panel, metrics list, and server registry. Also verified
the unreachable-host path (real `critical / 100% loss` result, no crash) and
confirmed `www-data` already has `cap_net_raw` on `/usr/bin/ping` on this
VPS (pre-existing, unaffected by this deploy) — no server-side capability
changes were needed. Drift-checked clean before copying, `php -l` passed on
both new PHP files, `php8.3-fpm` reloaded, post-deploy sha256 matched local
on all 4 files, and HTTP checks came back as expected
(`infrastructure-pulse.php` 302 login redirect, both JS assets 200, and the
new ping endpoint correctly 401s without authentication).

## Deployed — Infrastructure Pulse Accessibility Audit Fixes (2026-07-03 ~08:08 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `design/ui-consistency-audit`, commit
`e4696a8`. 2 files: `public/infrastructure-pulse.php`,
`public/assets/infrastructure-pulse.js`. Backup
`/opt/tracs/backups/infra-pulse-a11y-focus-20260703-080849/`.

Full-page audit of Infrastructure Pulse per its documented
mock/session-only scope (AI_MEMORY.md — no real backend, DB tables, or
CSRF-relevant mutations exist for this page yet, so most backend-audit
categories don't apply). Three real bugs fixed:

- The report panel (`renderReport`) fully replaced its `innerHTML` on every
  4s auto-refresh tick, silently dropping keyboard focus to `<body>` if a
  user had focused a "Needs Attention"/"Stable Nodes" row. Now captures the
  focused node's code before the swap and restores focus to it afterward.
- The Server Registry modal's tabs (`role="tab"`) had no matching
  `role="tabpanel"` / `aria-controls` / `aria-labelledby` wiring on their
  panes, so screen readers couldn't associate tab and content. Added the
  missing ids/attributes; no visual change (existing `display:none` /
  `.is-active` CSS already hides inactive panes).
- The inline "Remove server" confirmation had no focus management: opening
  it left focus wherever it was, and Cancel didn't return focus to the
  trigger. Now focuses Cancel on open, returns focus to the original Remove
  button on Cancel, and focuses the registry container on confirmed removal.

Drift-checked clean (prod sha256 matched the pre-fix commit exactly) before
copying. `php -l` passed, `php8.3-fpm` reloaded, post-deploy sha256 matched
local, and both URLs returned expected HTTP status
(`infrastructure-pulse.php` 302 to login when unauthenticated,
`assets/infrastructure-pulse.js` 200).

## Deployed — Opt Real-Time Toggles Out + Scope Unsaved-Changes Guard to Pages That Need It (2026-07-02 ~19:39 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Same branch as the prior entry,
`fix/checklist-unsaved-changes-false-positive`, commit `31dba8f`. 5 files:
`public/assets/unsaved-changes-guard.js`, `public/checklist.php`,
`public/index.php`, `public/mom.php`, `public/reminders.php`. Backup
`/opt/tracs/backups/unsaved-guard-scope-20260702-193932/`.

Follow-up correction to the ~19:19 entry: calling `markSaved()` after the
toggle settles was necessary but **not sufficient** on its own. The guard's
dirty-tracking runs off document-level `change`/`input`/`focusin` listeners
that are entirely global — independent of any scope registration — so a
checkbox is flagged dirty the instant the user clicks it, regardless of
whether its page is in `autoRegisterEditablePage`'s list. Pruning that list
alone (which was also requested — scope the feature to only the pages that
need it) would not by itself have stopped the false-positive banner.

- Added `data-unsaved-ignore` directly to every real-time-saved toggle
  checkbox — `.task-chk` (`index.php`, `checklist.php`), `.rem-check`
  (`index.php`, `reminders.php`), `.agenda-check` (`mom.php`). This is the
  guard's own documented opt-out: these controls are now never tracked at
  all, regardless of page or any future code path that might forget to call
  `markSaved()`.
- Audited every page previously in `autoRegisterEditablePage`'s
  `editablePages` set (checked each for real `<form method="post">` coverage
  — already independently handled by `autoRegisterForms()` — vs. genuine
  standalone/non-form editable content vs. real-time-only toggles) and
  pruned the set from 20 entries down to the 7 that actually have
  unprotected standalone editable surface: `mom`, `domain_price_crosscheck`,
  `domains` (`domain-transfer.php`'s `#dtModal`, a plain `<div>` not a
  `<form>`), `finance` (`#btModal`, same pattern), `feedback`
  (`cancellation_feedback.php`'s inline quick-add fields, no form wrapper),
  `infrastructure-pulse` (its add-server form has no `method` attribute, so
  `autoRegisterForms()` treats it as GET and skips it), `shifting-assignment`
  (already self-manages via its own `markSaved()` calls). Full per-page
  rationale is in the code comment in `unsaved-changes-guard.js`. Removed:
  `dashboard`, `checklist`, `reminders`, `cases`, `case`, `shift-reports`,
  `shift_report`, `activity`, `user-management`, `profile`, `monitoring`,
  `intern-management`, `settings`, and the dead `cancellation-feedback` alias
  (the real `data-tracs-page` value is `feedback`) — none had standalone
  editable content.

Verification:

- Local: full round trip against the real DB — created a throwaway reminder,
  confirmed `data-unsaved-ignore` renders on the checkbox, confirmed
  `checklist.php`/`reminders.php`/`index.php`/`mom.php` all carry the
  attribute, deleted the test reminder. `php -l` and `node --check` clean on
  all files.
- Prod: **read-only** — confirmed `data-unsaved-ignore` renders on the live
  checklist checkbox, confirmed the served `unsaved-changes-guard.js` carries
  the pruned 7-page allowlist, 0 PHP errors, sha256 local↔prod match on all
  5 files, `php -l` clean, `php8.3-fpm` reloaded (PHP files changed this
  time, unlike the prior JS/CSS-only entry). Did **not** toggle any of the
  operator's real checklist items on prod to further verify — even a
  toggle-then-revert would create real audit-log and notification side
  effects on live data, which is out of scope for a verification step.

## Deployed — Fix False "Unsaved Changes" Banner on Checklist/Reminder Toggles (2026-07-02 ~19:19 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `fix/checklist-unsaved-changes-false-positive`
(new branch), commit `c12752c`. 3 files: `public/assets/tracs.js`,
`public/assets/tracs.css`, `public/.htaccess`. No PHP changed — static-asset
deploy only, no `php8.3-fpm` reload needed. Backup
`/opt/tracs/backups/unsaved-guard-fix-20260702-191942/`.

Root cause: `unsaved-changes-guard.js`'s `autoRegisterEditablePage()` registers
the whole `.main-inner` content area as a tracked "form scope" on the
dashboard (and other pages) with no dedicated `<form>` around the checklist/
reminder checkboxes. `toggleTask()`/`toggleReminder()` (tracs.js) save via
AJAX on change and update the UI, but never told the guard the change was
already persisted — so the guard's document-level `change` listener flagged
the checkbox dirty and the "You have unsaved changes" bar stuck around
indefinitely, even though the item saved instantly.

- `toggleTask()`/`toggleReminder()` now call the guard's own public
  `window.TRACSUnsavedChanges.markSaved(item)` after the toggle settles
  (success or a reverted failure) — same pattern already used elsewhere in
  this file for modal saves.
- CSS: `.tracs-unsaved-bar__actions .btn[hidden] { display:none !important }`
  — the guard hides "Save now" via the `hidden` attribute when the dirty
  scope has no save handler (exactly the checklist's case), but `.btn`'s own
  `display` rule outranked the bare `[hidden]` UA default, so the button
  stayed visible and silently did nothing when clicked.
- `public/.htaccess`: the backup-file deny rule matched the substring "save"
  inside `unsaved-changes-guard.js` itself — the same false-positive class of
  bug already fixed on production's nginx config for this exact file, just
  the local Apache/dev-container equivalent this time. Tightened to a
  word-boundary match. (Prod runs nginx, not Apache, so this file is inert
  there — deployed anyway to keep the tracked file in sync with local.)

Verification:

- Local: full round trip against the real DB — confirmed the guard JS/CSS
  changes are syntactically valid (`node --check`), confirmed
  `unsaved-changes-guard.js` now serves 200 locally (was 403 due to the same
  `.htaccess` bug pattern, which is what let this investigation happen),
  confirmed real backup filenames are still blocked (no regression), and
  round-tripped the actual `task-toggle.php` AJAX endpoint via curl
  (toggle → revert, both `success:true`) to confirm the backend is untouched
  and correct.
- Prod: **read-only checks only** — both changed files serve correctly
  (200), `nginx -t` clean (config untouched, expected), sha256 local↔prod
  match on all 3 files, `node --check` on the server confirms `tracs.js`
  syntax is valid.

## Deployed — Kebab Trim, Title-Link, Single-Row Filter Bar, Admin Rename (2026-07-02 ~18:24 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `fix/monitoring-actions-filter-row-admin-rename`
(new branch, not `feat/task-monitoring-mom-permission-revision`), commit
`f67697d`. 2 files: `public/monitoring.php`,
`public/assets/tracs.css`. Backup
`/opt/tracs/backups/actions-filterrow-rename-20260702-182416/`.

- **Task title is now the "view details" link** (`?assignment_id=X#tmDetail`,
  same click-to-open convention as `.um-user-name` in user-management). The
  row kebab dropped its own redundant "View details" entry and now holds only
  **Mark done** and **Delete**, per the operator's request.
- **"Actions" header text removed** (kept an aria-label'd empty `th`).
- **Filter bar collapsed to one row.** Reference: `user-management.php`'s
  `.um-user-filter` (search + fieldset + actions as siblings in one flex/grid
  row). User/Status/Date, the "More filters" trigger, and Apply are now
  siblings in a single `.tm-filter-row` flex container. The advanced-filter
  drawer (Role/Division/Priority/Category/Intern-only) is now a floating
  popover (`position:absolute`) anchored under its trigger instead of an
  in-flow block, so opening it can never push the row to a second line.
  `.tm-filter` needed `overflow: visible` added (base `.panel` clips by
  default) so the popover isn't cut off at the panel edge.
- **Admin display name**: `tracs_users.name` for `admin@tracs.local` changed
  from "TRACS Super Admin" to "Vickry" on **both** local Docker DB and
  production, via a prepared statement targeted by exact email (not a
  positional selector — see the incident above).

Verification:

- Local: full round trip (create → check title-link/kebab/header/filter
  markup → delete), all correct, DB left clean.
- Prod: **read-only checks only** against the operator's real live task
  ("Check domain", id 9) — 0 PHP errors, title renders as a link to
  `#tmDetail`, kebab popover contains only Mark done + Delete, "Actions"
  header gone, filter form's elements confirmed as siblings in submission
  order (User → Status → Date → More-filters `<details>` → Apply). No
  create/delete actions were run against prod for this verification.
- sha256 local↔prod match on both files; `php -l` clean; `php8.3-fpm`
  reloaded.

## Deployed — Actions Header Removal + View-Details Reveal + Toast Feedback (2026-07-02 ~14:50 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `932e79d`. 2 files: `public/monitoring.php`, `public/assets/tracs.css`.
Backup `/opt/tracs/backups/actions-view-toast-20260702-145022/`.

- Removed the visible "Actions" column header (kept as aria-label'd empty th).
- "View details" now scrolls the detail panel into view (`id="tmDetail"` +
  scroll-margin) and flashes a `:target` highlight — the panel rendered fine
  server-side but sat beside/below the table so the nav looked like a no-op.
- Toast feedback: `tm_json_response` stashes a success flash; the reloaded page
  fires `window.tracsToast(...)` (replacing the static `.tm-flash` panel), so
  kebab actions (hidden forms, no modal) now get a toast too. Consumed once.
- Verified: sha256 local↔prod match, `php -l` clean, `php8.3-fpm` reloaded;
  header gone, toast fires on create, detail anchor present.

### Incident during verification — prod task deleted (data loss)

While verifying with a scripted create->delete round trip on prod, the delete
step selected the task id with `head -1` on the rendered list. That list sorts
by due date, so it picked the operator's real task **"Check transfer domain"**
(task#7, created 14:00:57 WIB, assignee id 17) instead of the test task, and
deleted it at 14:50:47 (audit `tracs_user_activity_logs#48`). The test task
`ProdVerify` was later removed too; **prod now has 0 tasks**.

Not recoverable automatically: DB backups only exist from 2026-06-27 (predate
the task), `log_bin=OFF`, and the audit row for the delete stored only
title+assignee (no due/priority/category/description/status). Restoration needs
the operator to supply the original fields (or recreate via the form).

**Guardrail:** never run create/delete round-trips against production for
verification, and never target destructive actions by positional selectors
(`head -1`) on prod. Verify data-mutating flows on local only.

## Deployed — Table Kebab Menu + Add Task Modal Declutter (2026-07-02 ~12:42 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `413c36f`. 2 files: `public/monitoring.php`, `public/assets/tracs.css`.

- **Table row actions → kebab.** The three inline icon buttons kept reading as
  cramped, so per the operator's request they now collapse into the app-standard
  kebab (`<details class="row-action-menu">` inside `.row-action-group`, trigger
  inline, popover drops as a menu — same pattern as reminders/mom). Popover has
  View details / Mark done / Delete.
- **Add Task modal "Assign to" declutter.** Dropped the fake stepper numbering
  ("1 ·" / "2 ·") to plain section labels; replaced the heavy blue-tinted box
  with a subtle top-border divider; collapsed the enhanced multiselects
  (People/Roles/Divisions) from the global `.is-multiple` 112px chip-area
  min-height to a 38px single-line control — they only render "N selected" text,
  so the tall boxes were dead space. Height override scoped to
  `.tm-assign-section` so other pages' multiselects are unchanged.

Verification:

- Drift check clean; post-deploy sha256 local↔prod match; `php -l` clean;
  `php8.3-fpm` reloaded. Backup
  `/opt/tracs/backups/kebab-assignmodal-20260702-124217/`.
- Prod create → delete round trip (self-cleaning): the row kebab renders with a
  real `data-task-id`, the popover Delete works, prod left with 0 tasks.
  Modal renders plain labels (no "1 ·/2 ·"), 0 PHP errors.

## Deployed — Task-Action 422 Fix + Dashboard Reminder Icon + nginx JS Deny Fix (2026-07-02 ~12:13 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `6777b82`.

Three reported prod issues:

- **Delete/edit/reassign/unassign failing with HTTP 422 "Task not specified".**
  Root cause: `model.php::tasks()` selected `t.*, ta.id AS assignment_id` but
  never `ta.task_id`, and `tracs_tasks`' PK is `id` (there is no `task_id`
  column), so `$task['task_id']` was undefined → the view rendered
  `data-task-id="0"` for every task action in both the table row and the Task
  Timing Insight detail panel. Fix: add `ta.task_id` to the SELECT. Verified
  end-to-end on local (create → delete → row + cascades gone) and on prod
  (`data-task-id` now renders the real id).
- **Dashboard Task Monitoring widget — remove the leading icon** from each
  Reminder List item; dropped the now-empty `30px` grid column in
  `.tm-reminder-list-item` (mirrors the existing icon-less `type-holiday`
  variant). Removed the now-unused `$icon` local.
- **`/assets/unsaved-changes-guard.js` returned 403 (MIME text/html).** Not a
  missing file — the nginx backup-file deny rule
  (`sites-available/tracs` line 41) matched the substring "save" inside
  "un**save**d". Tightened the regex so backup tokens
  (`backup|bak|old|orig|save|copy`) only match as whole words
  (`(?<![a-zA-Z])…(?![a-zA-Z])`) instead of anywhere in the name. This is a
  **server-only nginx change** (not in the repo); backups at
  `sites-available/tracs.bak-jsdeny-*` and `…bak-jsdeny2-*`. `nginx -t` clean,
  `nginx` reloaded.

What was applied:

- 3 app files (file-copy; backup
  `/opt/tracs/backups/taskaction-reminicon-20260702-121313/`):
  `modules/task-management/model.php`, `public/assets/tracs.css`,
  `public/index.php`. Ownership `vickry:www-data`; `php8.3-fpm` reloaded.
  No DB migration.

Verification:

- Drift check: prod copies of all 3 files were byte-identical to the prior
  deploy baseline before copy. Post-deploy sha256 local↔prod match; `php -l`
  clean; `php8.3-fpm` reloaded.
- Prod HTTP (authenticated): `/monitoring.php` renders non-zero
  `data-task-id`; `/index.php` reminder items carry no `tm-reminder-list-icon`
  (14 items still render), 0 PHP errors.
- nginx: `unsaved-changes-guard.js` + `tracs.js` → 200
  `application/javascript`; real backup names (`x.bak.js`, `styles-backup.css`,
  `app.old.js`) → 403; legit words (`copyright-notice.js`, `oldstyles.css`) →
  404 (pass the deny rule).

Ops note (local dev only): `admin@tracs.local` had 2FA enabled on the local
Docker DB, which blocked login with the newly-set password. Disabled 2FA on
the **local** account (`two_factor_enabled=0`, secret cleared,
`reset_required=0`) and cleared its stale login-attempt lockout so the password
logs in for dev. **Production `admin@tracs.local` was not touched** (it has no
2FA and already accepts the new password).

## Deployed — Monitoring Page Redesign (2026-07-02 ~11:53 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `7d18d19`.

Full monitoring.php UI/UX redesign from the audit (P0–P2):

- **Merged Status + "Time Left / Overdue" columns** into one "Status & SLA"
  cell (8→7 columns, rewidthed) — resolves the header/row/button collisions.
  Overdue duration now renders (`tm_time_delta` passes `abs()` so
  `tm_duration`'s `<=0` guard stops swallowing negative deltas).
- **Ticker right-edge fade mask** so scrolling items fade instead of clipping
  mid-glyph (global header CSS — affects all pages).
- **KPI clustering** into Workload / Risk / Performance; Risk cluster is
  red-tinted with larger numbers when active.
- **Persistent overdue pill** in the topbar, separate from the ticker.
- **Filter progressive disclosure**: User/Status/Date always visible, the rest
  behind a "More filters" drawer (auto-opens + count when active); active
  filters render as removable chips + Clear all.
- **Detail panel**: Timing detail and Activity log are collapsible sections.
- **Labeling**: single-sample timing stats suppressed (n<2), every rate
  annotated with its denominator. Adds `completed_count` +
  `timing_sample_size` to `model.php`'s `summary()`.

What was applied:

- 3 files (file-copy; backup
  `/opt/tracs/backups/monitoring-redesign-20260702-115245/`):
  `public/monitoring.php`, `public/assets/tracs.css`,
  `modules/task-management/model.php`. Ownership `vickry:www-data`;
  `php8.3-fpm` reloaded to clear opcache. No DB migration.

Verification:

- Drift check: prod copies of all 3 files were byte-identical to `8a4b418`
  before deploy — no production-only changes overwritten.
- Post-deploy: all 3 files sha256 local↔prod match; `php -l` clean;
  `php8.3-fpm` + `nginx` active after reload.
- HTTP: `/login.php` 200; `/monitoring.php` + `/index.php` 302→login;
  `/assets/tracs.css` 200 and serving the new cluster/pill/filter rules.
- Local end-to-end (authenticated, real docker DB): 0 PHP errors; KPI
  clusters, overdue pill, filter chips, merged Status & SLA cell (e.g.
  "Overdue 5h 7m"), and single-sample suppression all render correctly.

Ops note: `admin@tracs.local` password was rotated on both the production and
local DBs (bcrypt, cost 12) at the operator's request — value not recorded
here. Prod admin has no 2FA (password alone logs in); local admin still has
2FA enabled (`two_factor_enabled=1`), so the local login also needs its 2FA
code.

## Deployed — Monitoring Action-Button Cramping Fix (2026-07-02 ~09:34 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `8380f21`. Single file: `public/assets/tracs.css`.

Follow-up polish after the table root-fix: the three row action icons (View /
Mark done / Delete) were cramped against the panel edge on a default ~1280px
macOS viewport. Cause: `.tm-table`'s Actions cell inherited
`padding-right: calc(sp-3 + 36px)` (~48px) from the generic
`.tracs-table td:last-child` rule, which reserves room for the absolute
`.row-action-menu` dropdown trigger that `.tm-table` doesn't use. Reclaimed that
padding (8px each side), laid the buttons out as a right-aligned nowrap flex
row, and widened the Actions column 11%→14% (Task 22%→19%) so the ~108px of
buttons fit the ~112px column even at 1280px.

- Backup `/opt/tracs/backups/tm-actions-btn-20260702-093444/`. Drift check: prod
  css was identical to the prior deploy (`2108766`) beforehand. Post-deploy
  sha256 local↔prod match; served CSS confirmed carrying the 14% width + 8px
  padding; `/assets/tracs.css` 200. No FPM reload (static asset).

## Deployed — Unassign Feature + Monitoring Table Overflow Root-Fix (2026-07-02 ~09:18 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `2108766`.

Two things: a new **Unassign** feature, and the *actual* fix for the messy
monitoring table the earlier 07:59 pass only partially addressed.

- **Unassign a single user from a task** (`monitoring.php`,
  `task-management/{controller,model}.php`): new action in the Task Timing
  Insight detail panel next to Reassign (its inverse). `removeAssignee()`
  deletes one user's assignment and cascades to *that assignment's own* linked
  checklist item, reminder, and logs, while leaving the task and every other
  assignee untouched. Guards the last assignee (refuses to orphan a task — use
  Delete instead). Owner-or-monitor gated via existing `requireTaskManage()`.
  New `unassign_user` POST action, confirm dialog + hidden AJAX form.
- **Root-fix for the table overflow/overlap.** The 07:59 deploy shipped the new
  8-column `tracs.css` width rules but **not** the matching `monitoring.php`, so
  production kept rendering the old **9-column** layout (with the long-removed
  "Completion Time" column). The column-count mismatch is what made headers
  ("Time Left / Overdue") and timestamps spill into neighbouring columns and
  force a horizontal scrollbar. This finally ships `monitoring.php` (verified
  prod was byte-identical to `e980ef3`, i.e. two deploys stale). Also let
  `.tm-table` th/td text wrap instead of nowrap-overflowing, and rebalanced the
  8 column widths (sum = 100%); Actions column stays nowrap.

What was applied:

- 4 files (file-copy; backup
  `/opt/tracs/backups/unassign-table-fix-20260702-091801/`):
  `public/monitoring.php`, `public/assets/tracs.css`,
  `modules/task-management/controller.php`, `modules/task-management/model.php`.
  Ownership `vickry:www-data`; `php8.3-fpm` reloaded to clear opcache. No DB
  migration (removeAssignee uses existing tables only).

Verification:

- **Local end-to-end DB test (real docker DB, real model): 21/21 passed** —
  create 2-assignee task → unassign one → the removed user's assignment +
  linked checklist item + reminder + assignment logs are gone; the task,
  the other assignee, that assignee's checklist/reminder, and task-level logs
  all survive; last-assignee removal blocked; task/assignment-id mismatch
  blocked; full cleanup on deleteTask. No test residue left in the DB.
- Drift check: prod copies of all 4 files were byte-identical to `e980ef3`
  before deploy — no production-only changes overwritten.
- Post-deploy: all 4 files sha256 local↔prod match; `php -l` clean; prod
  `monitoring.php` now renders the 8-column header (Completion Time gone);
  `unassign_user`/`removeAssignee` present on prod; `php8.3-fpm`+`nginx` active
  with no new FPM errors after reload.
- HTTP: `/login.php` 200; `/monitoring.php` + `/index.php` 302→login;
  `/assets/tracs.css` 200 and serving the new 8-column width rules.

Deployed via key auth (`~/.ssh/tracs_deploy_ed25519`); no password used.

## Deployed — Login Toast Dedup + Monitoring UI Fixes (2026-07-02 ~07:59 WIB)

Status: **Deployed to production** (`103.82.93.75`, `/opt/tracs`,
`https://tracs.vickry.id`). Branch `feat/task-monitoring-mom-permission-revision`,
commit `c78fd73` (full branch state, closing the gap since the last deploy at
`e980ef3`).

Production was one deploy behind: three already-committed-and-pushed monitoring
fixes (`ae93750`, `c28ddbf`, `8dea5be`) had never shipped, on top of the same two
shared assets as this session's login fix — so all four went out together as one
file-copy pass:

- **Login page:** removed a duplicate error toast. The login form's inline
  `.err-box` was already rendering the server-side error; JS additionally copied
  that same text into a floating toast, which the client-side friendly-error
  mapper reworded (e.g. "too many attempts" became "server is taking longer than
  usual"), so the same failure showed twice with different wording. Dropped the
  redundant toast entirely. Also added a `max-height`/`opacity` transition to the
  login toast dock (`:has(.toast)`) so the login card eases into place instead of
  snapping when a toast dock does still appear (`tracs.js`, `tracs.css`).
- **Monitoring (already on branch, not yet deployed):** sticky-header/panel
  overlap fix on short viewports, native `<select>` bleed-through fix, capped
  Assign-To dropdown height, decluttered row actions to View/Mark done/Delete,
  removed Completion Time column, fixed table horizontal overflow, and hardened
  click-outside-to-close sitewide with a capture-phase backstop.

What was applied:

- 2 files (file-copy; backup
  `/opt/tracs/backups/login-toast-monitoring-fixes-20260702-075926/`):
  `public/assets/tracs.js`, `public/assets/tracs.css`. Ownership preserved
  `vickry:www-data`. No PHP changed, so no `php8.3-fpm` reload needed.

Verification on production:

- Both files sha256 local↔prod match exactly.
- Drift check: pre-deploy production copies were byte-identical to commit
  `e980ef3` (the last actually-deployed state) — no production-only changes were
  overwritten.
- HTTP: `/login.php` 200; `/assets/tracs.js` and `/assets/tracs.css` 200;
  `/index.php` and `/monitoring.php` 302→login (unauthenticated, expected).
- Deployed content confirmed live: duplicate-toast block absent from served
  `tracs.js`; new `.toast-dock--login:has(.toast)` rule present in served
  `tracs.css`.
- `php8.3-fpm` and `nginx` error logs show nothing new post-deploy (only
  pre-existing, unrelated bot-scan noise for `.env`/`.git` probing).

Deployed via key auth (`~/.ssh/tracs_deploy_ed25519`); no password used.

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
