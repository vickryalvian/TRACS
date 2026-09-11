<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../modules/client-portfolio/controller.php';
require_once __DIR__ . '/attachment-lib.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['GET', 'POST'], permissions: ['clients.view']);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    $controller = new ClientPortfolioController($conn, $context['user_id']);
    if (!$controller->schemaReady()) {
        \TRACS\Api\json_error('Client Portfolio schema is not installed.', 503, [], ['request_id' => $context['request_id']]);
    }
    tracs_client_attachment_ensure_table($conn);

    if ($method === 'POST') {
        \TRACS\Api\require_permission($conn, 'clients.manage', $context['user']);
        $clientId = (int)($_POST['client_id'] ?? 0);
        if ($clientId <= 0) throw new InvalidArgumentException('Choose a client before uploading files.');
        $controller->assertCanAccess($clientId);
        if (empty($_FILES['attachments'])) throw new InvalidArgumentException('Choose files before uploading.');

        $storedFiles = [];
        $ids = [];
        try {
            $conn->begin_transaction();
            $documentType = (string)($_POST['document_type'] ?? '');
            foreach (tracs_client_attachment_normalize_files($_FILES['attachments']) as $file) {
                if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $stored = tracs_client_attachment_store_file($file, $clientId, $documentType);
                $storedFiles[] = $stored;
                $ids[] = $controller->recordAttachment($clientId, $stored, \tracs_current_user_display($conn));
            }
            if (!$ids) throw new InvalidArgumentException('Choose files before uploading.');
            $conn->commit();
        } catch (Throwable $error) {
            try { $conn->rollback(); } catch (Throwable) {}
            foreach ($storedFiles as $stored) tracs_client_attachment_delete_file($stored);
            throw $error;
        }

        \TRACS\Api\json_success([
            'ids' => $ids,
            'client' => $controller->detail($clientId),
        ], 'Client files uploaded.', ['request_id' => $context['request_id']], 201);
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) \TRACS\Api\json_error('File not found.', 404, [], ['request_id' => $context['request_id']]);
    $attachment = $controller->attachment($id);
    if (!$attachment) \TRACS\Api\json_error('File not found.', 404, [], ['request_id' => $context['request_id']]);
    $fileName = basename((string)$attachment['stored_filename']);
    $path = tracs_client_attachment_storage_dir() . DIRECTORY_SEPARATOR . $fileName;
    if ($fileName === '' || !is_file($path)) \TRACS\Api\json_error('File not found.', 404, [], ['request_id' => $context['request_id']]);

    $displayName = tracs_client_attachment_sanitize_original((string)$attachment['original_filename']);
    $download = ($_GET['download'] ?? '') === '1' || !str_starts_with((string)$attachment['mime_type'], 'image/');
    header_remove('Content-Type');
    header('Content-Type: ' . (string)$attachment['mime_type']);
    header('Content-Length: ' . (string)filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, max-age=300');
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . addcslashes($displayName, '\\"') . '"');
    readfile($path);
    exit;
} catch (InvalidArgumentException $error) {
    \TRACS\Api\json_error($error->getMessage(), 422, [], ['request_id' => $context['request_id']]);
} catch (Throwable $error) {
    \TRACS\Api\write_error_log('Client attachment endpoint failed.', $error, ['user_id' => $context['user_id']]);
    \TRACS\Api\json_error('Client files are temporarily unavailable.', 500, [], ['request_id' => $context['request_id']]);
}
