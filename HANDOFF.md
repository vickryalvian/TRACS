# TRACS Operational Dashboard — Handoff & Status

## Module: Domain Price Crosscheck (Phase 1-8 Complete)

### Current Status
The Domain Price Crosscheck module is **fully operational** up to Phase 8. The UI, logic, task assignments, intern gating, and spreadsheet-like Pricing Matrix have been completely implemented, verified, and styled with responsive constraints.

### Phase 8 QA Report

**Navigation Update**
- Domain Price Crosscheck is now accessed from **Domains → Crosscheck Pricing** in the sidebar.
- Domain Transfer remains under **Domains → Domain Transfer** and still routes to the existing transfer tracker.

**Create Flow UI Update**
- The New Monthly Record button is aligned in the right-side page header action area.
- The create modal now uses Month and Year dropdowns, generates `YYYY-MM` internally, previews the selected period/month code, defaults to the next available period, and carries forward the latest exchange rate when available.
- Duplicate month creation is blocked with a readable message such as “A monthly record for May 2026 already exists. Please select the existing record instead.”

**Pricing Intelligence Summary Update**
- Added a Pricing Intelligence Summary for internal registrar cost vs IDCH Website Pricing only. External market pricing is out of scope.
- Summary uses the 30% target margin formula, recommended website price, gap to recommended, suggested rounded website price, margin risk, registrar source summary, exchange-rate impact, previous-month changes, and action buckets.
- Severity logic: Below Cost = Critical, Below Target Margin = Warning, Missing Data = Missing, significant registrar cost/source changes = Review/Warning, Safe = Safe.

**ccTLD and Template Duplication Update**
- Added separate gTLD Pricing and ccTLD Pricing sections in Domain Price Crosscheck.
- ccTLD pricing uses PANDI Registry Pricing vs IDCH ccTLD Pricing with Register, Renewal, and Redemption rows for `.AC.ID`, `.BIZ.ID`, `.CO.ID`, `.ID`, `.MY.ID`, `.OR.ID`, `.PONPES.ID`, `.SCH.ID`, `.WEB.ID`, and `.NET.ID`.
- New/Duplicate monthly records can copy previous month price entries as editable Draft template data. Approval metadata, audit logs, and task state are not copied; notes copy only when selected.
- Scoped button styling was added for dark/light mode readability across Duplicate Month, Recalculate Summary, Save Matrix, modal actions, disabled buttons, and warning/danger actions.

**1. Files Created/Modified**
- `public/domain_price_crosscheck.php` (Removed placeholder grid, built full Matrix HTML table)
- `public/api/domain-price-matrix.php` (Created dedicated API endpoint for saving matrix inputs)
- `public/assets/domain-price-crosscheck.css` (Added sticky columns, scrollbar tracking, and spacing improvements)
- `public/assets/domain-price-crosscheck.js` (Added AJAX matrix saving and recalculate trigger)
- `docs/DOMAIN_PRICE_CROSSCHECK.md` (Updated user documentation)
- `docs/DOMAIN_PRICE_CROSSCHECK_ARCHITECTURE.md` (Updated system documentation)

**2. UI/UX Improvements**
- Replaced the textual placeholder with an actual dense spreadsheet grid.
- Mapped professional naming conventions directly to row headers (`Liquid Registrar`, `IDCH Website Pricing`, etc.).
- Improved visual separation between USD rows, IDR rows, Internal rows, and Selling Price rows.

**3. Responsive/iPad Compatibility Improvements**
- Wrapped the matrix table in a `.table-container` with `overflow-x: auto` ensuring lateral scrolling on smaller screens without breaking the vertical bounds of the page.
- Utilized `position: sticky` on the first two table columns (`Source Group` and `Type`), ensuring users don't lose context while horizontally scrolling through dozens of TLDs on an iPad.

**4. Dark/Light Mode Improvements**
- Tuned matrix input backgrounds to be transparent (`background: transparent !important`), inheriting the correct wrapper theme.
- The sticky columns correctly map to `var(--s1)` and `var(--s2)` to prevent overlapping text bleeding through in dark mode.

**5. Accessibility Improvements**
- Inputs are given clear `:focus-within` blue glow states.
- The "Save Matrix" button receives a disabled state with a loader icon during AJAX transit, preventing accidental double-clicks.

**6. Functional Regression Tests**
- Month selection, Draft Creation, and Task Assignments still function normally.
- Interns are correctly isolated from privileged controls, and cannot see unassigned months.
- Saving matrix inputs routes properly to `domain-price-matrix.php` using CSRF validation.

### Recommended Next Phases (Future)
If the project continues later, **Phase 9** should focus on:
1. Building a CSV/Excel import parser to automatically bulk-fill the matrix.
2. Hooking up automated API checks to fetch prices directly from WHMCS or Liquid/Webnic endpoints.

> The system is ready for production use as a manual operational ledger.
