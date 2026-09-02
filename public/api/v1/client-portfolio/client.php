<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../core/access_control.php';
require_once __DIR__ . '/../../../../modules/client-portfolio/controller.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['GET', 'PATCH'], permissions: ['clients.view']);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$id = \tracs_is_positive_int($_GET['id'] ?? null) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    \TRACS\Api\json_error('Client not found.', 404, [], ['request_id' => $context['request_id']]);
}

try {
    $controller = new ClientPortfolioController($conn, $context['user_id']);
    if ($method === 'PATCH') {
        \TRACS\Api\require_permission($conn, 'clients.manage', $context['user']);
        $input = \TRACS\Api\get_request_json();
        if (!\tracs_user_can($conn, 'clients.view_all', $context['user_id'])) {
            unset($input['owner_user_id']);
        }
        $controller->update($id, $input, \tracs_current_user_display($conn));
    }
    $client = $controller->detail($id);
    if (!$client) {
        \TRACS\Api\json_error('Client not found.', 404, [], ['request_id' => $context['request_id']]);
    }
    \TRACS\Api\json_success($client, $method === 'PATCH' ? 'Client updated.' : 'Client loaded.', ['request_id' => $context['request_id']]);
} catch (InvalidArgumentException $error) {
    \TRACS\Api\json_error($error->getMessage(), 422, [], ['request_id' => $context['request_id']]);
} catch (Throwable $error) {
    \TRACS\Api\write_error_log('Client portfolio detail endpoint failed.', $error, ['user_id' => $context['user_id'], 'client_id' => $id]);
    \TRACS\Api\json_error('Client portfolio is temporarily unavailable.', 500, [], ['request_id' => $context['request_id']]);
}
