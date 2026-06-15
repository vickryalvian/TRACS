<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../api/v1/shift-assignment/assignments.php';
require_once __DIR__ . '/../../../../modules/shifting-assignment/ShiftingAssignmentService.php';

$context = \TRACS\Api\bootstrap(
    $conn,
    methods: ['GET'],
    permissions: ['shifts.view']
);

try {
    $query = \TRACS\Api\V1\ShiftAssignment\assignments_query($_GET);
    $service = new \ShiftingAssignmentService($conn, $context['user_id']);

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
    \TRACS\Api\json_error(
        $error->getMessage(),
        422,
        $error->errors,
        ['request_id' => $context['request_id']]
    );
} catch (\Throwable $error) {
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
