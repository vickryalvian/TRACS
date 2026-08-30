<?php
/**
 * TRACS — API: Feedback Delete
 */
header('Content-Type: application/json');
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';
require_once __DIR__ . '/_realtime_payloads.php';

$controller = new CancellationFeedbackController($conn, $uid);

$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
    exit;
}

if (!tracs_can_view_feedback($conn, $id)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found.']);
    exit;
}

$record = tracs_realtime_feedback($conn, $id);

if ($controller->deleteFeedback($id)) {
    echo json_encode(['success' => true, 'data' => ['record' => $record]]);
} else {
    echo json_encode(['success' => false, 'error' => 'Delete failed.']);
}
