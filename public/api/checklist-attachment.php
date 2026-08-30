<?php require '_bootstrap.php';
require_once __DIR__ . '/checklist-attachment-lib.php';

$id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
if (!$id) fail_not_found();

$attachment = checklist_attachment_fetch_for_user($conn, $id);
if (!$attachment || !tracs_can_view_checklist_item($conn, (int)($attachment['task_id'] ?? 0))) fail_not_found();

$variant = ($_GET['variant'] ?? '') === 'thumb' ? 'thumbnail_filename' : 'stored_filename';
$fileName = basename((string)($attachment[$variant] ?? ''));
if ($fileName === '') fail_not_found();

$path = checklist_attachment_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
if (!is_file($path)) fail_not_found();

header('Content-Type: ' . (string)$attachment['mime_type']);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . checklist_attachment_sanitize_original((string)$attachment['original_filename']) . '"');
header('Cache-Control: private, max-age=86400');
readfile($path);
exit;
