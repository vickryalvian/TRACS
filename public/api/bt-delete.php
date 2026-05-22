<?php
/* ── api/bt-delete.php — Delete balance transfer ────────────── */
require '_bootstrap.php';

$id = intval($body['id'] ?? 0);
if (!$id) fail('Invalid ID');
if (!tracs_can_view_balance_transfer($conn, $id)) fail_not_found();

$stmt = $conn->prepare("DELETE FROM balance_transfers WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
if (!$stmt->execute()) {
  error_log('TRACS bt-delete failed: ' . $stmt->error);
  fail('Database error', 500);
}
if ($stmt->affected_rows === 0) fail('Record not found');

try { logAct('balance_transfer','delete',$id,'Deleted transfer #'.$id); } catch(Throwable $e){}

ok([], 'Transfer deleted');
