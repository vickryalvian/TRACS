<?php require '_bootstrap.php';
$id = (int)($body['id'] ?? $_GET['id'] ?? 0);
if (!$id) fail('ID required');
$row = $conn->query("SELECT * FROM tracs_cases WHERE id=$id AND user_id=$uid")->fetch_assoc();
if (!$row) fail('Not found', 404);
ok($row);
