<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

const TRACS_CASE_ATTACHMENT_MAX_BYTES = 5242880;
const TRACS_CASE_ATTACHMENT_MAX_DIMENSION = 2400;
const TRACS_CASE_ATTACHMENT_MAX_PIXELS = 40000000;
const TRACS_CASE_ATTACHMENT_THUMB_MAX = 420;
const TRACS_CASE_ATTACHMENT_QUALITY = 82;

function case_attachment_allowed_mimes(): array {
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
}

function case_attachment_ensure_table(mysqli $conn): void {
    $sql = "
        CREATE TABLE IF NOT EXISTS `case_attachments` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `case_id` INT NOT NULL,
          `original_filename` VARCHAR(255) NOT NULL,
          `stored_filename` VARCHAR(255) NOT NULL,
          `thumbnail_filename` VARCHAR(255) NOT NULL,
          `file_path` VARCHAR(255) NOT NULL,
          `thumbnail_path` VARCHAR(255) NOT NULL,
          `mime_type` VARCHAR(100) NOT NULL,
          `file_size` INT UNSIGNED NOT NULL,
          `uploaded_by` INT NOT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_case_attachments_case` (`case_id`),
          KEY `idx_case_attachments_uploaded_by` (`uploaded_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    try {
        if ($conn->query($sql)) {
            return;
        }
        $error = $conn->error;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    error_log('TRACS case attachment table ensure failed: ' . $error);
    if (function_exists('fail')) {
        fail('Attachment storage is not ready.', 500);
    }
    throw new RuntimeException('Attachment storage is not ready.');
}

function case_attachment_storage_dir(): string {
    $uploads = __DIR__ . '/../uploads';
    if (!is_dir($uploads) && !mkdir($uploads, 0750, true)) {
        fail('Upload storage is not available.', 500);
    }
    $base = realpath($uploads);
    if ($base === false) {
        fail('Upload storage is not available.', 500);
    }
    $dir = $base . DIRECTORY_SEPARATOR . 'case_attachments';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        fail('Case attachment storage is not writable.', 500);
    }

    $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\nRequire all denied\n");
        @chmod($htaccess, 0640);
    }
    $index = $dir . DIRECTORY_SEPARATOR . 'index.html';
    if (!is_file($index)) {
        @file_put_contents($index, '');
        @chmod($index, 0640);
    }

    return $dir;
}

function case_attachment_sanitize_original(string $name): string {
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'image';
    return substr(trim($name, '._-'), 0, 180) ?: 'image';
}

function case_attachment_resource(string $path, string $mime) {
    return match ($mime) {
        'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
        'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
        default => false,
    };
}

function case_attachment_save_resource($source, int $sourceW, int $sourceH, int $maxDimension, string $dest, string $mime): bool {
    $ratio = min(1, $maxDimension / max($sourceW, $sourceH));
    $targetW = max(1, (int)round($sourceW * $ratio));
    $targetH = max(1, (int)round($sourceH * $ratio));
    $canvas = imagecreatetruecolor($targetW, $targetH);
    if (in_array($mime, ['image/png', 'image/webp'], true)) {
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, $transparent);
    }
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $sourceW, $sourceH);

    $saved = match ($mime) {
        'image/jpeg' => imagejpeg($canvas, $dest, TRACS_CASE_ATTACHMENT_QUALITY),
        'image/png' => imagepng($canvas, $dest, 6),
        'image/webp' => function_exists('imagewebp') ? imagewebp($canvas, $dest, TRACS_CASE_ATTACHMENT_QUALITY) : false,
        default => false,
    };
    imagedestroy($canvas);
    if ($saved) {
        @chmod($dest, 0640);
    }
    return (bool)$saved;
}

function case_attachment_normalize_files(array $files): array {
    if (!isset($files['name'])) {
        return [];
    }
    if (!is_array($files['name'])) {
        return [$files];
    }
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

function case_attachment_store_upload(mysqli $conn, array $file, int $caseId, int $uid): array {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose a valid image before uploading.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One attachment could not be uploaded. Please try again.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Empty image uploads are not allowed.');
    }
    if ($size > TRACS_CASE_ATTACHMENT_MAX_BYTES) {
        throw new RuntimeException('Each case attachment must be 5MB or smaller.');
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid image upload.');
    }

    $allowed = case_attachment_allowed_mimes();
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string)$finfo->file($tmpName));
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, JPEG, PNG, and WEBP images are supported.');
    }

    $info = @getimagesize($tmpName);
    $detectedMime = strtolower((string)($info['mime'] ?? ''));
    if (!$info || !isset($allowed[$detectedMime]) || $detectedMime !== $mime) {
        throw new RuntimeException('The uploaded file is not a readable image.');
    }
    $width = (int)($info[0] ?? 0);
    $height = (int)($info[1] ?? 0);
    if ($width < 1 || $height < 1) {
        throw new RuntimeException('The uploaded image is invalid.');
    }
    if (($width * $height) > TRACS_CASE_ATTACHMENT_MAX_PIXELS) {
        throw new RuntimeException('The uploaded image dimensions are too large.');
    }

    $resource = case_attachment_resource($tmpName, $mime);
    if ($resource === false) {
        throw new RuntimeException('The uploaded image could not be processed safely.');
    }

    $dir = case_attachment_storage_dir();
    $original = case_attachment_sanitize_original((string)($file['name'] ?? 'image'));
    $token = bin2hex(random_bytes(16));
    $ext = $allowed[$mime];
    $stored = 'case_' . $caseId . '_' . $token . '.' . $ext;
    $thumb = 'case_' . $caseId . '_' . $token . '_thumb.' . $ext;
    $storedPath = $dir . DIRECTORY_SEPARATOR . $stored;
    $thumbPath = $dir . DIRECTORY_SEPARATOR . $thumb;

    $savedFull = case_attachment_save_resource($resource, $width, $height, TRACS_CASE_ATTACHMENT_MAX_DIMENSION, $storedPath, $mime);
    $savedThumb = $savedFull && case_attachment_save_resource($resource, $width, $height, TRACS_CASE_ATTACHMENT_THUMB_MAX, $thumbPath, $mime);
    imagedestroy($resource);
    if (!$savedFull || !$savedThumb || !is_file($storedPath) || !is_file($thumbPath)) {
        @unlink($storedPath);
        @unlink($thumbPath);
        throw new RuntimeException('Unable to save case attachment.');
    }

    $finalSize = (int)filesize($storedPath);
    if ($finalSize <= 0) {
        @unlink($storedPath);
        @unlink($thumbPath);
        throw new RuntimeException('Unable to save case attachment.');
    }

    $relative = 'case_attachments/' . $stored;
    $thumbRelative = 'case_attachments/' . $thumb;
    $stmt = $conn->prepare("
        INSERT INTO case_attachments
            (case_id, original_filename, stored_filename, thumbnail_filename, file_path, thumbnail_path, mime_type, file_size, uploaded_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        @unlink($storedPath);
        @unlink($thumbPath);
        throw new RuntimeException('Unable to save case attachment metadata.');
    }
    $stmt->bind_param('issssssii', $caseId, $original, $stored, $thumb, $relative, $thumbRelative, $mime, $finalSize, $uid);
    if (!$stmt->execute()) {
        @unlink($storedPath);
        @unlink($thumbPath);
        $stmt->close();
        throw new RuntimeException('Unable to save case attachment metadata.');
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();

    return [
        'id' => $id,
        'stored_path' => $storedPath,
        'thumb_path' => $thumbPath,
    ];
}

function case_attachment_store_uploads(mysqli $conn, array $files, int $caseId, int $uid): array {
    $stored = [];
    foreach (case_attachment_normalize_files($files) as $file) {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $stored[] = case_attachment_store_upload($conn, $file, $caseId, $uid);
    }
    return $stored;
}

function case_attachment_delete_files(array $attachment): void {
    $dir = case_attachment_storage_dir();
    foreach (['stored_filename', 'thumbnail_filename'] as $key) {
        $name = basename((string)($attachment[$key] ?? ''));
        if ($name === '') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            @unlink($path);
        }
    }
}

function case_attachment_fetch_for_user(mysqli $conn, int $attachmentId, int $uid): ?array {
    case_attachment_ensure_table($conn);
    $stmt = $conn->prepare("
        SELECT a.*
        FROM case_attachments a
        INNER JOIN tracs_cases c ON c.id = a.case_id AND c.user_id = ?
        WHERE a.id = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('ii', $uid, $attachmentId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function case_attachment_list_for_case(mysqli $conn, int $caseId, int $uid): array {
    case_attachment_ensure_table($conn);
    $stmt = $conn->prepare("
        SELECT a.id, a.case_id, a.original_filename, a.mime_type, a.file_size, a.uploaded_by, a.created_at
        FROM case_attachments a
        INNER JOIN tracs_cases c ON c.id = a.case_id AND c.user_id = ?
        WHERE a.case_id = ?
        ORDER BY a.created_at ASC, a.id ASC
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $uid, $caseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        $id = (int)$row['id'];
        $row['thumbnail_url'] = '/api/case-attachment.php?id=' . $id . '&variant=thumb';
        $row['image_url'] = '/api/case-attachment.php?id=' . $id;
    }
    return $rows;
}

function case_attachment_delete_for_user(mysqli $conn, int $attachmentId, int $uid, bool $deleteFiles = true): bool {
    $attachment = case_attachment_fetch_for_user($conn, $attachmentId, $uid);
    if (!$attachment) {
        return false;
    }
    $stmt = $conn->prepare("DELETE FROM case_attachments WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $attachmentId);
    $ok = $stmt->execute();
    $stmt->close();
    if ($ok && $deleteFiles) {
        case_attachment_delete_files($attachment);
    }
    return $ok;
}

function case_attachment_full_list_for_case(mysqli $conn, int $caseId, int $uid): array {
    case_attachment_ensure_table($conn);
    $stmt = $conn->prepare("
        SELECT a.*
        FROM case_attachments a
        INNER JOIN tracs_cases c ON c.id = a.case_id AND c.user_id = ?
        WHERE a.case_id = ?
    ");
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('ii', $uid, $caseId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}
