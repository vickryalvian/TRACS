<?php require '_bootstrap.php';
require_once __DIR__ . '/case-attachment-lib.php';
tracs_ensure_case_status_values($conn);
$rawId = $body['id'] ?? $_GET['id'] ?? null;
$id = tracs_is_positive_int($rawId) ? (int)$rawId : 0;
if (!$id) fail_not_found();
$stmt = $conn->prepare("
    SELECT c.*, COALESCE(NULLIF(c.created_by_name, ''), NULLIF(u.name, ''), u.email, 'System') AS creator_name
    FROM tracs_cases c
    LEFT JOIN tracs_users u ON u.id = c.created_by
    WHERE c.id = ?
    LIMIT 1
");
if (!$stmt) fail('Database error', 500);
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) fail('Not found', 404);
$row['attachments'] = case_attachment_list_for_case($conn, $id, $uid);
$activity = [];
$stmt = $conn->prepare("
    SELECT l.action, l.description, l.created_at,
           COALESCE(NULLIF(u.name, ''), u.email, 'System') AS actor_name
    FROM tracs_activity_logs l
    LEFT JOIN tracs_users u ON u.id = l.user_id
    WHERE l.module = 'Cases' AND l.reference_id = ?
    ORDER BY l.created_at DESC
    LIMIT 30
");
if ($stmt) {
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($item = $result->fetch_assoc()) {
        $activity[] = $item;
    }
    $stmt->close();
}
$row['activity'] = $activity;
$row['can_manage'] = tracs_user_can($conn, 'cases.manage', $uid);
$row['can_delete'] = in_array((string)($authUser['role_slug'] ?? ''), ['super_admin','admin'], true) || tracs_user_can($conn, 'cases.delete', $uid);
ok($row);
