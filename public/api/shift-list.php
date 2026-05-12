<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

$SC = new ShiftReportController($conn, $uid);
$grouped = $SC->getTodayByShift();
$stats = $SC->getTodayStats();

ok(['reports' => $grouped, 'stats' => $stats]);
