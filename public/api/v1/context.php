<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../api/v1/context.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['GET']);

try {
    $permissions = \tracs_user_permissions($conn, $context['user_id']);
    $data = \TRACS\Api\V1\context_data(
        $context['user'],
        $permissions,
        \csrf_token()
    );

    \TRACS\Api\json_success(
        $data,
        'Context loaded.',
        ['request_id' => $context['request_id']]
    );
} catch (Throwable $error) {
    \TRACS\Api\write_error_log(
        'Pilot context endpoint failed.',
        $error,
        ['user_id' => $context['user_id']]
    );
    \TRACS\Api\json_error(
        'Context is temporarily unavailable.',
        500,
        [],
        ['request_id' => $context['request_id']]
    );
}
