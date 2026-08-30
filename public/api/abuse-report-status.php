<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';
require_once __DIR__ . '/../../core/notifications.php';

$id = tracs_is_positive_int($body['id'] ?? null) ? (int)$body['id'] : 0;
$status = AbuseReportModel::normalizeStatus($body['status'] ?? '');
$source = AbuseReportModel::clean($body['source'] ?? 'manual', 40) ?: 'manual';
if (!$id) {
    fail('Invalid abuse report update', 422);
}

$controller = new AbuseReportController($conn, $uid);
try {
    $conn->begin_transaction();
    $changes = $controller->updateStatus($id, $status, $creator_name, $source);
    $report = $controller->getReportDetail($id);
    if ($changes) {
        logAct($conn, $uid, 'status_changed', 'Abuse Reports', 'Abuse report moved to ' . AbuseReportModel::statusLabel($status) . ': ' . ($report['title'] ?? 'Untitled'), $id);
        tickerEvent($conn, $uid, 'Abuse report ' . ($report['report_number'] ?? ('#' . $id)) . ' moved to ' . AbuseReportModel::statusLabel($status), $status === 'resolved' ? 'success' : 'warning', 'abuse_reports', $id);
        if ($status === 'resolved' && function_exists('tracs_notify_abuse_report_resolved')) {
            tracs_notify_abuse_report_resolved($conn, $id, (string)($report['title'] ?? 'Abuse report'), $uid);
        }
    }
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    error_log('TRACS abuse report status failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Not found' ? 'Not found' : 'Abuse report status could not be updated', $e->getMessage() === 'Not found' ? 404 : 500);
}

ok($report, $changes ? 'Abuse report status updated' : 'Abuse report is already in this stage');
