<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

$filter = (string)($_GET['f'] ?? 'all');
$q = trim((string)($_GET['q'] ?? ''));

$where = ['user_id = ?'];
$types = 'i';
$params = [$uid];

if ($filter === 'critical') {
    $where[] = "priority = 'critical'";
} elseif ($filter === 'stuck') {
    $where[] = "status = 'stuck'";
} elseif ($filter === 'active') {
    $where[] = "status = 'active'";
} elseif ($filter === 'in_progress') {
    $where[] = "status = 'in_progress'";
} elseif ($filter === 'on_hold') {
    $where[] = "status = 'on_hold'";
} elseif ($filter === 'overdue') {
    $where[] = 'next_check_at < NOW()';
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(title LIKE ? OR notes LIKE ?)';
    $types .= 'ss';
    array_push($params, $like, $like);
}
export_add_date_filter($where, $types, $params, 'created_at', $from, $to, true);

$sql = "SELECT id, title, status, priority, next_check_at, notes, created_at, updated_at
        FROM tracs_cases
        WHERE " . implode(' AND ', $where) . "
        ORDER BY FIELD(status, 'stuck', 'active', 'in_progress', 'pending', 'on_hold', 'completed'), next_check_at ASC";

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('cases', $from, $to),
    ['Case #', 'Title', 'Status', 'Priority', 'Next Check At', 'Notes', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        $row['id'] ?? '',
        $row['title'] ?? '',
        $row['status'] ?? '',
        $row['priority'] ?? '',
        $row['next_check_at'] ?? '',
        $row['notes'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
