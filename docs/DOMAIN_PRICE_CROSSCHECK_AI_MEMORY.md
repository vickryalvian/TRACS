# AI Memory — Domain Price Crosscheck Module
Last Updated: 2026-05-20 (ccTLD pricing and template duplication update)

## Module Status
**PRODUCTION READY — Phase 10 Complete**

## Overview
A manual-input-first operational ledger for comparing registrar costs vs IDCH selling prices on a monthly basis.

## Key Technical Facts

### Database (do not rename columns)
- `domain_price_months` — root monthly snapshots
- `domain_price_tlds` — active TLD extensions with `tld_category` (`gtld` / `cctld`)
- `domain_price_sources` — source registry for internal registrar/registry/internal cost sources
- `domain_price_entries` — the matrix entries. price_type ENUM:
  - `cost_register`, `cost_renewal`, `cost_transfer` (registrar/internal costs)
  - `selling_website_register`, `selling_website_renewal`, `selling_website_transfer` (IDCH Website)
  - `selling_paas_register`, `selling_paas_renewal`, `selling_paas_transfer` (PAAS)
- `domain_price_summaries` — computed summary + manual notes per TLD per month
- `domain_price_audit_logs` — audit trail
- `domain_price_task_links` — junction table for TRACS task assignments

### API Endpoints
- `public/api/domain-price-matrix.php` — save_matrix action (batch save)
- `public/api/domain-price-workflow.php` — save_tld_note action
- `public/api/domain-price-task.php` — assign_task action
- `public/api/domain-price-recalculate.php` — recalculate action (Phase 10)

### Professional Source Labels (UI only — do NOT change DB column names)
- Liquid Registrar (source_type: registrar)
- Webnic Registrar (source_type: registrar)
- IDCH Internal Pricing (source_type: internal)
- IDCH Website Pricing (selling, no source_id)
- PAAS Pricing (selling, no source_id)
- PANDI Registry Pricing (source_type: registry)
- IDCH ccTLD Pricing (source_type: internal)

### Scope Boundary
- Do not add external market pricing fields, imports, feeds, or summary cards.
- The module only compares internal registrar/registry cost, exchange rate, the 30% target margin, IDCH Website Pricing, optional PAAS Pricing already present in the matrix, and IDCH ccTLD Pricing.

### Calculation Rules
- Lowest cost = min(idr_value) across allowed internal registrar cost entries per TLD/type.
- Recommended Website Price = lowest_cost * 1.30.
- Margin amount = current IDCH Website Pricing - lowest_cost.
- Margin% = margin amount / lowest_cost * 100.
- Gap to recommended = recommended website price - current IDCH Website Pricing.
- Suggested rounded website price = recommended website price rounded up to nearest Rp1,000.
- Below Cost = current IDCH Website Pricing < lowest_cost.
- Below Target Margin = current IDCH Website Pricing >= lowest_cost and < recommended website price.
- Safe = current IDCH Website Pricing >= recommended website price.
- Missing Data = missing registrar cost, exchange rate, or IDCH Website Pricing.
- Significant cost increase thresholds: 10% = Review, 20% = Warning.

### Status Workflow
draft → pending_review → approved
- draft: fully editable
- pending_review: submitted by user, locked for edits, ready for approver review
- approved: locked. Unlock requires mandatory reason + domain_price.approve permission.

### Intern Isolation
- `hasInternAccess(monthId, roleSlug, userId)` in controller
- Interns only see months assigned to them via domain_price_task_links

### Permissions
- `domain_price.view` — read-only access
- `domain_price.manage` — create, edit, save matrix, submit, recalculate, notes
- `domain_price.approve` — approve, unlock

### Navigation Placement
- Primary sidebar location is **Domains → Crosscheck Pricing**.
- This feature belongs under the Domains sidebar menu, not as a separate top-level item and not inside the Task Management menu.
- Task Management integration remains for assignment/workflow via `domain_price_task_links`.

### Create-Month UI
- Header action location: right side of the Domain Price Crosscheck page header.
- Create modal uses Month + Year dropdowns and generates hidden `month_code` as `YYYY-MM`.
- Modal preview must show selected period and generated code.
- Default period is current month unless it already exists, then the next available month.
- Default exchange rate comes from the latest monthly record when available.
- Duplicate month creation is blocked server-side and mirrored client-side in the modal.
- New month creation can copy previous month pricing as editable Draft template data.
- Duplicate Previous Month copies entries but not submitted/approved metadata, audit logs, or task state.
- Manual notes are copied only when the user explicitly selects Copy notes.

### Pricing Intelligence Summary
- Summary components: Executive Summary Cards, Priority Findings List, Recommended Website Price Adjustment table, Registrar Source Summary, Exchange Rate Impact Summary, Previous Month Change Summary, and Action Buckets.
- Severity mapping: Below Cost = Critical, Below Target Margin = Warning, Missing Data = Missing, significant cost increase/source change = Review/Warning, Safe = Safe.
- Suggested actions: Increase Website Price Immediately, Adjust Website Price to Target Margin, Complete Missing Data, Review Registrar Cost Change, Review Source Change, Escalate to Admin/Finance, Keep Current Website Price.
- Summary filters are client-side and must not alter saved calculation data.

### ccTLD Pricing
- Page has separate gTLD Pricing and ccTLD Pricing sections.
- Default ccTLDs: .AC.ID, .BIZ.ID, .CO.ID, .ID, .MY.ID, .OR.ID, .PONPES.ID, .SCH.ID, .WEB.ID, .NET.ID.
- ccTLD source groups: PANDI Registry Pricing and IDCH ccTLD Pricing.
- ccTLD rows use existing price types: `cost_register`, `cost_renewal`, `cost_transfer` as Register, Renewal, Redemption.
- ccTLD formula: PANDI Registry Pricing as cost, IDCH ccTLD Pricing as current price, 30% target margin.
- ccTLD statuses: Below Cost, Below Target Margin, Safe, Missing Data, Review.
- ccTLD import/export remains TODO.

### UI / Theme
- Domain Price Crosscheck has scoped button styling for dark/light mode.
- Duplicate Month, Recalculate Summary, Save Matrix, New Monthly Record, modal footer buttons, disabled buttons, and warning/danger buttons should stay readable in both themes.

## Known Limitations (V1)
- No CSV/Excel bulk import
- No automated scraping or registrar API integration
- No WHMCS integration
- Calculation engine works on register/renewal only (transfer rows are captured but not summarized)
- ccTLD uses Redemption rows in the UI but persisted recalculation engine remains focused on gTLD register/renewal summaries.
- ccTLD import/export template support is not implemented yet.
- Recalculate must be triggered manually after matrix edits

## Recommended Phase 11
1. CSV/Excel bulk import for matrix data
2. Automated registrar price API integration
3. Transfer pricing in calculation engine
4. Dashboard widget summary
