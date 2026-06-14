# TRACS Full Refactor Roadmap

## Target

The final target is the entire TRACS system, not only Calendar, Shift
Assignment, or Dashboard:

- React JS frontend.
- Tailwind CSS following the existing TRACS visual identity.
- Advanced PHP backend and API layer.
- MySQL as the primary database.
- Gradual module-by-module migration with the current PHP application working.

This is not a big-bang rewrite. Existing business logic, permissions, sessions,
CSRF protection, validation, database access, audit behavior, uploads, exports,
and operational data remain authoritative until an approved replacement reaches
verified parity.

## Reference Implementation

`public/calendar.php` is the zero-mistake reference implementation. The Calendar
pilot defines the expected direction for:

- PHP-authenticated application shell and permission boundary.
- Manifest-loaded React island.
- Tailwind utilities isolated from legacy pages without Preflight.
- Mapping to current TRACS CSS variables and design tokens.
- Layout, spacing, cards, toolbars, filters, date controls, modals, toasts,
  tables, responsive behavior, and accessibility.
- Loading, empty, error, retry, and success states.
- API dates in ISO format with UI dates displayed as `dd-mm-yyyy`.

Future modules must learn from Calendar without weakening source-module
permissions or copying source data unnecessarily.

## Delivery Phases

1. Foundation, testing baseline, rollback, inventory, and safety documentation.
2. Full frontend architecture and build/loading strategy.
3. TRACS design-system consolidation and Tailwind token mapping.
4. Shared React and Tailwind component foundation.
5. Advanced PHP/API response, middleware, validation, service, and repository
   foundations.
6. Shift Assignment migration in small behavior-preserving batches.
7. Module-by-module migration across the remaining TRACS system.
8. Data and API normalization using reversible migrations.
9. Security hardening and permission regression.
10. Performance, accessibility, and UX polish.
11. Documentation, deployment, rollback, and TRACS v2 readiness.

## Migration Principles

- One module and one reviewable behavioral slice per branch.
- Characterize current behavior before changing it.
- Keep a PHP fallback until React parity is proven.
- React never bypasses PHP authentication, permission, or object-scope rules.
- Tailwind translates the existing design system; it does not introduce a new
  visual identity.
- Database changes require backup, paired `up.sql` and `down.sql`, verification,
  and explicit rollback notes.
- Every batch records branch, changed files, risk, tests, and rollback command.

## Suggested Module Order

1. Shift Assignment.
2. Checklist and Reminder.
3. Task Assignment and Monitoring.
4. Dashboard widgets, one widget at a time.
5. Cases.
6. Shift Reports.
7. MoM / Meeting.
8. Cancellation Feedback.
9. Domain Transfer Log and Finance.
10. Domain Price Crosscheck.
11. Infrastructure Pulse.
12. OpsTrack, Network Pulse, and TV Mode.
13. User Management, roles, and permissions.
14. Activity, reports, and CSV exports.
15. Profile and settings.
16. Login, authentication, and 2FA UI.
17. Super Admin monitoring and deployment tooling.

Authentication and permission foundations are tested and hardened early, but
their frontend migration remains late because of the system-wide risk.

## Current Phase

Phase 2 defines the React-island, Tailwind-isolation, Vite build, shared
component, API-client, PHP backend, and module migration architecture. It
performs no module refactor, React conversion, UI change, business-logic change,
dependency installation, or database-schema change.

Canonical Phase 2 plans:

- `docs/react-tailwind-architecture.md`
- `docs/frontend-migration-plan.md`
- `docs/php-api-architecture-plan.md`
