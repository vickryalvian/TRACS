<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

const TRACS_SCREENSHOT_HISTORY_MAX_BYTES = 10485760;
const TRACS_SCREENSHOT_HISTORY_THUMB_MAX = 480;
const TRACS_SCREENSHOT_HISTORY_KEEP = 5;

function screenshot_history_storage_dir(): string {
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
    $dir = $base . DIRECTORY_SEPARATOR . 'screenshot_history';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        if (function_exists('fail')) fail('Screenshot history storage is not writable.', 500);
        throw new RuntimeException('Screenshot history storage is not writable.');
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

function screenshot_history_save_resized($source, int $sourceW, int $sourceH, int $maxDimension, string $dest): bool {
    $ratio = min(1, $maxDimension / max($sourceW, $sourceH));
    $targetW = max(1, (int)round($sourceW * $ratio));
    $targetH = max(1, (int)round($sourceH * $ratio));
    $canvas = imagecreatetruecolor($targetW, $targetH);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefilledrectangle($canvas, 0, 0, $targetW, $targetH, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $sourceW, $sourceH);
    $saved = imagepng($canvas, $dest, 6);
    imagedestroy($canvas);
    if ($saved) @chmod($dest, 0640);
    return (bool)$saved;
}

/**
 * Persists a raw PNG (as returned by PageFleets) plus its capture metadata:
 * full image + thumbnail on disk, one row in screenshot_history. Returns the
 * new row's id and served URLs, or null if persistence failed (capture
 * result is still shown to the user even if history logging fails).
 */
function screenshot_history_persist(
    mysqli $conn,
    string $pngBytes,
    int $uid,
    string $rawInput,
    string $host,
    string $region,
    string $regionLabel,
    array $meta
): ?array {
    $size = strlen($pngBytes);
    if ($size <= 0 || $size > TRACS_SCREENSHOT_HISTORY_MAX_BYTES) return null;

    $info = @getimagesizefromstring($pngBytes);
    if (!$info || strtolower((string)($info['mime'] ?? '')) !== 'image/png') return null;
    $width = (int)($info[0] ?? 0);
    $height = (int)($info[1] ?? 0);
    if ($width < 1 || $height < 1) return null;

    try {
        $dir = screenshot_history_storage_dir();
    } catch (Throwable $e) {
        return null;
    }
    $token = bin2hex(random_bytes(16));
    $stored = 'shot_' . $token . '.png';
    $thumb = 'shot_' . $token . '_thumb.png';
    $storedPath = $dir . DIRECTORY_SEPARATOR . $stored;
    $thumbPath = $dir . DIRECTORY_SEPARATOR . $thumb;

    if (@file_put_contents($storedPath, $pngBytes) === false) return null;
    @chmod($storedPath, 0640);

    $resource = @imagecreatefromstring($pngBytes);
    $thumbSaved = $resource !== false && screenshot_history_save_resized($resource, $width, $height, TRACS_SCREENSHOT_HISTORY_THUMB_MAX, $thumbPath);
    if ($resource !== false) imagedestroy($resource);
    if (!$thumbSaved) {
        // Full image still counts as a successful capture; fall back to it as its own thumbnail source.
        $thumb = $stored;
        $thumbPath = $storedPath;
    }

    $stmt = $conn->prepare("
        INSERT INTO screenshot_history
            (user_id, raw_input, host, region, region_label, stored_filename, thumbnail_filename,
             width, height, file_size_bytes, load_ms, dns_ms, tcp_ms, ssl_ms, ttfb_ms, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'success', NOW())
    ");
    if (!$stmt) {
        @unlink($storedPath);
        if ($thumbPath !== $storedPath) @unlink($thumbPath);
        return null;
    }
    $loadMs = isset($meta['load']) ? (int)$meta['load'] : null;
    $dnsMs  = isset($meta['dns']) ? (int)$meta['dns'] : null;
    $tcpMs  = isset($meta['tcp']) ? (int)$meta['tcp'] : null;
    $sslMs  = isset($meta['ssl']) ? (int)$meta['ssl'] : null;
    $ttfbMs = isset($meta['ttfb']) ? (int)$meta['ttfb'] : null;
    $stmt->bind_param(
        'issssssiiiiiiii',
        $uid, $rawInput, $host, $region, $regionLabel, $stored, $thumb,
        $width, $height, $size, $loadMs, $dnsMs, $tcpMs, $sslMs, $ttfbMs
    );
    if (!$stmt->execute()) {
        $stmt->close();
        @unlink($storedPath);
        if ($thumbPath !== $storedPath) @unlink($thumbPath);
        return null;
    }
    $id = (int)$stmt->insert_id;
    $stmt->close();

    screenshot_history_prune_old($conn);

    return [
        'id' => $id,
        'width' => $width,
        'height' => $height,
        'file_size_bytes' => $size,
        'image_url' => '/api/screenshot-history-image.php?id=' . $id,
        'thumbnail_url' => '/api/screenshot-history-image.php?id=' . $id . '&variant=thumb',
    ];
}

/** Keeps disk usage bounded — history list only ever shows the latest few anyway. */
function screenshot_history_prune_old(mysqli $conn): void {
    $keep = TRACS_SCREENSHOT_HISTORY_KEEP * 4;
    $stmt = $conn->prepare("SELECT id, stored_filename, thumbnail_filename FROM screenshot_history ORDER BY created_at DESC, id DESC LIMIT 1000000 OFFSET ?");
    if (!$stmt) return;
    $stmt->bind_param('i', $keep);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    if (!$rows) return;

    $dir = screenshot_history_storage_dir();
    $ids = [];
    foreach ($rows as $row) {
        $ids[] = (int)$row['id'];
        foreach (['stored_filename', 'thumbnail_filename'] as $key) {
            $name = basename((string)($row[$key] ?? ''));
            if ($name === '') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) @unlink($path);
        }
    }
    if (!$ids) return;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $del = $conn->prepare("DELETE FROM screenshot_history WHERE id IN ($placeholders)");
    if (!$del) return;
    $types = str_repeat('i', count($ids));
    $del->bind_param($types, ...$ids);
    $del->execute();
    $del->close();
}

function screenshot_history_fetch(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT * FROM screenshot_history WHERE id = ? LIMIT 1");
    if (!$stmt) return null;
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

function screenshot_history_list_recent(mysqli $conn, int $limit = TRACS_SCREENSHOT_HISTORY_KEEP): array {
    $stmt = $conn->prepare("
        SELECT sh.*, COALESCE(NULLIF(u.name,''), u.email) AS captured_by_name
        FROM screenshot_history sh
        LEFT JOIN tracs_users u ON u.id = sh.user_id
        WHERE sh.status = 'success'
        ORDER BY sh.created_at DESC, sh.id DESC
        LIMIT ?
    ");
    if (!$stmt) return [];
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    foreach ($rows as &$row) {
        $id = (int)$row['id'];
        $row['image_url'] = '/api/screenshot-history-image.php?id=' . $id;
        $row['thumbnail_url'] = '/api/screenshot-history-image.php?id=' . $id . '&variant=thumb';
    }
    return $rows;
}
