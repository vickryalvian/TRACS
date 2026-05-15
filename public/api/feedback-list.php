<?php
/**
 * TRACS — API: Feedback List
 */
header('Content-Type: application/json');
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/controller.php';

$controller = new CancellationFeedbackController($conn, $uid);

$limit = intval($_GET['limit'] ?? 50);
$offset = intval($_GET['offset'] ?? 0);
$filters = [
    'q'          => $_GET['q'] ?? '',
    'service'    => $_GET['service'] ?? '',
    'reason'     => $_GET['reason'] ?? '',
    'resolution' => $_GET['resolution'] ?? '',
    'date_from'  => $_GET['date_from'] ?? '',
    'date_to'    => $_GET['date_to'] ?? '',
];

$list = $controller->getFeedbackList($filters, $limit, $offset);
$total = $controller->getTotalCount($filters);

echo json_encode([
    'success' => true,
    'data'    => $list,
    'total'   => $total,
    'limit'   => $limit,
    'offset'  => $offset
]);
