<?php require '_bootstrap.php';
tracs_ensure_creator_columns($conn, 'tracs_side_tasks', 'user_id');
$title=trim($body['title']??''); if(!$title) fail('Title required');
$desc=$body['description']??'';
$stmt=$conn->prepare("INSERT INTO tracs_side_tasks (user_id,title,description,is_completed,created_by,created_by_name,created_at,updated_at) VALUES (?,?,?,0,?,?,NOW(),NOW())");
$stmt->bind_param('issis',$uid,$title,$desc,$uid,$creator_name);
if(!$stmt->execute()) {
    error_log('TRACS task-create failed: ' . $conn->error);
    fail('Database error', 500);
}
$id=$stmt->insert_id; $stmt->close();
logAct($conn,$uid,'created','Checklist',"Added task: {$title}",$id);
tickerEvent($conn, $uid, "Checklist updated: {$title}", 'info', 'checklist', $id);
ok(['id'=>$id],'Task created');
