<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../core/infrastructure_servers.php';

api_require_permissions(['dashboard.view']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    fail('GET required.', 405);
}

$code = strtoupper(trim((string)($_GET['code'] ?? '')));
if ($code === '' || mb_strlen($code) > 8) {
    fail('Invalid server code.', 422);
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 60;
$rows = tracs_infra_server_history($conn, $code, $limit);
$data = array_map(static function (array $row): array {
    return [
        'status' => $row['status'],
        'latency_ms' => $row['latency_ms'] !== null ? (float)$row['latency_ms'] : null,
        'packet_loss_percent' => $row['packet_loss_percent'] !== null ? (float)$row['packet_loss_percent'] : null,
        'checked_at' => tracs_infra_iso($row['checked_at']),
    ];
}, $rows);

ok($data);
