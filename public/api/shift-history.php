<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

$SC = new ShiftReportController($conn, $uid);

$filters = [
    'date' => $_GET['date'] ?? null,
    'shift' => $_GET['shift'] ?? null,
    'status' => $_GET['status'] ?? null,
    'priority' => $_GET['priority'] ?? null,
];
$page = (int)($_GET['page'] ?? 1);
$limit = 50;
$offset = ($page - 1) * $limit;

$history = $SC->getHistory($filters, $limit, $offset);

ok(['history' => $history]);
