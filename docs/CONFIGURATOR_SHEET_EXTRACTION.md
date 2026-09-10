# Configurator Sheet Extraction

Source: `Hitungan Sales.xlsx`

SHA-256: `6e17c014513e23b271b02d8acc87fbc6927184bc11419aeead81c35224721698`

## Worksheets

All worksheets were scanned for values, formulas, errors, and validation lists. Dimensions include formatted blank rows.

| Sheet | Dimensions | Nonempty cells | Formulas | Dropdowns |
| --- | --- | ---: | ---: | ---: |
| Update PL Stormwall | 1000 x 26 | 2101 | 7 | 0 |
| VPS | 314 x 29 | 205 | 44 | 1 |
| PAKET WHM CPANEL | 1001 x 12 | 58 | 28 | 0 |
| VPS Reseller | 1000 x 12 | 156 | 48 | 0 |
| Pricelist Console | 18 x 15 | 106 | 5 | 4 |
| Rekap Produk | 973 x 38 | 1350 | 23 | 1 |
| HARGA MODAL COLO | 1001 x 29 | 72 | 0 | 0 |
| Colocation DCI | 977 x 24 | 199 | 53 | 0 |
| Colocatio DRBBDDC | 74 x 18 | 113 | 7 | 0 |
| Colocation Bogor | 1000 x 19 | 241 | 65 | 0 |
| Colocation IDC | 38 x 5 | 24 | 8 | 0 |
| Colocation BALI | 1 x 1 | 0 | 0 | 0 |
| Network | 33 x 10 | 42 | 0 | 0 |
| Hosting Custom | 18 x 9 | 53 | 13 | 0 |
| Dedicated Server | 993 x 21 | 372 | 100 | 2 |
| Harga Satuan DS | 1011 x 26 | 541 | 230 | 0 |
| Server Fisik | 1000 x 4 | 42 | 2 | 0 |
| Awanio CEP | 26 x 6 | 5 | 3 | 0 |
| Nextcloud | 18 x 9 | 22 | 1 | 0 |
| Zimbra NE | 994 x 26 | 95 | 17 | 0 |
| Lisensi cpanel | 995 x 5 | 39 | 12 | 0 |
| Gsuite | 1000 x 4 | 32 | 12 | 1 |
| Interbio | 1000 x 29 | 95 | 42 | 0 |
| smartfren | 1001 x 4 | 100 | 0 | 0 |
| Perpetual License Windows | 1 x 1 | 1 | 0 | 0 |
| Lisensi OS | 1001 x 16 | 141 | 6 | 2 |

## Imported Ranges

Dedicated Server B2:C60: complete rental master, preserving every name and numeric value. CPU categorization includes B57 and B60; B11 is Storage despite the delayed Storage heading at A12. No disk-string parsing.

VPS A2:B6: per-core/per-GB resource rates; A14:C39: all distinct add-ons, with MRC/NRC billing from column C. Duplicate names use the first row, as Excel VLOOKUP does.

Network B4:C8 and E4:F7: both tabular bandwidth lists. Hosting Custom A2:B4: per-core/per-GB rates.

## Calculations and Ambiguities

- Dedicated Server E2:E19 uses a mixed validation list and VLOOKUP into B2:C115. F21 only sums F2:F14, accidentally excluding later selections. TRACS sums every selected line, then multiplies by nodes, then applies the workbook's 11% PPN.
- The validation includes duplicate Default Setup and a None sentinel without a price. A blank selection replaces None; no invented zero-price item is imported. B61:C115 is blank.
- Plesk says Wajib per Tahun despite the Monthly Price column heading: stored as annual, never divided by 12. Setup DS rows are one-time charges based on the Setup category; review this interpretation before production use.
- Fractional prices (e.g. C44:C50) are preserved as numeric doubles, not silently rounded during import. Currency display and payable totals round to two decimals; subtotals sum source precision before rounding.
- Original case, internal spaces, and trailing spaces are retained. Labels such as 2 x 4TB SSD and 2 x  7.86TB Nvme U2 are not corrected or reinterpreted.
- VPS A6 says Per 50GB 200K but B6 and D6 calculate 3000 per GB. Imported numeric lookup rate is 3000; the contradictory wording is preserved for review.
- VPS additional-services heading says Monthly, but C14:C35 explicitly says NRC. The row-level billing period takes precedence. Older CBT examples use 10% while the main calculators use 11%; CBT examples are excluded.
- VPS!A31:B31: conflicting duplicate 'Install Imunify'; ignored, matching VLOOKUP's first exact match.
- VPS!A32:B32: conflicting duplicate 'Install WebServer'; ignored, matching VLOOKUP's first exact match.
- VPS!A33:B33: conflicting duplicate 'Install Litespeed'; ignored, matching VLOOKUP's first exact match.

## Intentionally Excluded

- Dedicated Server K:L: physical-purchase cost master, not rental prices; N:T: client-specific margin and private-cloud scratch calculations.
- Harga Satuan DS: CAPEX/depreciation estimates, not the selectable Sales rental master. Duplicate disk 7.68 TB NVME at A14:A15 has different costs; K33 contains an unusual range addition; D51 double-counts D47:D49. These do not drive the calculator.
- Colocation DCI, Colocation Bogor, Colocatio DRBBDDC, Colocation IDC, HARGA MODAL COLO: competing historical/current cost, resale, setup and deposit models. DCI B143 says Full Rack 10U; IDC B32 says 12A but D32 divides by 9; Bogor includes a mandatory three-month deposit and multiple newer price areas. No authoritative complete billing model selected, so no standalone colocation calculator is enabled. The DS Colo 1U master remains available.
- Colocation BALI and Perpetual License Windows: empty or heading-only.
- Hosting Custom A12:B14: explicitly estimated Bali pricing, excluded pending confirmation.
- Network I4:I19: prose-only location-specific add-ons, not a consistent lookup table; deferred rather than inferring billing units.
- Update PL Stormwall: foreign-currency partner/client tables, exchange-rate and formatted-text prices including #VALUE! errors; separate pricing model.
- PAKET WHM CPANEL, VPS Reseller, Pricelist Console, Rekap Produk, Server Fisik, Nextcloud, Zimbra NE, Lisensi cpanel, Gsuite: packaged, reseller, annual-license or physical-sale price models outside the four verified simple calculators.
- Awanio CEP, Interbio, smartfren, Lisensi OS: scratch calculations, customer-specific quotes, specifications or invoice records; not shared master prices.

## Item Counts

| Service | Category | Items |
| --- | --- | ---: |
| Dedicated Server | CPU | 7 |
| Dedicated Server | RAM | 4 |
| Dedicated Server | Storage | 17 |
| Dedicated Server | License | 4 |
| Dedicated Server | Setup | 9 |
| Dedicated Server | Network | 14 |
| Dedicated Server | Hardware | 2 |
| Dedicated Server | Power | 1 |
| Dedicated Server | Colocation | 1 |
| VPS | CPU | 1 |
| VPS | RAM | 1 |
| VPS | Storage | 3 |
| VPS | Setup | 19 |
| VPS | Support | 4 |
| Network | Dedicated Bandwidth/IP Transit | 5 |
| Network | Telkom Neucentrix Local/Eyeball Content | 4 |
| Hosting Custom | CPU | 1 |
| Hosting Custom | RAM | 1 |
| Hosting Custom | Storage | 1 |

## Extracted Values

| Service | Category | Exact Excel Item | Excel Price | Billing | Source |
| --- | --- | --- | ---: | --- | --- |
| Dedicated Server | CPU | `2 x E5 2620 v4 (16 Core 32 Threads)` | 2700000.0 | monthly | Dedicated Server!B2:C2 |
| Dedicated Server | CPU | `2 x E5 2696 v4 (44 Cores 88 Threads) ` | 3700000.0 | monthly | Dedicated Server!B3:C3 |
| Dedicated Server | CPU | `2 x AMD Epyc 7542` | 4800000.0 | monthly | Dedicated Server!B4:C4 |
| Dedicated Server | CPU | `2 x AMD Epyc 7502` | 7000000.0 | monthly | Dedicated Server!B5:C5 |
| Dedicated Server | CPU | `AMD Epyc 7502 server storage` | 7500000.0 | monthly | Dedicated Server!B6:C6 |
| Dedicated Server | RAM | `64 GB` | 1000000.0 | monthly | Dedicated Server!B7:C7 |
| Dedicated Server | RAM | `128 GB` | 1800000.0 | monthly | Dedicated Server!B8:C8 |
| Dedicated Server | RAM | `256 GB` | 2500000.0 | monthly | Dedicated Server!B9:C9 |
| Dedicated Server | RAM | `512 GB` | 4300000.0 | monthly | Dedicated Server!B10:C10 |
| Dedicated Server | Storage | `2 x 480 Gb SSD` | 850000.0 | monthly | Dedicated Server!B11:C11 |
| Dedicated Server | Storage | `2 x 960 GB SSD` | 1500000.0 | monthly | Dedicated Server!B12:C12 |
| Dedicated Server | Storage | `2 x 1 TB SAS` | 1000000.0 | monthly | Dedicated Server!B13:C13 |
| Dedicated Server | Storage | `2 x 2 TB SSD` | 2000000.0 | monthly | Dedicated Server!B14:C14 |
| Dedicated Server | Storage | `2 x 4TB SSD` | 3600000.0 | monthly | Dedicated Server!B15:C15 |
| Dedicated Server | Storage | `2 x 8 TB SSD` | 5460000.0 | monthly | Dedicated Server!B16:C16 |
| Dedicated Server | Storage | `6 x 8 TB SSD` | 11300000.0 | monthly | Dedicated Server!B17:C17 |
| Dedicated Server | Storage | `2 x 12 TB SSD` | 6000000.0 | monthly | Dedicated Server!B18:C18 |
| Dedicated Server | Storage | `2 X 16 TB SSD` | 6200000.0 | monthly | Dedicated Server!B19:C19 |
| Dedicated Server | Storage | `2 x 6 TB SAS` | 4000000.0 | monthly | Dedicated Server!B20:C20 |
| Dedicated Server | Storage | `4 x 6 TB SAS` | 6000000.0 | monthly | Dedicated Server!B21:C21 |
| Dedicated Server | Storage | `6 x 6 TB SAS` | 7500000.0 | monthly | Dedicated Server!B22:C22 |
| Dedicated Server | Storage | `2 x 1 TB NVMe` | 2500000.0 | monthly | Dedicated Server!B23:C23 |
| Dedicated Server | Storage | `2 x 2 TB NVMe` | 3000000.0 | monthly | Dedicated Server!B24:C24 |
| Dedicated Server | Storage | `2 x 3.84 TB NVME` | 3500000.0 | monthly | Dedicated Server!B25:C25 |
| Dedicated Server | Storage | `2 x  7.86TB Nvme U2` | 4300000.0 | monthly | Dedicated Server!B26:C26 |
| Dedicated Server | Storage | `2 x 15.36 TB NVME U2` | 12100000.0 | monthly | Dedicated Server!B27:C27 |
| Dedicated Server | License | `Windows Server License` | 500000.0 | monthly | Dedicated Server!B28:C28 |
| Dedicated Server | License | `SQL Server per 2 core` | 2500000.0 | monthly | Dedicated Server!B29:C29 |
| Dedicated Server | License | `Plesk (Wajib per Tahun)` | 10800000.0 | annual | Dedicated Server!B30:C30 |
| Dedicated Server | License | `cPanel for DS` | 500000.0 | monthly | Dedicated Server!B31:C31 |
| Dedicated Server | Setup | `Default Setup` | 250000.0 | one_time | Dedicated Server!B32:C32 |
| Dedicated Server | Setup | `Install OS Custom` | 250000.0 | one_time | Dedicated Server!B33:C33 |
| Dedicated Server | Setup | `Install WHM Cpanel` | 250000.0 | one_time | Dedicated Server!B34:C34 |
| Dedicated Server | Setup | `Install Imunify` | 250000.0 | one_time | Dedicated Server!B35:C35 |
| Dedicated Server | Setup | `Install WebServer` | 250000.0 | one_time | Dedicated Server!B36:C36 |
| Dedicated Server | Setup | `Install DS OS default` | 3000000.0 | one_time | Dedicated Server!B37:C37 |
| Dedicated Server | Setup | `Install Litespeed` | 250000.0 | one_time | Dedicated Server!B38:C38 |
| Dedicated Server | Setup | `Install Mail Server Private` | 5000000.0 | one_time | Dedicated Server!B39:C39 |
| Dedicated Server | Setup | `Migrasi Email per account` | 150000.0 | one_time | Dedicated Server!B40:C40 |
| Dedicated Server | Network | `NIC 2 x 10G` | 465111.0 | monthly | Dedicated Server!B41:C41 |
| Dedicated Server | Network | `IPMI 1 x 1G` | 350000.0 | monthly | Dedicated Server!B42:C42 |
| Dedicated Server | Hardware | `GPU NVIDIA Tesla T4` | 8000000.0 | monthly | Dedicated Server!B43:C43 |
| Dedicated Server | Network | `switch 48SFP+ 6QSFP28` | 4954444.444444445 | monthly | Dedicated Server!B44:C44 |
| Dedicated Server | Network | `52 x HP X240 3m DAC Cable (SFP+ to SFP+)` | 4388222.222222222 | monthly | Dedicated Server!B45:C45 |
| Dedicated Server | Network | `3 x HP 3m DAC Cable (QSFP+ to QSFP+)  ` | 1152666.6666666665 | monthly | Dedicated Server!B46:C46 |
| Dedicated Server | Network | `3 x HP x120 1G SFP LC SX Transceiver  17jt/pc` | 4307333.333333334 | monthly | Dedicated Server!B47:C47 |
| Dedicated Server | Network | `4 x HPE Networking X130 10G SFP+ LC SR Transceiver` | 5682444.444444444 | monthly | Dedicated Server!B48:C48 |
| Dedicated Server | Network | `4 x Patch Cords OM3 Fiber Optic LC LC Multimode Duplex 2m` | 230533.33333333334 | monthly | Dedicated Server!B49:C49 |
| Dedicated Server | Network | `3 x RJ45-DB9 DCE Female Serial Adapter` | 194133.33333333334 | monthly | Dedicated Server!B50:C50 |
| Dedicated Server | Network | `Switch B 48G 4XG 2QSFP+` | 2950000.0 | monthly | Dedicated Server!B51:C51 |
| Dedicated Server | Network | `2 x HP x120 1G SFP LC SX Transceiver` | 2932222.222222222 | monthly | Dedicated Server!B52:C52 |
| Dedicated Server | Network | `Switch Huawei CE6850-48T6Q-HI` | 12072667.0 | monthly | Dedicated Server!B53:C53 |
| Dedicated Server | Power | `Listrik 1 Ampere` | 1500000.0 | monthly | Dedicated Server!B54:C54 |
| Dedicated Server | Colocation | `Colo 1U` | 500000.0 | monthly | Dedicated Server!B55:C55 |
| Dedicated Server | Hardware | `Server AS-1124US-TNRP (NVME)` | 9241556.0 | monthly | Dedicated Server!B56:C56 |
| Dedicated Server | CPU | `E5 2620 v4 (16 Core 32 Threads)` | 1800000.0 | monthly | Dedicated Server!B57:C57 |
| Dedicated Server | Network | `NIC 2 x 100G` | 2600000.0 | monthly | Dedicated Server!B58:C58 |
| Dedicated Server | Network | `NIC 2 x 40G` | 2000000.0 | monthly | Dedicated Server!B59:C59 |
| Dedicated Server | CPU | `Processor  2 x AMD Epyc 7713 2Ghz` | 9100000.0 | monthly | Dedicated Server!B60:C60 |
| VPS | CPU | `CPU (Core)` | 50000.0 | monthly | VPS!A2:B2 |
| VPS | RAM | `RAM (GB)` | 50000.0 | monthly | VPS!A3:B3 |
| VPS | Storage | `Storage (GB) (SSD)` | 1500.0 | monthly | VPS!A4:B4 |
| VPS | Storage | `Storage (GB) (HDD)` | 2000.0 | monthly | VPS!A5:B5 |
| VPS | Storage | `NVME (Additional : Per 50GB 200K)` | 3000.0 | monthly | VPS!A6:B6 |
| VPS | Setup | `Install WHM Cpanel` | 370000.0 | one_time | VPS!A14:B14 |
| VPS | Setup | `Install Imunify` | 250000.0 | one_time | VPS!A15:B15 |
| VPS | Setup | `Install WebServer` | 250000.0 | one_time | VPS!A16:B16 |
| VPS | Setup | `Install Litespeed` | 250000.0 | one_time | VPS!A17:B17 |
| VPS | Setup | `Basic Config` | 100000.0 | one_time | VPS!A18:B18 |
| VPS | Setup | `Basic Security` | 100000.0 | one_time | VPS!A19:B19 |
| VPS | Setup | `Install Firewall` | 100000.0 | one_time | VPS!A20:B20 |
| VPS | Setup | `Patching` | 100000.0 | one_time | VPS!A21:B21 |
| VPS | Setup | `Troubleshooting` | 200000.0 | one_time | VPS!A22:B22 |
| VPS | Setup | `Install App Catalog` | 200000.0 | one_time | VPS!A23:B23 |
| VPS | Setup | `Install Proxmox` | 450000.0 | one_time | VPS!A24:B24 |
| VPS | Setup | `Install Solusi VM` | 450000.0 | one_time | VPS!A25:B25 |
| VPS | Setup | `Install Virtualizor` | 450000.0 | one_time | VPS!A26:B26 |
| VPS | Setup | `Install VM Ware` | 450000.0 | one_time | VPS!A27:B27 |
| VPS | Setup | `Install Zimbra` | 450000.0 | one_time | VPS!A28:B28 |
| VPS | Setup | `Install NextCloud` | 450000.0 | one_time | VPS!A29:B29 |
| VPS | Setup | `Install Jetbackup` | 150000.0 | one_time | VPS!A30:B30 |
| VPS | Setup | `Install CloudLinux` | 150000.0 | one_time | VPS!A34:B34 |
| VPS | Setup | `Install Softaculous` | 150000.0 | one_time | VPS!A35:B35 |
| VPS | Support | `Tiket < 35 Menit` | 250000.0 | monthly | VPS!A36:B36 |
| VPS | Support | `Tiket < 15 Menit` | 500000.0 | monthly | VPS!A37:B37 |
| VPS | Support | `Grup Chat / WA Personal Tiketing` | 1000000.0 | monthly | VPS!A38:B38 |
| VPS | Support | `Grup Chat / Online Meeting` | 2000000.0 | monthly | VPS!A39:B39 |
| Network | Dedicated Bandwidth/IP Transit | `100 Mbps` | 5000000.0 | monthly | Network!B4:C4 |
| Network | Dedicated Bandwidth/IP Transit | `200 Mbps` | 9000000.0 | monthly | Network!B5:C5 |
| Network | Dedicated Bandwidth/IP Transit | `500 Mbps` | 15000000.0 | monthly | Network!B6:C6 |
| Network | Dedicated Bandwidth/IP Transit | `1 Gbps` | 20000000.0 | monthly | Network!B7:C7 |
| Network | Dedicated Bandwidth/IP Transit | `*internet 10gbps lokal inter` | 150000000.0 | monthly | Network!B8:C8 |
| Network | Telkom Neucentrix Local/Eyeball Content | `100 Mbps` | 4000000.0 | monthly | Network!E4:F4 |
| Network | Telkom Neucentrix Local/Eyeball Content | `200 Mbps` | 7000000.0 | monthly | Network!E5:F5 |
| Network | Telkom Neucentrix Local/Eyeball Content | `500 Mbps` | 13000000.0 | monthly | Network!E6:F6 |
| Network | Telkom Neucentrix Local/Eyeball Content | `1 Gbps` | 17000000.0 | monthly | Network!E7:F7 |
| Hosting Custom | CPU | `CPU (Core)` | 12000.0 | monthly | Hosting Custom!A2:B2 |
| Hosting Custom | RAM | `RAM (GB)` | 12000.0 | monthly | Hosting Custom!A3:B3 |
| Hosting Custom | Storage | `Storage (GB)` | 5000.0 | monthly | Hosting Custom!A4:B4 |
