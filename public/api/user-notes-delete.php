<?php require '_bootstrap.php';
require_once __DIR__ . '/../../core/user_notes.php';

if (!tracs_is_supervisor_or_above($conn, $uid)) {
    fail('Forbidden', 403);
}

$noteId = tracs_is_positive_int($body['id'] ?? null) ? (int)$body['id'] : 0;
if (!$noteId) fail('id required', 422);

$note = tracs_user_note_find($conn, $noteId);
if (!$note) fail_not_found();

if (!tracs_user_note_delete($conn, $noteId)) {
    fail('Unable to delete note', 500);
}

logAct($conn, $uid, 'deleted', 'UserNotes', "Deleted internal note for user #{$note['target_user_id']}", (int)$note['target_user_id']);
ok(null, 'Note deleted');
