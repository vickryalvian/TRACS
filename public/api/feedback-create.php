<?php
/**
 * TRACS — API: Feedback Create
 */
header('Content-Type: application/json');
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';
tracs_ensure_creator_columns($conn, 'tracs_cancellation_feedback', null);

$controller = new CancellationFeedbackController($conn, $uid);
$services = cf_filter_allowed_values($_POST['service'] ?? [], cf_allowed_services());
$reasons = cf_filter_allowed_values($_POST['reason'] ?? [], cf_allowed_reasons());
$resolution = trim((string)($_POST['resolution'] ?? ''));
if ($resolution !== '' && !in_array($resolution, cf_allowed_resolutions(), true)) {
    $resolution = '';
}

$data = [
    'submitter_name'      => $creator_name,
    'cancelled_service'   => cf_encode_multi_value($services),
    'cancellation_reason' => cf_encode_multi_value($reasons),
    'additional_details'  => trim((string)($_POST['details'] ?? '')),
    'whmcs_reference'     => trim((string)($_POST['reference'] ?? '')),
    'email_address'       => trim((string)($_POST['email'] ?? '')),
    'payment_resolution'  => $resolution,
];

// Validation
if (empty($services) || empty($reasons)) {
    echo json_encode(['success' => false, 'error' => 'Service and Reason are required.']);
    exit;
}

if ($data['email_address'] !== '' && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Email address is invalid.']);
    exit;
}

$id = $controller->createFeedback($data);

if ($id) {
    echo json_encode(['success' => true, 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
