<?php require '_bootstrap.php';
$id=(int)($body['id']??0); if(!$id) fail('ID required');
$stmt=$conn->prepare("DELETE FROM tracs_ticker_messages WHERE id=? AND user_id=?");
if(!$stmt) fail('Database error',500);
$stmt->bind_param('ii',$id,$uid);
$stmt->execute();
$stmt->close();
ok(null,'Deleted');
