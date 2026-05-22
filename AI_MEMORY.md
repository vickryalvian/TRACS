# TRACS — AI Memory & Project Rules

> **READ THIS FIRST.** This file preserves critical project intelligence for future AI sessions, developers, and handoffs. Do not delete it, and do not treat it as generic boilerplate.

## Project Identity

- **Name:** TRACS Operational Dashboard
- **Purpose:** Operational control panel for CS/support/legal workflows, especially Indonesian operations teams.
- **Stack:** Vanilla PHP 8 + MySQL/MariaDB + Apache + vanilla JS/CSS.
- **Document root:** [public](/tracs/public), not repository root.
- **Timezone:** Asia/Jakarta / WIB.
- **Design:** Dense operational dashboard with TRACS branding, sidebar navigation, ticker bar, stat strips, compact panels, modals, and dark/light theme support.
- **No framework/build step:** No Composer, no npm, no SPA routing unless the owner intentionally changes architecture.

## Current System Shape

- Public pages live under [public](/tracs/public).
- Auth files live under [public/auth](/tracs/public/auth).
- JSON/CSV endpoints live under [public/api](/tracs/public/api).
- Business logic lives under [modules](/tracs/modules), with MoM currently bridged through [modules/mom/controller.php](/tracs/modules/mom/controller.php) to [public/modules/mom/controller.php](/tracs/public/modules/mom/controller.php).
- Shared helpers live in [core](/tracs/core) and [public/includes](/tracs/public/includes).
- Fresh schema is [config/install.sql](/tracs/config/install.sql); existing installs use [config/migrations](/tracs/config/migrations).

## Do Not Break

1. **Document root:** keep Apache/Nginx pointed at `/public`.
2. **Session/auth flow:** public pages call `tracs_start_session()` then include `public/auth/auth_check.php`.
3. **API bootstrap:** endpoints under `public/api` require `_bootstrap.php`; it handles session guard, CSRF verification for mutating methods, DB include, auth user refresh, JSON helpers, creator tracking, and activity/ticker helpers.
4. **CSRF:** keep `csrf_meta_tag()` in the header and `verify_csrf()` on POST/PUT/PATCH/DELETE flows.
5. **Require paths:** prefer `__DIR__` based includes.
6. **Ticker duplication:** `header.php` doubles ticker HTML for seamless scrolling; keep that behavior.
7. **Shared layout:** `public/includes/header.php` owns shell, ticker, sidebar, theme menu, and nav permissions; `footer.php` owns shared modals and script loading.
8. **Design tokens:** use CSS custom properties and existing component classes in `tracs.css`; avoid one-off inline color systems.
9. **Dirty worktree safety:** this repo may contain in-progress user edits and prior backup folders. Do not revert unrelated PHP/CSS/JS changes.

## Coding Conventions

### PHP

- Use prepared statements for SQL.
- Escape HTML with `esc()` from [public/includes/page_helpers.php](/tracs/public/includes/page_helpers.php).
- Keep activity/ticker failures non-fatal.
- Use `tracs_ensure_creator_columns()` where existing modules already do.
- Preserve user attribution fields such as `created_by` and `created_by_name`.
- Keep module permissions checked with `tracs_user_can()` / `tracs_require_permission()` where present.

### APIs

- Use `_bootstrap.php` for authenticated endpoints.
- Return the existing response shape for the endpoint family being edited. Most endpoints use `ok()`/`fail()`, while some legacy MoM endpoints return legacy `success`/`error` or `ok`/`msg` shapes.
- Mutating requests require CSRF.
- CSV export endpoints may produce downloads instead of JSON.

### Frontend

- Shared app JS is [public/assets/tracs.js](/tracs/public/assets/tracs.js).
- MoM JS is [public/assets/mom-functions.js](/tracs/public/assets/mom-functions.js).
- TV mode JS is [public/assets/tv-mode.js](/tracs/public/assets/tv-mode.js).
- Use existing helpers: `api()`, `toast()`, `confirm()`, modal helpers, row data attributes, and optimistic DOM updates where established.
- Use lucide icons already loaded by the header.

## Design Rules

- Keep TRACS compact, operational, and scannable.
- Keep the sidebar icon-only with hover tips and role-aware navigation.
- Keep the top ticker as a live operational signal, not a decorative banner.
- Use stat cards, tables, filter bars, panels, badges, and compact modal forms consistently.
- Preserve theme support through `theme_bootstrap.php`, `tracs_user_preferences`, and CSS variables.
- Do not make dashboard panels oversized marketing cards.
- Do not split `tracs.css` unless a future design-system migration is explicitly requested.

## Active Modules And Priorities

| Module | Current priority |
| --- | --- |
| Dashboard | Preserve layout and critical counts; improve measurement widgets carefully. |
| Cases / Reminders / Checklist | Core daily workflow; avoid regressions. |
| Task Monitoring | High priority for assignment, accountability, and measurement. |
| MoM | Keep scheduled meeting/reminder/ops-window flow accurate. |
| Shift Reports | Important for handover and ISO-style traceability. |
| User Management | Permission-sensitive; test role access after edits. |
| Cancellation Feedback | Important for retention intelligence and reporting. |
| TV Mode | Role-gated wall display; avoid coupling it to dashboard DOM. |
| ISO 9001 / Measurement | Future direction: measurement page/subdomain, KPIs, achievement tracking, evidence exports. |
| Domain Price Crosscheck | Operational comparison panel for TLD base cost vs selling prices. Accessed from Domains → Crosscheck Pricing; Task Management integration remains for assignment/workflow. See [DOMAIN_PRICE_CROSSCHECK.md](file:///Users/ulfahanifah/Documents/tracs/docs/DOMAIN_PRICE_CROSSCHECK.md), [DOMAIN_PRICE_CROSSCHECK_ARCHITECTURE.md](file:///Users/ulfahanifah/Documents/tracs/docs/DOMAIN_PRICE_CROSSCHECK_ARCHITECTURE.md), and [DOMAIN_PRICE_CROSSCHECK_AI_MEMORY.md](file:///Users/ulfahanifah/Documents/tracs/docs/DOMAIN_PRICE_CROSSCHECK_AI_MEMORY.md). |

## Known Constraints

- No real-time websocket layer; most pages refresh or update via local AJAX.
- Some modules still auto-create legacy tables for migration tolerance.
- Some legacy table names are intentionally unprefixed: `balance_transfers`, `domain_transfers`, `activity_feed`, `ops_status`.
- MoM has two API styles: current `api_mom.php` and legacy `mom-action.php`.
- Root `.env` exists, but `database.php` currently reads PHP `$_ENV`; Docker now configures PHP to expose environment variables to `$_ENV`.
- External CDN dependencies exist for fonts, lucide, and flatpickr.

## Future AI Agent Instructions

1. Read this file, [ARCHITECTURE.md](/tracs/ARCHITECTURE.md), [HANDOFF.md](/tracs/HANDOFF.md), and [TASKS.md](/tracs/TASKS.md) before major edits.
2. Inspect live code before updating docs or schema.
3. Back up targeted docs/config before edits when asked.
4. Do not delete obsolete files immediately; mark legacy/deprecated and explain migration path.
5. Do not touch unrelated PHP/CSS/JS while doing documentation/config work.
6. Preserve TRACS branding and useful project notes.
7. For risky DB or permission changes, add migration notes and test with at least an admin and a non-admin role.
