<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';
require_once __DIR__ . '/abuse-report-evidence-lib.php';

if (!tracs_user_can_delete_abuse_reports($conn, $uid)) {
    fail('Forbidden', 403);
}

$id = tracs_is_positive_int($body['id'] ?? null) ? (int)$body['id'] : 0;
if (!$id) {
    fail('ID required', 422);
}

$controller = new AbuseReportController($conn, $uid);
try {
    $conn->begin_transaction();
    $deleted = $controller->delete($id);
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    error_log('TRACS abuse report delete failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Not found' ? 'Not found' : 'Database error', $e->getMessage() === 'Not found' ? 404 : 500);
}

foreach ($deleted['evidence'] as $file) {
    abuse_report_evidence_delete_file($file);
}

logAct($conn, $uid, 'deleted', 'Abuse Reports', 'Deleted abuse report: ' . $deleted['title'], $id);
ok(['id' => $id], 'Abuse report deleted');
