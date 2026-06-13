# TRACS Operational Dashboard - Handoff

## Current State

TRACS is an integrated operational dashboard with active work in the current dirty worktree. Do not revert unrelated PHP, CSS, JavaScript, SQL, documentation, logs, uploads, or backup folders.

Current implementation highlights:

- Restored five-item dashboard stat strip.
- Shared case ticket detail from dashboard and `cases.php`.
- Case `in_progress` status, resolve action, progress timeline, and image attachments.
- Dashboard Task Monitoring tabs: Checklist and Reminder, Assignments, Activity.
- Task assignments sync into checklist and optional reminders.
- Shift reports support Active, On Hold, Resolved, and image attachments.
- In-app/browser notification center with scheduler worker.
- Infrastructure Pulse full page, dashboard widget, and TV widget using shared mock data.
- Domain Price Crosscheck uses canonical route `domain-price-crosscheck.php` and a compact tabbed operational layout.
- Settings are in the avatar/profile menu.

## Do Not Revert

- Do not restore grouped dashboard stat sections; the current dashboard uses the compact stat strip.
- Do not recreate a separate dashboard Reminder tab.
- Do not move Domain Pricing Crosscheck or Domain Transfer Log out of `Tasks & Monitoring` without an explicit navigation decision.
- Do not describe `domain_price_crosscheck.php` as current; it is only a 308 compatibility redirect.
- Do not remove resolved shift items from context views simply because they no longer need handover.
- Do not replace the shared case ticket modal with separate dashboard/case-page implementations.
- Do not treat Infrastructure Pulse mock incidents as real monitoring data.
- Do not remove or modify build/authorship signatures.

## UI Direction

- Clean, compact, operational-first, low visual noise.
- Consistent widget spacing, internal padding, row height, and scroll areas.
- Blue is for active/highlighted state.
- Completed work remains readable.
- Holiday/special-day icons and labels must match the actual context.
- Preserve light/dark mode and the shared Inter/system font stack.

## Behavior Notes

### Dashboard

- Cases, Task Monitoring, Shift Handover, Currency Converter, and Infrastructure Pulse are active.
- Dashboard case rows call the same `openCaseTicket()` flow as case-list rows.
- Infrastructure Pulse and Shift Summary share the left-side summary slider.

### Task Monitoring

- `Checklist and Reminder` contains Operational Checklist plus Reminder List.
- `Assignments` shows assignment rows and assignment alerts.
- `Activity` shows recent activity and the active Reminder List.
- The full assignment workflow remains at `monitoring.php`; `tasks.php` is a compatibility include.

### Case Ticket

- Resolve is the primary right-side footer action.
- Close is only in the header.
- Edit/Delete are inside the icon-only More trigger.
- Timeline animation is clipped to current progress; resolved state stops animation.

### Shift Handover

- Active = needs handover.
- On Hold = monitoring context.
- Resolved = informational context.
- Dashboard reminder starts 30 minutes before shift change.
- Stored notification creation is in the final 15 minutes and depends on the worker or dashboard execution.

### Notifications

- Stored types cover case creation, reminder creation/due timing, task assignment, meeting timing, and shift handover.
- Browser permission is optional; in-app notification behavior remains available.
- Production cron should run `bin/tracs-notification-worker.php` every minute.

### Infrastructure Pulse

- **Partially Implemented:** UI and shared mock store are functional.
- Real ICMP/TCP/HTTP workers, persistent target registry, incidents, and alert correlation are planned.
- TV Mode includes an Infrastructure Pulse widget, not a separate Infrastructure-only TV route.

## Bug-Prone Areas

- Dashboard widget spacing and stat-strip breakpoints.
- Task Monitoring viewport height, nested scrolling, and checklist/reminder column balance.
- Assigned-task awareness appearing in both checklist and assignment views without duplicate confusion.
- Case timeline animation, reduced-motion behavior, and modal action visibility by permission.
- Shift report reminder timing around shift boundaries.
- TV Mode overlap, cropped content, small text, overflow, and excessive scrolling across resolutions.
- Infrastructure mock labels accidentally being presented as live telemetry.
- Domain Price matrix sticky columns, dropdowns, modals, tab state, and audit visibility.
- Holiday naming/icon relevance.
- Role and object-level permission checks.
- Case and shift attachment authorization, file validation, and writable directories.
- Docker attachment testing because GD is not installed in the current image.

## Recommended Verification

1. Test dashboard, cases, Task Monitoring, shift reports, notifications, and Domain Price as Admin and Agent.
2. Test Intern access to own tasks and restricted modules.
3. Test case ticket open/resolve/edit permissions from both dashboard and case page.
4. Run the notification worker manually, then verify cron, dedupe, browser permission denial, and click-through routes.
5. Test TV Mode in macOS browser, fullscreen, 1920x1080, a smaller viewport, and dark mode.
6. Confirm Infrastructure Pulse clearly says mock/session-only.
7. Test Domain Price tabs, matrix save/recalculate, audit trail, extension/source management, and legacy redirect.
8. Test Docker fresh boot; record the expected attachment failure until GD is added.
9. Test a clean installer and chronological migrations against a database backup.

## Documentation Map

- `README.md`: project entry point, quick start, modules, and documentation index.
- `ARCHITECTURE.md`: technical structure and module relationships.
- `AI_MEMORY.md`: durable product and implementation rules.
- `HANDOFF.md`: continuation context and regression hotspots.
- `TASKS.md`: roadmap, known issues, and status labels.
- `README_MOM.md`: current MoM behavior.
- `SECURITY_AUDIT_CHECKLIST.md`: application security review.
- `VPS_SECURITY_CONFIGURATION.md`: production server baseline.
- `docs/`: focused Domain Price, Infrastructure Pulse, 2FA, and signature notes.

Update the closest existing file first. Create a new document only when it reduces duplication or prevents an existing file from becoming unwieldy.
