<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';

$rawId = $body['id'] ?? $_GET['id'] ?? null;
$id = tracs_is_positive_int($rawId) ? (int)$rawId : 0;
if (!$id) {
    fail_not_found();
}

$controller = new AbuseReportController($conn, $uid);
$report = $controller->getReportDetail($id);
if (!$report) {
    fail_not_found();
}
$report['can_manage'] = tracs_user_can($conn, 'abuse_reports.manage', $uid);
ok($report);
