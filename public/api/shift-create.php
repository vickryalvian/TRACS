<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';
require_once __DIR__ . '/shift-attachment-lib.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$title = trim($input['title'] ?? '');
if (!$title) fail('Title required');

$shift = trim($input['shift_name'] ?? 'Shift 1');
$priority = in_array($input['priority']??'',['low','medium','high','critical']) ? $input['priority'] : 'medium';
$status = in_array($input['status']??'',['active','on_hold','resolved'], true) ? $input['status'] : 'active';
$details = $input['details'] ?? '';
$resolutionNote = trim((string)($input['resolution_note'] ?? ''));
$resolvedAt = trim((string)($input['resolved_at'] ?? ''));

$SC = new ShiftReportController($conn, $uid);
$storedUploads = [];
try {
    $conn->begin_transaction();
    $id = $SC->create([
        'shift_name' => $shift,
        'title' => $title,
        'details' => $details,
        'priority' => $priority,
        'active_date' => $input['active_date'] ?? date('Y-m-d'),
        'status' => $status,
        'resolution_note' => $resolutionNote,
        'resolved_at' => $resolvedAt,
    ]);
    if (!$id) throw new RuntimeException('Database error');
    shift_attachment_ensure_table($conn);
    if (!empty($_FILES['attachments'])) {
        $storedUploads = shift_attachment_store_uploads($conn, $_FILES['attachments'], (int)$id, $uid);
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($storedUploads as $upload) {
        if (!empty($upload['stored_path'])) @unlink($upload['stored_path']);
        if (!empty($upload['thumb_path'])) @unlink($upload['thumb_path']);
    }
    fail($e->getMessage() === 'Database error' ? 'Database error' : $e->getMessage(), $e->getMessage() === 'Database error' ? 500 : 400);
}

logAct($conn, $uid, 'created', 'Shift Reports', "Added shift report: {$title}", $id);
if ($status !== 'resolved') {
    tickerEvent($conn, $uid, "New shift report added: {$title}", $status === 'on_hold' ? 'warning' : 'info', 'shift-reports', $id);
}
ok(['id'=>$id, 'attachments'=>count($storedUploads)], 'Shift report created');
