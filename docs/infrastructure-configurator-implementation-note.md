# Configurator Implementation Note

## Existing TRACS Patterns Reused

- Route shell: top-level PHP page in `public/*.php`.
- Feature namespace: isolated PHP module in `modules/<feature>/`.
- Layout: existing `public/includes/header.php` and `public/includes/footer.php`.
- Access guard: existing `tracs_require_page_permission($conn, 'dashboard.view')` pattern used by Infrastructure Pulse.
- Ticker data: existing `AlertTickerController::formatAlertsForTicker()`.
- UI primitives: `topbar`, `panel`, `panel-head`, `panel-title`, `panel-meta`, `form-input`, `form-select`, `btn`, `badge`, and `empty`.
- Assets: page-scoped CSS/JS loaded only when `$active_page === 'configurator'`.

## Completed Beta MVP Scope

- Phase 1: added the Configurator beta route, isolated module foundation, product selector, price-book model, and shared MRC/OTC summary.
- Phase 2: added manual configurators for Dedicated Server, VM, Colocation, and Custom Service.
- Phase 3: added reusable deterministic pricing helpers for MRC, OTC, storage capacity, and cost-based CAPEX recovery reference pricing.
- Phase 4: added deterministic smart requirement parsing and generated starting configurations for the supported beta examples.
- Phase 5: added friendly empty/error/loading states, responsive form layouts, beta assumptions display, shared add-ons, and focused tests.
- Workbook polish pass: aligned Dedicated Server and VPS/VM reference rates with the current Sales workbook where inspected, and added budget-aware recommendations.
- Full simple setup: Dedicated Server, VM, Colocation, and shared Add-ons are available in one workflow.
- Manual override is intentionally open to all users who can access the Configurator page.

## Architecture

- Static beta reference data lives in `modules/infrastructure-configurator/repositories.php`.
- Pricing and storage capacity math lives in `modules/infrastructure-configurator/pricing.php`.
- Requirement parsing and dedicated-server recommendation logic lives in `modules/infrastructure-configurator/parser.php`.
- The route renders server-side PHP and passes the beta rate card to isolated page JavaScript through `window.TRACS_INFRA_CONFIGURATOR_DATA`.
- The browser-side JavaScript keeps generated configurations editable and updates the shared pricing summary in real time.
- Budget-aware recommendations parse inputs such as `Rp. 8.000.000`, `server 2TB Rp. 8.000.000`, and `500 GB with Rp. 10.000.000`.
- Shared add-ons are normalized in the rate card and merged into the same pricing summary as each product.
- Summary totals show MRC, OTC, PPN 11%, MRC including PPN, and first invoice estimate.

## Deliberate Deferrals

- No database migration.
- No pricing admin.
- No dependency changes.
- No quote/proposal workflow.
- No external LLM integration.
- No inventory or contract-pricing integration.
