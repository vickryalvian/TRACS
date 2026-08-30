# Infrastructure Configurator Phase 0 Note

## Existing TRACS Patterns Reused

- Route shell: top-level PHP page in `public/*.php`.
- Feature namespace: isolated PHP module in `modules/<feature>/`.
- Layout: existing `public/includes/header.php` and `public/includes/footer.php`.
- Access guard: existing `tracs_require_page_permission($conn, 'dashboard.view')` pattern used by Infrastructure Pulse.
- Ticker data: existing `AlertTickerController::formatAlertsForTicker()`.
- UI primitives: `topbar`, `panel`, `panel-head`, `panel-title`, `panel-meta`, `form-input`, `form-select`, `btn`, `badge`, and `empty`.
- Assets: page-scoped CSS/JS loaded only when `$active_page === 'infrastructure-configurator'`.

## Phase 1 Scope

- Add the Infrastructure Configurator beta route.
- Add an isolated beta data/controller foundation.
- Show product selection for Dedicated Server, VM, Colocation, and Custom Service.
- Show a beta price-book label and shared MRC/OTC pricing summary placeholder.
- Keep configurator forms as placeholders for Phase 2.

## Deliberate Deferrals

- No database migration.
- No pricing admin.
- No smart requirement parser.
- No manual configurator calculations.
- No dependency changes.
