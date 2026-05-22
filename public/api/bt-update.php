<?php
/* ── api/bt-update.php — Edit balance transfer ──────────────── */
require '_bootstrap.php';

$id              = intval($body['id']             ?? 0);
$sender_email    = trim($body['sender_email']    ?? '');
$sender_user_id  = trim($body['sender_user_id']  ?? '');
$sender_type     = trim($body['sender_type']     ?? '');
$receiver_email  = trim($body['receiver_email']  ?? '');
$receiver_user_id= trim($body['receiver_user_id']?? '');
$receiver_type   = trim($body['receiver_type']   ?? '');
$amount          = floatval($body['amount']       ?? 0);
$status          = trim($body['status']           ?? 'pending');
$ticket_id       = trim($body['ticket_id']        ?? '') ?: null;
$transfer_date   = trim($body['transfer_date']    ?? '');

$allowed_types  = ['client_area','billing_console','billing_awan'];
$allowed_status = ['done','pending'];

if (!$id)                                                                     fail('Invalid ID');
if (!tracs_can_view_balance_transfer($conn, $id))                             fail_not_found();
if ($sender_email   && !filter_var($sender_email,   FILTER_VALIDATE_EMAIL))  fail('Invalid sender email');
if ($receiver_email && !filter_var($receiver_email, FILTER_VALIDATE_EMAIL))  fail('Invalid receiver email');
if (!in_array($sender_type,   $allowed_types,  true)) fail('Invalid sender type');
if (!in_array($receiver_type, $allowed_types,  true)) fail('Invalid receiver type');
if ($amount <= 0)                                      fail('Amount must be greater than 0');
if (!in_array($status, $allowed_status, true))         fail('Invalid status');

$dt = null;
if ($transfer_date) {
  $d = DateTime::createFromFormat('Y-m-d\TH:i', $transfer_date)
    ?: DateTime::createFromFormat('Y-m-d H:i:s', $transfer_date)
    ?: DateTime::createFromFormat('Y-m-d H:i',   $transfer_date);
  if ($d) $dt = $d->format('Y-m-d H:i:s');
}
if (!$dt) $dt = date('Y-m-d H:i:s');

$stmt = $conn->prepare("
  UPDATE balance_transfers SET
    transfer_date    = ?,
    sender_email     = ?,
    sender_user_id   = ?,
    sender_type      = ?,
    receiver_email   = ?,
    receiver_user_id = ?,
    receiver_type    = ?,
    amount           = ?,
    status           = ?,
    ticket_id        = ?
  WHERE id = ?
");

$stmt->bind_param(
  'sssssssdssi',
  $dt,
  $sender_email, $sender_user_id, $sender_type,
  $receiver_email, $receiver_user_id, $receiver_type,
  $amount, $status, $ticket_id,
  $id
);

if (!$stmt->execute()) {
  error_log('TRACS bt-update failed: ' . $stmt->error);
  fail('Database error', 500);
}
if ($stmt->affected_rows === 0) fail('Record not found or no changes');

try { logAct('balance_transfer','update',$id,'Updated transfer #'.$id); } catch(Throwable $e){}

ok([], 'Transfer updated');
