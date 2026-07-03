<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../core/infrastructure_servers.php';

api_require_permissions(['dashboard.view']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail('POST required.', 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$code = strtoupper(trim((string)($payload['code'] ?? '')));
if ($code === '' || mb_strlen($code) > 8) {
    fail('Invalid server code.', 422);
}

if (!tracs_infra_server_soft_delete($conn, $code)) {
    fail('Server not found.', 404);
}

logAct($conn, $uid, 'deleted', 'Infrastructure Pulse', "Removed monitoring target {$code}");
ok(null, 'Server removed from monitoring.');
