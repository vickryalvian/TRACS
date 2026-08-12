<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../modules/abuse-report/model.php';

const TRACS_ABUSE_EVIDENCE_MAX_BYTES = 10485760;

function abuse_report_evidence_allowed_mimes(): array {
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'message/rfc822' => 'eml',
        'application/octet-stream' => 'bin',
        'application/zip' => 'zip',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/msword' => 'doc',
    ];
}

function abuse_report_evidence_type(string $filename, string $mime, string $requested = ''): string {
    $requested = strtolower(trim($requested));
    if (in_array($requested, ['screenshot','email','header','log','document','image','other'], true)) {
        return $requested;
    }
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (str_starts_with($mime, 'image/')) return $ext === 'png' || $ext === 'jpg' || $ext === 'jpeg' || $ext === 'webp' ? 'image' : 'screenshot';
    if ($mime === 'message/rfc822' || $ext === 'eml') return 'email';
    if (in_array($ext, ['log', 'txt', 'csv'], true)) return $ext === 'log' ? 'log' : 'header';
    if (in_array($ext, ['pdf', 'doc', 'docx', 'zip'], true)) return 'document';
    return 'other';
}

function abuse_report_evidence_storage_dir(): string {
    $uploads = __DIR__ . '/../uploads';
    if (!is_dir($uploads) && !mkdir($uploads, 0750, true)) {
        fail('Upload storage is not available.', 500);
    }
    $base = realpath($uploads);
    if ($base === false) {
        fail('Upload storage is not available.', 500);
    }
    $dir = $base . DIRECTORY_SEPARATOR . 'abuse_report_evidence';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        fail('Evidence storage is not writable.', 500);
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

function abuse_report_evidence_sanitize_original(string $name): string {
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'evidence';
    return substr(trim($name, '._-'), 0, 180) ?: 'evidence';
}

function abuse_report_evidence_normalize_files(array $files): array {
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

function abuse_report_evidence_store_file(array $file, int $reportId, string $requestedType = ''): array {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Choose evidence before uploading.');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One evidence file could not be uploaded.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) {
        throw new RuntimeException('Empty evidence files are not allowed.');
    }
    if ($size > TRACS_ABUSE_EVIDENCE_MAX_BYTES) {
        throw new RuntimeException('Each evidence file must be 10MB or smaller.');
    }
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException('Invalid evidence upload.');
    }

    $original = abuse_report_evidence_sanitize_original((string)($file['name'] ?? 'evidence'));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string)$finfo->file($tmpName));
    $allowed = abuse_report_evidence_allowed_mimes();
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!isset($allowed[$mime])) {
        $textExt = ['txt', 'log', 'csv', 'eml', 'headers'];
        if (!in_array($ext, $textExt, true)) {
            throw new RuntimeException('Unsupported evidence file type.');
        }
        $mime = $ext === 'eml' ? 'message/rfc822' : 'text/plain';
    }

    if (str_starts_with($mime, 'image/') && @getimagesize($tmpName) === false) {
        throw new RuntimeException('The uploaded image evidence is not readable.');
    }

    $storageExt = $allowed[$mime] ?? ($ext ?: 'txt');
    if ($ext !== '' && preg_match('/^[a-z0-9]{1,8}$/', $ext)) {
        $storageExt = $ext;
    }
    $token = bin2hex(random_bytes(16));
    $stored = 'abuse_' . $reportId . '_' . $token . '.' . $storageExt;
    $dir = abuse_report_evidence_storage_dir();
    $dest = $dir . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($tmpName, $dest)) {
        throw new RuntimeException('Unable to save evidence file.');
    }
    @chmod($dest, 0640);

    return [
        'evidence_type' => abuse_report_evidence_type($original, $mime, $requestedType),
        'original_filename' => $original,
        'stored_filename' => $stored,
        'file_path' => 'abuse_report_evidence/' . $stored,
        'mime_type' => $mime,
        'file_size' => (int)filesize($dest),
        'stored_path' => $dest,
    ];
}

function abuse_report_evidence_delete_file(array $file): void {
    $name = basename((string)($file['stored_filename'] ?? ''));
    if ($name === '') return;
    $path = abuse_report_evidence_storage_dir() . DIRECTORY_SEPARATOR . $name;
    if (is_file($path)) {
        @unlink($path);
    }
}
