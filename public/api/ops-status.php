<?php require '_bootstrap.php';

$action = $body['action'] ?? '';

if ($action === 'save') {
    $id = (int)($body['id'] ?? 0);
    $message = trim($body['message'] ?? '');
    $severity = $body['severity'] ?? 'info';

    if ($message === '') fail('Message is required');
    if (!in_array($severity, ['info','warning','critical','solved'], true)) {
        $severity = 'info';
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE ops_status SET message=?, severity=? WHERE id=?");
        if (!$stmt) fail('ops_status table is missing or invalid', 500);
        $stmt->bind_param('ssi', $message, $severity, $id);
        if (!$stmt->execute()) fail('Failed saving ops status', 500);
        $stmt->close();
        logAct($conn, $uid, 'updated', 'Ops Status', "Updated ops status: {$message}", $id);
        ok(null, 'Ops status updated');
    }

    $stmt = $conn->prepare("INSERT INTO ops_status (message,severity,is_active) VALUES (?,?,1)");
    if (!$stmt) fail('ops_status table is missing or invalid', 500);
    $stmt->bind_param('ss', $message, $severity);
    if (!$stmt->execute()) fail('Failed saving ops status', 500);
    $id = $stmt->insert_id;
    $stmt->close();
    logAct($conn, $uid, 'created', 'Ops Status', "Created ops status: {$message}", $id);
    ok(['id'=>$id], 'Ops status created');
}

if ($action === 'archive') {
    $id = (int)($body['id'] ?? 0);
    if (!$id) fail('ID required');

    $stmt = $conn->prepare("UPDATE ops_status SET is_active=0 WHERE id=?");
    if (!$stmt) fail('ops_status table is missing or invalid', 500);
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) fail('Failed archiving ops status', 500);
    $stmt->close();
    logAct($conn, $uid, 'archived', 'Ops Status', 'Archived ops status', $id);
    ok(null, 'Ops status archived');
}

fail('Invalid action', 404);
