<?php require '_bootstrap.php';
require_once __DIR__ . '/case-attachment-lib.php';

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$id    = (int)($input['id'] ?? 0);
$title = trim($input['title'] ?? '');
if (!$id)    fail('ID required');
if (!$title) fail('Title required');
$status   = in_array($input['status']??'',['active','pending','stuck','completed'], true) ? $input['status'] : 'active';
$priority = in_array($input['priority']??'',['low','medium','high','critical'], true) ? $input['priority'] : 'medium';
$next     = !empty($input['next_check_at']) ? date('Y-m-d H:i:s', strtotime($input['next_check_at'])) : null;
$notes    = $input['notes'] ?? '';
$removeRaw = $input['remove_attachment_ids'] ?? [];
$removeIds = is_array($removeRaw) ? $removeRaw : explode(',', (string)$removeRaw);
$removeIds = array_values(array_filter(array_map('intval', $removeIds), fn($v) => $v > 0));

case_attachment_ensure_table($conn);
$storedUploads = [];
$deletedAttachments = [];

try {
    $conn->begin_transaction();
    $check = $conn->prepare("SELECT id FROM tracs_cases WHERE id=? AND user_id=? LIMIT 1");
    if (!$check) {
        throw new RuntimeException('Database error');
    }
    $check->bind_param('ii', $id, $uid);
    $check->execute();
    $found = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$found) {
        $conn->rollback();
        fail('Case not found or not yours', 404);
    }

    $stmt = $conn->prepare("UPDATE tracs_cases SET title=?,status=?,priority=?,next_check_at=?,notes=?,updated_at=NOW() WHERE id=? AND user_id=?");
    $stmt->bind_param('sssssii', $title,$status,$priority,$next,$notes,$id,$uid);
    if (!$stmt->execute()) {
        throw new RuntimeException('Database error');
    }
    $stmt->close();

    foreach ($removeIds as $attachmentId) {
        $attachment = case_attachment_fetch_for_user($conn, $attachmentId, $uid);
        if (!$attachment || (int)$attachment['case_id'] !== $id || !case_attachment_delete_for_user($conn, $attachmentId, $uid, false)) {
            throw new RuntimeException('Unable to remove one attachment.');
        }
        $deletedAttachments[] = $attachment;
    }

    if (!empty($_FILES['attachments'])) {
        $storedUploads = case_attachment_store_uploads($conn, $_FILES['attachments'], $id, $uid);
    }

    $conn->commit();
    foreach ($deletedAttachments as $attachment) {
        case_attachment_delete_files($attachment);
    }
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($storedUploads as $upload) {
        if (!empty($upload['stored_path'])) @unlink($upload['stored_path']);
        if (!empty($upload['thumb_path'])) @unlink($upload['thumb_path']);
    }
    error_log('TRACS case update attachment failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Database error' ? 'Database error' : $e->getMessage(), $e->getMessage() === 'Database error' ? 500 : 400);
}

logAct($conn,$uid,'updated','Cases',"Updated case: {$title}",$id);
tickerEvent($conn, $uid, "Case #{$id} updated: {$title}", 'info', 'cases', $id);
ok(['attachments_added'=>count($storedUploads)],'Case updated');
