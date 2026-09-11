<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../core/access_control.php';
require_once __DIR__ . '/../../../../modules/client-portfolio/controller.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['POST'], permissions: ['clients.view', 'clients.manage']);

try {
    $input = \TRACS\Api\get_request_json();
    $action = trim((string)($input['action'] ?? ''));
    $clientId = \tracs_is_positive_int($input['client_id'] ?? null) ? (int)$input['client_id'] : 0;
    $controller = new ClientPortfolioController($conn, $context['user_id']);
    $actorName = \tracs_current_user_display($conn);

    $id = match ($action) {
        'save_contact' => $controller->saveContact($clientId, $input, $actorName),
        'add_service' => $controller->addService($clientId, $input, $actorName),
        'add_addon' => $controller->addAddon($clientId, $input, $actorName),
        'renew_service' => $controller->renewService($clientId, $input, $actorName),
        'add_billing' => $controller->addBilling($clientId, $input, $actorName),
        'add_followup' => $controller->addFollowup($clientId, $input, $actorName),
        'update_followup' => $controller->updateFollowup((int)($input['followup_id'] ?? 0), $input, $actorName),
        'complete_followup' => $controller->completeFollowup((int)($input['followup_id'] ?? 0), $actorName),
        default => throw new InvalidArgumentException('Unknown client action.'),
    };

    $detailId = in_array($action, ['complete_followup', 'update_followup'], true) ? $id : $clientId;
    \TRACS\Api\json_success($controller->detail($detailId), 'Client action saved.', ['request_id' => $context['request_id']]);
} catch (InvalidArgumentException $error) {
    \TRACS\Api\json_error($error->getMessage(), 422, [], ['request_id' => $context['request_id']]);
} catch (Throwable $error) {
    \TRACS\Api\write_error_log('Client portfolio action failed.', $error, ['user_id' => $context['user_id']]);
    \TRACS\Api\json_error('Client action could not be completed.', 500, [], ['request_id' => $context['request_id']]);
}
