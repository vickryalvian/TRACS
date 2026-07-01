<?php
/**
 * case-reorder.php
 *
 * Persists the manual Workflow Board order for one status column. The board is
 * Trello-style: each status column keeps its own ordering (board_order, lower =
 * higher in the column). Optionally also moves cards into this column's status
 * (cross-column drag) in the same transaction.
 *
 * Method/permission enforced in _bootstrap.php (POST, cases.view). Per product
 * spec every authenticated user who can view cases may reorder/move cards.
 *
 * Body:
 *   status       string  target column status (active|pending|in_progress|stuck|on_hold|completed)
 *   ordered_ids  int[]   full ordered list of case IDs that should now live in this column, top→bottom
 */

require '_bootstrap.php';
tracs_ensure_case_status_values($conn);
tracs_ensure_case_board_order($conn);

$status = strtolower(trim((string)($body['status'] ?? '')));
$allowedStatuses = ['active', 'pending', 'in_progress', 'stuck', 'on_hold', 'completed'];
if (!in_array($status, $allowedStatuses, true)) {
    fail('Invalid target column', 422);
}

$rawIds = $body['ordered_ids'] ?? [];
if (!is_array($rawIds)) {
    fail('ordered_ids must be an array', 422);
}
// Sanitize: positive ints, de-duplicated, order preserved, capped for safety.
$orderedIds = [];
$seen = [];
foreach ($rawIds as $rawId) {
    $cid = (int)$rawId;
    if ($cid > 0 && !isset($seen[$cid])) {
        $seen[$cid] = true;
        $orderedIds[] = $cid;
    }
}
if (count($orderedIds) > 2000) {
    fail('Too many cases in one column', 422);
}

$movedStatus = 0;
$reordered = count($orderedIds);

try {
    $conn->begin_transaction();

    if ($orderedIds !== []) {
        $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
        $types = str_repeat('i', count($orderedIds));

        // Lock the affected rows and learn their current status so we can log
        // cross-column moves. Only cases that actually exist are touched.
        $stmt = $conn->prepare("SELECT id, status, title FROM tracs_cases WHERE id IN ($placeholders) FOR UPDATE");
        if (!$stmt) {
            throw new RuntimeException('Unable to load cases');
        }
        $stmt->bind_param($types, ...$orderedIds);
        $stmt->execute();
        $existing = [];
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $existing[(int)$row['id']] = $row;
        }
        $stmt->close();

        // Persist ordering (and status for any card dragged in from another
        // column) one row at a time — the list is small per column.
        $update = $conn->prepare("UPDATE tracs_cases SET board_order = ?, status = ?, updated_at = NOW() WHERE id = ?");
        if (!$update) {
            throw new RuntimeException('Unable to update order');
        }
        $position = 0;
        foreach ($orderedIds as $cid) {
            if (!isset($existing[$cid])) {
                continue; // skip unknown/stale ids
            }
            $prevStatus = strtolower((string)$existing[$cid]['status']);
            if ($prevStatus !== $status) {
                $movedStatus++;
            }
            $update->bind_param('isi', $position, $status, $cid);
            if (!$update->execute()) {
                $update->close();
                throw new RuntimeException('Unable to update order');
            }
            $position++;
        }
        $update->close();
    }

    // One compact activity-log entry per reorder action (avoids log spam).
    $description = sprintf(
        'Workflow board reordered: %d card%s in %s column%s',
        $reordered,
        $reordered === 1 ? '' : 's',
        ucfirst(str_replace('_', ' ', $status)),
        $movedStatus > 0 ? sprintf(' (%d moved in)', $movedStatus) : ''
    );
    $action = 'board_reordered';
    $module = 'Cases';
    $logStmt = $conn->prepare("
        INSERT INTO tracs_activity_logs (user_id, action, module, description, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    if ($logStmt) {
        $logStmt->bind_param('isss', $uid, $action, $module, $description);
        $logStmt->execute();
        $logStmt->close();
    }

    $conn->commit();
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable) {
    }
    error_log('TRACS case reorder failed: ' . $e->getMessage());
    fail('Case order could not be saved', 500);
}

ok(['status' => $status, 'reordered' => $reordered, 'moved' => $movedStatus], 'Board order saved');
