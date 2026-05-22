<?php
/**
 * TRACS — Domain Price Matrix API
 * Handles saving pricing matrix entries and triggering calculations.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../modules/domain-price-crosscheck/controller.php';

if (!tracs_user_can($conn, 'domain_price.manage')) {
    fail('Permission denied.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.');
}

if (!verify_csrf()) {
    fail('CSRF validation failed.');
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    fail('Invalid JSON payload.');
}

$action = $data['action'] ?? '';

if ($action === 'save_matrix') {
    $monthId = (int)($data['month_id'] ?? 0);
    $entries = $data['entries'] ?? [];
    
    if ($monthId <= 0 || !is_array($entries)) {
        fail('Missing required parameters.');
    }
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        $DPC = new DomainPriceCrosscheckController($conn, $userId);
        
        $result = $DPC->saveMatrixEntries($monthId, $entries, $ipAddress);
        
        ok($result['message'] ?? 'Matrix saved successfully!', $result);
    } catch (Exception $e) {
        fail($e->getMessage());
    }
}

fail('Unknown action.');
