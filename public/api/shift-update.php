<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';
require_once __DIR__ . '/shift-attachment-lib.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$id = (int)($input['id'] ?? 0);
if (!$id) fail('ID required');
if (!tracs_can_view_report($conn, $id)) fail_not_found();

$title = trim($input['title'] ?? '');
if (!$title) fail('Title required');

$shift = trim($input['shift_name'] ?? 'Shift 1');
$priority = in_array($input['priority']??'',['low','medium','high','critical']) ? $input['priority'] : 'medium';
$status = in_array($input['status']??'',['active','on_hold','resolved'], true) ? $input['status'] : 'active';
$details = $input['details'] ?? '';
$resolutionNote = trim((string)($input['resolution_note'] ?? ''));
$resolvedAt = trim((string)($input['resolved_at'] ?? ''));

$SC = new ShiftReportController($conn, $uid);
$existing = $SC->getById($id);
if (!$existing) fail('Report not found', 404);
$storedUploads = [];
try {
    $conn->begin_transaction();
    $success = $SC->update($id, [
        'shift_name' => $shift,
        'title' => $title,
        'details' => $details,
        'priority' => $priority,
        'active_date' => $input['active_date'] ?? date('Y-m-d'),
        'status' => $status,
        'resolution_note' => $resolutionNote,
        'resolved_at' => $resolvedAt,
    ]);
    if (!$success) throw new RuntimeException('Not found');
    shift_attachment_ensure_table($conn);
    if (!empty($_FILES['attachments'])) {
        $storedUploads = shift_attachment_store_uploads($conn, $_FILES['attachments'], $id, $uid);
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($storedUploads as $upload) {
        if (!empty($upload['stored_path'])) @unlink($upload['stored_path']);
        if (!empty($upload['thumb_path'])) @unlink($upload['thumb_path']);
    }
    fail($e->getMessage() === 'Not found' ? 'Error updating report or not found' : $e->getMessage(), $e->getMessage() === 'Not found' ? 404 : 400);
}

logAct($conn, $uid, 'updated', 'Shift Reports', "Updated shift report: {$title}", $id);
if (($existing['status'] ?? '') !== $status) {
    logAct($conn, $uid, 'status_changed', 'Shift Reports', "Shift report status changed from " . ($existing['status'] ?? 'unknown') . " to {$status}: {$title}", $id);
}
ok(['attachments_added'=>count($storedUploads)], 'Shift report updated');
