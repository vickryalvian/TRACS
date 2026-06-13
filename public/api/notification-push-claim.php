<?php require '_bootstrap.php';
require_once __DIR__ . '/../../core/notifications.php';

api_require_any_permission(['dashboard.view', 'profile.view_own']);

$ids = $body['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$permission = (string)($body['permission'] ?? 'default');
if (!in_array($permission, ['granted', 'denied', 'default', 'unsupported'], true)) {
    $permission = 'default';
}
$claimed = tracs_notification_claim_push($conn, $uid, $ids, $permission);
ok(['claimed_ids' => $claimed], 'Push claim updated');
