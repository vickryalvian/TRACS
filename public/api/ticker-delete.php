<?php require '_bootstrap.php';
$id=(int)($body['id']??0); if(!$id) fail('ID required');
$conn->query("DELETE FROM tracs_ticker_messages WHERE id=$id AND user_id=$uid");
ok(null,'Deleted');
