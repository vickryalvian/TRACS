<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../api/v1/shift-assignment/context.php';
require_once __DIR__ . '/../../../../modules/shifting-assignment/ShiftingAssignmentService.php';

$context = \TRACS\Api\bootstrap(
    $conn,
    methods: ['GET'],
    permissions: ['shifts.view']
);

try {
    $service = new \ShiftingAssignmentService($conn, $context['user_id']);
    $data = \TRACS\Api\V1\ShiftAssignment\context_data(
        $context['user'],
        [
            'manage' => $service->canManage(),
            'create' => (string)($context['user']['role_slug'] ?? '') === 'super_admin'
                && \TRACS\Api\has_explicit_role_permission(
                    $conn,
                    'shifts.manage',
                    $context['user']
                ),
            'update' => (string)($context['user']['role_slug'] ?? '') === 'super_admin'
                && \TRACS\Api\has_explicit_role_permission(
                    $conn,
                    'shifts.manage',
                    $context['user']
                ),
            'delete' => (string)($context['user']['role_slug'] ?? '') === 'super_admin'
                && \TRACS\Api\has_explicit_role_permission(
                    $conn,
                    'shifts.manage',
                    $context['user']
                ),
            'settings' => $service->canManageSettings(),
            'monthly_templates' => $service->canManageMonthlyTemplates(),
            'export' => $service->canExport(),
            'scope_role' => $service->scopeRole(),
        ],
        $service->getTemplates(),
        $service->getAssignmentTypes(),
        $service->getAgents(),
        $service->getDivisions(),
        $service->getSettings(),
        $service->normalizeRange(null, null),
        \csrf_token()
    );

    \TRACS\Api\json_success(
        $data,
        'Shift Assignment context loaded.',
        ['request_id' => $context['request_id']]
    );
} catch (\Throwable $error) {
    \TRACS\Api\write_error_log(
        'Shift Assignment context endpoint failed.',
        $error,
        ['user_id' => $context['user_id']]
    );
    \TRACS\Api\json_error(
        'Shift Assignment context is temporarily unavailable.',
        500,
        [],
        ['request_id' => $context['request_id']]
    );
}
