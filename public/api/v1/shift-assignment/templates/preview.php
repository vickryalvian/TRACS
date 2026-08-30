<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../../config/database.php';
require_once __DIR__ . '/../../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../../api/v1/shift-assignment/assignments.php';
require_once __DIR__ . '/../../../../../api/v1/shift-assignment/templates/preview.php';
require_once __DIR__ . '/../../../../../modules/shifting-assignment/ShiftingAssignmentService.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['POST']);

try {
    \TRACS\Api\require_exact_role($conn, 'super_admin', $context['user']);
    \TRACS\Api\require_explicit_role_permission(
        $conn,
        'shifts.manage',
        $context['user']
    );

    $service = new \ShiftingAssignmentService($conn, $context['user_id']);
    $rawInput = \TRACS\Api\get_request_json();
    $start = trim((string)($rawInput['start_date'] ?? ''));
    $end = trim((string)($rawInput['end_date'] ?? ''));

    $holidays = ($start !== '' && $end !== ''
        && \TRACS\Api\safe_date_parse($start)
        && \TRACS\Api\safe_date_parse($end))
            ? $service->getHolidays($start, $end)
            : [];
    $existing = ($start !== '' && $end !== ''
        && \TRACS\Api\safe_date_parse($start)
        && \TRACS\Api\safe_date_parse($end))
            ? $service->getAssignments(['start' => $start, 'end' => $end])
            : [];

    $data = \TRACS\Api\V1\ShiftAssignment\Templates\template_preview_data(
        $rawInput,
        $service->getAgents(),
        $service->getTemplates(),
        $service->getAssignmentTypes(),
        $holidays,
        $existing,
        $service->getSettings(),
        static fn(
            array $assignments,
            array $agents,
            array $settings,
            string $rangeStart,
            string $rangeEnd
        ): array => $service->calculateWorkloadRecap(
            $assignments,
            $agents,
            $settings,
            $rangeStart,
            $rangeEnd
        ),
        static fn(array $assignments, array $settings): array =>
            $service->getJumpShiftWarnings($assignments, $settings),
        static fn(array $assignments): array =>
            $service->getConflictWarnings($assignments),
        static fn(array $assignments): array =>
            $service->detectCoverageGaps(
                $start,
                $end,
                $assignments,
                [],
                $holidays
            )
    );

    \TRACS\Api\json_success(
        $data,
        'Template preview generated.',
        ['request_id' => $context['request_id']]
    );
} catch (\TRACS\Api\RequestValidationException $error) {
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
} catch (\Throwable $error) {
    \TRACS\Api\write_error_log(
        'Shift Assignment template preview endpoint failed.',
        $error,
        ['user_id' => $context['user_id']]
    );
    \TRACS\Api\json_error(
        'Template preview is temporarily unavailable.',
        500,
        [],
        ['request_id' => $context['request_id']]
    );
}
