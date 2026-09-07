# Clients audit implementation

The Clients roster stays in browse mode. Company links open `client-detail.php?id=…` with Overview, Services, Billing, Renewals, Followups, and Activity sections. Add/Edit Client uses a native modal dialog styled as a right drawer. Both creation save paths are supported.

The existing service and contact tables are reused. Additional PICs can be added, edited, and made primary; profile edits update the current primary contact. Client creation and profile updates roll back on contact failures. Blank renewal prices preserve the existing service price.

The list supports server-side search, owner/status/service/PIC/payment/tax/date filters, sorting, and 25-row pages. Filters and sorting are encoded in the URL and retained by detail/back links. Summary and attention data cover all matching records, not just the displayed page; the UI labels them “Current filters.” Renewal KPI counts eligible services independently of higher-priority billing alerts. Cancelled invoices do not affect billing totals or attention counts.

## Design decisions

This is an operations roster for TRACS staff, preserving the supplied dark-theme/card/rail direction. ENERGY 1 / RHYTHM 2 / MOTION 1: compact data, separated triage and browsing areas, and hover/focus feedback.

- Existing TRACS colors, type, radii, and controls preserve the application identity. Scoped foreground corrections keep controls readable in both themes.
- A full-width table supports comparison across clients; pagination bounds visible rows.
- The attention strip separates daily triage from roster scanning; five items and a filtered “View all” action bound its height.
- The drawer preserves list context while native dialog behavior provides focus containment and Escape handling.
- Status colors describe record state; icons retain the existing application set and identify add/edit/sort/remove actions.

## Verification

| Check | Result and evidence |
| --- | --- |
| Database behavior | PASS: `php tests/client-portfolio-flow.php` creates and removes its own random test database; checks owner isolation, PIC updates/primary selection, rollback, overlapping KPI signals, cancelled invoices, date ranges, sorting, pagination, and renewal price preservation. |
| Browser flows | PASS: `cd frontend && node tests/clients-browser.mjs` checks no automatic selection, URL persistence, filtering without focus loss, paging, sorting, detail tabs/reload, PIC submission, both creation save paths, Escape/focus restoration, empty states, and retry/not-found states. |
| Responsive/theme layout | PASS: desktop and 390px mobile browser checks and screenshots; light/dark inspection; no document-wide horizontal overflow. Tables and attention items scroll within their containers. |
| Build/regressions | PASS: `npm run build:preview`, frontend contract suite, preview bundle contract, PHP syntax checks, and `git diff --check`. The bundle contract now includes the existing Branch Network entry. |
| Anti-slop layout scan | PASS: Impeccable layout detector returns no findings. Existing product styling and operational content drive the layout; no marketing claims or fabricated production data added. |

Browser tests use explicit fixture API responses and the built production assets. Database tests exercise the model against disposable MySQL tables. They do not claim an authenticated end-to-end test against production. Screenshots are written outside the repository to `/tmp/tracs-clients-qa`.

No new migration or dependency is needed; both existing Client Portfolio migrations remain prerequisites. Sorting/pagination run on the server after matching client aggregates are loaded; SQL-level pagination can be introduced if the roster grows beyond the audit's 50–200 client target. Future P3 bulk actions, named saved views, and richer activity threads remain outside this implementation.
