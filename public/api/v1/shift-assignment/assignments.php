<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../api/v1/shift-assignment/assignments.php';
require_once __DIR__ . '/../../../../modules/shifting-assignment/ShiftingAssignmentService.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['GET', 'POST']);
$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'POST') {
        \TRACS\Api\require_permission($conn, 'shifts.manage', $context['user']);
        \TRACS\Api\require_exact_role($conn, 'super_admin', $context['user']);

        $service = new \ShiftingAssignmentService($conn, $context['user_id']);
        $rawInput = \TRACS\Api\get_request_json();
        $createAttempt = \TRACS\Api\V1\ShiftAssignment\create_assignment_attempt_summary($rawInput);
        $createInput = \TRACS\Api\V1\ShiftAssignment\create_assignment_input($rawInput);
        $data = \TRACS\Api\V1\ShiftAssignment\create_assignment_data(
            $rawInput,
            static fn(array $input): array => $service->saveAssignment($input)
        );
        \TRACS\Api\write_audit_log(
            $conn,
            $context['user_id'],
            'shift_assignment.create',
            'shift_assignment',
            (int)$data['assignment']['id'],
            null,
            [
                'request' => \TRACS\Api\V1\ShiftAssignment\create_assignment_audit_summary($createInput),
                'created' => $data['assignment'],
                'request_id' => $context['request_id'],
                'result' => 'success',
            ],
            'Created through controlled v1 API.'
        );
        \TRACS\Api\json_success(
            $data,
            'Shift assignment created.',
            ['request_id' => $context['request_id']],
            201
        );
    }

    \TRACS\Api\require_permission($conn, 'shifts.view', $context['user']);
    $service = new \ShiftingAssignmentService($conn, $context['user_id']);
    $query = \TRACS\Api\V1\ShiftAssignment\assignments_query($_GET);

    $agents = \TRACS\Api\V1\ShiftAssignment\filter_agents_by_role(
        $service->getAgents(),
        $query['role']
    );
    if ($query['division_id'] !== null) {
        $agents = array_values(array_filter(
            $agents,
            static fn(array $agent): bool => (int)$agent['division_id'] === $query['division_id']
        ));
    }
    if ($query['user_id'] !== null) {
        $agents = array_values(array_filter(
            $agents,
            static fn(array $agent): bool => (int)$agent['id'] === $query['user_id']
        ));
    }
    $allowedAgentIds = array_fill_keys(array_map(
        static fn(array $agent): int => (int)$agent['id'],
        $agents
    ), true);

    $serviceFilters = [
        'start' => $query['start'],
        'end' => $query['end'],
        'division_id' => $query['division_id'],
        'user_id' => $query['user_id'],
        'assignment_type' => $query['assignment_type'],
        'status' => $query['status'],
    ];
    $assignments = $service->getAssignments($serviceFilters);
    if ($query['role'] !== '') {
        $assignments = array_values(array_filter(
            $assignments,
            static fn(array $row): bool => isset($allowedAgentIds[(int)$row['user_id']])
        ));
    }

    $settings = $service->getSettings($query['division_id']);
    $holidays = $service->getHolidays($query['start'], $query['end']);
    $recap = $service->calculateWorkloadRecap(
        $assignments,
        $agents,
        $settings,
        $query['start'],
        $query['end']
    );
    $warnings = array_merge(
        $service->getJumpShiftWarnings($assignments, $settings),
        $service->getConflictWarnings($assignments),
        $service->detectCoverageGaps(
            $query['start'],
            $query['end'],
            $assignments,
            $serviceFilters,
            $holidays
        )
    );

    $data = \TRACS\Api\V1\ShiftAssignment\assignments_data(
        $query,
        $assignments,
        $recap,
        $warnings,
        $holidays
    );

    \TRACS\Api\json_success(
        $data,
        'Shift assignments loaded.',
        ['request_id' => $context['request_id']]
    );
} catch (\TRACS\Api\RequestValidationException $error) {
    if ($method === 'POST') {
        \TRACS\Api\write_audit_log(
            $conn,
            $context['user_id'],
            'shift_assignment.create_failed',
            'shift_assignment',
            null,
            null,
            [
                'request' => isset($createInput)
                    ? \TRACS\Api\V1\ShiftAssignment\create_assignment_audit_summary($createInput)
                    : ($createAttempt ?? []),
                'request_id' => $context['request_id'],
                'result' => 'validation_failed',
                'fields' => array_keys($error->errors),
            ],
            'Controlled v1 create validation failed.'
        );
    }
    if ($method === 'POST' && $error->errors !== []) {
        \TRACS\Api\send_json(
            \TRACS\Api\response_payload(
                false,
                'Validation failed.',
                (object)[],
                \TRACS\Api\V1\ShiftAssignment\validation_error_list($error->errors),
                ['request_id' => $context['request_id']]
            ),
            422
        );
    }
    \TRACS\Api\json_error(
        $error->getMessage(),
        $method === 'GET' ? 422 : 400,
        $method === 'GET' ? $error->errors : [],
        ['request_id' => $context['request_id']]
    );
} catch (\ShiftValidationException $error) {
    if ($method !== 'POST') {
        \TRACS\Api\write_error_log(
            'Shift Assignment read endpoint failed.',
            $error,
            ['user_id' => $context['user_id']]
        );
        \TRACS\Api\json_error(
            'Shift assignments are temporarily unavailable.',
            500,
            [],
            ['request_id' => $context['request_id']]
        );
    }
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        'shift_assignment.create_failed',
        'shift_assignment',
        null,
        null,
        [
            'request' => isset($createInput)
                ? \TRACS\Api\V1\ShiftAssignment\create_assignment_audit_summary($createInput)
                : ($createAttempt ?? []),
            'request_id' => $context['request_id'],
            'result' => 'validation_failed',
            'fields' => array_keys($error->errors),
        ],
        'Controlled v1 create service validation failed.'
    );
    \TRACS\Api\send_json(
        \TRACS\Api\response_payload(
            false,
            'Validation failed.',
            (object)[],
            \TRACS\Api\V1\ShiftAssignment\validation_error_list($error->errors),
            ['request_id' => $context['request_id']]
        ),
        422
    );
} catch (\InvalidArgumentException $error) {
    if ($method !== 'POST') {
        \TRACS\Api\write_error_log(
            'Shift Assignment read endpoint failed.',
            $error,
            ['user_id' => $context['user_id']]
        );
        \TRACS\Api\json_error(
            'Shift assignments are temporarily unavailable.',
            500,
            [],
            ['request_id' => $context['request_id']]
        );
    }
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        'shift_assignment.create_failed',
        'shift_assignment',
        null,
        null,
        [
            'request' => isset($createInput)
                ? \TRACS\Api\V1\ShiftAssignment\create_assignment_audit_summary($createInput)
                : ($createAttempt ?? []),
            'request_id' => $context['request_id'],
            'result' => 'validation_failed',
        ],
        'Controlled v1 create service validation failed.'
    );
    \TRACS\Api\json_error(
        \tracs_public_error_message($error->getMessage(), 'Validation failed.'),
        422,
        [],
        ['request_id' => $context['request_id']]
    );
} catch (\DomainException $error) {
    if ($method !== 'POST') {
        \TRACS\Api\write_error_log(
            'Shift Assignment read endpoint failed.',
            $error,
            ['user_id' => $context['user_id']]
        );
        \TRACS\Api\json_error(
            'Shift assignments are temporarily unavailable.',
            500,
            [],
            ['request_id' => $context['request_id']]
        );
    }
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        'shift_assignment.create_failed',
        'shift_assignment',
        null,
        null,
        [
            'request' => isset($createInput)
                ? \TRACS\Api\V1\ShiftAssignment\create_assignment_audit_summary($createInput)
                : ($createAttempt ?? []),
            'request_id' => $context['request_id'],
            'result' => 'conflict',
        ],
        'Controlled v1 create conflict.'
    );
    \TRACS\Api\json_error(
        \tracs_public_error_message($error->getMessage(), 'Assignment conflict.'),
        409,
        [],
        ['request_id' => $context['request_id']]
    );
} catch (\Throwable $error) {
    \TRACS\Api\write_error_log(
        $method === 'POST'
            ? 'Shift Assignment create endpoint failed.'
            : 'Shift Assignment read endpoint failed.',
        $error,
        ['user_id' => $context['user_id']]
    );
    if ($method === 'POST') {
        \TRACS\Api\write_audit_log(
            $conn,
            $context['user_id'],
            'shift_assignment.create_failed',
            'shift_assignment',
            null,
            null,
            [
                'request' => isset($createInput)
                    ? \TRACS\Api\V1\ShiftAssignment\create_assignment_audit_summary($createInput)
                    : ($createAttempt ?? []),
                'request_id' => $context['request_id'],
                'result' => 'server_error',
            ],
            'Controlled v1 create failed.'
        );
    }
    \TRACS\Api\json_error(
        $method === 'POST'
            ? 'The shift assignment could not be created.'
            : 'Shift assignments are temporarily unavailable.',
        500,
        [],
        ['request_id' => $context['request_id']]
    );
}
