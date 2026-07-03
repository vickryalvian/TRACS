<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../core/infrastructure_ping.php';

api_require_permissions(['dashboard.view']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail('POST required.', 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$host = trim((string)($payload['host'] ?? ''));
$count = (int)($payload['count'] ?? 4);
$timeoutSeconds = (int)($payload['timeout'] ?? 5);

if (!tracs_infra_ping_host_is_valid($host)) {
    fail('Invalid host.', 422);
}

$rateKey = 'tracs_infra_ping_last_' . md5($host);
$now = time();
$last = (int)($_SESSION[$rateKey] ?? 0);
if ($last > 0 && ($now - $last) < 10) {
    http_response_code(429);
    header('Retry-After: ' . (10 - ($now - $last)));
    echo json_encode([
        'success' => false,
        'message' => 'Please wait before checking this host again.',
        'retry_after' => 10 - ($now - $last),
    ]);
    exit;
}
$_SESSION[$rateKey] = $now;

try {
    $result = tracs_infra_ping_host($host, $count, $timeoutSeconds);
    ok($result);
} catch (Throwable $error) {
    error_log('TRACS infrastructure ping failed: ' . $error->getMessage());
    fail('Network check is temporarily unavailable.', 503);
}
