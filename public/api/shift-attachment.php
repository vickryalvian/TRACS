<?php require '_bootstrap.php';
require_once __DIR__ . '/shift-attachment-lib.php';

$id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
if (!$id) fail_not_found();

$attachment = shift_attachment_fetch_for_user($conn, $id, $uid);
if (!$attachment || !tracs_can_view_report($conn, (int)$attachment['shift_report_id'])) fail_not_found();

$variant = ($_GET['variant'] ?? '') === 'thumb' ? 'thumbnail_filename' : 'stored_filename';
$fileName = basename((string)($attachment[$variant] ?? ''));
if ($fileName === '') fail_not_found();

$path = shift_attachment_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
if (!is_file($path)) fail_not_found();

header('Content-Type: ' . (string)$attachment['mime_type']);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="' . shift_attachment_sanitize_original((string)$attachment['original_filename']) . '"');
header('Cache-Control: private, max-age=86400');
readfile($path);
exit;
