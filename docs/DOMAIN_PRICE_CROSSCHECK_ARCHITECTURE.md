# Architecture — Domain Price Crosscheck Module

## Core Components
1. **Frontend**: `public/domain-price-crosscheck.php` handles the dashboard module layout, task assignment wrappers, dynamic Matrix Grid, and Pricing Intelligence Summary.
2. **AJAX Endpoints**:
   - `public/api/domain-price-task.php`: Handles assigning the crosscheck to a user via TRACS Tasks.
   - `public/api/domain-price-workflow.php`: Handles saving granular TLD manual notes.
   - `public/api/domain-price-matrix.php`: Handles the batch saving of the spreadsheet matrix grid.
3. **Controller/Model**: `modules/domain-price-crosscheck/controller.php` & `model.php` enforce state checks, permission mapping, logging, and database transactions.

## Sidebar / Route Integration
- Sidebar file modified: `public/includes/header.php`.
- Primary navigation path: **Tasks & Monitoring → Domain Pricing Crosscheck**.
- Crosscheck Pricing route: `public/domain-price-crosscheck.php`.
- `public/domain_price_crosscheck.php` is a 308 compatibility redirect only.
- Domain Transfer Log remains a sibling item under Tasks & Monitoring and routes to `public/domains.php`.
- The Tasks & Monitoring parent menu is marked active/open for the module's active page.
- Crosscheck Pricing visibility follows `tracs_user_can($conn, 'domain_price.view')`. Domain Transfer keeps the existing sidebar behavior and route-level implementation used by `domains.php`.

## Create-Month UI Integration
- UI files modified: `public/domain-price-crosscheck.php`, `public/assets/domain-price-crosscheck.css`, and `public/assets/domain-price-crosscheck.js`.
- The page header places create actions in the right-side `.topbar-right` action area while preserving the shared TRACS topbar structure.
- `public/domain-price-crosscheck.php` computes the create-modal defaults from existing `domain_price_months` records:
  - current month when no current record exists,
  - next available month when the current month already exists,
  - latest `exchange_rate_usd_idr` as the suggested exchange rate.
- The modal posts `period_month`, `period_year`, generated hidden `month_code`, and clean numeric `exchange_rate`.
- Server-side duplicate prevention checks `DomainPriceCrosscheckController::getMonthByCode()` before creation and shows a readable period label such as `May 2026`.
- JavaScript keeps the selected-period preview and generated month code synchronized, formats exchange-rate display on blur, and disables creation when the selected month/year already exists.
- The create flow can copy the previous month pricing into the new Draft as editable template data. Manual notes are copied only when explicitly selected.
- `DomainPriceCrosscheckController::duplicatePreviousMonth()` copies price entries into the new Draft and intentionally does not copy approval metadata, audit logs, or task state.

## Theme / UI Styling
- Button polish is scoped to `public/assets/domain-price-crosscheck.css`.
- Duplicate Month, New Monthly Record, Save Matrix, Recalculate Summary, modal footer actions, warning/danger actions, disabled states, hover states, and focus states use the page-scoped button rules.
- The Recalculate Summary button is rendered only for editable records and the controller blocks recalculation for approved snapshots until they are unlocked.

## Pricing Intelligence Summary
- Implemented in `public/domain-price-crosscheck.php` without adding database columns.
- Uses saved matrix values from `domain_price_entries` plus optional manual note data from `domain_price_summaries`.
- Internal cost source scope is limited to `Liquid Registrar`, `Webnic Registrar`, and `IDCH Internal Pricing` if present.
- It does not use external market pricing, external website pricing, or external imports.
- Core formulas:
  - `cost_idr = registrar_usd_price × monthly_exchange_rate`
  - `recommended_website_price = lowest_cost_idr × 1.30`
  - `margin_percent = (current_idch_website_price - lowest_cost_idr) / lowest_cost_idr × 100`
  - `gap_to_recommended = recommended_website_price - current_idch_website_price`
- Suggested rounded website price is a UI calculation: recommended website price rounded up to the nearest Rp1,000.
- Previous month comparison uses the latest visible month before the current month and compares lowest registrar cost, recommended website price, current IDCH Website Pricing, and source changes.
- Significant registrar cost increase thresholds are constants: 10% for Review and 20% for Warning.
- Summary filters are client-side buttons in `public/assets/domain-price-crosscheck.js`; styles live in `public/assets/domain-price-crosscheck.css`.
- The page exposes compact module tabs for Overview, Price Matrix, Intelligence Summary, ccTLD Check, Website Price Adjustment, Action Buckets, Notes & Follow-ups, and Audit Trail.
- Overview includes Priority Findings, Action Buckets, and Latest Audit Activity previews.
- Registrar sources and domain extensions are managed through the Manage Price Matrix modal. Deactivation/soft-delete preserves historical entries.

## ccTLD Pricing
- Migration added: `config/migrations/2026_05_27_domain_price_cctld_pricing.sql`.
- Existing tables are reused with `domain_price_tlds.tld_category` (`gtld` / `cctld`) to avoid splitting the module into parallel data stores.
- ccTLD source rows:
  - `PANDI Registry Pricing` (`source_type = registry`)
  - `IDCH ccTLD Pricing` (`source_type = internal`)
- Default ccTLD seed extensions: `.AC.ID`, `.BIZ.ID`, `.CO.ID`, `.ID`, `.MY.ID`, `.OR.ID`, `.PONPES.ID`, `.SCH.ID`, `.WEB.ID`, `.NET.ID`.
- ccTLD matrix uses existing `domain_price_entries` rows:
  - `cost_register` = Register
  - `cost_renewal` = Renewal
  - `cost_transfer` = Redemption
- ccTLD summary applies the same 30% target margin:
  - `recommended_price = PANDI Registry Pricing × 1.30`
  - `margin_percent = (IDCH ccTLD Pricing - PANDI Registry Pricing) / PANDI Registry Pricing × 100`
- ccTLD import/export is a TODO because no stable Domain Price import/export endpoint exists yet.

## Database Tables
- `domain_price_months`: The root snapshot records.
- `domain_price_sources`: Registered internal registrar/registry/internal cost sources used by the module.
- `domain_price_tlds`: Scoped TLD records with `tld_category` for gTLD vs ccTLD separation.
- `domain_price_entries`: The crosscheck matrix values linked by Month, Source, and TLD.
- `domain_price_audit_logs`: Detailed operational logging for compliance.
- `domain_price_tld_notes`: Granular status notes for follow-ups.
- `domain_price_task_links`: A junction table mapping snapshots to native `tracs_reminders`.

## Notifications & Isolation
- The `controller.php` utilizes `tracs_ticker_events` to dispatch operational alerts when tasks are assigned or snapshots are approved/unlocked.
- Intern accounts are tightly restricted in the UI and Controller via `hasInternAccess()`, effectively isolating data visibility exclusively to explicitly assigned snapshots.
- Assignment workflow uses `domain_price_task_links`; module activity currently uses ticker events rather than the central browser-notification trigger set.

## Known Limitations
- The pricing matrix is manually populated; no automated registrar scraping or API integrations exist in V1.
- CSV/Excel bulk import is not implemented.
- ccTLD import/export template support is not implemented yet.
