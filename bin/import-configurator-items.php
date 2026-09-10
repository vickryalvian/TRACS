<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../modules/infrastructure-configurator/master.php';
$options = getopt('', ['apply', 'migrate', 'verify', 'dataset:', 'report:']);
$path = $options['dataset'] ?? __DIR__ . '/../config/seeds/configurator-items.json';
try {
    $dataset = json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    $keys = [];
    foreach ($dataset['items'] as $item) {
        tracs_configurator_validate_item($item);
        $key = hash('sha256', $item['source_file'] . "\n" . $item['source_sheet'] . "\n" . $item['source_reference']);
        if (isset($keys[$key])) throw new RuntimeException('Duplicate source reference.');
        $keys[$key] = $item;
    }
    $tax = $dataset['tax_rate'] ?? null;
    if (!is_numeric($tax) || $tax < 0 || $tax > 1) throw new RuntimeException('Invalid source tax.');
    if (!isset($options['apply']) && !isset($options['verify'])) {
        echo 'Validated ' . count($keys) . " items. Dry run: no database connection or writes.\n";
        echo "Use --apply --migrate for first initialization; --apply preserves existing items; --verify compares database values.\n";
        exit;
    }
    require __DIR__ . '/../config/database.php';
    if (isset($options['migrate']) && isset($options['apply'])) {
        $sql = file_get_contents(__DIR__ . '/../config/migrations/2026_09_10_configurator_master.sql');
        $conn->multi_query($sql);
        do { if ($result = $conn->store_result()) $result->free(); } while ($conn->more_results() && $conn->next_result());
    }
    $inserted = 0;
    if (isset($options['apply'])) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO tracs_configurator_settings (id,tax_rate) VALUES (1,?) ON DUPLICATE KEY UPDATE id=id');
            $stmt->bind_param('d', $tax);
            $stmt->execute();
            $stmt->close();
            $stmt = $conn->prepare('INSERT INTO tracs_configurator_items (service_type,category,name,price,billing_period,unit_quantity,active,sort_order,source_file,source_sheet,source_reference,source_key) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE source_key=source_key');
            foreach ($keys as $key => $item) {
                $units = (int)$item['unit_quantity'];
                $active = (int)$item['active'];
                $stmt->bind_param('sssdsiiissss', $item['service_type'], $item['category'], $item['name'], $item['price'], $item['billing_period'], $units, $active, $item['sort_order'], $item['source_file'], $item['source_sheet'], $item['source_reference'], $key);
                $stmt->execute();
                $inserted += $stmt->affected_rows === 1 ? 1 : 0;
            }
            $stmt->close();
            $conn->commit();
        } catch (Throwable $e) { $conn->rollback(); throw $e; }
    }
    $rows = $conn->query('SELECT * FROM tracs_configurator_items WHERE source_key IS NOT NULL')->fetch_all(MYSQLI_ASSOC);
    $indexed = array_column($rows, null, 'source_key');
    $report = ["# Configurator Database Verification", "", "Source SHA-256: `" . $dataset['source_sha256'] . "`", "", '| Category | Excel Item | Excel Price | Imported Item | Imported Price | Status |', '| --- | --- | ---: | --- | ---: | --- |'];
    $mismatch = 0;
    foreach ($keys as $key => $item) {
        $row = $indexed[$key] ?? null;
        $matches = $row !== null;
        foreach (['name', 'service_type', 'category', 'billing_period'] as $field) $matches = $matches && $row[$field] === $item[$field];
        $matches = $matches && (float)$row['price'] === (float)$item['price'] && (bool)$row['active'] === $item['active'] && (bool)$row['unit_quantity'] === $item['unit_quantity'];
        if (!$matches) $mismatch++;
        $report[] = '| ' . $item['category'] . ' | `' . $item['name'] . '` | ' . $item['price'] . ' | `' . ($row['name'] ?? 'MISSING') . '` | ' . ($row['price'] ?? '-') . ' | ' . ($matches ? 'MATCH' : 'DIFFERENT') . ' |';
    }
    $reportPath = $options['report'] ?? __DIR__ . '/../docs/CONFIGURATOR_DATA_VERIFICATION.md';
    file_put_contents($reportPath, implode("\n", $report) . "\n");
    echo "Inserted $inserted items; " . count($keys) . " compared; $mismatch differences. Report: $reportPath\n";
    exit($mismatch ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Configurator import failed: ' . $e->getMessage() . "\n");
    exit(1);
}
