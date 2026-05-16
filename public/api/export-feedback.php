<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

$q = trim((string)($_GET['q'] ?? ''));
$service = trim((string)($_GET['service'] ?? ''));
$reason = trim((string)($_GET['reason'] ?? ''));
$resolution = trim((string)($_GET['resolution'] ?? ''));

$where = ['1=1'];
$types = '';
$params = [];

if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(email_address LIKE ? OR whmcs_reference LIKE ? OR submitter_name LIKE ? OR cancelled_service LIKE ? OR additional_details LIKE ?)';
    $types .= 'sssss';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($service !== '') {
    $where[] = 'cancelled_service = ?';
    $types .= 's';
    $params[] = $service;
}
if ($reason !== '') {
    $where[] = 'cancellation_reason = ?';
    $types .= 's';
    $params[] = $reason;
}
if ($resolution !== '') {
    $where[] = 'payment_resolution = ?';
    $types .= 's';
    $params[] = $resolution;
}

export_add_date_filter($where, $types, $params, 'created_at', $from, $to, true);

$sql = 'SELECT id, submitter_name, cancelled_service, cancellation_reason, additional_details,
               whmcs_reference, email_address, payment_resolution, created_at, updated_at
        FROM tracs_cancellation_feedback
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY created_at DESC';

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('cancellation_feedback', $from, $to),
    ['Feedback #', 'Submitter', 'Service', 'Reason', 'Details', 'Reference', 'Email', 'Resolution', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        $row['id'] ?? '',
        $row['submitter_name'] ?? '',
        $row['cancelled_service'] ?? '',
        $row['cancellation_reason'] ?? '',
        $row['additional_details'] ?? '',
        $row['whmcs_reference'] ?? '',
        $row['email_address'] ?? '',
        $row['payment_resolution'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
