<?php require '_bootstrap.php';
require_once __DIR__ . '/../../core/user_notes.php';

if (!tracs_is_supervisor_or_above($conn, $uid)) {
    fail('Forbidden', 403);
}

$targetUserId = tracs_is_positive_int($_GET['user_id'] ?? null) ? (int)$_GET['user_id'] : 0;
if (!$targetUserId) fail('user_id required', 422);

$target = tracs_get_user_by_id($conn, $targetUserId);
if (!$target) fail_not_found();

ok(['notes' => tracs_user_notes_list($conn, $targetUserId)]);
