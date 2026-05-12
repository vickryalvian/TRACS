<?php
/* ── api/bt-delete.php — Delete balance transfer ────────────── */
require '_bootstrap.php';

$id = intval($body['id'] ?? 0);
if (!$id) fail('Invalid ID');

$stmt = $conn->prepare("DELETE FROM balance_transfers WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
if (!$stmt->execute()) fail('Database error: ' . $stmt->error);
if ($stmt->affected_rows === 0) fail('Record not found');

try { logAct('balance_transfer','delete',$id,'Deleted transfer #'.$id); } catch(Throwable $e){}

ok([], 'Transfer deleted');
