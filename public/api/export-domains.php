<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

$allowedStatuses = ['pending transfer','locked','error epp code','move domain','done','cancelled','retransferred','transferred away','pending verification','renew period'];
$status = (string)($_GET['s'] ?? 'all');
$month = trim((string)($_GET['m'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = [];
$types = '';
$params = [];

if (in_array($status, $allowedStatuses, true)) {
    $where[] = 'transfer_status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month)) {
    $where[] = "DATE_FORMAT(process_start_date, '%Y-%m') = ?";
    $types .= 's';
    $params[] = $month;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(domain_name LIKE ? OR webnic_reseller_transfer LIKE ? OR notes LIKE ?)';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
export_add_date_filter($where, $types, $params, 'process_start_date', $from, $to);

$sql = 'SELECT domain_name, transfer_status, process_start_date, process_end_date, webnic_reseller_transfer, notes, created_at, updated_at
        FROM domain_transfers';
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY created_at DESC';

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('domains', $from, $to),
    ['Domain', 'Status', 'Process Start Date', 'Process End Date', 'Reseller', 'Notes', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        $row['domain_name'] ?? '',
        $row['transfer_status'] ?? '',
        $row['process_start_date'] ?? '',
        $row['process_end_date'] ?? '',
        $row['webnic_reseller_transfer'] ?? '',
        $row['notes'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
