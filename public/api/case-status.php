<?php
require '_bootstrap.php';
tracs_ensure_case_status_values($conn);

$id = tracs_is_positive_int($body['id'] ?? null) ? (int)$body['id'] : 0;
$status = strtolower(trim((string)($body['status'] ?? '')));
$source = strtolower(trim((string)($body['source'] ?? 'manual')));
$allowedStatuses = ['active', 'pending', 'in_progress', 'stuck', 'on_hold', 'completed'];
$allowedSources = ['drag_drop', 'quick_action', 'drawer_action', 'manual'];

if (!$id || !in_array($status, $allowedStatuses, true)) {
    fail('Invalid case update', 422);
}
if (!in_array($source, $allowedSources, true)) {
    $source = 'manual';
}

$note = trim((string)($body['note'] ?? ''));
$note = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $note) ?? '';
if (function_exists('mb_substr')) {
    $note = mb_substr($note, 0, 2000);
} else {
    $note = substr($note, 0, 2000);
}

$labels = [
    'active' => 'Active',
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'stuck' => 'Stuck',
    'on_hold' => 'On Hold',
    'completed' => 'Resolved',
];

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare("
        SELECT id, title, status, priority, next_check_at, notes, created_at, updated_at
        FROM tracs_cases
        WHERE id = ? AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to load case');
    }
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$case) {
        $conn->rollback();
        fail_not_found();
    }

    $previous = strtolower((string)($case['status'] ?? 'pending'));
    if ($previous === $status) {
        $conn->commit();
        $case['status'] = $status;
        ok($case, 'Case is already in this workflow stage');
    }

    $stmt = $conn->prepare("UPDATE tracs_cases SET status = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
    if (!$stmt) {
        throw new RuntimeException('Unable to update case');
    }
    $stmt->bind_param('sii', $status, $id, $uid);
    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();
        throw new RuntimeException('Unable to update case');
    }
    $stmt->close();

    $description = sprintf(
        'Case status changed from %s to %s via %s: %s',
        $labels[$previous] ?? ucfirst(str_replace('_', ' ', $previous)),
        $labels[$status],
        $source,
        (string)$case['title']
    );
    if ($note !== '') {
        $description .= '. Note: ' . $note;
    }

    $action = 'status_changed';
    $module = 'Cases';
    $stmt = $conn->prepare("
        INSERT INTO tracs_activity_logs (user_id, action, module, description, reference_id, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to record case activity');
    }
    $stmt->bind_param('isssi', $uid, $action, $module, $description, $id);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to record case activity');
    }
    $stmt->close();

    $stmt = $conn->prepare("
        SELECT id, title, status, priority, next_check_at, notes, created_at, updated_at
        FROM tracs_cases
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        throw new RuntimeException('Unable to reload case');
    }
    $stmt->bind_param('ii', $id, $uid);
    $stmt->execute();
    $updated = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    error_log('TRACS case status update failed: ' . $e->getMessage());
    fail('Case status could not be updated', 500);
}

$tickerType = $status === 'completed' ? 'success' : ($status === 'stuck' ? 'urgent' : 'info');
tickerEvent($conn, $uid, "Case #{$id} moved to {$labels[$status]}: {$updated['title']}", $tickerType, 'cases', $id);
ok($updated, 'Case status updated');
