<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');
if (!tracs_can_view_report($conn, $id)) fail_not_found();

$SC = new ShiftReportController($conn, $uid);
$report = $SC->getById($id);

if (!$report) fail('Report not found', 404);

$note = trim((string)($body['resolution_note'] ?? ''));
$resolvedAt = trim((string)($body['resolved_at'] ?? ''));
$success = $SC->resolve($id, $note, $resolvedAt);
if (!$success) fail('Error resolving report');

logAct($conn, $uid, 'completed', 'Shift Reports', "Resolved shift report: " . $report['title'], $id);
if (($report['status'] ?? '') !== 'resolved') {
    logAct($conn, $uid, 'status_changed', 'Shift Reports', "Shift report status changed from " . ($report['status'] ?? 'unknown') . " to resolved: " . $report['title'], $id);
}
ok(null, 'Shift report resolved');
