<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';

$status = AbuseReportModel::normalizeStatus($body['status'] ?? '');
$rawIds = $body['ordered_ids'] ?? [];
if (!is_array($rawIds)) {
    fail('ordered_ids must be an array', 422);
}

$controller = new AbuseReportController($conn, $uid);
try {
    $conn->begin_transaction();
    $result = $controller->reorder($status, $rawIds, $creator_name);
    logAct(
        $conn,
        $uid,
        'board_reordered',
        'Abuse Reports',
        'Abuse report board reordered: ' . (int)$result['reordered'] . ' in ' . AbuseReportModel::statusLabel($status),
        null
    );
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    error_log('TRACS abuse report reorder failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Too many reports in one column' ? $e->getMessage() : 'Abuse report order could not be saved', $e->getMessage() === 'Too many reports in one column' ? 422 : 500);
}

ok(['status' => $status] + $result, 'Abuse report board order saved');
