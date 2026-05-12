<?php
/**
 * TRACS — API: Feedback Create
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../auth/auth_check.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';

$uid = $_SESSION['user_id'] ?? 0;
$controller = new CancellationFeedbackController($conn, $uid);

$data = [
    'submitter_name'      => $_POST['submitter'] ?? '',
    'cancelled_service'   => $_POST['service'] ?? '',
    'cancellation_reason' => $_POST['reason'] ?? '',
    'additional_details'  => $_POST['details'] ?? '',
    'whmcs_reference'     => $_POST['reference'] ?? '',
    'email_address'       => $_POST['email'] ?? '',
    'payment_resolution'  => $_POST['resolution'] ?? '',
];

// Validation
if (empty($data['submitter_name']) || empty($data['cancelled_service']) || empty($data['cancellation_reason'])) {
    echo json_encode(['success' => false, 'error' => 'Submitter, Service, and Reason are required.']);
    exit;
}

$id = $controller->createFeedback($data);

if ($id) {
    echo json_encode(['success' => true, 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
