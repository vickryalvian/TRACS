<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');

$SC = new ShiftReportController($conn, $uid);
$report = $SC->getById($id);

if (!$report) fail('Report not found', 404);

$success = $SC->resolve($id);
if (!$success) fail('Error resolving report');

logAct($conn, $uid, 'completed', 'Shift Reports', "Resolved shift report: " . $report['title'], $id);
tickerEvent($conn, $uid, "Shift report resolved: " . $report['title'], 'success', 'shift-reports', $id);
ok(null, 'Shift report resolved');
