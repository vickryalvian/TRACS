# Sales Configurator Overhaul

Implemented on `codex/infrastructure-sales-configurator-overhaul`.

## Current Workflow

Select service and billing period, select category-specific items, add/remove rows,
enter nodes, and read subtotal, PPN, and grand total. Dedicated Server starts with
empty CPU, RAM, and Storage rows. More Storage rows can be added without limit
other than the overall 100-line guard. A storage label is one priced master item.
VPS and Hosting Custom also support the workbook's per-core/per-GB quantities.
Monthly, annual, and one-time selections are separate configurations, never added
together as though all charges recur monthly. Switching service/period starts a
fresh configuration; refreshing prices retains the current selection where possible.

## Inspection and Scope

- Existing route: `public/configurator.php`, guarded by the existing session/auth
  includes and `dashboard.view`. Navigation and page-specific asset integration
  remain in the existing header. No global layout, permission registry, or other
  module was changed for this task.
- Previous flow: hardcoded PHP rate cards embedded into the page, product cards,
  free-text requirement parsing, hardware recommendations, RAID capacity and disk
  validation, plus browser calculations and manual overrides. No feature API,
  pricing administration, persistence, or migration existed.
- Both repository search and `SHOW TABLES LIKE '%configurator%'` in the local
  Docker database found no existing Configurator tables before migration.
- `repositories.php`, `pricing.php`, and `parser.php` are retained as inactive
  legacy helpers. The controller no longer includes or calls them. Their existing
  foundation tests still pass. No RAID or recommendation code remains in the
  active page or JavaScript. The old data does not seed the new database.

## Architecture and Master Data

The existing module controller calls `master.php`. `view.php` renders the native
TRACS form/typography/button shell. Browser JavaScript loads numeric data through
the existing route's JSON actions. It never reads Excel or embeds a price list.

`tracs_configurator_items` stores service, category, exact item name, specification,
numeric price, billing period, per-unit quantity flag, active flag, order, source
file/sheet/range, source key, revision, and timestamps. DOUBLE preserves the
workbook's fractional numeric values without rounding on import. Monetary totals
are rounded to two decimal places after summing source precision.

`tracs_configurator_settings` stores the workbook-derived PPN rate and a revision.
The editor uses existing `settings.manage`; all calculator users still require
`dashboard.view`. Item changes and tax changes require CSRF and POST. Prepared
statements and validation protect writes. Revision checks reject stale edits.
Deactivation replaces destructive deletion. The editor supports adding items,
changing service/category/name/specification/price/billing/order, and activation.
Master Data supports combined search, service, category and active-status filters,
with configured-order, name and numeric price sorting and a matching-item count.
The responsive item dialog keeps its header/actions visible around a scrollable
form. Service/category suggestions use existing master values while allowing new
values. New items inherit the current service/category filters.

## Pricing and Calculations

1. Database catalog -> category and billing filtered dropdown -> immediate price.
2. Sum prices for selected rows (multiply only workbook per-unit rows by units).
3. Multiply unrounded subtotal by nodes; round before-tax total to two decimals.
4. Apply margin, default 30% cost-plus markup on the subtotal for all nodes.
   Sales may override the percentage (0-1000%) or use a fixed Rupiah amount
   (0-1,000,000,000,000) applied once to the whole configuration. Round margin
   to two decimals and add it to the subtotal before PPN.
5. Multiply the subtotal including margin by database PPN; round tax to two decimals.
6. Grand total = subtotal including margin + tax.

The browser displays totals immediately, then checks them against current database
prices using the server calculator. A changed price, removed/inactive item, invalid
quantity, expired session, or unavailable API clears unverified totals. Catalog
requests use `no-store`; reloads and browser back/forward restoration fetch current
prices. Every selected item has an editable unit price and a reset-to-master
action. Overrides are configuration-only, available to calculator users, and
never update Master Data. Changing the item/category clears its override;
refreshing prices retains overrides. For per-unit resources, the override is
multiplied by units and nodes. No saved browser price snapshots are used.

Workbook example: 3,700,000 + 1,800,000 + 3,600,000 + 1,500,000 = 10,600,000 per
node. With margin set to 0%, 11% PPN = 1,166,000; total = 11,766,000.
With the new default 30% margin, margin = 3,180,000, subtotal before PPN =
13,780,000, PPN = 1,515,800 and grand total = 15,295,800. Empty configurations
remain zero even in fixed-margin mode. Server validation and browser calculations
use the same override and margin rules. These controls require no database migration.

## API

All actions remain on `configurator.php?action=...` after authentication and page
permission checks. JSON responses use `success` and `data` or `message`.

| Action | Method | Permission | Behavior |
| --- | --- | --- | --- |
| catalog | GET | dashboard.view | Active items; managers also receive inactive items for editing |
| calculate | POST + CSRF | dashboard.view | Re-fetch numeric prices; validate service, category, period, units and nodes |
| save_item | POST + CSRF | dashboard.view + settings.manage | Create or revise a master item |
| save_tax | POST + CSRF | dashboard.view + settings.manage | Revise feature PPN configuration |

## Extraction and Migration

`bin/extract-configurator-workbook.py` uses openpyxl to inspect the actual workbook
and generate `config/seeds/configurator-items.json` plus the extraction report.
It records a SHA-256 hash, source ranges, and original names/prices. Every worksheet
is scanned. Only reviewed simple Sales ranges are imported; excluded worksheets
and ambiguous billing/models are explicitly documented in
[CONFIGURATOR_SHEET_EXTRACTION.md](CONFIGURATOR_SHEET_EXTRACTION.md).

Imported: 59 Dedicated Server, 28 VPS, 9 Network, 3 Hosting Custom items. Dedicated
Server includes 7 CPU, 4 RAM, 17 Storage, 4 License, 9 Setup, 14 Network, 2 Hardware,
1 Power and 1 Colocation item. All 59 rental master rows B2:C60 are represented.

Reproduction (run from repository root, with Python containing openpyxl):

```sh
python3 bin/extract-configurator-workbook.py '/path/to/Hitungan Sales.xlsx'
php bin/import-configurator-items.php
php bin/import-configurator-items.php --apply --migrate
php bin/import-configurator-items.php --verify
```

The default import is validation-only and does not connect to the database.
`--apply --migrate` explicitly creates the two feature tables if absent and seeds
them. There are no DROP, DELETE, or changes to existing tables. Item insertion and
tax initialization run in a transaction after the schema migration. A stable
file/sheet/range source key prevents duplicate imports. Existing values, including
administrator changes and deactivated items, are never overwritten by reimport.
Source changes appear as differences in verification; reconcile them in Master
Data. Source rows moved to a new range have a new identity and require review.

Applied to the already-running local Docker TRACS database: first import inserted
99; second inserted 0; both compared 99 with zero differences. No production
database was contacted. Rollback is application-code rollback; the additive tables
and imported records can remain intact without affecting the older route.

## Verification

- PHP syntax checks for every new/changed PHP entry point; JavaScript syntax check.
- Existing `tests/infrastructure-configurator-foundation.php` passes (legacy).
- `tests/configurator-sales.php --database`: workbook totals, add/remove/change,
  node multiplication, configurable tax, invalid nodes/quantities, category and
  period mismatches, unknown/inactive items, per-unit VPS, empty state. Database
  create/edit/deactivate, reload and stale-revision tests pass; writes roll back.
- `tests/configurator-extraction.py <workbook>`: independently re-extracts source,
  checks seed reproducibility and source hash, then compares all 99 database
  item names, prices, services, categories and billing periods exactly.
- `tests/configurator-browser.cjs`: real view and CSS in headless Chrome, API
  interception, real PHP calculation helper. Category-only lists, inactive item
  exclusion, preset prices, repeated Storage, remove/change, nodes, currency,
  Master Data edit, per-unit VPS, billing isolation, stale prices, error/retry and
  reload pass. Desktop 1440px and mobile 390px screenshots inspected; no horizontal
  overflow. Browser test requires Playwright and installed Chrome; set
  `PLAYWRIGHT_MODULE` to its module path if it is not locally installed.
- Anonymous request to actual `configurator.php?action=catalog` redirects to login
  with HTTP 302. Authenticated role-specific HTTP checks remain untested; existing
  guards are retained, and no auth/permission files were edited.

The verification table is [CONFIGURATOR_DATA_VERIFICATION.md](CONFIGURATOR_DATA_VERIFICATION.md).
The running local app is http://localhost:8080/configurator.php (login required).

## Changed Files

- Route and page assets: `public/configurator.php`,
  `public/assets/infrastructure-configurator.js`, `.css`.
- Module: `controller.php`, new `master.php`, new `view.php`.
- Migration and seed: `config/migrations/2026_09_10_configurator_master.sql`,
  `config/seeds/configurator-items.json`.
- Import/extraction commands: `bin/import-configurator-items.php`,
  `bin/extract-configurator-workbook.py`.
- Tests: `tests/configurator-sales.php`, `tests/configurator-extraction.py`,
  `tests/configurator-browser.cjs`.
- Documentation: extraction, overhaul, verification, historical implementation
  note, and `AI_MEMORY.md`.

## Limitations and Follow-up

Standalone colocation remains deferred because its sheets mix historical prices,
cost and sales rates, mandatory deposits and inconsistent setup models. Other
packaged/licensed/reseller models are documented but not exposed as simple rental
calculators. No full-workbook import or support for those models is claimed.
Confirm the noted DS setup classification and VPS NVMe wording conflict with
Sales before production adoption. The workbook's truncated F21 sum is deliberately
corrected to include every selected row, as required by the new workflow.

Quotes are not persisted or exported. Billing-period switches reset rows. There
is no combined first-invoice or deposit calculator, automatic pricing sync, or
recommendation engine. Authenticated browser checks with real role accounts and
production deployment are still required before release.
