<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

const TRACS_SHIFT_ATTACHMENT_MAX_BYTES = 5242880;
const TRACS_SHIFT_ATTACHMENT_MAX_DIMENSION = 2200;
const TRACS_SHIFT_ATTACHMENT_MAX_PIXELS = 40000000;
const TRACS_SHIFT_ATTACHMENT_THUMB_MAX = 460;
const TRACS_SHIFT_ATTACHMENT_QUALITY = 82;

function shift_attachment_allowed_mimes(): array {
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function shift_attachment_ensure_table(mysqli $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS `shift_report_attachments` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `shift_report_id` INT UNSIGNED NOT NULL,
          `original_filename` VARCHAR(255) NOT NULL,
          `stored_filename` VARCHAR(255) NOT NULL,
          `thumbnail_filename` VARCHAR(255) NOT NULL,
          `mime_type` VARCHAR(100) NOT NULL,
          `file_size` INT UNSIGNED NOT NULL,
          `uploaded_by` INT UNSIGNED NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_shift_report_attachments_report` (`shift_report_id`),
          KEY `idx_shift_report_attachments_uploaded_by` (`uploaded_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sql)) {
        if (function_exists('fail')) fail('Shift attachment storage is not ready.', 500);
        throw new RuntimeException('Shift attachment storage is not ready.');
    }
}

function shift_attachment_storage_dir(): string {
    $uploads = __DIR__ . '/../uploads';
    if (!is_dir($uploads) && !mkdir($uploads, 0750, true)) {
        if (function_exists('fail')) fail('Upload storage is not available.', 500);
        throw new RuntimeException('Upload storage is not available.');
    }
    $base = realpath($uploads);
    if ($base === false) {
        if (function_exists('fail')) fail('Upload storage is not available.', 500);
        throw new RuntimeException('Upload storage is not available.');
    }
    $dir = $base . DIRECTORY_SEPARATOR . 'shift_report_attachments';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        if (function_exists('fail')) fail('Shift attachment storage is not writable.', 500);
        throw new RuntimeException('Shift attachment storage is not writable.');
    }
    foreach (['.htaccess' => "Options -Indexes\nRequire all denied\n", 'index.html' => ''] as $file => $content) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            @file_put_contents($path, $content);
            @chmod($path, 0640);
        }
    }
    return $dir;
}

function shift_attachment_sanitize_original(string $name): string {
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($name)) ?: 'image';
    return substr(trim($name, '._-'), 0, 180) ?: 'image';
}

function shift_attachment_resource(string $path, string $mime) {
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function shift_attachment_save_resource($source, int $sourceW, int $sourceH, int $maxDimension, string $dest, string $mime): bool {
    $ratio = min(1, $maxDimension / max($sourceW, $sourceH));
    $targetW = max(1, (int)round($sourceW * $ratio));
    $targetH = max(1, (int)round($sourceH * $ratio));
    $canvas = imagecreatetruecolor($targetW, $targetH);
    if (in_array($mime, ['image/png', 'image/webp'], true)) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    }
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $sourceW, $sourceH);
    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($canvas, $dest, TRACS_SHIFT_ATTACHMENT_QUALITY),
        'image/png' => imagepng($canvas, $dest, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $dest, TRACS_SHIFT_ATTACHMENT_QUALITY) : false,
        default => false,
    };
    imagedestroy($canvas);
    if ($saved) @chmod($dest, 0640);
    return (bool)$saved;
}

function shift_attachment_normalize_files(array $files): array {
    if (!isset($files['name'])) return [];
    if (!is_array($files['name'])) return [$files];
    $normalized = [];
    foreach ($files['name'] as $idx => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $files['type'][$idx] ?? '',
            'tmp_name' => $files['tmp_name'][$idx] ?? '',
            'error' => $files['error'][$idx] ?? UPLOAD_ERR_NO_FILE,
            'size' => $files['size'][$idx] ?? 0,
        ];
    }
    return $normalized;
}

function shift_attachment_store_upload(mysqli $conn, array $file, int $reportId, int $uid): array {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a valid image before uploading.');
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('One screenshot could not be uploaded. Please try again.');
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) throw new RuntimeException('Empty image uploads are not allowed.');
    if ($size > TRACS_SHIFT_ATTACHMENT_MAX_BYTES) throw new RuntimeException('Each shift screenshot must be 5MB or smaller.');
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) throw new RuntimeException('Invalid image upload.');

    $allowed = shift_attachment_allowed_mimes();
    $mime = strtolower((string)(new finfo(FILEINFO_MIME_TYPE))->file($tmpName));
    $info = @getimagesize($tmpName);
    $detectedMime = strtolower((string)($info['mime'] ?? ''));
    if (!isset($allowed[$mime]) || !$info || $detectedMime !== $mime) {
        throw new RuntimeException('Only JPG, JPEG, PNG, and WEBP images are supported.');
    }
    $width = (int)($info[0] ?? 0);
    $height = (int)($info[1] ?? 0);
    if ($width < 1 || $height < 1 || ($width * $height) > TRACS_SHIFT_ATTACHMENT_MAX_PIXELS) {
        throw new RuntimeException('The uploaded image dimensions are not supported.');
    }
    $dir = shift_attachment_storage_dir();
    $token = bin2hex(random_bytes(16));
    $ext = $allowed[$mime];
    $stored = 'shift_' . $reportId . '_' . $token . '.' . $ext;
    $thumb = 'shift_' . $reportId . '_' . $token . '_thumb.' . $ext;
    $storedPath = $dir . DIRECTORY_SEPARATOR . $stored;
    $thumbPath = $dir . DIRECTORY_SEPARATOR . $thumb;
    $resource = shift_attachment_resource($tmpName, $mime);
    if ($resource !== false) {
        $savedFull = shift_attachment_save_resource($resource, $width, $height, TRACS_SHIFT_ATTACHMENT_MAX_DIMENSION, $storedPath, $mime);
        $savedThumb = $savedFull && shift_attachment_save_resource($resource, $width, $height, TRACS_SHIFT_ATTACHMENT_THUMB_MAX, $thumbPath, $mime);
        imagedestroy($resource);
    } else {
        $savedFull = move_uploaded_file($tmpName, $storedPath);
        $savedThumb = $savedFull && copy($storedPath, $thumbPath);
        if ($savedFull) @chmod($storedPath, 0640);
        if ($savedThumb) @chmod($thumbPath, 0640);
    }
    if (!$savedFull || !$savedThumb) {
        @unlink($storedPath);
        @unlink($thumbPath);
        throw new RuntimeException('Unable to save shift screenshot.');
    }

    $original = shift_attachment_sanitize_original((string)($file['name'] ?? 'image'));
    $finalSize = (int)filesize($storedPath);
    $stmt = $conn->prepare("
        INSERT INTO shift_report_attachments
            (shift_report_id, original_filename, stored_filename, thumbnail_filename, mime_type, file_size, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        @unlink($storedPath);
        @unlink($thumbPath);
        throw new RuntimeException('Unable to save shift screenshot metadata.');
    }
    $stmt->bind_param('issssii', $reportId, $original, $stored, $thumb, $mime, $finalSize, $uid);
    if (!$stmt->execute()) {
        @unlink($storedPath);
        @unlink($thumbPath);
        $stmt->close();
        throw new RuntimeException('Unable to save shift screenshot metadata.');
    }
    $stmt->close();
    return ['stored_path' => $storedPath, 'thumb_path' => $thumbPath];
}

function shift_attachment_store_uploads(mysqli $conn, array $files, int $reportId, int $uid): array {
    $stored = [];
    foreach (shift_attachment_normalize_files($files) as $file) {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $stored[] = shift_attachment_store_upload($conn, $file, $reportId, $uid);
    }
    return $stored;
}

function shift_attachment_fetch_for_user(mysqli $conn, int $attachmentId, int $uid): ?array {
    shift_attachment_ensure_table($conn);
    $stmt = $conn->prepare("
        SELECT a.*
        FROM shift_report_attachments a
        INNER JOIN tracs_shift_reports r ON r.id = a.shift_report_id
        WHERE a.id = ?
        LIMIT 1
    ");
    if (!$stmt) return null;
    $stmt->bind_param('i', $attachmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function shift_attachment_list_for_report(mysqli $conn, int $reportId): array {
    shift_attachment_ensure_table($conn);
    $stmt = $conn->prepare("
        SELECT id, shift_report_id, original_filename, mime_type, file_size, uploaded_by, created_at
        FROM shift_report_attachments
        WHERE shift_report_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    if (!$stmt) return [];
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        $id = (int)$row['id'];
        $row['thumbnail_url'] = '/api/shift-attachment.php?id=' . $id . '&variant=thumb';
        $row['image_url'] = '/api/shift-attachment.php?id=' . $id;
    }
    return $rows;
}

function shift_attachment_full_list_for_report(mysqli $conn, int $reportId): array {
    shift_attachment_ensure_table($conn);
    $stmt = $conn->prepare("SELECT * FROM shift_report_attachments WHERE shift_report_id = ?");
    if (!$stmt) return [];
    $stmt->bind_param('i', $reportId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function shift_attachment_delete_files(array $attachment): void {
    $dir = shift_attachment_storage_dir();
    foreach (['stored_filename', 'thumbnail_filename'] as $key) {
        $name = basename((string)($attachment[$key] ?? ''));
        if ($name === '') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) @unlink($path);
    }
}
