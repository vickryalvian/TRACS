<?php
/**
 * TRACS — Domain Price Task API
 * Handles assigning tasks for domain price crosscheck.
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../modules/domain-price-crosscheck/controller.php';

api_require_any_permission(['domain_price.approve', 'domain_price.manage']);

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    fail('Invalid request method.');
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data) {
    fail('Invalid JSON payload.');
}

$action = $data['action'] ?? '';

if ($action === 'assign_task') {
    $monthId = (int)($data['month_id'] ?? 0);
    $assignedTo = (int)($data['assigned_to'] ?? 0);
    $dueDate = trim($data['due_date'] ?? '');
    $priority = trim($data['priority'] ?? 'medium');
    
    if ($monthId <= 0 || $assignedTo <= 0 || empty($dueDate)) {
        fail('Missing required parameters (month, assignee, due date).');
    }
    
    $validPriorities = ['low', 'medium', 'high', 'critical'];
    if (!in_array($priority, $validPriorities)) {
        $priority = 'medium';
    }
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $userId = (int)($_SESSION['user_id'] ?? 0);
        
        $DPC = new DomainPriceCrosscheckController($conn, $userId);
        $result = $DPC->assignTask($monthId, $assignedTo, $dueDate, $priority, $ipAddress);
        
        ok($result, 'Task assigned successfully!');
    } catch (Exception $e) {
        fail($e->getMessage());
    }
}

fail('Unknown action.');
