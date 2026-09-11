<?php
declare(strict_types=1);

const TRACS_CLIENT_ATTACHMENT_MAX_BYTES = 10485760;

function tracs_client_attachment_allowed_mimes(): array
{
    return [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/csv' => 'csv',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
    ];
}

function tracs_client_attachment_ensure_table(mysqli $conn): void
{
    $sql = "
        CREATE TABLE IF NOT EXISTS `tracs_client_attachments` (
          `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
          `client_id` INT UNSIGNED NOT NULL,
          `document_type` ENUM('quotation','tax_invoice','invoice','contract','screenshot','document','other') NOT NULL DEFAULT 'document',
          `original_filename` VARCHAR(255) NOT NULL,
          `stored_filename` VARCHAR(255) NOT NULL,
          `file_path` VARCHAR(255) NOT NULL,
          `mime_type` VARCHAR(120) NOT NULL,
          `file_size` INT UNSIGNED NOT NULL,
          `uploaded_by` INT UNSIGNED DEFAULT NULL,
          `uploaded_by_name` VARCHAR(150) DEFAULT NULL,
          `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          INDEX `idx_client_attachments_client` (`client_id`, `created_at`),
          INDEX `idx_client_attachments_type` (`document_type`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    if (!$conn->query($sql)) {
        throw new RuntimeException('Client attachment storage is not ready.');
    }
}

function tracs_client_attachment_storage_dir(): string
{
    $uploads = __DIR__ . '/../../../uploads';
    if (!is_dir($uploads) && !mkdir($uploads, 0750, true)) {
        throw new RuntimeException('Upload storage is not available.');
    }
    $base = realpath($uploads);
    if ($base === false) {
        throw new RuntimeException('Upload storage is not available.');
    }
    $dir = $base . DIRECTORY_SEPARATOR . 'client_attachments';
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException('Client attachment storage is not writable.');
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

function tracs_client_attachment_sanitize_original(string $name): string
{
    $name = basename($name);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name) ?: 'document';
    return substr(trim($name, '._-'), 0, 180) ?: 'document';
}

function tracs_client_attachment_type(string $filename, string $mime, string $requested = ''): string
{
    $requested = strtolower(trim($requested));
    if (in_array($requested, ['quotation', 'tax_invoice', 'invoice', 'contract', 'screenshot', 'document', 'other'], true)) {
        return $requested;
    }
    $name = strtolower($filename);
    if (str_contains($name, 'quotation') || str_contains($name, 'quote')) return 'quotation';
    if (str_contains($name, 'faktur') || str_contains($name, 'tax')) return 'tax_invoice';
    if (str_contains($name, 'invoice')) return 'invoice';
    if (str_contains($name, 'contract') || str_contains($name, 'agreement')) return 'contract';
    if (str_starts_with($mime, 'image/')) return 'screenshot';
    return 'document';
}

function tracs_client_attachment_normalize_files(array $files): array
{
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

function tracs_client_attachment_store_file(array $file, int $clientId, string $requestedType): array
{
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) throw new RuntimeException('Choose a file before uploading.');
    if ($error !== UPLOAD_ERR_OK) throw new RuntimeException('One file could not be uploaded.');
    $size = (int)($file['size'] ?? 0);
    if ($size <= 0) throw new RuntimeException('Empty file uploads are not allowed.');
    if ($size > TRACS_CLIENT_ATTACHMENT_MAX_BYTES) throw new RuntimeException('Each client file must be 10MB or smaller.');
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) throw new RuntimeException('Invalid file upload.');

    $original = tracs_client_attachment_sanitize_original((string)($file['name'] ?? 'document'));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string)$finfo->file($tmpName));
    $allowed = tracs_client_attachment_allowed_mimes();
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    if (!isset($allowed[$mime])) {
        if (!in_array($ext, ['txt', 'csv'], true)) throw new RuntimeException('Unsupported client file type.');
        $mime = $ext === 'csv' ? 'text/csv' : 'text/plain';
    }
    if (str_starts_with($mime, 'image/') && @getimagesize($tmpName) === false) {
        throw new RuntimeException('The uploaded image is not readable.');
    }

    $storageExt = $allowed[$mime] ?? ($ext ?: 'bin');
    if ($ext !== '' && preg_match('/^[a-z0-9]{1,8}$/', $ext)) $storageExt = $ext;
    $stored = 'client_' . $clientId . '_' . bin2hex(random_bytes(16)) . '.' . $storageExt;
    $dest = tracs_client_attachment_storage_dir() . DIRECTORY_SEPARATOR . $stored;
    if (!move_uploaded_file($tmpName, $dest)) throw new RuntimeException('Unable to save client file.');
    @chmod($dest, 0640);

    return [
        'document_type' => tracs_client_attachment_type($original, $mime, $requestedType),
        'original_filename' => $original,
        'stored_filename' => $stored,
        'file_path' => 'client_attachments/' . $stored,
        'mime_type' => $mime,
        'file_size' => (int)filesize($dest),
        'stored_path' => $dest,
    ];
}

function tracs_client_attachment_delete_file(array $file): void
{
    $name = basename((string)($file['stored_filename'] ?? ''));
    if ($name === '') return;
    $path = tracs_client_attachment_storage_dir() . DIRECTORY_SEPARATOR . $name;
    if (is_file($path)) @unlink($path);
}
