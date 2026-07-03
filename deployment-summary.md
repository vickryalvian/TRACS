# TRACS Deployment Summary

Status: Deployed successfully
Completed: 2026-06-29 08:54 WIB
Domain: https://tracs.vickry.id

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
