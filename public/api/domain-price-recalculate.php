<?php
/**
 * TRACS — Domain Price Recalculate API
 * Triggers the calculation engine for a specific monthly record.
 *
 * Permissions: domain_price.manage (manage or approve).
 * CSRF protected. Audit-logged.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../modules/domain-price-crosscheck/controller.php';

api_require_any_permission(['domain_price.manage', 'domain_price.approve']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.', 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    fail('Invalid JSON payload.');
}

$action  = $data['action'] ?? '';
$monthId = (int)($data['month_id'] ?? 0);

if ($action !== 'recalculate' || $monthId <= 0) {
    fail('Missing or invalid parameters.');
}

try {
    $userId    = (int)($_SESSION['user_id'] ?? 0);
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $DPC    = new DomainPriceCrosscheckController($conn, $userId);
    $result = $DPC->recalculateSummary($monthId, $ipAddress);

    ok($result, $result['message'] ?? 'Recalculation complete.');

} catch (Exception $e) {
    fail($e->getMessage());
}
