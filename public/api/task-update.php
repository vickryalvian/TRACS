<?php require '_bootstrap.php';
require_once __DIR__ . '/checklist-attachment-lib.php';
$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;
$id=(int)($input['id']??0); $title=trim($input['title']??'');
if(!$id) fail('ID required'); if(!$title) fail('Title required');
$desc=$input['description']??'';
// Every authenticated user with checklist.manage may update any checklist item.
$stmt=$conn->prepare("UPDATE tracs_side_tasks SET title=?,description=?,updated_at=NOW() WHERE id=?");
$stmt->bind_param('ssi',$title,$desc,$id);
if(!$stmt->execute()||$stmt->affected_rows===0) fail('Not found',404);
$stmt->close();
$storedUploads = [];
if (!empty($_FILES['attachments'])) {
    try {
        checklist_attachment_ensure_table($conn);
        $storedUploads = checklist_attachment_store_uploads($conn, $_FILES['attachments'], $id, $uid);
    } catch (Throwable $e) {
        error_log('TRACS checklist attachment upload failed: ' . $e->getMessage());
    }
}
logAct($conn,$uid,'updated','Checklist',"Updated task: {$title}",$id);
try {
    require_once __DIR__.'/../../modules/task-management/controller.php';
    (new TaskManagementController($conn, $uid))->syncTaskFromChecklist($id, $title, $desc);
} catch (Throwable $e) { /* Task Management mirror is non-fatal for the checklist item itself. */ }
ok(['attachments'=>count($storedUploads)],'Updated');
