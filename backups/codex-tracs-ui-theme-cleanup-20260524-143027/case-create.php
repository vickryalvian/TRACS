<?php require '_bootstrap.php';
require_once __DIR__ . '/case-attachment-lib.php';
tracs_ensure_creator_columns($conn, 'tracs_cases', 'user_id');

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$title = trim($input['title'] ?? '');
if (!$title) fail('Title is required');
$status    = in_array($input['status']??'', ['active','pending','stuck','completed'], true) ? $input['status'] : 'active';
$priority  = in_array($input['priority']??'', ['low','medium','high','critical'], true) ? $input['priority'] : 'medium';
$next      = !empty($input['next_check_at']) ? date('Y-m-d H:i:s', strtotime($input['next_check_at'])) : null;
$notes     = $input['notes'] ?? '';

case_attachment_ensure_table($conn);
$storedUploads = [];

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare("INSERT INTO tracs_cases (user_id,title,status,priority,next_check_at,notes,created_by,created_by_name,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())");
    $stmt->bind_param('isssssis', $uid, $title, $status, $priority, $next, $notes, $uid, $creator_name);
    if (!$stmt->execute()) {
        error_log('TRACS case-create failed: ' . $conn->error);
        throw new RuntimeException('Database error');
    }
    $id = $stmt->insert_id;
    $stmt->close();

    if (!empty($_FILES['attachments'])) {
        $storedUploads = case_attachment_store_uploads($conn, $_FILES['attachments'], (int)$id, $uid);
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($storedUploads as $upload) {
        if (!empty($upload['stored_path'])) @unlink($upload['stored_path']);
        if (!empty($upload['thumb_path'])) @unlink($upload['thumb_path']);
    }
    error_log('TRACS case attachment create failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Database error' ? 'Database error' : $e->getMessage(), $e->getMessage() === 'Database error' ? 500 : 400);
}

logAct($conn,$uid,'created','Cases',"Created case: {$title}",$id);
tickerEvent($conn, $uid, "New case added: {$title}", 'info', 'cases', $id);
ok(['id'=>$id, 'attachments'=>count($storedUploads)],'Case created');
