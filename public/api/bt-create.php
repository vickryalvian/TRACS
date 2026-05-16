<?php
/* ── api/bt-create.php — Create balance transfer ────────────────
   TRACS · Prepared statements only · No framework               */
require '_bootstrap.php';

$sender_email    = trim($body['sender_email']    ?? '');
$sender_user_id  = trim($body['sender_user_id']  ?? '');
$sender_type     = trim($body['sender_type']     ?? '');
$receiver_email  = trim($body['receiver_email']  ?? '');
$receiver_user_id= trim($body['receiver_user_id']?? '');
$receiver_type   = trim($body['receiver_type']   ?? '');
$amount          = floatval($body['amount']       ?? 0);
$status          = trim($body['status']           ?? 'pending');
$admin_name      = trim($body['admin_name']       ?? '');
$ticket_id       = trim($body['ticket_id']        ?? '') ?: null;
$transfer_date   = trim($body['transfer_date']    ?? '');

// ── Validation ────────────────────────────────────────────────
$allowed_types   = ['client_area','billing_console','billing_awan'];
$allowed_status  = ['done','pending'];

// Email: optional, but if provided must be valid format
if ($sender_email && !filter_var($sender_email, FILTER_VALIDATE_EMAIL))   fail('Invalid sender email');
if ($receiver_email && !filter_var($receiver_email, FILTER_VALIDATE_EMAIL)) fail('Invalid receiver email');

if (!in_array($sender_type, $allowed_types, true))   fail('Invalid sender type');
if (!in_array($receiver_type, $allowed_types, true)) fail('Invalid receiver type');
if ($amount <= 0)                                     fail('Amount must be greater than 0');
if (!in_array($status, $allowed_status, true))        fail('Invalid status');
if (!$admin_name)                                     fail('Admin name is required');

// ── Validate / normalise transfer_date ───────────────────────
$dt = null;
if ($transfer_date) {
  $d = DateTime::createFromFormat('Y-m-d\TH:i', $transfer_date)
    ?: DateTime::createFromFormat('Y-m-d H:i:s', $transfer_date)
    ?: DateTime::createFromFormat('Y-m-d H:i',   $transfer_date);
  if ($d) $dt = $d->format('Y-m-d H:i:s');
}
if (!$dt) $dt = date('Y-m-d H:i:s');

// ── Auto-create table (migration-safe) ───────────────────────
$conn->query("CREATE TABLE IF NOT EXISTS `balance_transfers` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `transfer_date`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sender_email`     VARCHAR(254)     NOT NULL DEFAULT '',
  `sender_user_id`   VARCHAR(100)     NOT NULL DEFAULT '',
  `sender_type`      ENUM('client_area','billing_console','billing_awan') NOT NULL DEFAULT 'client_area',
  `receiver_email`   VARCHAR(254)     NOT NULL DEFAULT '',
  `receiver_user_id` VARCHAR(100)     NOT NULL DEFAULT '',
  `receiver_type`    ENUM('client_area','billing_console','billing_awan') NOT NULL DEFAULT 'client_area',
  `amount`           DECIMAL(15,2)    NOT NULL DEFAULT '0.00',
  `status`           ENUM('done','pending') NOT NULL DEFAULT 'pending',
  `admin_name`       VARCHAR(150)     NOT NULL DEFAULT '',
  `ticket_id`        VARCHAR(100)     NULL DEFAULT NULL,
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_admin`          (`admin_name`),
  INDEX `idx_ticket`         (`ticket_id`),
  INDEX `idx_transfer_date`  (`transfer_date`),
  INDEX `idx_sender_email`   (`sender_email`(64)),
  INDEX `idx_receiver_email` (`receiver_email`(64)),
  INDEX `idx_status`         (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
tracs_ensure_creator_columns($conn, 'balance_transfers', null);

// ── Insert ────────────────────────────────────────────────────
$stmt = $conn->prepare("
  INSERT INTO balance_transfers
    (transfer_date, sender_email, sender_user_id, sender_type,
     receiver_email, receiver_user_id, receiver_type,
     amount, status, admin_name, ticket_id, created_by, created_by_name)
  VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
");

$stmt->bind_param(
  'sssssssdsssis',
  $dt,
  $sender_email, $sender_user_id, $sender_type,
  $receiver_email, $receiver_user_id, $receiver_type,
  $amount, $status, $admin_name, $ticket_id, $uid, $creator_name
);

if (!$stmt->execute()) fail('Database error: ' . $stmt->error);

try { logAct('balance_transfer','create',$conn->insert_id,'Logged transfer: '.$sender_email.' → '.$receiver_email); } catch(Throwable $e){}

// The signature of tickerEvent requires $uid which we get from session in _bootstrap.php.
if (isset($uid)) {
    tickerEvent($conn, $uid, "Finance transfer recorded: " . number_format($amount, 0) . " to " . ($receiver_email ?: $receiver_user_id), 'info', 'finance', $conn->insert_id);
}

ok(['id' => $conn->insert_id], 'Transfer recorded');
