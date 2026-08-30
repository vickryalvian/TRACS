<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';
require_once __DIR__ . '/../../core/notifications.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$id = tracs_is_positive_int($input['id'] ?? null) ? (int)$input['id'] : 0;
if (!$id) {
    fail('ID required', 422);
}

$controller = new AbuseReportController($conn, $uid);
try {
    $conn->begin_transaction();
    $changes = $controller->update($id, $input, $creator_name);
    $report = $controller->getReportDetail($id);
    logAct($conn, $uid, 'updated', 'Abuse Reports', 'Updated abuse report: ' . ($report['title'] ?? 'Untitled'), $id);
    tickerEvent($conn, $uid, 'Abuse report updated: ' . ($report['report_number'] ?? ('#' . $id)), 'info', 'abuse_reports', $id);
    if (isset($changes['priority']) && ($changes['priority']['new'] ?? '') === 'critical' && function_exists('tracs_notify_abuse_report_critical')) {
        tracs_notify_abuse_report_critical($conn, $id, (string)($report['title'] ?? 'Abuse report'), $uid);
    }
    if (isset($changes['assigned_user_id']) && !empty($report['assigned_user_id']) && function_exists('tracs_notify_abuse_report_assigned')) {
        tracs_notify_abuse_report_assigned($conn, $id, (int)$report['assigned_user_id'], (string)($report['title'] ?? 'Abuse report'), $uid);
    }
    if (isset($changes['status']) && ($changes['status']['new'] ?? '') === 'resolved' && function_exists('tracs_notify_abuse_report_resolved')) {
        tracs_notify_abuse_report_resolved($conn, $id, (string)($report['title'] ?? 'Abuse report'), $uid);
    }
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    error_log('TRACS abuse report update failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Not found' ? 'Not found' : ($e->getMessage() === 'Database error' ? 'Database error' : $e->getMessage()), $e->getMessage() === 'Not found' ? 404 : ($e->getMessage() === 'Database error' ? 500 : 400));
}

ok(['report' => $report, 'changes' => $changes], 'Abuse report updated');
