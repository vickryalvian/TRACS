<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');

$title = trim($body['title'] ?? '');
if (!$title) fail('Title required');

$shift = trim($body['shift_name'] ?? 'Shift 1');
$priority = in_array($body['priority']??'',['low','medium','high','critical']) ? $body['priority'] : 'medium';
$details = $body['details'] ?? '';

$SC = new ShiftReportController($conn, $uid);
$success = $SC->update($id, [
    'shift_name' => $shift,
    'title' => $title,
    'details' => $details,
    'priority' => $priority,
    'active_date' => $body['active_date'] ?? date('Y-m-d')
]);

if (!$success) fail('Error updating report or not found', 404);

logAct($conn, $uid, 'updated', 'Shift Reports', "Updated shift report: {$title}", $id);
ok(null, 'Shift report updated');
