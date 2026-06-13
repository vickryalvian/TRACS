# TRACS - Tasks & Roadmap

Status labels in this file are deliberate: `Completed`, `In Progress`, `Partially Implemented`, `Planned`, and `Legacy`.

## Completed

- [x] Restored dashboard stat-strip layout.
- [x] Dashboard Task Monitoring tabs: Checklist and Reminder, Assignments, Activity.
- [x] Assignment-to-checklist sync and optional assignment-to-reminder sync.
- [x] Shared case ticket detail from dashboard and case page.
- [x] Case `in_progress` status, Resolve action, timeline, and image attachments.
- [x] Shift Active/On Hold/Resolved status model and shift image attachments.
- [x] Notification tables, dedupe/logging, in-app center, browser permission flow, and service worker click handling.
- [x] Canonical Domain Price route `domain-price-crosscheck.php` with legacy 308 redirect.
- [x] Domain Price overview, matrix, intelligence, ccTLD, adjustment, action bucket, notes, audit, source, and extension surfaces.
- [x] Settings moved to avatar/profile menu.
- [x] Mandatory 2FA, login throttling, CAPTCHA escalation, session hardening, and permission-aware routes.

## In Progress

- [ ] Validate current documentation against every post-May 27 migration after deployment testing.
- [ ] Verify notification scheduler under a real one-minute cron and inspect dedupe/log volume.
- [ ] Confirm clean install from `config/install.sql`.
- [ ] Confirm chronological migrations against a copy of an older database.
- [ ] Stabilize Task Monitoring nested scroll behavior and compact row sizing at all breakpoints.
- [ ] Validate case/shift attachment upload, delete, and permission flows in production.
- [ ] Consolidate MoM current/legacy API behavior after caller inventory.

## Partially Implemented

- [ ] Infrastructure Pulse: UI, mock store, dashboard widget, and TV widget exist; backend probes, persistence, incidents, and alerts do not.
- [ ] TV Mode responsive states exist; full device/resolution verification remains pending.
- [ ] Browser notifications work while the application/browser permission path is available; they are not a remote web-push subscription system.
- [ ] Docker local stack works for core PHP/MySQL flows; attachment processing is incomplete because GD is absent from the current image.

## UI/UX Follow-Up

- [ ] Check dashboard stat strip at 1400, 1200, 960, and mobile breakpoints.
- [ ] Check Task Monitoring viewport height, empty space, and checklist/reminder column balance.
- [ ] Keep checklist rows as compact as case rows.
- [ ] Verify completed checklist/task rows remain readable in light and dark modes.
- [ ] Confirm case timeline animation stops at the current dot and respects reduced motion.
- [ ] Confirm Resolve remains right-aligned and Close appears only in the ticket header.
- [ ] Review whether the Edit menu item should become a direct icon-only action.
- [ ] Test Domain Price sticky matrix columns, dropdowns, source/extension modals, tabs, and Audit Trail.
- [ ] Verify holiday/special-day labels and icon treatments remain contextually correct.

## TV Mode Verification

- [ ] macOS browser window.
- [ ] Fullscreen browser.
- [ ] 1920x1080 TV display.
- [ ] Smaller display/short viewport.
- [ ] 4K display.
- [ ] Dark mode.
- [ ] No overlap, cropping, unreadable text, horizontal overflow, or excessive scrolling.
- [ ] Infrastructure widget clearly indicates prototype/mock state where appropriate.

## Security And Deployment

- [ ] Run the full application checklist in `SECURITY_AUDIT_CHECKLIST.md`.
- [ ] Configure Ubuntu 24.04 VPS, Nginx/Apache, PHP-FPM, MySQL/MariaDB, UFW, Fail2ban, unattended upgrades, TLS, backups, and log monitoring.
- [ ] Set all production secrets outside source control.
- [ ] Verify proxy trust configuration and Secure cookies.
- [ ] Ensure repository root, Markdown, SQL, logs, backups, and environment files are not web-accessible.
- [ ] Review permission maps whenever a page/API is added.
- [ ] Add rate limiting or abuse controls to sensitive non-login endpoints where needed.
- [ ] Test backup and restore for database plus uploads.

## Database And Runtime

- [ ] Add GD to the Docker image and document any required JPEG/WebP build packages.
- [ ] Add a repeatable migration runner or explicit migration ledger.
- [ ] Update `config/README.md` whenever a schema fragment or migration is added.
- [ ] Decide the long-term fate of `balance_transfers`, `domain_transfers`, `activity_feed`, and `ops_status`.
- [ ] Add indexes only after measuring real query volume.
- [ ] Add an admin diagnostic for missing tables/columns and worker health.

## Planned

- [ ] Real Infrastructure Pulse workers for ICMP/TCP/HTTP checks.
- [ ] Infrastructure server/result/incident tables and alert correlation.
- [ ] Dedicated Infrastructure-only TV route, if operationally required.
- [ ] Domain Price CSV/Excel import and registrar/WHMCS API integration.
- [ ] Global search across operational modules.
- [ ] Calendar/timeline view for reminders, cases, MoM, and task due dates.
- [ ] PDF/print evidence reports.
- [ ] KPI/SLA/achievement tracking and management-review exports.
- [ ] Optional email/WhatsApp delivery.
- [ ] Self-hosted frontend assets for offline/privacy-sensitive deployments.

## Legacy

- The separate dashboard Reminder tab is legacy and must not be recreated without an explicit decision.
- `public/domain_price_crosscheck.php` is a redirect-only compatibility route.
- Historical package instructions under `MOM README/` are not current deployment guidance.
- Docker is local-development tooling, not the documented production runtime.
- Old grouped dashboard stat-card experiments are not the current dashboard design.
