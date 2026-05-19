# TRACS — Handoff Document

**Date:** 2026-05-18  
**Status:** Active development / local Docker-ready  
**Repository:** `/tracs`

## Current Status

TRACS is no longer just a first-deploy dashboard. It is an expanded operational system with cases, reminders, checklist, shift reports, MoM, task assignment/monitoring, finance, domains, cancellation feedback, user management, profile/preferences, activity logs, ticker events, and TV mode.

There are existing uncommitted application changes in PHP/CSS/JS and prior backup folders. Treat them as user work unless explicitly told otherwise.

## Before Editing Checklist

- Read [AI_MEMORY.md](/tracs/AI_MEMORY.md), [ARCHITECTURE.md](/tracs/ARCHITECTURE.md), and this file.
- Run `git status --short`.
- Inspect the relevant PHP page, API endpoint, module controller/model, CSS, JS, and schema/migration files.
- Back up any target files if the task asks for backups or touches docs/config/schema.
- Confirm whether a module depends on role permissions or migration readiness.
- Avoid unrelated formatting churn.
- Test the touched flow with an authenticated session when possible.

## Implemented Features

| Feature | Status | Notes |
| --- | --- | --- |
| Login / Logout | Implemented | Session auth, password verification, inactive/suspended account guard. |
| CSRF | Implemented | Meta tag plus API verification for mutating requests. |
| Dashboard | Implemented | Ops overview, ticker, cases/reminders/checklist, shift/MoM signals, activity, currency widget. |
| Cases | Implemented | CRUD, filters, search, export, next checks. |
| Reminders | Implemented | CRUD, due states, completion, dashboard/ticker integration. |
| Checklist | Implemented | CRUD, completion progress, task-management sync hooks. |
| Task Monitoring | Implemented | Assignment by user/role/division, status updates, review, performance/monitoring. |
| Shift Reports | Implemented | Shift handover and activity snapshots. |
| MoM | Implemented | Scheduling, lifecycle, agenda, notes, decisions, actions, reminders, case links, screenshots, history/export. |
| Finance | Implemented | Balance transfer logs and export; some finance records are intentionally audit-like. |
| Domains | Implemented | Domain transfer/expiry tracking and activity feed. |
| Cancellation Feedback | Implemented | Intake, analytics, multi-value service/reason fields, export. |
| User Management | Implemented | Roles, permissions, divisions, intern profiles, activity logs. |
| Profile / Theme | Implemented | User profile, security, preferences, theme selection. |
| TV Mode | Implemented | Role-gated wall display using summary API. |
| Docker | Implemented | App + MySQL stack; PHP env exposure fixed in Dockerfile. |

## Do Not Break

- `/public` must remain the web root.
- Keep TRACS header/sidebar/ticker shell intact.
- Keep the dashboard compact and operational.
- Keep `public/api/_bootstrap.php` as the API entry guard.
- Keep CSRF checks for mutating API and form actions.
- Keep role/permission gating around monitoring, TV mode, and user management.
- Keep ticker HTML duplication in `header.php`.
- Keep shared modals/scripts in `footer.php` unless a full UI refactor is planned.
- Do not delete legacy DB tables or archived SQL without proving no PHP references remain.

## Known Bugs / Risks

| Risk | Severity | Notes |
| --- | --- | --- |
| Root `.env` is not loaded by PHP app | Medium | `config/env.php` loads `config/.env`, but `database.php` does not include it. Docker uses environment variables and Dockerfile now exposes them to `$_ENV`. |
| Some modules auto-create tables | Medium | Domains/finance legacy flows still create some tables for tolerance. Keep fresh installer complete anyway. |
| MoM error text references old schema path | Low | Some code says `config/mom_database_schema.sql`; current schema is in `config/install.sql` and `config/schema/moms.sql`. |
| External CDNs | Low | Google Fonts, lucide, flatpickr require network unless bundled later. |
| No websocket/live multi-user refresh | Low | Most updates are page refresh or local AJAX. |
| Permissions depend on migrations | Medium | User management and task monitoring need current migrations/schema. |

## Database Notes

- Fresh installs: run [config/install.sql](/tracs/config/install.sql).
- Existing installs: run [config/migrations](/tracs/config/migrations) chronologically after a DB backup.
- Default seeded account is `admin@tracs.local` / `password`; change immediately.
- Keep `balance_transfers`, `domain_transfers`, `activity_feed`, and `ops_status` until code references are migrated.
- MoM requires `tracs_moms` and `tracs_mom_*` tables.
- Task monitoring requires `tracs_tasks`, `tracs_task_assignments`, logs/reviews/reminders, and user-management tables.

## Deployment Notes

### Docker

```bash
docker compose up -d --build
docker compose logs -f app
```

Use `http://localhost:8080`. MySQL is exposed on host port `3307`.

### Traditional Hosting

- Point document root to `/public`.
- Import `config/install.sql`.
- Configure DB credentials through PHP environment variables or edit `config/database.php`.
- Enable Apache rewrite and headers.
- Disable display errors in production.
- Ensure upload paths used by MoM screenshots are writable.
- Enable HTTPS and secure PHP session flags.

## UI/UX Rules

- Use the existing `panel`, `stat-card`, `filter-bar`, `badge`, `btn`, table, modal, and toast patterns.
- Keep controls compact and scannable.
- Preserve dark/light theme variables.
- Use lucide icons for nav/actions.
- Avoid large decorative sections; this is an operations tool.
- Do not put cards inside cards unless it is already an established local pattern for a specific page.

## Next Priorities

1. Verify fresh Docker bootstrap against current `install.sql`.
2. Add a DB/config health check page or CLI script for migrations/schema readiness.
3. Clean up MoM legacy error messages and duplicated API styles when safe.
4. Add ISO 9001 measurement/KPI dashboard and evidence exports.
5. Review security hardening: login rate limiting, session cookie flags, upload validation, and least-privilege permissions.

## Handoff For AI Agents

When resuming work, first identify whether the task is documentation, UI, backend, schema, Docker, or deployment. Then inspect the current code for that area before editing. TRACS has evolved quickly; old notes may be useful context, but the source code and schema are the authority.
