<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

$module = trim((string)($_GET['module'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$limit = (int)($_GET['limit'] ?? 0);

$where = ['user_id = ?'];
$types = 'i';
$params = [$uid];

if ($module !== '') {
    $where[] = 'module = ?';
    $types .= 's';
    $params[] = $module;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(action LIKE ? OR module LIKE ? OR description LIKE ?)';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
export_add_date_filter($where, $types, $params, 'created_at', $from, $to, true);

$sql = 'SELECT action, module, description, reference_id, created_at
        FROM tracs_activity_logs
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY created_at DESC';

if ($limit > 0 && $limit <= 5000) {
    $sql .= ' LIMIT ?';
    $types .= 'i';
    $params[] = $limit;
}

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('activity', $from, $to),
    ['Created At', 'Action', 'Module', 'Description', 'Reference'],
    $result,
    fn(array $row) => [
        $row['created_at'] ?? '',
        $row['action'] ?? '',
        $row['module'] ?? '',
        $row['description'] ?? '',
        $row['reference_id'] ?? '',
    ]
);
