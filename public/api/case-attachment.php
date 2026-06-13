<?php
require '_bootstrap.php';
require_once __DIR__ . '/case-attachment-lib.php';

$id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
if (!$id) {
    fail_not_found();
}

$attachment = case_attachment_fetch_for_user($conn, $id, $uid);
if (!$attachment) {
    fail_not_found();
}

$variant = (string)($_GET['variant'] ?? 'image');
$fileName = $variant === 'thumb'
    ? basename((string)$attachment['thumbnail_filename'])
    : basename((string)$attachment['stored_filename']);
if ($fileName === '') {
    fail_not_found();
}

$path = case_attachment_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
if (!is_file($path)) {
    fail_not_found();
}

$download = ($_GET['download'] ?? '') === '1';
$displayName = case_attachment_sanitize_original((string)$attachment['original_filename']);
header_remove('Content-Type');
header('Content-Type: ' . (string)$attachment['mime_type']);
header('Content-Length: ' . (string)filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=300');
header(
    'Content-Disposition: ' .
    ($download ? 'attachment' : 'inline') .
    '; filename="' . addcslashes($displayName, '\\"') . '"'
);
readfile($path);
exit;
