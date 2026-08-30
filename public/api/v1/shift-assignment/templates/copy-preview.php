<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../../config/database.php';
require_once __DIR__ . '/../../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../../api/v1/shift-assignment/assignments.php';
require_once __DIR__ . '/../../../../../api/v1/shift-assignment/templates/copy-preview.php';
require_once __DIR__ . '/../../../../../modules/shifting-assignment/ShiftingAssignmentService.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['POST']);

try {
    \TRACS\Api\require_exact_role($conn, 'super_admin', $context['user']);
    \TRACS\Api\require_explicit_role_permission(
        $conn,
        'shifts.manage',
        $context['user']
    );
    if (copy_preview_permission_exists($conn, 'shifts.template.copy_preview')) {
        \TRACS\Api\require_explicit_role_permission(
            $conn,
            'shifts.template.copy_preview',
            $context['user']
        );
    }

    $service = new \ShiftingAssignmentService($conn, $context['user_id']);
    $rawInput = \TRACS\Api\get_request_json();
    $sourceStart = trim((string)($rawInput['source_start_date'] ?? ''));
    $sourceEnd = trim((string)($rawInput['source_end_date'] ?? ''));
    $targetStart = trim((string)($rawInput['target_start_date'] ?? ''));
    $targetEnd = trim((string)($rawInput['target_end_date'] ?? ''));

    $validSource = $sourceStart !== '' && $sourceEnd !== ''
        && \TRACS\Api\safe_date_parse($sourceStart)
        && \TRACS\Api\safe_date_parse($sourceEnd);
    $validTarget = $targetStart !== '' && $targetEnd !== ''
        && \TRACS\Api\safe_date_parse($targetStart)
        && \TRACS\Api\safe_date_parse($targetEnd);

    $sourceAssignments = $validSource
        ? $service->getAssignments(['start' => $sourceStart, 'end' => $sourceEnd])
        : [];
    $targetAssignments = $validTarget
        ? $service->getAssignments(['start' => $targetStart, 'end' => $targetEnd])
        : [];
    $targetHolidays = $validTarget
        ? $service->getHolidays($targetStart, $targetEnd)
        : [];

    $data = \TRACS\Api\V1\ShiftAssignment\Templates\copy_preview_data(
        $rawInput,
        $service->getAgents(),
        $service->getTemplates(),
        $service->getAssignmentTypes(),
        $targetHolidays,
        $sourceAssignments,
        $targetAssignments,
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
                $targetStart,
                $targetEnd,
                $assignments,
                [],
                $targetHolidays
            )
    );

    \TRACS\Api\json_success(
        $data,
        'Copy schedule preview generated.',
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
        'Shift Assignment copy preview endpoint failed.',
        $error,
        ['user_id' => $context['user_id']]
    );
    \TRACS\Api\json_error(
        'Copy schedule preview is temporarily unavailable.',
        500,
        [],
        ['request_id' => $context['request_id']]
    );
}

function copy_preview_permission_exists(mysqli $conn, string $permission): bool
{
    $stmt = $conn->prepare('SELECT 1 FROM tracs_permissions WHERE permission_key=? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $permission);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $exists;
}
