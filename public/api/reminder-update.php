<?php require '_bootstrap.php';
$id    = (int)($body['id'] ?? 0);
$title = trim($body['title'] ?? '');
$due   = trim($body['due_date'] ?? '');
if (!$id)    fail('ID required');
if (!$title) fail('Title required');
if (!$due)   fail('Due date required');
$priority = in_array($body['priority']??'',['low','medium','high','critical']) ? $body['priority'] : 'medium';
$desc     = $body['description'] ?? '';
$due_fmt  = date('Y-m-d H:i:s', strtotime($due));
// Reminders are fully public: any authenticated user may update any reminder.
$stmt = $conn->prepare("UPDATE tracs_reminders SET title=?,description=?,due_date=?,priority=?,updated_at=NOW() WHERE id=?");
$stmt->bind_param('ssssi',$title,$desc,$due_fmt,$priority,$id);
if (!$stmt->execute()) fail('Database error');
if ($stmt->affected_rows===0) fail('Not found',404);
$stmt->close();
logAct($conn,$uid,'updated','Reminders',"Updated reminder: {$title}",$id);
ok(null,'Reminder updated');
