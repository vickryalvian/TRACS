<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';
require_once __DIR__ . '/abuse-report-evidence-lib.php';

$id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
if (!$id) {
    fail_not_found();
}

$controller = new AbuseReportController($conn, $uid);
$evidence = $controller->model()->fetchEvidence($id);
if (!$evidence) {
    fail_not_found();
}
$report = $controller->getReportDetail((int)$evidence['report_id']);
if (!$report) {
    fail_not_found();
}

$fileName = basename((string)$evidence['stored_filename']);
if ($fileName === '') {
    fail_not_found();
}
$path = abuse_report_evidence_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
if (!is_file($path)) {
    fail_not_found();
}

$download = ($_GET['download'] ?? '') === '1' || !str_starts_with((string)$evidence['mime_type'], 'image/');
$displayName = abuse_report_evidence_sanitize_original((string)$evidence['original_filename']);
header_remove('Content-Type');
header('Content-Type: ' . (string)$evidence['mime_type']);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header(
    'Content-Disposition: ' .
    ($download ? 'attachment' : 'inline') .
    '; filename="' . addcslashes($displayName, '\\"') . '"'
);
readfile($path);
exit;
