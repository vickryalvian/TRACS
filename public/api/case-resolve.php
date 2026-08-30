<?php require '_bootstrap.php';
tracs_ensure_case_status_values($conn);

$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');

$stmt = $conn->prepare("SELECT title, status, user_id FROM tracs_cases WHERE id=? LIMIT 1");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) fail('Case not found', 404);
if ((int)$row['user_id'] !== $uid && !tracs_user_can($conn, 'cases.manage', $uid)) {
    fail('Forbidden', 403);
}

$stmt = $conn->prepare("UPDATE tracs_cases SET status='completed', updated_at=NOW() WHERE id=?");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('i', $id);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) fail('Database error', 500);

logAct($conn, $uid, 'completed', 'Cases', "Resolved case: {$row['title']}", $id);
if (($row['status'] ?? '') !== 'completed') {
    logAct($conn, $uid, 'status_changed', 'Cases', "Case status changed from {$row['status']} to completed via resolve_action: {$row['title']}", $id);
}
tickerEvent($conn, $uid, "Case #{$id} resolved: {$row['title']}", 'success', 'cases', $id);
ok(['id' => $id, 'status' => 'completed'], 'Case resolved');
