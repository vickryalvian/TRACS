<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../api/_bootstrap.php';
require_once __DIR__ . '/../../../../api/v1/shift-assignment/assignments.php';
require_once __DIR__ . '/../../../../api/v1/shift-assignment/assignment.php';
require_once __DIR__ . '/../../../../modules/shifting-assignment/ShiftingAssignmentService.php';

$context = \TRACS\Api\bootstrap($conn, methods: ['PATCH', 'DELETE']);

try {
    \TRACS\Api\require_exact_role($conn, 'super_admin', $context['user']);
    \TRACS\Api\require_explicit_role_permission(
        $conn,
        'shifts.manage',
        $context['user']
    );

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $assignmentId = $method === 'DELETE'
        ? \TRACS\Api\V1\ShiftAssignment\delete_assignment_id($_GET)
        : \TRACS\Api\V1\ShiftAssignment\update_assignment_id($_GET);
    $service = new \ShiftingAssignmentService($conn, $context['user_id']);
    $existing = $service->getAssignment($assignmentId);
    if (!$existing) {
        $action = $method === 'DELETE' ? 'delete' : 'update';
        \TRACS\Api\write_audit_log(
            $conn,
            $context['user_id'],
            "shift_assignment.{$action}_failed",
            'shift_assignment',
            $assignmentId,
            null,
            [
                'request_id' => $context['request_id'],
                'result' => 'not_found',
            ],
            "Controlled v1 {$action} target was not found."
        );
        \TRACS\Api\json_error(
            'Shift assignment not found.',
            404,
            [],
            ['request_id' => $context['request_id']]
        );
    }

    if ($method === 'DELETE') {
        $before = \TRACS\Api\V1\ShiftAssignment\delete_assignment_safe_summary($existing);
        $data = \TRACS\Api\V1\ShiftAssignment\delete_assignment_data(
            $existing,
            static fn(int $id): bool => $service->deleteAssignment($id)
        );
        \TRACS\Api\write_audit_log(
            $conn,
            $context['user_id'],
            'shift_assignment.delete',
            'shift_assignment',
            $assignmentId,
            $before,
            [
                'request_id' => $context['request_id'],
                'result' => 'success',
            ],
            'Deleted through controlled v1 API.'
        );
        \TRACS\Api\json_success(
            $data,
            'Shift assignment deleted.',
            ['request_id' => $context['request_id']]
        );
    }

    $rawInput = \TRACS\Api\get_request_json();
    $attempt = \TRACS\Api\V1\ShiftAssignment\update_assignment_attempt_summary($rawInput);
    $before = \TRACS\Api\V1\ShiftAssignment\update_assignment_safe_summary($existing);
    $data = \TRACS\Api\V1\ShiftAssignment\update_assignment_data(
        $rawInput,
        $existing,
        static fn(array $input): array => $service->saveAssignment($input),
        static fn(int $id): ?array => $service->getAssignment($id)
    );
    $after = $data['assignment'];

    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        'shift_assignment.update',
        'shift_assignment',
        $assignmentId,
        $before,
        [
            'updated' => $after,
            'changed_fields' => \TRACS\Api\V1\ShiftAssignment\update_assignment_changed_fields(
                $before,
                $after
            ),
            'request_id' => $context['request_id'],
            'result' => 'success',
        ],
        'Updated through controlled v1 API.'
    );

    \TRACS\Api\json_success(
        $data,
        'Shift assignment updated.',
        ['request_id' => $context['request_id']]
    );
} catch (\TRACS\Api\RequestValidationException $error) {
    $action = ($method ?? '') === 'DELETE' ? 'delete' : 'update';
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        "shift_assignment.{$action}_failed",
        'shift_assignment',
        $assignmentId ?? null,
        $before ?? null,
        [
            'request' => $attempt ?? [],
            'request_id' => $context['request_id'],
            'result' => 'validation_failed',
            'fields' => array_keys($error->errors),
        ],
        "Controlled v1 {$action} validation failed."
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
} catch (\ShiftValidationException $error) {
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        'shift_assignment.update_failed',
        'shift_assignment',
        $assignmentId ?? null,
        $before ?? null,
        [
            'request' => $attempt ?? [],
            'request_id' => $context['request_id'],
            'result' => 'validation_failed',
            'fields' => array_keys($error->errors),
        ],
        'Controlled v1 update service validation failed.'
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
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        'shift_assignment.update_failed',
        'shift_assignment',
        $assignmentId ?? null,
        $before ?? null,
        [
            'request' => $attempt ?? [],
            'request_id' => $context['request_id'],
            'result' => 'validation_failed',
        ],
        'Controlled v1 update service validation failed.'
    );
    \TRACS\Api\json_error(
        \tracs_public_error_message($error->getMessage(), 'Validation failed.'),
        422,
        [],
        ['request_id' => $context['request_id']]
    );
} catch (\DomainException $error) {
    $action = ($method ?? '') === 'DELETE' ? 'delete' : 'update';
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        "shift_assignment.{$action}_failed",
        'shift_assignment',
        $assignmentId ?? null,
        $before ?? null,
        [
            'request' => $attempt ?? [],
            'request_id' => $context['request_id'],
            'result' => 'conflict',
        ],
        "Controlled v1 {$action} conflict."
    );
    \TRACS\Api\json_error(
        \tracs_public_error_message($error->getMessage(), 'Assignment conflict.'),
        409,
        [],
        ['request_id' => $context['request_id']]
    );
} catch (\Throwable $error) {
    $notFound = $error instanceof \RuntimeException
        && $error->getMessage() === 'Assignment not found.';
    $action = ($method ?? '') === 'DELETE' ? 'delete' : 'update';
    \TRACS\Api\write_error_log(
        "Shift Assignment {$action} endpoint failed.",
        $error,
        ['user_id' => $context['user_id'], 'assignment_id' => $assignmentId ?? null]
    );
    \TRACS\Api\write_audit_log(
        $conn,
        $context['user_id'],
        "shift_assignment.{$action}_failed",
        'shift_assignment',
        $assignmentId ?? null,
        $before ?? null,
        [
            'request' => $attempt ?? [],
            'request_id' => $context['request_id'],
            'result' => $notFound ? 'not_found' : 'server_error',
        ],
        "Controlled v1 {$action} failed."
    );
    \TRACS\Api\json_error(
        $notFound
            ? 'Shift assignment not found.'
            : "The shift assignment could not be {$action}d.",
        $notFound ? 404 : 500,
        [],
        ['request_id' => $context['request_id']]
    );
}
