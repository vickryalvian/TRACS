<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

$title = trim($body['title'] ?? '');
if (!$title) fail('Title required');

$shift = trim($body['shift_name'] ?? 'Shift 1');
$priority = in_array($body['priority']??'',['low','medium','high','critical']) ? $body['priority'] : 'medium';
$details = $body['details'] ?? '';

$SC = new ShiftReportController($conn, $uid);
$id = $SC->create([
    'shift_name' => $shift,
    'title' => $title,
    'details' => $details,
    'priority' => $priority,
    'active_date' => $body['active_date'] ?? date('Y-m-d')
]);

if (!$id) fail('Database error');

logAct($conn, $uid, 'created', 'Shift Reports', "Added shift report: {$title}", $id);
tickerEvent($conn, $uid, "New shift report added: {$title}", 'info', 'shift-reports', $id);
ok(['id'=>$id], 'Shift report created');
