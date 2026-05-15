<?php
require_once __DIR__ . '/_export_helpers.php';

[$from, $to] = export_date_range();

if (!export_table_exists($conn, 'tracs_moms')) {
    export_fail('MOM database schema is not installed.', 500);
}

$status = trim((string)($_GET['status'] ?? ''));
$type = trim((string)($_GET['type'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));
$hasMeetingAt = export_column_exists($conn, 'tracs_moms', 'meeting_at');
$hasActions = export_table_exists($conn, 'tracs_mom_actions');
$dateExpr = $hasMeetingAt ? 'COALESCE(m.meeting_at, m.created_at)' : 'm.created_at';
$meetingSelect = $hasMeetingAt ? 'm.meeting_at' : 'NULL AS meeting_at';
$actionSelect = $hasActions ? '(SELECT COUNT(*) FROM tracs_mom_actions a WHERE a.mom_id = m.id)' : '0';

$where = ['m.created_by = ?'];
$types = 'i';
$params = [$uid];

if (in_array($status, ['upcoming', 'ongoing', 'completed', 'cancelled'], true)) {
    $where[] = 'm.status = ?';
    $types .= 's';
    $params[] = $status;
}
if (in_array($type, ['weekly', 'training', 'coordination', 'urgent'], true)) {
    $where[] = 'm.type = ?';
    $types .= 's';
    $params[] = $type;
}
if ($q !== '') {
    $like = '%' . $q . '%';
    $where[] = '(m.title LIKE ? OR m.objective LIKE ? OR m.participants LIKE ? OR m.summary LIKE ?)';
    $types .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}
export_add_date_filter($where, $types, $params, $dateExpr, $from, $to, true);

$sql = "SELECT m.id, m.title, m.type, m.status, m.objective, m.participants, $meetingSelect,
               m.started_at, m.completed_at, m.cancelled_at, m.summary, m.created_at, m.updated_at,
               $actionSelect AS action_count
        FROM tracs_moms m
        WHERE " . implode(' AND ', $where) . "
        ORDER BY $dateExpr DESC";

$result = export_query($conn, $sql, $types, $params);
export_send_csv(
    export_filename('moms', $from, $to),
    ['MOM #', 'Title', 'Type', 'Status', 'Objective', 'Participants', 'Meeting At', 'Action Count', 'Started At', 'Completed At', 'Cancelled At', 'Summary', 'Created At', 'Updated At'],
    $result,
    fn(array $row) => [
        $row['id'] ?? '',
        $row['title'] ?? '',
        $row['type'] ?? '',
        $row['status'] ?? '',
        $row['objective'] ?? '',
        $row['participants'] ?? '',
        $row['meeting_at'] ?? '',
        $row['action_count'] ?? '',
        $row['started_at'] ?? '',
        $row['completed_at'] ?? '',
        $row['cancelled_at'] ?? '',
        $row['summary'] ?? '',
        $row['created_at'] ?? '',
        $row['updated_at'] ?? '',
    ]
);
