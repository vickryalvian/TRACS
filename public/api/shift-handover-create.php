<?php require '_bootstrap.php';
require_once __DIR__.'/../../modules/shift-reports/controller.php';

/**
 * Create one shift handover (an agent's end-of-shift report) that bundles many
 * items in a single submit. Attachments are uploaded per item in a second pass
 * by the client via shift-update.php, reusing the existing attachment pipeline.
 */

$input = $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST) ? $_POST : $body;

$shift = trim($input['shift_name'] ?? 'Shift 1');
$date = $input['active_date'] ?? date('Y-m-d');
$summary = trim((string)($input['summary'] ?? ''));

$rawItems = $input['items'] ?? [];
if (!is_array($rawItems)) $rawItems = [];

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

if (empty($items)) fail('Add at least one handover item with a title.');

$SC = new ShiftReportController($conn, $uid);
$createdItems = [];
$handoverId = 0;
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
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    fail($e->getMessage() === 'Database error' ? 'Database error' : $e->getMessage(), $e->getMessage() === 'Database error' ? 500 : 400);
}

$itemCount = count($createdItems);
logAct($conn, $uid, 'created', 'Shift Reports', "Filed shift handover ({$shift}, {$date}) with {$itemCount} item" . ($itemCount === 1 ? '' : 's'), $handoverId);
foreach ($createdItems as $ci) {
    if ($ci['status'] !== 'resolved') {
        tickerEvent($conn, $uid, "New shift report added: {$ci['title']}", 'info', 'shift-reports', $ci['id']);
    }
}

ok(['handover_id' => $handoverId, 'items' => $createdItems], 'Shift handover filed');
