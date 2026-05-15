<?php
/**
 * TRACS — API: Feedback Update
 */
header('Content-Type: application/json');
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';

$controller = new CancellationFeedbackController($conn, $uid);

$id = intval($_POST['id'] ?? 0);
$data = [
    'submitter_name'      => $_POST['submitter'] ?? '',
    'cancelled_service'   => $_POST['service'] ?? '',
    'cancellation_reason' => $_POST['reason'] ?? '',
    'additional_details'  => $_POST['details'] ?? '',
    'whmcs_reference'     => $_POST['reference'] ?? '',
    'email_address'       => $_POST['email'] ?? '',
    'payment_resolution'  => $_POST['resolution'] ?? '',
];

if (!$id || empty($data['submitter_name']) || empty($data['cancelled_service']) || empty($data['cancellation_reason'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid input.']);
    exit;
}

if ($controller->updateFeedback($id, $data)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Update failed.']);
}
