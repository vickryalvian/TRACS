<?php
require_once __DIR__ . '/_export_helpers.php';
require_once __DIR__ . '/../../modules/cancellation-feedback/model.php';

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
    $where[] = '(f.email_address LIKE ? OR f.whmcs_reference LIKE ? OR f.submitter_name LIKE ? OR f.cancelled_service LIKE ? OR f.additional_details LIKE ? OR f.created_by_name LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $types .= 'ssssssss';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
}
if ($service !== '') {
    $where[] = '(f.cancelled_service = ? OR f.cancelled_service LIKE ?)';
    $types .= 'ss';
    $params[] = $service;
    $params[] = '%"' . $service . '"%';
}
if ($reason !== '') {
    $where[] = '(f.cancellation_reason = ? OR f.cancellation_reason LIKE ?)';
    $types .= 'ss';
    $params[] = $reason;
    $params[] = '%"' . $reason . '"%';
}
if ($resolution !== '') {
    $where[] = 'f.payment_resolution = ?';
    $types .= 's';
    $params[] = $resolution;
}

export_add_date_filter($where, $types, $params, 'f.created_at', $from, $to, true);

$sql = 'SELECT f.id,
               COALESCE(NULLIF(f.created_by_name,\'\'), NULLIF(u.name,\'\'), u.email, NULLIF(f.submitter_name,\'\'), \'System\') AS submitter_name,
               f.cancelled_service, f.cancellation_reason, f.additional_details,
               f.whmcs_reference, f.email_address, f.payment_resolution, f.created_at, f.updated_at
        FROM tracs_cancellation_feedback f
        LEFT JOIN tracs_users u ON f.created_by = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY f.created_at DESC';

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('cancellation_feedback', $from, $to),
    ['Feedback #', 'Submitter', 'Service', 'Reason', 'Details', 'Reference', 'Email', 'Resolution', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        $row['id'] ?? '',
        $row['submitter_name'] ?? '',
        cf_display_multi_value($row['cancelled_service'] ?? ''),
        cf_display_multi_value($row['cancellation_reason'] ?? ''),
        $row['additional_details'] ?? '',
        $row['whmcs_reference'] ?? '',
        $row['email_address'] ?? '',
        $row['payment_resolution'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
