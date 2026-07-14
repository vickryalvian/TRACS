<?php
/* ── api/bt-delete.php — Delete balance transfer ────────────── */
require '_bootstrap.php';
require_once __DIR__ . '/_realtime_payloads.php';

$id = intval($body['id'] ?? 0);
if (!$id) fail('Invalid ID');
if (!tracs_can_view_balance_transfer($conn, $id)) fail_not_found();
$record = tracs_realtime_balance_transfer($conn, $id);

$stmt = $conn->prepare("DELETE FROM balance_transfers WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
  error_log('TRACS bt-delete failed: ' . $stmt->error);
  fail('Database error', 500);
}
if ($stmt->affected_rows === 0) fail('Record not found');

try { logAct($conn, $uid, 'delete', 'Balance Transfer', 'Deleted transfer #'.$id, $id); } catch(Throwable $e){}

ok(['record' => $record], 'Transfer deleted');
