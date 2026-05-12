<?php require '_bootstrap.php';
$id=(int)($body['id']??0); if(!$id) fail('ID required');
$row=$conn->query("SELECT title FROM tracs_side_tasks WHERE id=$id AND user_id=$uid")->fetch_assoc();
if(!$row) fail('Not found',404);
$conn->query("DELETE FROM tracs_side_tasks WHERE id=$id AND user_id=$uid");
logAct($conn,$uid,'deleted','Checklist',"Deleted task: {$row['title']}",$id);
ok(null,'Deleted');
