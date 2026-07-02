<?php require '_bootstrap.php';
$id=(int)($body['id']??0); if(!$id) fail('ID required');
// Ticker announcements are a shared public feed, so any authenticated user
// (matching who can add one via the Manage Announcements modal) can remove one.
$stmt=$conn->prepare("DELETE FROM tracs_ticker_messages WHERE id=?");
if(!$stmt) fail('Database error',500);
$stmt->bind_param('i',$id);
$stmt->execute();
$stmt->close();
ok(null,'Deleted');
