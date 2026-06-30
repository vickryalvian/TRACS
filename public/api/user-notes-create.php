<?php require '_bootstrap.php';
require_once __DIR__ . '/../../core/user_notes.php';

if (!tracs_is_supervisor_or_above($conn, $uid)) {
    fail('Forbidden', 403);
}

$targetUserId = tracs_is_positive_int($body['user_id'] ?? null) ? (int)$body['user_id'] : 0;
if (!$targetUserId) fail('user_id required', 422);

$target = tracs_get_user_by_id($conn, $targetUserId);
if (!$target) fail_not_found();

$category = (string)($body['category'] ?? 'administrative');
$content = (string)($body['content'] ?? '');
if (trim($content) === '') fail('Note content required', 422);

$id = tracs_user_note_create($conn, $targetUserId, $uid, $category, $content);
if (!$id) fail('Unable to save note', 500);

logAct($conn, $uid, 'created', 'UserNotes', "Added internal note for user #{$targetUserId}", $targetUserId);
ok(['id' => $id], 'Note added');
