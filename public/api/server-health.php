<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../core/server_monitoring.php';

api_require_super_admin();

if (!empty($_GET)) {
    fail('Query parameters are not supported.', 400);
}

$now = time();
$lastRefresh = (int)($_SESSION['tracs_server_health_last_refresh'] ?? 0);
if ($lastRefresh > 0 && ($now - $lastRefresh) < 5) {
    http_response_code(429);
    header('Retry-After: ' . (5 - ($now - $lastRefresh)));
    echo json_encode([
        'success' => false,
        'message' => 'Please wait before refreshing server health again.',
        'retry_after' => 5 - ($now - $lastRefresh),
    ]);
    exit;
}
$_SESSION['tracs_server_health_last_refresh'] = $now;

try {
    $data = tracs_collect_server_monitoring($conn);
    logAct($conn, $uid, 'viewed', 'Server Health', 'Refreshed server health and sanitized error log summary');
    ok($data);
} catch (Throwable $error) {
    error_log('TRACS server monitoring failed: ' . $error->getMessage());
    fail('Server health data is temporarily unavailable.', 503);
}
