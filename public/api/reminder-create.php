<?php require '_bootstrap.php';
tracs_ensure_creator_columns($conn, 'tracs_reminders', 'user_id');
$title = trim($body['title'] ?? '');
$due   = trim($body['due_date'] ?? '');
if (!$title) fail('Title required');
if (!$due)   fail('Due date required');
$priority = in_array($body['priority']??'',['low','medium','high','critical']) ? $body['priority'] : 'medium';
$desc     = $body['description'] ?? '';
$due_fmt  = date('Y-m-d H:i:s', strtotime($due));
$stmt = $conn->prepare("INSERT INTO tracs_reminders (user_id,title,description,due_date,priority,is_completed,created_by,created_by_name,created_at) VALUES (?,?,?,?,?,0,?,?,NOW())");
$stmt->bind_param('issssis',$uid,$title,$desc,$due_fmt,$priority,$uid,$creator_name);
if (!$stmt->execute()) {
    error_log('TRACS reminder-create failed: ' . $conn->error);
    fail('Database error', 500);
}
$id=$stmt->insert_id; $stmt->close();
logAct($conn,$uid,'created','Reminders',"Created reminder: {$title}",$id);
tickerEvent($conn, $uid, "New reminder scheduled: {$title}", 'info', 'reminders', $id);
ok(['id'=>$id],'Reminder created');
