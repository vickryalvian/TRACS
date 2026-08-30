<?php require '_bootstrap.php';
require_once __DIR__ . '/checklist-attachment-lib.php';
tracs_ensure_creator_columns($conn, 'tracs_side_tasks', 'user_id');
$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$title=trim($input['title']??''); if(!$title) fail('Title required');
$desc=$input['description']??'';
$stmt=$conn->prepare("INSERT INTO tracs_side_tasks (user_id,title,description,is_completed,created_by,created_by_name,created_at,updated_at) VALUES (?,?,?,0,?,?,NOW(),NOW())");
$stmt->bind_param('issis',$uid,$title,$desc,$uid,$creator_name);
if(!$stmt->execute()) {
    error_log('TRACS task-create failed: ' . $conn->error);
    fail('Database error', 500);
}
$id=$stmt->insert_id; $stmt->close();
$storedUploads = [];
if (!empty($_FILES['attachments'])) {
    try {
        checklist_attachment_ensure_table($conn);
        $storedUploads = checklist_attachment_store_uploads($conn, $_FILES['attachments'], (int)$id, $uid);
    } catch (Throwable $e) {
        error_log('TRACS checklist attachment upload failed: ' . $e->getMessage());
    }
}
logAct($conn,$uid,'created','Checklist',"Added task: {$title}",$id);
tickerEvent($conn, $uid, "Checklist updated: {$title}", 'info', 'checklist', $id);
try {
    require_once __DIR__.'/../../modules/task-management/controller.php';
    (new TaskManagementController($conn, $uid))->createFromChecklist((int)$id, $title, $desc);
} catch (Throwable $e) { /* Task Management mirror is non-fatal for the checklist item itself. */ }
ok(['id'=>$id,'attachments'=>count($storedUploads)],'Task created');
