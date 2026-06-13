<?php
require_once __DIR__ . '/_bootstrap.php';

$id = tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    fail_not_found();
}

$stmt = $conn->prepare("
    SELECT s.mom_id, s.filename
    FROM tracs_mom_screenshots s
    INNER JOIN tracs_moms m ON m.id = s.mom_id
    WHERE s.id = ?
    LIMIT 1
");
if (!$stmt) {
    fail_not_found();
}
$stmt->bind_param('i', $id);
$stmt->execute();
$screenshot = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$screenshot || !tracs_can_view_mom($conn, (int)$screenshot['mom_id'])) {
    fail_not_found();
}

$filename = basename((string)$screenshot['filename']);
if (!preg_match('/^mom_\d+_\d+_[a-f0-9]{8}\.(?:png|jpe?g|webp)$/i', $filename)) {
    fail_not_found();
}
$path = __DIR__ . '/../uploads/mom/' . $filename;
if (!is_file($path) || !is_readable($path)) {
    fail_not_found();
}

$mime = strtolower((string)(new finfo(FILEINFO_MIME_TYPE))->file($path));
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
    fail_not_found();
}

header_remove('Content-Type');
header('Content-Type: ' . $mime);
header('Content-Length: ' . (string)filesize($path));
header('Content-Disposition: inline; filename="mom-screenshot.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
