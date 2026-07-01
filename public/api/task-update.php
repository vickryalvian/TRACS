<?php require '_bootstrap.php';
$id=(int)($body['id']??0); $title=trim($body['title']??'');
if(!$id) fail('ID required'); if(!$title) fail('Title required');
$desc=$body['description']??'';
// Every authenticated user with checklist.manage may update any checklist item.
$stmt=$conn->prepare("UPDATE tracs_side_tasks SET title=?,description=?,updated_at=NOW() WHERE id=?");
$stmt->bind_param('ssi',$title,$desc,$id);
if(!$stmt->execute()||$stmt->affected_rows===0) fail('Not found',404);
$stmt->close();
logAct($conn,$uid,'updated','Checklist',"Updated task: {$title}",$id);
ok(null,'Updated');
