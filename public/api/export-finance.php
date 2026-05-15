<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

$status = (string)($_GET['s'] ?? 'all');
$month = trim((string)($_GET['m'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$types = '';
$params = [];

if (in_array($status, ['done', 'pending'], true)) {
    $where[] = 'status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $where[] = "DATE_FORMAT(transfer_date, '%Y-%m') = ?";
    $types .= 's';
    $params[] = $month;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(sender_email LIKE ? OR receiver_email LIKE ? OR sender_user_id LIKE ? OR receiver_user_id LIKE ? OR ticket_id LIKE ? OR admin_name LIKE ?)';
    $types .= 'ssssss';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
export_add_date_filter($where, $types, $params, 'transfer_date', $from, $to, true);

$sql = 'SELECT transfer_date, sender_email, sender_user_id, sender_type, receiver_email, receiver_user_id,
               receiver_type, amount, status, admin_name, ticket_id, created_at, updated_at
        FROM balance_transfers';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY transfer_date DESC';

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('finance', $from, $to),
    ['Transfer Date', 'Sender Email', 'Sender User ID', 'Sender Type', 'Receiver Email', 'Receiver User ID', 'Receiver Type', 'Amount', 'Status', 'Admin', 'Ticket ID', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        $row['transfer_date'] ?? '',
        $row['sender_email'] ?? '',
        $row['sender_user_id'] ?? '',
        $row['sender_type'] ?? '',
        $row['receiver_email'] ?? '',
        $row['receiver_user_id'] ?? '',
        $row['receiver_type'] ?? '',
        $row['amount'] ?? '',
        $row['status'] ?? '',
        $row['admin_name'] ?? '',
        $row['ticket_id'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
