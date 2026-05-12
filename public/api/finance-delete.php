<?php require '_bootstrap.php';
$id=(int)($body['id']??0); if(!$id) fail('ID required');
$row=$conn->query("SELECT note FROM tracs_finance_transfers WHERE id=$id AND user_id=$uid")->fetch_assoc();
if(!$row) fail('Not found',404);
$conn->query("DELETE FROM tracs_finance_transfers WHERE id=$id AND user_id=$uid");
logAct($conn,$uid,'deleted','Finance',"Deleted transfer: {$row['note']}",$id);
ok(null,'Deleted');
