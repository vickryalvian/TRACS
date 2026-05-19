# TRACS — Tasks & Roadmap

## Completed

- [x] Core dashboard with cases, reminders, checklist, activity feed, ticker, and operational stats.
- [x] Session login/logout and auth guard.
- [x] CSRF helpers and mutating API verification.
- [x] Cases CRUD, search, filters, and export.
- [x] Reminders CRUD, due-state filters, completion, and dashboard/ticker integration.
- [x] Checklist CRUD and progress tracking.
- [x] Shift reports and shift activity snapshots.
- [x] MoM module with meeting schedule, agenda, notes, decisions, actions, reminders, case links, screenshots, history, and export.
- [x] Finance/balance transfer logging and export.
- [x] Domain tracking and export.
- [x] Cancellation feedback dashboard with multi-select values and retention intelligence.
- [x] User management with roles, permissions, divisions, intern profiles, and activity logs.
- [x] Task assignment/monitoring with checklist/reminder sync.
- [x] Profile, password, and theme preferences.
- [x] TV mode wall display.
- [x] Docker app + MySQL setup.
- [x] Documentation/config audit backup created under `backup/docs-config/20260518-1434/`.

## In Progress

- [ ] Stabilize and verify task monitoring flows after recent edits.
- [ ] Validate TV mode and user-management changes currently present in the dirty worktree.
- [ ] Confirm current `config/install.sql` fully matches every active module and migration.
- [ ] Verify Docker fresh boot with a clean database volume.

## High Priority

- [ ] Change default admin password after every fresh install.
- [ ] Add a visible system/schema readiness checklist for admin users.
- [ ] Add or document a repeatable migration command/runbook.
- [ ] Clean up stale MoM messages that reference `config/mom_database_schema.sql`.
- [ ] Decide whether root `.env` should be officially supported by including `config/env.php` or documenting environment-only config.

## UI/UX Polish

- [ ] Review dashboard density on small screens.
- [ ] Ensure table actions and filter/export menus are consistent across cases, domains, finance, feedback, MoM, and shift reports.
- [ ] Polish TV mode empty/error/loading states.
- [ ] Confirm all button labels fit on mobile widths.
- [ ] Audit inline styles in modals/pages and migrate reusable patterns into `tracs.css` where worthwhile.

## Bugs

- [ ] Test MoM screenshot upload path and permissions on Docker and production hosting.
- [ ] Verify MoM action-to-case/status update behavior against current API.
- [ ] Confirm root `.env` expectations do not mislead local developers.
- [ ] Check whether all export endpoints enforce auth and expected permissions.
- [ ] Confirm API paths use `/api/...` consistently when app is deployed below a subdirectory.

## Security

- [ ] Add login rate limiting.
- [ ] Set production session flags: `cookie_secure`, `cookie_httponly`, `cookie_samesite`.
- [ ] Review upload validation and storage for MoM screenshots.
- [ ] Review permission coverage for finance, domains, feedback, exports, and TV summary APIs.
- [ ] Disable display errors in production pages that currently enable them during development.
- [ ] Add backup/restore instructions for database and uploads.

## Database / Config

- [ ] Test clean install from `config/install.sql`.
- [ ] Test chronological migrations on a copy of an older database.
- [ ] Decide long-term fate of legacy tables: `balance_transfers`, `domain_transfers`, `activity_feed`, `ops_status`.
- [ ] Update `config/README.md` migration list to include all current migrations.
- [ ] Add indexes for heavy dashboard/report filters after real data volume is known.
- [ ] Add a script or admin diagnostic to report missing tables/columns.

## Future Features

- [ ] Global search across cases, reminders, checklist, tasks, MoM, domains, finance, and feedback.
- [ ] Case detail timeline with notes/comments and linked MoM/task/reminder history.
- [ ] In-app notification center.
- [ ] Calendar/timeline view for reminders, cases, MoM, and task due dates.
- [ ] Attachment management for cases and shift reports.
- [ ] PDF/print reports for management review.
- [ ] Optional email/WhatsApp reminders.
- [ ] Self-host CDN assets for offline/privacy-sensitive deployments.

## ISO 9001 / Measurement Tracking

- [ ] Define KPIs for response, follow-up, completion, overdue work, cancellation reasons, and shift handover quality.
- [ ] Add measurement dashboard/page or dedicated measurement subdomain.
- [ ] Add achievement tracking by user/division/team.
- [ ] Add evidence exports for audits: MoM decisions/actions, task completion, shift reports, cancellation insights, activity logs.
- [ ] Add monthly management-review report.
- [ ] Map modules to ISO 9001 evidence categories and retention periods.

## Legacy / Archived Notes

- Old post-deploy tasks such as “delete install.sql from server” are not universal. Prefer restricting access and keeping source-controlled installer available for fresh deployments.
- Old “multi-user roles” task is complete as user management/roles/permissions, but permission coverage still needs audit.
- Old “dark/light toggle” task is complete through theme preferences.
- Old “due date to tasks” task is superseded by the newer task-management module.
- Old “finance edit endpoint” remains a product decision; audit-log style immutable finance entries may be preferred.
