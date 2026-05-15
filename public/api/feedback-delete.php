<?php
/**
 * TRACS — API: Feedback Delete
 */
header('Content-Type: application/json');
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';

$controller = new CancellationFeedbackController($conn, $uid);

$id = intval($_POST['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid ID.']);
    exit;
}

if ($controller->deleteFeedback($id)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Delete failed.']);
}
