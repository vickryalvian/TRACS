<?php require '_bootstrap.php';
require_once __DIR__ . '/screenshot-history-lib.php';

$id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
if (!$id) fail_not_found();

$row = screenshot_history_fetch($conn, $id);
if (!$row) fail_not_found();

$variant = ($_GET['variant'] ?? '') === 'thumb' ? 'thumbnail_filename' : 'stored_filename';
$fileName = basename((string)($row[$variant] ?? ''));
if ($fileName === '') fail_not_found();

$path = screenshot_history_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
if (!is_file($path)) fail_not_found();

header('Content-Type: image/png');
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="screenshot-' . $id . '.png"');
header('Cache-Control: private, max-age=86400');
readfile($path);
exit;
