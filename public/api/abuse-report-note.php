<?php
require '_bootstrap.php';
require_once __DIR__ . '/../../modules/abuse-report/controller.php';

$id = tracs_is_positive_int($body['id'] ?? null) ? (int)$body['id'] : 0;
$note = (string)($body['note'] ?? '');
if (!$id) {
    fail('ID required', 422);
}

$controller = new AbuseReportController($conn, $uid);
try {
    $conn->begin_transaction();
    $noteId = $controller->addNote($id, $note, $creator_name);
    $report = $controller->getReportDetail($id);
    logAct($conn, $uid, 'note_added', 'Abuse Reports', 'Added internal note to abuse report: ' . ($report['title'] ?? 'Untitled'), $id);
    tickerEvent($conn, $uid, 'Abuse report note added: ' . ($report['report_number'] ?? ('#' . $id)), 'info', 'abuse_reports', $id);
    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    error_log('TRACS abuse report note failed: ' . $e->getMessage());
    fail($e->getMessage() === 'Not found' ? 'Not found' : ($e->getMessage() === 'Note is required' ? 'Note is required' : 'Note could not be saved'), $e->getMessage() === 'Not found' ? 404 : 400);
}

ok(['id' => $noteId, 'report' => $report], 'Note added');
