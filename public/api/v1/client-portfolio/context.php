<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../modules/client-portfolio/controller.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['GET'], permissions: ['clients.view']);

try {
    $controller = new ClientPortfolioController($conn, $context['user_id']);
    \TRACS\Api\json_success(
        $controller->context($context['user']),
        'Client portfolio context loaded.',
        ['request_id' => $context['request_id']]
    );
} catch (Throwable $error) {
    \TRACS\Api\write_error_log('Client portfolio context failed.', $error, ['user_id' => $context['user_id']]);
    \TRACS\Api\json_error('Client portfolio is temporarily unavailable.', 500, [], ['request_id' => $context['request_id']]);
}
