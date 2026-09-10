<?php
require_once __DIR__ . '/../modules/infrastructure-configurator/master.php';
function sales_expect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function sales_reject(callable $fn): void {
    try { $fn(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected validation failure.');
}
$seed = json_decode(file_get_contents(__DIR__ . '/../config/seeds/configurator-items.json'), true, 64, JSON_THROW_ON_ERROR);
$catalog = ['items' => [], 'tax_rate' => $seed['tax_rate']];
foreach ($seed['items'] as $index => $item) {
    tracs_configurator_validate_item($item);
    $item['id'] = $index + 1;
    $catalog['items'][] = $item;
}
$line = function (string $reference) use ($catalog): array {
    foreach ($catalog['items'] as $item) {
        if ($item['service_type'] === 'Dedicated Server' && $item['source_reference'] === $reference) return ['id' => $item['id'], 'category' => $item['category'], 'quantity' => 1];
    }
    throw new RuntimeException('Missing fixture.');
};
$input = ['service_type' => 'Dedicated Server', 'billing_period' => 'monthly', 'nodes' => 1, 'lines' => [$line('B3:C3'), $line('B8:C8'), $line('B15:C15'), $line('B12:C12')]];
$default = tracs_configurator_calculate($catalog, $input);
sales_expect($default['margin'] === 3180000.0 && $default['grand_total'] === 15295800.0, 'Default 30 percent margin failed.');
$input['margin_mode'] = 'percentage'; $input['margin_value'] = 0;
$fixed = $input; $fixed['margin_mode'] = 'amount'; $fixed['margin_value'] = 1000000;
sales_expect(tracs_configurator_calculate($catalog, $fixed)['grand_total'] === 12876000.0, 'Fixed margin failed.');
$fixed['nodes'] = 2;
sales_expect(tracs_configurator_calculate($catalog, $fixed)['margin'] === 1000000.0, 'Fixed margin must apply once to the configuration.');
$override = $input; $override['lines'][0]['override_price'] = 0;
sales_expect(tracs_configurator_calculate($catalog, $override)['subtotal_per_node'] === 6900000.0, 'Zero override failed.');
foreach ([-1, '', 'invalid', INF, 1e13] as $price) {
    $override['lines'][0]['override_price'] = $price;
    sales_reject(fn() => tracs_configurator_calculate($catalog, $override));
}
$badMargin = $input; $badMargin['margin_value'] = -1;
sales_reject(fn() => tracs_configurator_calculate($catalog, $badMargin));
$result = tracs_configurator_calculate($catalog, $input);
sales_expect($result['subtotal_per_node'] === 10600000.0 && $result['tax'] === 1166000.0 && $result['grand_total'] === 11766000.0, 'Workbook example failed.');
$input['nodes'] = 3;
sales_expect(tracs_configurator_calculate($catalog, $input)['grand_total'] === 35298000.0, 'Node multiplication failed.');
array_pop($input['lines']);
sales_expect(tracs_configurator_calculate($catalog, $input)['subtotal_per_node'] === 9100000.0, 'Remove storage failed.');
$input['lines'][0] = $line('B2:C2');
sales_expect(tracs_configurator_calculate($catalog, $input)['subtotal_per_node'] === 8100000.0, 'Change CPU failed.');
$inactive = $catalog;
$inactive['items'][$input['lines'][0]['id'] - 1]['active'] = false;
sales_reject(fn() => tracs_configurator_calculate($inactive, $input));
$bad = $input; $bad['lines'][0]['category'] = 'RAM';
sales_reject(fn() => tracs_configurator_calculate($catalog, $bad));
foreach ([0, -1, 1.5, 10001, 'invalid'] as $nodes) {
    $bad = $input; $bad['nodes'] = $nodes;
    sales_reject(fn() => tracs_configurator_calculate($catalog, $bad));
}
$bad = $input; $bad['lines'][0]['id'] = 999999;
sales_reject(fn() => tracs_configurator_calculate($catalog, $bad));
$bad = $input; $bad['lines'][0]['quantity'] = 2;
sales_reject(fn() => tracs_configurator_calculate($catalog, $bad));
$bad = $input; $bad['billing_period'] = 'annual';
sales_reject(fn() => tracs_configurator_calculate($catalog, $bad));
$empty = $input; $empty['lines'] = [];
sales_expect(tracs_configurator_calculate($catalog, $empty)['grand_total'] === 0.0, 'Empty total failed.');
$noTax = $catalog; $noTax['tax_rate'] = 0;
sales_expect(tracs_configurator_calculate($noTax, $input)['tax'] === 0.0, 'Configurable tax failed.');
$vps = array_values(array_filter($catalog['items'], fn($item) => $item['service_type'] === 'VPS' && $item['category'] === 'CPU'))[0];
$vm = ['service_type' => 'VPS', 'billing_period' => 'monthly', 'nodes' => 2, 'margin_value' => 0, 'lines' => [['id' => $vps['id'], 'category' => 'CPU', 'quantity' => 8]]];
sales_expect(tracs_configurator_calculate($catalog, $vm)['grand_total'] === 888000.0, 'VPS per-unit calculation failed.');
$vm['lines'][0]['override_price'] = 40000;
sales_expect(tracs_configurator_calculate($catalog, $vm)['grand_total'] === 710400.0, 'VPS override must multiply by units and nodes.');
echo "Sales calculator validation and workbook calculations passed.\n";

if (in_array('--database', $argv, true)) {
    require __DIR__ . '/../config/database.php';
    $conn->begin_transaction();
    try {
        $before = tracs_configurator_catalog($conn, true);
        sales_expect(count($before['items']) >= 99, 'Missing imported records.');
        $item = $before['items'][0];
        $item['price'] = 1234567;
        $item['active'] = false;
        tracs_configurator_save_item($conn, $item);
        $active = tracs_configurator_catalog($conn);
        sales_expect(!in_array($item['id'], array_column($active['items'], 'id')), 'Inactive item leaked.');
        sales_reject(fn() => tracs_configurator_save_item($conn, $item));
        $fresh = array_column(tracs_configurator_catalog($conn, true)['items'], null, 'id')[$item['id']];
        sales_expect($fresh['price'] === 1234567.0 && $fresh['revision'] === $item['revision'] + 1, 'Save/reload failed.');
        $fresh['active'] = true;
        tracs_configurator_save_item($conn, $fresh);
        $fresh['id'] = 0;
        $fresh['name'] = 'Temporary test item';
        tracs_configurator_save_item($conn, $fresh);
        sales_expect(count(tracs_configurator_catalog($conn, true)['items']) === count($before['items']) + 1, 'Create failed.');
    } finally { $conn->rollback(); }
    echo "Database create, edit, inactive filtering, optimistic concurrency, and reload checks passed; test changes rolled back.\n";
}
