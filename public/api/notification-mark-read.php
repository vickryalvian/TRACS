<?php require '_bootstrap.php';
require_once __DIR__ . '/../../core/notifications.php';

api_require_any_permission(['dashboard.view', 'profile.view_own']);

$ids = $body['ids'] ?? [];
if (!is_array($ids)) $ids = [];
$affected = tracs_notification_mark_read($conn, $uid, $ids);
ok(['updated' => $affected], 'Notifications marked read');
