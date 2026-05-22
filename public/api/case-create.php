<?php require '_bootstrap.php';
tracs_ensure_creator_columns($conn, 'tracs_cases', 'user_id');
$title = trim($body['title'] ?? '');
if (!$title) fail('Title is required');
$status    = in_array($body['status']??'', ['active','pending','stuck','completed']) ? $body['status'] : 'active';
$priority  = in_array($body['priority']??'', ['low','medium','high','critical']) ? $body['priority'] : 'medium';
$next      = !empty($body['next_check_at']) ? date('Y-m-d H:i:s', strtotime($body['next_check_at'])) : null;
$notes     = $body['notes'] ?? '';
$stmt = $conn->prepare("INSERT INTO tracs_cases (user_id,title,status,priority,next_check_at,notes,created_by,created_by_name,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())");
$stmt->bind_param('isssssis', $uid, $title, $status, $priority, $next, $notes, $uid, $creator_name);
if (!$stmt->execute()) {
    error_log('TRACS case-create failed: ' . $conn->error);
    fail('Database error', 500);
}
$id = $stmt->insert_id; $stmt->close();
logAct($conn,$uid,'created','Cases',"Created case: {$title}",$id);
tickerEvent($conn, $uid, "New case added: {$title}", 'info', 'cases', $id);
ok(['id'=>$id],'Case created');
