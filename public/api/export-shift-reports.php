<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

$date = export_date_param('date');
$shift = trim((string)($_GET['shift'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$priority = trim((string)($_GET['priority'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

$where = ['1=1'];
$types = '';
$params = [];

if ($date !== null) {
    $where[] = 'r.active_date = ?';
    $types .= 's';
    $params[] = $date;
}
if (in_array($shift, ['Shift 1', 'Shift 2', 'Shift 3'], true)) {
    $where[] = 'r.shift_name = ?';
    $types .= 's';
    $params[] = $shift;
}
if (in_array($status, ['active', 'on_hold', 'resolved'], true)) {
    $where[] = 'r.status = ?';
    $types .= 's';
    $params[] = $status;
}
if (in_array($priority, ['critical', 'high', 'medium', 'low'], true)) {
    $where[] = 'r.priority = ?';
    $types .= 's';
    $params[] = $priority;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(r.title LIKE ? OR r.details LIKE ?)';
    $types .= 'ss';
    array_push($params, $like, $like);
}
export_add_date_filter($where, $types, $params, 'r.active_date', $from, $to);

$sql = 'SELECT r.active_date, r.shift_name, r.title, r.details, r.priority, r.status, r.resolution_note,
               COALESCE(NULLIF(r.created_by_name,\'\'), NULLIF(u.name,\'\'), u.email, \'System\') AS creator_name,
               r.resolved_at, r.created_at, r.updated_at
        FROM tracs_shift_reports r
        LEFT JOIN tracs_users u ON r.created_by = u.id
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY r.active_date DESC, FIELD(r.status, \'active\', \'on_hold\', \'resolved\'), r.created_at DESC';

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('shift_reports', $from, $to),
    ['Section', 'Active Date', 'Shift', 'Title', 'Details', 'Priority', 'Status', 'Resolution Note', 'Created By', 'Resolved At', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        ($row['status'] ?? '') === 'resolved' ? 'Resolved This Shift' : ((($row['status'] ?? '') === 'on_hold') ? 'On Hold / Monitoring' : 'Needs Handover'),
        $row['active_date'] ?? '',
        $row['shift_name'] ?? '',
        $row['title'] ?? '',
        $row['details'] ?? '',
        $row['priority'] ?? '',
        $row['status'] ?? '',
        $row['resolution_note'] ?? '',
        $row['creator_name'] ?? '',
        $row['resolved_at'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
