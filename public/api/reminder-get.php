<?php require '_bootstrap.php';
$id=(int)($body['id']??$_GET['id']??0); if(!$id) fail('ID required');
// Reminders are fully public: any authenticated user may view any reminder.
$stmt = $conn->prepare("SELECT * FROM tracs_reminders WHERE id=? LIMIT 1");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if(!$row) fail('Not found',404);
ok($row);
