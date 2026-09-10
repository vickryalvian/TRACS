<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_configurator_catalog(mysqli $conn, bool $includeInactive = false): array {
    $settings = $conn->query('SELECT tax_rate, revision FROM tracs_configurator_settings WHERE id = 1')->fetch_assoc();
    if (!$settings) {
        throw new RuntimeException('Configurator master data has not been initialized.');
    }
    $rows = $conn->query('SELECT * FROM tracs_configurator_items' . ($includeInactive ? '' : ' WHERE active = 1') . ' ORDER BY sort_order, id')->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$row) {
        foreach (['id', 'sort_order', 'revision'] as $key) $row[$key] = (int)$row[$key];
        $row['price'] = (float)$row['price'];
        $row['active'] = (bool)$row['active'];
        $row['unit_quantity'] = (bool)$row['unit_quantity'];
    }
    return ['items' => $rows, 'tax_rate' => (float)$settings['tax_rate'], 'tax_revision' => (int)$settings['revision']];
}

function tracs_configurator_validate_item(array $input): array {
    $item = [];
    foreach (['service_type' => 100, 'category' => 100, 'name' => 500] as $key => $max) {
        $value = $input[$key] ?? null;
        if (!is_string($value) || trim($value) === '' || mb_strlen($value) > $max) throw new InvalidArgumentException('Enter a valid ' . str_replace('_', ' ', $key) . '.');
        $item[$key] = $value;
    }
    $price = $input['price'] ?? null;
    if (!is_numeric($price) || !is_finite((float)$price) || $price < 0 || $price > 1e12) throw new InvalidArgumentException('Price must be between 0 and 1,000,000,000,000.');
    $item['price'] = (float)$price;
    $item['billing_period'] = $input['billing_period'] ?? '';
    if (!in_array($item['billing_period'], ['monthly', 'annual', 'one_time'], true)) throw new InvalidArgumentException('Choose a valid billing period.');
    $order = filter_var($input['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    if ($order === false || abs($order) > 1000000) throw new InvalidArgumentException('Invalid sort order.');
    $item['sort_order'] = $order;
    foreach (['active', 'unit_quantity'] as $key) $item[$key] = empty($input[$key]) ? 0 : 1;
    $description = $input['description'] ?? '';
    if (!is_string($description) || strlen($description) > 10000) throw new InvalidArgumentException('Description is too long.');
    $item['description'] = $description;
    return $item;
}

function tracs_configurator_save_item(mysqli $conn, array $input): void {
    $item = tracs_configurator_validate_item($input);
    $id = filter_var($input['id'] ?? 0, FILTER_VALIDATE_INT);
    $revision = filter_var($input['revision'] ?? 0, FILTER_VALIDATE_INT);
    if ($id === false || $id < 0 || $revision === false) throw new InvalidArgumentException('Invalid item.');
    $values = [$item['service_type'], $item['category'], $item['name'], $item['description'], $item['price'], $item['billing_period'], $item['active'], $item['unit_quantity'], $item['sort_order']];
    if ($id) {
        $stmt = $conn->prepare('UPDATE tracs_configurator_items SET service_type=?, category=?, name=?, description=?, price=?, billing_period=?, active=?, unit_quantity=?, sort_order=?, revision=revision+1 WHERE id=? AND revision=?');
        $values[] = $id;
        $values[] = $revision;
        $stmt->bind_param('ssssdsiiiii', ...$values);
    } else {
        $stmt = $conn->prepare('INSERT INTO tracs_configurator_items (service_type,category,name,description,price,billing_period,active,unit_quantity,sort_order) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->bind_param('ssssdsiii', ...$values);
    }
    $stmt->execute();
    if ($stmt->affected_rows !== 1) throw new InvalidArgumentException('This item changed in another session. Reload Master Data before saving.');
    $stmt->close();
}

function tracs_configurator_calculate(array $catalog, array $input): array {
    $nodes = filter_var($input['nodes'] ?? null, FILTER_VALIDATE_INT);
    $lines = $input['lines'] ?? null;
    if ($nodes === false || $nodes < 1 || $nodes > 10000 || !is_array($lines) || count($lines) > 100) throw new InvalidArgumentException('Use 1 to 10,000 nodes and at most 100 items.');
    $period = $input['billing_period'] ?? '';
    $service = $input['service_type'] ?? '';
    $indexed = array_column($catalog['items'], null, 'id');
    if (!array_filter($catalog['items'], fn($item) => $item['active'] && $item['service_type'] === $service && $item['billing_period'] === $period)) throw new InvalidArgumentException('Choose an available service and billing period.');
    $subtotal = 0.0;
    foreach ($lines as $line) {
        if (!is_array($line)) throw new InvalidArgumentException('Invalid line item.');
        $id = filter_var($line['id'] ?? null, FILTER_VALIDATE_INT);
        if (($line['custom'] ?? false) === true) {
            $item = tracs_configurator_validate_item([
                'name' => $line['name'] ?? '', 'category' => $line['category'] ?? '',
                'service_type' => $service, 'billing_period' => $period,
                'price' => $line['override_price'] ?? '', 'active' => true, 'unit_quantity' => true,
            ]);
        } else {
            $item = $id === false ? null : ($indexed[$id] ?? null);
        }
        if (!$item || !$item['active'] || $item['service_type'] !== $service || $item['billing_period'] !== $period || $item['category'] !== ($line['category'] ?? '')) throw new InvalidArgumentException('An item is no longer available. Refresh prices and select it again.');
        $quantity = filter_var($line['quantity'] ?? 1, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $quantity > 100000 || (!$item['unit_quantity'] && $quantity !== 1)) throw new InvalidArgumentException('Invalid item quantity.');
        $price = $line['override_price'] ?? $item['price'];
        if (!is_numeric($price) || !is_finite((float)$price) || $price < 0 || $price > 1e12) throw new InvalidArgumentException('Override price must be between 0 and 1,000,000,000,000.');
        $subtotal += (float)$price * $quantity;
    }
    $base = round($subtotal * $nodes, 2);
    $mode = $input['margin_mode'] ?? 'percentage';
    $value = $input['margin_value'] ?? 30;
    if (!in_array($mode, ['percentage', 'amount'], true) || !is_numeric($value) || !is_finite((float)$value) || $value < 0 || $value > ($mode === 'percentage' ? 1000 : 1e12)) throw new InvalidArgumentException('Enter a valid nonnegative margin.');
    $margin = count($lines) ? round($mode === 'percentage' ? $base * (float)$value / 100 : (float)$value, 2) : 0.0;
    $beforeTax = round($base + $margin, 2);
    if (!is_finite($beforeTax) || $beforeTax > 1e14) throw new InvalidArgumentException('Total exceeds the supported amount.');
    $tax = round($beforeTax * $catalog['tax_rate'], 2);
    return ['subtotal_per_node' => round($subtotal, 2), 'subtotal_before_margin' => $base, 'margin' => $margin, 'subtotal_before_tax' => $beforeTax, 'tax' => $tax, 'grand_total' => round($beforeTax + $tax, 2)];
}
