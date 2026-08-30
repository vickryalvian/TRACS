<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';
require_once __DIR__.'/shift-attachment-lib.php';

/**
 * Create one shift handover (an agent's end-of-shift report). The shift
 * summary is the mandatory part; items are optional extra cases. Submitted as
 * multipart so shift-level "attachments[]" screenshots can ride along in the
 * same request. Item-specific screenshots upload in a second pass by the
 * client via shift-update.php, reusing the existing per-case pipeline.
 */

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;

$shift = trim($input['shift_name'] ?? 'Shift 1');
$date = $input['active_date'] ?? date('Y-m-d');
$summary = trim((string)($input['summary'] ?? ''));
if ($summary === '') fail('Shift summary is required.');

$rawItemsInput = $input['items'] ?? [];
$rawItems = is_string($rawItemsInput) ? (json_decode($rawItemsInput, true) ?: []) : (is_array($rawItemsInput) ? $rawItemsInput : []);

$items = [];
foreach ($rawItems as $index => $item) {
    if (!is_array($item)) continue;
    $title = trim((string)($item['title'] ?? ''));
    if ($title === '') continue;
    $items[] = [
        'client_index'    => (int)$index,
        'title'           => $title,
        'details'         => (string)($item['details'] ?? ''),
        'priority'        => in_array($item['priority'] ?? '', ['low','medium','high','critical'], true) ? $item['priority'] : 'medium',
        'status'          => in_array($item['status'] ?? '', ['active','on_hold','resolved'], true) ? $item['status'] : 'active',
        'resolution_note' => trim((string)($item['resolution_note'] ?? '')),
        'resolved_at'     => trim((string)($item['resolved_at'] ?? '')),
    ];
}

$SC = new ShiftReportController($conn, $uid);
$createdItems = [];
$handoverId = 0;
$storedUploads = [];
try {
    $conn->begin_transaction();
    $handoverId = $SC->createHandover([
        'shift_name'  => $shift,
        'active_date' => $date,
        'summary'     => $summary,
    ]);
    if (!$handoverId) throw new RuntimeException('Database error');

    foreach ($items as $item) {
        $id = $SC->create([
            'shift_name'      => $shift,
            'active_date'     => $date,
            'handover_id'     => $handoverId,
            'title'           => $item['title'],
            'details'         => $item['details'],
            'priority'        => $item['priority'],
            'status'          => $item['status'],
            'resolution_note' => $item['resolution_note'],
            'resolved_at'     => $item['resolved_at'],
        ]);
        if (!$id) throw new RuntimeException('Database error');
        $createdItems[] = ['client_index' => $item['client_index'], 'id' => (int)$id, 'title' => $item['title'], 'status' => $item['status']];
    }

    shift_attachment_ensure_table($conn);
    if (!empty($_FILES['attachments'])) {
        $storedUploads = shift_attachment_store_handover_uploads($conn, $_FILES['attachments'], $handoverId, $uid);
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    foreach ($storedUploads as $upload) {
        if (!empty($upload['stored_path'])) @unlink($upload['stored_path']);
        if (!empty($upload['thumb_path'])) @unlink($upload['thumb_path']);
    }
    fail($e->getMessage() === 'Database error' ? 'Database error' : $e->getMessage(), $e->getMessage() === 'Database error' ? 500 : 400);
}

$itemCount = count($createdItems);
logAct($conn, $uid, 'created', 'Shift Reports', "Filed shift handover ({$shift}, {$date}) with {$itemCount} item" . ($itemCount === 1 ? '' : 's'), $handoverId);
foreach ($createdItems as $ci) {
    if ($ci['status'] !== 'resolved') {
        tickerEvent($conn, $uid, "New shift report added: {$ci['title']}", 'info', 'shift-reports', $ci['id']);
    }
}

ok(['handover_id' => $handoverId, 'items' => $createdItems, 'attachments' => count($storedUploads)], 'Shift handover filed');
