<?php require '_bootstrap.php';
require_once __DIR__ . '/../../modules/activity-log/controller.php';

/** Checklist history popup: which items have been checked off, not the full
 * create/edit/delete action log. Reuses the same activity log already
 * written by task-toggle.php (action='completed' when an item is checked),
 * filtered to the Checklist module. Over-fetches raw rows since only the
 * 'completed' subset is kept. */
$AC = new ActivityLogController($conn, $uid);
$limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
$raw = $AC->getActivityByModule('Checklist', max($limit * 4, 200)) ?: [];
$completed = array_values(array_filter($raw, fn($row) => ($row['action'] ?? '') === 'completed'));
$items = array_map([$AC, 'formatActivity'], array_slice($completed, 0, $limit));
ok(['items' => $items]);
