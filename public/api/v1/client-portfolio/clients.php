<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../modules/client-portfolio/controller.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['GET', 'POST'], permissions: ['clients.view']);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    $controller = new ClientPortfolioController($conn, $context['user_id']);
    if (!$controller->schemaReady()) {
        \TRACS\Api\json_error('Client Portfolio schema is not installed.', 503, [], ['request_id' => $context['request_id']]);
    }

    if ($method === 'POST') {
        \TRACS\Api\require_permission($conn, 'clients.manage', $context['user']);
        $input = \TRACS\Api\get_request_json();
        if (!\tracs_user_can($conn, 'clients.view_all', $context['user_id'])) {
            $input['owner_user_id'] = $context['user_id'];
        }
        $id = $controller->create($input, \tracs_current_user_display($conn));
        \TRACS\Api\json_success($controller->detail($id), 'Client created.', ['request_id' => $context['request_id']], 201);
    }

    $data = $controller->list([
        'scope' => $_GET['scope'] ?? 'mine',
        'q' => trim((string)($_GET['q'] ?? '')),
        'owner_user_id' => $_GET['owner_user_id'] ?? '',
        'status' => $_GET['status'] ?? '',
        'billing_status' => $_GET['billing_status'] ?? '',
        'attention' => $_GET['attention'] ?? '',
        'service_type' => $_GET['service_type'] ?? '',
        'service_status' => $_GET['service_status'] ?? '',
        'renewal_window' => $_GET['renewal_window'] ?? '',
    ]);
    \TRACS\Api\json_success($data, 'Clients loaded.', ['request_id' => $context['request_id']]);
} catch (InvalidArgumentException $error) {
    \TRACS\Api\json_error($error->getMessage(), 422, [], ['request_id' => $context['request_id']]);
} catch (Throwable $error) {
    \TRACS\Api\write_error_log('Client portfolio clients endpoint failed.', $error, ['user_id' => $context['user_id']]);
    \TRACS\Api\json_error('Client portfolio is temporarily unavailable.', 500, [], ['request_id' => $context['request_id']]);
}
