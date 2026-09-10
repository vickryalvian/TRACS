"""Extract reviewed Sales ranges; requires openpyxl, never runs in the web app."""
import argparse
import hashlib
import json
from collections import Counter
from pathlib import Path

import openpyxl


def extract(path):
    workbook = openpyxl.load_workbook(path, data_only=False)
    values = openpyxl.load_workbook(path, data_only=True)
    items = []
    issues = []

    def add(sheet, row, name_col, price_col, service, category, period="monthly", units=False):
        source = workbook[sheet]
        name = source[f"{name_col}{row}"].value
        price = values[sheet][f"{price_col}{row}"].value
        if not isinstance(name, str) or not isinstance(price, (float, int)) or price < 0:
            raise ValueError(f"Unresolved item {sheet}!{name_col}{row}:{price_col}{row}")
        items.append(dict(service_type=service, category=category, name=name,
                          price=price, billing_period=period, unit_quantity=units,
                          active=True, sort_order=len(items), source_file=path.name,
                          source_sheet=sheet, source_reference=f"{name_col}{row}:{price_col}{row}"))

    for row in range(2, 61):
        if row <= 6 or row in (57, 60):
            category = "CPU"
        elif row <= 10:
            category = "RAM"
        elif row <= 27:
            category = "Storage"
        elif row <= 31:
            category = "License"
        elif row <= 40:
            category = "Setup"
        elif row in (43, 56):
            category = "Hardware"
        elif row == 54:
            category = "Power"
        elif row == 55:
            category = "Colocation"
        else:
            category = "Network"
        period = "annual" if row == 30 else "one_time" if 32 <= row <= 40 else "monthly"
        add("Dedicated Server", row, "B", "C", "Dedicated Server", category, period)

    for row in range(2, 7):
        add("VPS", row, "A", "B", "VPS", "CPU" if row == 2 else "RAM" if row == 3 else "Storage", units=True)
    seen = set()
    for row in range(14, 40):
        name = workbook["VPS"][f"A{row}"].value
        if name in seen:
            issues.append(f"VPS!A{row}:B{row}: conflicting duplicate {name!r}; ignored, matching VLOOKUP's first exact match.")
            continue
        seen.add(name)
        add("VPS", row, "A", "B", "VPS", "Support" if row >= 36 else "Setup", "monthly" if row >= 36 else "one_time")
    for row in range(4, 9):
        add("Network", row, "B", "C", "Network", "Dedicated Bandwidth/IP Transit")
    for row in range(4, 8):
        add("Network", row, "E", "F", "Network", "Telkom Neucentrix Local/Eyeball Content")
    for row in range(2, 5):
        add("Hosting Custom", row, "A", "B", "Hosting Custom", "CPU" if row == 2 else "RAM" if row == 3 else "Storage", units=True)

    return workbook, dict(source_file=path.name, source_sha256=hashlib.sha256(path.read_bytes()).hexdigest(),
                         tax_rate=0.11, tax_source="Dedicated Server!F22; VPS!D8; Hosting Custom!D6", items=items), issues


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("workbook", type=Path)
    parser.add_argument("--output", type=Path, default=Path("config/seeds/configurator-items.json"))
    parser.add_argument("--report", type=Path, default=Path("docs/CONFIGURATOR_SHEET_EXTRACTION.md"))
    args = parser.parse_args()
    workbook, dataset, issues = extract(args.workbook)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(dataset, ensure_ascii=False, indent=2) + "\n")
    lines = ["# Configurator Sheet Extraction", "", f"Source: `{dataset['source_file']}`", "",
             f"SHA-256: `{dataset['source_sha256']}`", "", "## Worksheets", "",
             "All worksheets were scanned for values, formulas, errors, and validation lists. Dimensions include formatted blank rows.", "",
             "| Sheet | Dimensions | Nonempty cells | Formulas | Dropdowns |", "| --- | --- | ---: | ---: | ---: |"]
    for sheet in workbook:
        cells = [c for row in sheet for c in row if c.value is not None]
        lines.append(f"| {sheet.title} | {sheet.max_row} x {sheet.max_column} | {len(cells)} | {sum(c.data_type == 'f' for c in cells)} | {len(sheet.data_validations.dataValidation)} |")
    lines += ["", "## Imported Ranges", "",
              "Dedicated Server B2:C60: complete rental master, preserving every name and numeric value. CPU categorization includes B57 and B60; B11 is Storage despite the delayed Storage heading at A12. No disk-string parsing.", "",
              "VPS A2:B6: per-core/per-GB resource rates; A14:C39: all distinct add-ons, with MRC/NRC billing from column C. Duplicate names use the first row, as Excel VLOOKUP does.", "",
              "Network B4:C8 and E4:F7: both tabular bandwidth lists. Hosting Custom A2:B4: per-core/per-GB rates.", "",
              "## Calculations and Ambiguities", "",
              "- Dedicated Server E2:E19 uses a mixed validation list and VLOOKUP into B2:C115. F21 only sums F2:F14, accidentally excluding later selections. TRACS sums every selected line, then multiplies by nodes, then applies the workbook's 11% PPN.",
              "- The validation includes duplicate Default Setup and a None sentinel without a price. A blank selection replaces None; no invented zero-price item is imported. B61:C115 is blank.",
              "- Plesk says Wajib per Tahun despite the Monthly Price column heading: stored as annual, never divided by 12. Setup DS rows are one-time charges based on the Setup category; review this interpretation before production use.",
              "- Fractional prices (e.g. C44:C50) are preserved as numeric doubles, not silently rounded during import. Currency display and payable totals round to two decimals; subtotals sum source precision before rounding.",
              "- Original case, internal spaces, and trailing spaces are retained. Labels such as 2 x 4TB SSD and 2 x  7.86TB Nvme U2 are not corrected or reinterpreted.",
              "- VPS A6 says Per 50GB 200K but B6 and D6 calculate 3000 per GB. Imported numeric lookup rate is 3000; the contradictory wording is preserved for review.",
              "- VPS additional-services heading says Monthly, but C14:C35 explicitly says NRC. The row-level billing period takes precedence. Older CBT examples use 10% while the main calculators use 11%; CBT examples are excluded."]
    lines += [f"- {issue}" for issue in issues]
    lines += ["", "## Intentionally Excluded", "",
              "- Dedicated Server K:L: physical-purchase cost master, not rental prices; N:T: client-specific margin and private-cloud scratch calculations.",
              "- Harga Satuan DS: CAPEX/depreciation estimates, not the selectable Sales rental master. Duplicate disk 7.68 TB NVME at A14:A15 has different costs; K33 contains an unusual range addition; D51 double-counts D47:D49. These do not drive the calculator.",
              "- Colocation DCI, Colocation Bogor, Colocatio DRBBDDC, Colocation IDC, HARGA MODAL COLO: competing historical/current cost, resale, setup and deposit models. DCI B143 says Full Rack 10U; IDC B32 says 12A but D32 divides by 9; Bogor includes a mandatory three-month deposit and multiple newer price areas. No authoritative complete billing model selected, so no standalone colocation calculator is enabled. The DS Colo 1U master remains available.",
              "- Colocation BALI and Perpetual License Windows: empty or heading-only.",
              "- Hosting Custom A12:B14: explicitly estimated Bali pricing, excluded pending confirmation.",
              "- Network I4:I19: prose-only location-specific add-ons, not a consistent lookup table; deferred rather than inferring billing units.",
              "- Update PL Stormwall: foreign-currency partner/client tables, exchange-rate and formatted-text prices including #VALUE! errors; separate pricing model.",
              "- PAKET WHM CPANEL, VPS Reseller, Pricelist Console, Rekap Produk, Server Fisik, Nextcloud, Zimbra NE, Lisensi cpanel, Gsuite: packaged, reseller, annual-license or physical-sale price models outside the four verified simple calculators.",
              "- Awanio CEP, Interbio, smartfren, Lisensi OS: scratch calculations, customer-specific quotes, specifications or invoice records; not shared master prices.", "",
              "## Item Counts", "", "| Service | Category | Items |", "| --- | --- | ---: |"]
    for (service, category), count in Counter((i['service_type'], i['category']) for i in dataset['items']).items():
        lines.append(f"| {service} | {category} | {count} |")
    lines += ["", "## Extracted Values", "", "| Service | Category | Exact Excel Item | Excel Price | Billing | Source |", "| --- | --- | --- | ---: | --- | --- |"]
    for item in dataset['items']:
        lines.append(f"| {item['service_type']} | {item['category']} | `{item['name']}` | {item['price']} | {item['billing_period']} | {item['source_sheet']}!{item['source_reference']} |")
    args.report.write_text("\n".join(lines) + "\n")
    print(f"Extracted {len(dataset['items'])} items from {len(workbook.sheetnames)} scanned sheets.")


if __name__ == "__main__":
    main()
