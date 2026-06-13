<?php require '_bootstrap.php';
tracs_ensure_case_status_values($conn);

$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');

$stmt = $conn->prepare("SELECT title, status FROM tracs_cases WHERE id=? AND user_id=? LIMIT 1");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) fail('Case not found', 404);

$stmt = $conn->prepare("UPDATE tracs_cases SET status='completed', updated_at=NOW() WHERE id=? AND user_id=?");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('ii', $id, $uid);
$ok = $stmt->execute();
$stmt->close();
if (!$ok) fail('Database error', 500);

logAct($conn, $uid, 'completed', 'Cases', "Resolved case: {$row['title']}", $id);
if (($row['status'] ?? '') !== 'completed') {
    logAct($conn, $uid, 'status_changed', 'Cases', "Case status changed from {$row['status']} to completed via resolve_action: {$row['title']}", $id);
}
tickerEvent($conn, $uid, "Case #{$id} resolved: {$row['title']}", 'success', 'cases', $id);
ok(['id' => $id, 'status' => 'completed'], 'Case resolved');
