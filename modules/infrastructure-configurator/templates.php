<?php
require_once __DIR__ . '/master.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_configurator_templates(mysqli $conn, int $userId): array {
    $stmt = $conn->prepare('SELECT id, name, configuration FROM tracs_configurator_templates WHERE user_id=? ORDER BY name, id');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        $row['id'] = (int)$row['id'];
        $row['configuration'] = json_decode($row['configuration'], true, 32, JSON_THROW_ON_ERROR);
    }
    return $rows;
}

function tracs_configurator_save_template(mysqli $conn, int $userId, array $input): array {
    $name = $input['name'] ?? null;
    $config = $input['configuration'] ?? null;
    if ($userId <= 0 || !is_string($name) || trim($name) === '' || mb_strlen($name) > 150 || !is_array($config)) throw new InvalidArgumentException('Enter a template name (up to 150 characters).');
    $name = trim($name);
    tracs_configurator_calculate(tracs_configurator_catalog($conn), $config);
    if (empty($config['lines'])) throw new InvalidArgumentException('Select at least one item before saving a template.');
    $config = array_intersect_key($config, array_flip(['service_type', 'billing_period', 'nodes', 'margin_mode', 'margin_value', 'lines']));
    $config['lines'] = array_map(fn($line) => array_intersect_key($line, array_flip(['id', 'custom', 'name', 'category', 'quantity', 'override_price'])), $config['lines']);
    $json = json_encode($config, JSON_THROW_ON_ERROR);
    $stmt = $conn->prepare('INSERT INTO tracs_configurator_templates (user_id,name,configuration) VALUES (?,?,?)');
    $stmt->bind_param('iss', $userId, $name, $json);
    try { $stmt->execute(); }
    catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) throw new InvalidArgumentException('A template with this name already exists. Choose a different name.');
        throw $e;
    } finally { $stmt->close(); }
    return tracs_configurator_templates($conn, $userId);
}
