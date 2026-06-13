<?php require '_bootstrap.php';
require_once __DIR__ . '/../../core/notifications.php';

api_require_any_permission(['dashboard.view', 'profile.view_own']);

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : (int)($body['limit'] ?? 20);
ok(tracs_notification_recent($conn, $uid, $limit), 'Notifications loaded');
