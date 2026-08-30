<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

/** Update the shift-summary note on an existing handover header. */

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$id = (int)($input['id'] ?? 0);
if (!$id) fail('ID required');

$SC = new ShiftReportController($conn, $uid);
$handover = $SC->getHandover($id);
if (!$handover) fail('Handover not found', 404);

$summary = (string)($input['summary'] ?? '');
if (!$SC->updateHandoverSummary($id, $summary)) fail('Could not update the handover summary.');

logAct($conn, $uid, 'updated', 'Shift Reports', "Updated shift handover summary #{$id}", $id);
ok(['id' => $id], 'Handover summary updated');
