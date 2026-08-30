<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';
require_once __DIR__ . '/abuse-report-evidence-lib.php';
require_once __DIR__ . '/../../core/notifications.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$controller = new AbuseReportController($conn, $uid);
$storedFiles = [];

try {
    $conn->begin_transaction();
    $id = $controller->create($input, $creator_name);

    if (!empty($_FILES['evidence'])) {
        $requestedType = (string)($input['evidence_type'] ?? '');
        foreach (abuse_report_evidence_normalize_files($_FILES['evidence']) as $file) {
            if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $stored = abuse_report_evidence_store_file($file, $id, $requestedType);
            $storedFiles[] = $stored;
            $controller->model()->recordEvidence($id, $stored, $stored['evidence_type'], $uid, $creator_name);
        }
    }

    $report = $controller->getReportDetail($id);
    logAct($conn, $uid, 'created', 'Abuse Reports', 'Created abuse report: ' . ($report['title'] ?? 'Untitled'), $id);
    tickerEvent($conn, $uid, 'New abuse report: ' . ($report['report_number'] ?? ('#' . $id)) . ' ' . ($report['title'] ?? ''), ($report['priority'] ?? '') === 'critical' ? 'critical' : 'warning', 'abuse_reports', $id);
    if (function_exists('tracs_notify_abuse_report_created')) {
        tracs_notify_abuse_report_created($conn, $id, (string)($report['title'] ?? 'Abuse report'), $uid);
    }
    if (($report['priority'] ?? '') === 'critical' && function_exists('tracs_notify_abuse_report_critical')) {
        tracs_notify_abuse_report_critical($conn, $id, (string)($report['title'] ?? 'Abuse report'), $uid);
    }
    if (!empty($report['assigned_user_id']) && function_exists('tracs_notify_abuse_report_assigned')) {
        tracs_notify_abuse_report_assigned($conn, $id, (int)$report['assigned_user_id'], (string)($report['title'] ?? 'Abuse report'), $uid);
    }
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    foreach ($storedFiles as $stored) {
        abuse_report_evidence_delete_file($stored);
    }
    error_log('TRACS abuse report create failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Database error' ? 'Database error' : $e->getMessage(), $e->getMessage() === 'Database error' ? 500 : 400);
}

ok($report, 'Abuse report created');
