<?php require '_bootstrap.php';
$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');
$row = $conn->query("SELECT title FROM tracs_cases WHERE id=$id AND user_id=$uid")->fetch_assoc();
if (!$row) fail('Case not found', 404);
$conn->query("DELETE FROM tracs_cases WHERE id=$id AND user_id=$uid");
logAct($conn,$uid,'deleted','Cases',"Deleted case: {$row['title']}",$id);
ok(null,'Case deleted');
