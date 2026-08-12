<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';
require_once __DIR__ . '/abuse-report-evidence-lib.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$id = tracs_is_positive_int($input['id'] ?? null) ? (int)$input['id'] : 0;
if (!$id) {
    fail('ID required', 422);
}
if (empty($_FILES['evidence'])) {
    fail('Choose evidence before uploading.', 422);
}

$controller = new AbuseReportController($conn, $uid);
$storedFiles = [];
try {
    $conn->begin_transaction();
    $report = $controller->getReportDetail($id);
    if (!$report) {
        throw new RuntimeException('Not found');
    }
    $requestedType = (string)($input['evidence_type'] ?? '');
    $evidenceIds = [];
    foreach (abuse_report_evidence_normalize_files($_FILES['evidence']) as $file) {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $stored = abuse_report_evidence_store_file($file, $id, $requestedType);
        $storedFiles[] = $stored;
        $evidenceIds[] = $controller->model()->recordEvidence($id, $stored, $stored['evidence_type'], $uid, $creator_name);
    }
    if (!$evidenceIds) {
        throw new RuntimeException('Choose evidence before uploading.');
    }
    $report = $controller->getReportDetail($id);
    logAct($conn, $uid, 'evidence_uploaded', 'Abuse Reports', 'Uploaded evidence for abuse report: ' . ($report['title'] ?? 'Untitled'), $id);
    tickerEvent($conn, $uid, 'Evidence uploaded for abuse report ' . ($report['report_number'] ?? ('#' . $id)), 'info', 'abuse_reports', $id);
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    foreach ($storedFiles as $stored) {
        abuse_report_evidence_delete_file($stored);
    }
    error_log('TRACS abuse evidence upload failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Not found' ? 'Not found' : $e->getMessage(), $e->getMessage() === 'Not found' ? 404 : 400);
}

ok(['ids' => $evidenceIds, 'report' => $report], 'Evidence uploaded');
