<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');
if (!tracs_can_view_report($conn, $id)) fail_not_found();

$SC = new ShiftReportController($conn, $uid);
$report = $SC->getById($id);

if (!$report) fail('Report not found', 404);

$success = $SC->delete($id);
if (!$success) fail('Error deleting report');

logAct($conn, $uid, 'deleted', 'Shift Reports', "Deleted shift report: " . $report['title'], $id);
ok(null, 'Shift report deleted');
