<?php
/**
 * TRACS — API: Feedback Update
 */
header('Content-Type: application/json');
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';

$controller = new CancellationFeedbackController($conn, $uid);

$id = intval($_POST['id'] ?? 0);
$services = cf_filter_allowed_values($_POST['service'] ?? [], cf_allowed_services());
$reasons = cf_filter_allowed_values($_POST['reason'] ?? [], cf_allowed_reasons());
$resolution = trim((string)($_POST['resolution'] ?? ''));
if ($resolution !== '' && !in_array($resolution, cf_allowed_resolutions(), true)) {
    $resolution = '';
}

$data = [
    'cancelled_service'   => cf_encode_multi_value($services),
    'cancellation_reason' => cf_encode_multi_value($reasons),
    'additional_details'  => trim((string)($_POST['details'] ?? '')),
    'whmcs_reference'     => trim((string)($_POST['reference'] ?? '')),
    'email_address'       => trim((string)($_POST['email'] ?? '')),
    'payment_resolution'  => $resolution,
];

if (!$id || empty($services) || empty($reasons)) {
    echo json_encode(['success' => false, 'error' => 'Invalid input.']);
    exit;
}

if ($data['email_address'] !== '' && !filter_var($data['email_address'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Email address is invalid.']);
    exit;
}

if ($controller->updateFeedback($id, $data)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Update failed.']);
}
