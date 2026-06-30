<?php require '_bootstrap.php';
require_once __DIR__ . '/case-attachment-lib.php';
$canDeleteCase = tracs_user_can_delete_cases($conn, $uid);
if (!$canDeleteCase) {
    fail('Forbidden', 403);
}
$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');
$stmt = $conn->prepare("SELECT title FROM tracs_cases WHERE id=? LIMIT 1");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) fail('Case not found', 404);
$attachments = case_attachment_full_list_for_case($conn, $id, $uid);
$stmt = $conn->prepare("DELETE FROM tracs_cases WHERE id=?");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('i', $id);
$stmt->execute();
$deleted = $stmt->affected_rows > 0;
$stmt->close();
if ($deleted) {
    foreach ($attachments as $attachment) {
        case_attachment_delete_files($attachment);
    }
}
logAct($conn,$uid,'deleted','Cases',"Deleted case: {$row['title']}",$id);
ok(null,'Case deleted');
