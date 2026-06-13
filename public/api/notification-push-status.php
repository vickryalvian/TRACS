<?php require '_bootstrap.php';
require_once __DIR__ . '/../../core/notifications.php';

api_require_any_permission(['dashboard.view', 'profile.view_own']);

$ids = $body['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$status = (string)($body['status'] ?? 'failed');
$error = isset($body['error']) ? (string)$body['error'] : null;
$affected = tracs_notification_set_push_status($conn, $uid, $ids, $status, $error);
ok(['updated' => $affected], 'Push status updated');
