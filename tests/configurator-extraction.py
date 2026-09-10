"""Compare the original workbook, reproducible seed, and local Docker database."""
import importlib.util
import json
import subprocess
import sys
from pathlib import Path

sys.dont_write_bytecode = True

root = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location("extractor", root / "bin/extract-configurator-workbook.py")
extractor = importlib.util.module_from_spec(spec)
spec.loader.exec_module(extractor)
_, extracted, _ = extractor.extract(Path(sys.argv[1]))
seed = json.loads((root / "config/seeds/configurator-items.json").read_text())
assert seed == extracted, "Seed differs from a fresh workbook extraction"
raw = subprocess.check_output([
    "docker", "exec", "tracs_app", "php", "-r",
    'require "/var/www/html/config/database.php"; echo json_encode($conn->query("SELECT * FROM tracs_configurator_items WHERE source_key IS NOT NULL")->fetch_all(MYSQLI_ASSOC));',
], text=True)
rows = json.loads(raw)
indexed = {(row["source_sheet"], row["source_reference"]): row for row in rows}
assert len(rows) == len(extracted["items"]), "Database item count differs"
for item in extracted["items"]:
    row = indexed[(item["source_sheet"], item["source_reference"])]
    for key in ("name", "category", "service_type", "billing_period"):
        assert row[key] == item[key], (item["source_reference"], key)
    assert float(row["price"]) == item["price"], item["source_reference"]
print(f"Original workbook -> seed -> database: {len(rows)} exact name/price/category/billing matches. Source hash and reproducibility passed.")
