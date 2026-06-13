<?php

require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
require_once __DIR__ . '/../modules/shifting-assignment/ShiftingAssignmentService.php';

tracs_require_page_permission($conn, 'shifts.export');
$service = new ShiftingAssignmentService($conn, (int)($_SESSION['user_id'] ?? 0));
$data = $service->getPageData([
    'start' => $_GET['start'] ?? null,
    'end' => $_GET['end'] ?? null,
    'division_id' => $_GET['division_id'] ?? null,
    'user_id' => $_GET['user_id'] ?? null,
    'assignment_type' => $_GET['assignment_type'] ?? null,
    'status' => $_GET['status'] ?? null,
    'q' => $_GET['q'] ?? null,
]);

$filename = 'tracs-workload-' . $data['range']['start'] . '-to-' . $data['range']['end'] . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'wb');
fputcsv($output, [
    'Date Range', 'Agent', 'Division', 'Working Days', 'Total Hours', 'Regular Hours',
    'Overtime Hours', 'Holiday Hours', 'Standby Hours', 'Target Hours', 'Difference Hours',
    'Jumpshift Count', 'Conflict Count', 'Status',
]);
foreach ($data['recap'] as $row) {
    fputcsv($output, [
        $data['range']['start'] . ' - ' . $data['range']['end'],
        $row['agent_name'],
        $row['division_name'],
        $row['working_days'],
        round($row['total_minutes'] / 60, 2),
        round($row['regular_minutes'] / 60, 2),
        round($row['overtime_minutes'] / 60, 2),
        round($row['holiday_minutes'] / 60, 2),
        round($row['standby_minutes'] / 60, 2),
        round($row['target_minutes'] / 60, 2),
        round($row['difference_minutes'] / 60, 2),
        $row['jumpshift_count'],
        $row['conflict_count'],
        $row['status'],
    ]);
}
fclose($output);