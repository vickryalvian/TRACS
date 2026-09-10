<?php
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../modules/infrastructure-configurator/templates.php';
$conn->begin_transaction();
try {
    $catalog = tracs_configurator_catalog($conn);
    $item = $catalog['items'][0];
    $config = ['service_type' => $item['service_type'], 'billing_period' => $item['billing_period'], 'nodes' => 2, 'margin_mode' => 'amount', 'margin_value' => 100000, 'lines' => [
        ['id' => $item['id'], 'category' => $item['category'], 'quantity' => 1, 'override_price' => 123000],
        ['custom' => true, 'name' => 'Support', 'category' => 'Custom', 'quantity' => 2, 'override_price' => 10000],
    ]];
    $name = 'Template test ' . bin2hex(random_bytes(8));
    $rows = tracs_configurator_save_template($conn, 2147483646, ['name' => $name, 'configuration' => $config]);
    $saved = array_values(array_filter($rows, fn($row) => $row['name'] === $name))[0];
    if ($saved['configuration'] != $config) throw new RuntimeException('Template round trip differs.');
    foreach (tracs_configurator_templates($conn, 2147483645) as $row) {
        if ($row['id'] === $saved['id']) throw new RuntimeException('Template leaked to another owner.');
    }
    try {
        tracs_configurator_save_template($conn, 2147483646, ['name' => $name, 'configuration' => $config]);
        throw new RuntimeException('Duplicate template accepted.');
    } catch (InvalidArgumentException $e) {}
    echo "Template persistence, custom/override values, owner isolation, and duplicate protection passed.\n";
} finally { $conn->rollback(); }
