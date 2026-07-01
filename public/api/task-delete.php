<?php require '_bootstrap.php';
tracs_ensure_creator_columns($conn, 'tracs_side_tasks', 'user_id');
$id=(int)($body['id']??0); if(!$id) fail('ID required');
$stmt=$conn->prepare("SELECT title FROM tracs_side_tasks WHERE id=? LIMIT 1");
if(!$stmt) fail('Database error',500);
$stmt->bind_param('i',$id);
$stmt->execute();
$row=$stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$row) fail('Not found',404);
// Only the checklist item's original creator may delete it. Supervisor/admin
// roles do not bypass this unless a future permission is explicitly added.
$stmt=$conn->prepare("DELETE FROM tracs_side_tasks WHERE id=? AND created_by=?");
if(!$stmt) fail('Database error',500);
$stmt->bind_param('ii',$id,$uid);
$stmt->execute();
if($stmt->affected_rows===0){ $stmt->close(); fail('Only the creator can delete this checklist item',403); }
$stmt->close();
logAct($conn,$uid,'deleted','Checklist',"Deleted task: {$row['title']}",$id);
ok(null,'Deleted');
