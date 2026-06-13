<?php
/**
 * TRACS — Domain Price Crosscheck Workflow API
 * Handles manual notes and status saving via AJAX.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../modules/domain-price-crosscheck/controller.php';

api_require_permissions(['domain_price.manage']);

// Only accept POST requests with valid CSRF
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.');
}

// Parse JSON payload
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    fail('Invalid JSON payload.');
}

$action = $data['action'] ?? '';

if ($action === 'save_tld_note') {
    $monthId = (int)($data['month_id'] ?? 0);
    $tldId = (int)($data['tld_id'] ?? 0);
    $manualNote = trim($data['manual_note'] ?? '');
    $detailedNote = trim($data['detailed_note'] ?? '');
    $followUpStatus = trim($data['follow_up_status'] ?? 'No Action');
    
    // Basic validation
    $validStatuses = ['No Action', 'Need Review', 'Waiting Finance', 'Waiting Approval', 'Updated'];
    if (!in_array($followUpStatus, $validStatuses, true)) {
        $followUpStatus = 'No Action';
    }
    
    if ($monthId <= 0 || $tldId <= 0) {
        fail('Missing required parameters.');
    }
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        $DPC = new DomainPriceCrosscheckController($conn, $userId);
        $result = $DPC->saveTldNote($monthId, $tldId, $manualNote, $detailedNote, $followUpStatus, $ipAddress);
        
        ok($result, 'Note saved successfully!');
    } catch (Exception $e) {
        error_log('TRACS domain price workflow failed: ' . $e->getMessage());
        fail(tracs_public_exception_message($e, 'The pricing workflow could not be updated.'));
    }
}

fail('Unknown action.');
