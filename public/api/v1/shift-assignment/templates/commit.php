<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../../config/database.php';
require_once __DIR__ . '/../../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../../api/v1/shift-assignment/assignments.php';
require_once __DIR__ . '/../../../../../api/v1/shift-assignment/templates/preview.php';
require_once __DIR__ . '/../../../../../api/v1/shift-assignment/templates/commit.php';
require_once __DIR__ . '/../../../../../modules/shifting-assignment/ShiftingAssignmentService.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['POST']);
$createdIds = [];
$rawInput = [];
$attempt = [];

try {
    \TRACS\Api\require_exact_role($conn, 'super_admin', $context['user']);
    \TRACS\Api\require_explicit_role_permission(
        $conn,
        'shifts.manage',
        $context['user']
    );
    if (template_commit_permission_exists($conn, 'shifts.template.commit')) {
        \TRACS\Api\require_explicit_role_permission(
            $conn,
            'shifts.template.commit',
            $context['user']
        );
    }

    $service = new \ShiftingAssignmentService($conn, $context['user_id']);
    $rawInput = \TRACS\Api\get_request_json();
    $attempt = \TRACS\Api\V1\ShiftAssignment\Templates\template_commit_attempt_summary($rawInput);

    // Fail closed before any assignment write if commit audit storage is unavailable.
    \TRACS\Api\V1\ShiftAssignment\Templates\template_commit_audit(
        $conn,
        $context['user_id'],
        'shift_assignment.template.commit_attempt',
        null,
        null,
        [
            'request' => $attempt,
            'request_id' => $context['request_id'],
            'result' => 'attempt',
        ],
        'Controlled template commit attempt.'
    );

    $previewPayload = is_array($rawInput['preview_payload'] ?? null)
        ? $rawInput['preview_payload']
        : [];
    $start = trim((string)($previewPayload['start_date'] ?? ''));
    $end = trim((string)($previewPayload['end_date'] ?? ''));
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

    $result = \TRACS\Api\V1\ShiftAssignment\Templates\template_commit_data(
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
            ),
        static function (array $input) use ($service, &$createdIds): array {
            $saved = $service->saveAssignment($input);
            $id = (int)($saved['id'] ?? 0);
            if ($id > 0) {
                $createdIds[] = $id;
            }
            return $saved;
        }
    );

    if (!empty($result['blocked'])) {
        \TRACS\Api\V1\ShiftAssignment\Templates\template_commit_audit(
            $conn,
            $context['user_id'],
            'shift_assignment.template.commit_blocked',
            null,
            null,
            [
                'request' => $attempt,
                'request_id' => $context['request_id'],
                'result' => 'conflict',
                'conflicts' => $result['data']['conflicts'] ?? [],
                'blocked_items' => $result['data']['blocked_items'] ?? [],
            ],
            'Controlled template commit blocked by conflicts.'
        );
        \TRACS\Api\send_json(
            \TRACS\Api\response_payload(
                false,
                'Template commit blocked by conflicts.',
                $result['data'],
                [],
                ['request_id' => $context['request_id']]
            ),
            409
        );
    }

    \TRACS\Api\V1\ShiftAssignment\Templates\template_commit_audit(
        $conn,
        $context['user_id'],
        'shift_assignment.template.commit',
        null,
        null,
        array_merge(
            $result['audit'] ?? [],
            [
                'request_id' => $context['request_id'],
                'result' => 'success',
            ]
        ),
        'Controlled template commit completed.'
    );

    \TRACS\Api\json_success(
        $result['data'],
        'Template applied.',
        ['request_id' => $context['request_id']],
        201
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
} catch (\DomainException $error) {
    template_commit_cleanup_created($conn, $createdIds);
    \TRACS\Api\json_error(
        \tracs_public_error_message($error->getMessage(), 'Template commit conflict.'),
        409,
        [],
        ['request_id' => $context['request_id']]
    );
} catch (\Throwable $error) {
    template_commit_cleanup_created($conn, $createdIds);
    \TRACS\Api\write_error_log(
        'Shift Assignment template commit endpoint failed.',
        $error,
        ['user_id' => $context['user_id'], 'created_assignment_ids' => $createdIds]
    );
    \TRACS\Api\json_error(
        'Template commit is temporarily unavailable.',
        500,
        [],
        ['request_id' => $context['request_id']]
    );
}

function template_commit_cleanup_created(mysqli $conn, array $createdIds): void
{
    $createdIds = array_values(array_unique(array_filter(
        array_map('intval', $createdIds),
        static fn(int $id): bool => $id > 0
    )));
    if ($createdIds === []) {
        return;
    }
    $ids = implode(',', $createdIds);
    $conn->query("DELETE FROM shift_assignments WHERE id IN ({$ids})");
}

function template_commit_permission_exists(mysqli $conn, string $permission): bool
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
