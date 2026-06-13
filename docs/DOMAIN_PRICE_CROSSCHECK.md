# TRACS Domain Price Crosscheck Module

The Domain Price Crosscheck module is a specialized operational dashboard used to track and compare domain registrar costs against IDCloudHost selling prices (Website & PAAS) on a monthly basis.

## Scope
- This module compares internal registrar/registry costs, monthly exchange rate, the 30% target margin rule, and IDCH pricing.
- External market pricing, scraping, and third-party pricing feeds are not part of this module.
- PAAS Pricing remains optional/internal when it is already part of the current monthly matrix flow.
- The goal is to ensure IDCH Website Pricing does not go below registrar cost and meets the 30% target margin.

## Navigation
- Primary sidebar location: **Tasks & Monitoring → Domain Pricing Crosscheck**.
- Canonical route: `domain-price-crosscheck.php`.
- Legacy route `domain_price_crosscheck.php` is redirect-only and returns a 308 to the canonical route.
- Domain Transfer Log is a sibling item under the same **Tasks & Monitoring** submenu.
- Visibility follows `domain_price.view`; management and approval actions use separate permissions.

## Monthly Record Creation
- The page header keeps the title/subtitle on the left and the **New Monthly Record** action on the far right of the header action row.
- Creating a monthly draft uses **Month** and **Year** dropdowns instead of a raw `YYYY-MM` text field.
- The submitted `month_code` is still generated in `YYYY-MM` format. Example: Month `May` + Year `2026` becomes `2026-05`.
- The create modal shows a readonly preview for the selected period and generated month code.
- Defaults are operational:
  - If the current month has no record, the modal suggests the current month.
  - If the current month already exists, the modal suggests the next available month.
  - Exchange rate defaults to the latest monthly record's rate when one exists; otherwise it stays empty with a placeholder.
- Duplicate month/year creation is blocked with a clear message telling the user to select the existing record.
- New drafts can use the previous month pricing as an editable template. The copied data starts as Draft data only; approval metadata, audit logs, and task completion state are not copied.
- Manual notes are copied only when the user selects **Copy notes**.
- The empty state invites users to create the first monthly record and explains that records compare registrar cost, IDCH Website Pricing, and PAAS Pricing.

## Key Features
- **Compact Module Tabs**: Overview, Price Matrix, Intelligence Summary, ccTLD Check, Website Price Adjustment, Action Buckets, Notes & Follow-ups, and Audit Trail.
- **Monthly Snapshots**: All pricing records are scoped to a specific month.
- **Pricing Matrix**: A spreadsheet-like grid allowing administrators to input USD costs for international registrars and IDR costs for internal sources.
- **ccTLD Pricing Matrix**: A separate spreadsheet-like section for Indonesian ccTLD pricing using PANDI Registry Pricing and IDCH ccTLD Pricing.
- **Automatic Conversions**: If a USD cost is entered, the system automatically translates it into IDR using the month's locked exchange rate.
- **Live KURS Preview**: USD registrar inputs now update the visible IDR preview immediately in the browser. This is a preview only; Save Matrix and Recalculate Summary still control persisted entries, reports, monthly records, and final metrics history.
- **Derived Internal Pricing**: For editable drafts, IDCH Internal Pricing uses `lowest active registrar USD × monthly KURS × 1.30`. Registrar matrix order is the tie-breaker when USD values are equal. The derived value is only persisted when the user explicitly saves the matrix.
- USD prices are presentation-formatted to two decimal places in registrar inputs, live previews, and ccTLD `Harga USD`; stored raw values are not rewritten by display formatting.
- **Pricing Intelligence Summary**: Operational decision support for below-cost prices, below-target margins, recommended website price adjustments, registrar source changes, and exchange-rate impact.
- **Review & Approval Workflow**: Drafts are submitted for review and then approved. Approved snapshots are completely locked against modifications to preserve audit integrity.
- **Task Management**: Monthly audits can be assigned to specific team members.
- **Intern Isolation**: Interns can only view the specific monthly snapshots assigned to them.
- **Comprehensive Audit Logs**: Every price tweak, status change, and note is securely logged.
- **Matrix Management**: Authorized users can manage registrar sources, domain extensions, category, and display order without deleting historical monthly data.

## Status Workflow
1. **Draft**: The month is created. The exchange rate and prices can be modified freely.
2. **In Progress / Waiting Review**: The month is submitted for review. Editing is locked.
3. **Approved**: The month is verified and locked permanently. Only Superadmins can unlock it for revision by providing a mandatory reason.

## Pricing Categories
- **Liquid Registrar**: Cost (USD/IDR)
- **Webnic Registrar**: Cost (USD/IDR)
- **IDCH Internal Pricing**: Cost (IDR)
- **IDCH Website Pricing**: Selling Price (IDR)
- **PAAS Pricing**: Selling Price (IDR)
- **PANDI Registry Pricing**: ccTLD registry cost (IDR)
- **IDCH ccTLD Pricing**: ccTLD selling/reference price (IDR)

## gTLD and ccTLD Separation
- The module separates **gTLD Pricing** and **ccTLD Pricing** inside the Domain Price Crosscheck page.
- Default ccTLD extensions are `.AC.ID`, `.BIZ.ID`, `.CO.ID`, `.ID`, `.MY.ID`, `.OR.ID`, `.PONPES.ID`, `.SCH.ID`, `.WEB.ID`, and `.NET.ID`.
- ccTLD rows are grouped by month, source, and type:
  - PANDI Registry Pricing: Register, Renewal, Redemption
  - IDCH ccTLD Pricing: Register, Renewal, Redemption
- ccTLD formulas:
  - `lowest_cost_idr = PANDI Registry Pricing`
  - `recommended_price = lowest_cost_idr × 1.30`
  - `harga_usd = ceil_to_2_decimals((lowest_cost_idr / monthly_usd_idr_kurs) × 1.30)` because the stored ccTLD base price is IDR and the page KURS is USD → IDR.
  - `margin_amount = IDCH ccTLD Pricing - lowest_cost_idr`
  - `margin_percent = margin_amount / lowest_cost_idr × 100`
  - `gap_to_recommended = recommended_price - IDCH ccTLD Pricing`
- ccTLD statuses are Below Cost, Below Target Margin, Safe, Missing Data, and Review.

## Pricing Intelligence Summary
- Formula:
  - `cost_idr = registrar_usd_price × monthly_exchange_rate`
  - `lowest_cost_idr = minimum valid registrar cost`
  - `recommended_website_price = lowest_cost_idr × 1.30`
  - `margin_amount = current_idch_website_price - lowest_cost_idr`
  - `margin_percent = margin_amount / lowest_cost_idr × 100`
  - `gap_to_recommended = recommended_website_price - current_idch_website_price`
- Executive cards show Total TLDs Checked, Below Cost, Below Target Margin, Safe, Missing Data, Recommended Website Adjustments, Estimated Margin Risk, and Pending Review.
- Priority findings are ordered as Critical Below Cost, Below Target Margin, Missing Data, Registrar Cost Increased, Recommended Source Changed, and Safe.
- Priority findings are displayed in two compact columns, Register and Renewal, so same-extension issues can be compared more quickly.
- Recommended Website Price Adjustment table shows current website price, lowest registrar cost, target margin, recommended price, required increase, suggested rounded website price, status, and suggested action.
- Suggested rounded website price is calculated by rounding the recommended website price up to the next Rp1,000.
- Registrar Source Summary counts which internal registrar source is cheapest most often.
- Registrar cost cells are compared by extension and price type. All valid registrar USD values participate, empty/invalid values are ignored, and every matching cheapest value is subtly highlighted in green so ties remain visible. The UI is data-driven and can support more registrar sources beyond Liquid and Webnic.
- Exchange Rate Impact Summary shows previous/current exchange rate differences and affected USD-based registrar costs when previous month data exists.
- Previous Month Change Summary compares lowest registrar cost, recommended website price, current IDCH Website Pricing, and recommended source changes.
- Action buckets group work into Increase Website Price Immediately, Adjust Website Price to Target Margin, Complete Missing Data, Review Registrar Cost Change, and Keep Current Website Price.
- The summary also includes ccTLD cards and findings for PANDI Registry Pricing vs IDCH ccTLD Pricing.
- Manage Domain Extensions displays each extension's category selector and sort order; category edits are saved through the existing module controller/model without changing monthly price data. Superadmins can soft-delete extensions from active matrices while preserving historical monthly records.
- Operational Audit Trail is a visible page tab and the Overview includes a latest-audit preview.

## Button and Theme Behavior
- Duplicate Month, New Monthly Record, Save Matrix, Recalculate Summary, modal, disabled, warning, and locked-state buttons use scoped Domain Price Crosscheck styles for readable dark/light theme contrast.
- Recalculate Summary is available for editable records, shows a loading state while running, and is blocked for approved records until they are unlocked.

## Severity Logic
- **Critical**: IDCH Website Pricing is below lowest registrar cost.
- **Warning**: IDCH Website Pricing is at or above cost but below the 30% target margin, or registrar cost increased by at least 20% from previous month.
- **Missing**: Registrar cost, exchange rate, or IDCH Website Pricing is missing.
- **Review**: Registrar cost increased by at least 10% from previous month, or recommended source changed.
- **Safe**: IDCH Website Pricing is at or above the recommended website price.

## Known Limitations
- No scraping, registrar API import, or external pricing feed is implemented.
- Summary calculations use saved matrix values; unsaved edits must be saved before recalculation/summary review.
- Transfer pricing can be stored by schema, but the current UI summary focuses on Register and Renewal rows already present in the matrix.
- Import/export for ccTLD pricing is documented as a TODO; no existing import/export flow was changed.
