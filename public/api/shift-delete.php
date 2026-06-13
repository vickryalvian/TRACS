<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';
require_once __DIR__ . '/shift-attachment-lib.php';

$id = (int)($body['id'] ?? 0);
if (!$id) fail('ID required');
if (!tracs_can_view_report($conn, $id)) fail_not_found();

$SC = new ShiftReportController($conn, $uid);
$report = $SC->getById($id);

if (!$report) fail('Report not found', 404);
$attachments = shift_attachment_full_list_for_report($conn, $id);

$success = $SC->delete($id);
if (!$success) fail('Error deleting report');
if ($attachments) {
    $stmt = $conn->prepare("DELETE FROM shift_report_attachments WHERE shift_report_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
    foreach ($attachments as $attachment) {
        shift_attachment_delete_files($attachment);
    }
}

logAct($conn, $uid, 'deleted', 'Shift Reports', "Deleted shift report: " . $report['title'], $id);
ok(null, 'Shift report deleted');
