<?php
declare(strict_types=1);

namespace TRACS\Api\V1\ShiftAssignment\Templates;

require_once __DIR__ . '/../../../../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../../_request.php';
require_once __DIR__ . '/preview.php';

function template_commit_input(array $input): array
{
    $allowed = ['preview_payload', 'confirmation', 'options'];
    $errors = [];
    foreach ($input as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowed, true)) {
            $errors[(string)$key] = 'Field is not supported.';
        }
    }

    if (($input['confirmation'] ?? null) !== 'APPLY TEMPLATE') {
        $errors['confirmation'] = 'Type APPLY TEMPLATE exactly to commit the template.';
    }

    $payload = $input['preview_payload'] ?? null;
    if (!is_array($payload)) {
        $errors['preview_payload'] = 'Preview payload is required.';
        $payload = [];
    }

    $options = $input['options'] ?? [];
    if ($options !== [] && !is_array($options)) {
        $errors['options'] = 'Options must be an object.';
        $options = [];
    }
    $conflictPolicy = (string)($options['conflict_policy'] ?? 'block');
    if ($conflictPolicy !== 'block') {
        $errors['options.conflict_policy'] = 'Only block conflict policy is supported.';
    }

    if ($errors !== []) {
        throw new \TRACS\Api\RequestValidationException('Validation failed.', $errors);
    }

    return [
        'preview_payload' => $payload,
        'options' => [
            'conflict_policy' => 'block',
        ],
    ];
}

function template_commit_attempt_summary(array $input): array
{
    $payload = is_array($input['preview_payload'] ?? null) ? $input['preview_payload'] : [];
    $agents = is_array($payload['agents'] ?? null)
        ? array_values(array_map('intval', $payload['agents']))
        : [];

    return [
        'start_date' => is_scalar($payload['start_date'] ?? null)
            ? substr(trim((string)$payload['start_date']), 0, 10)
            : '',
        'end_date' => is_scalar($payload['end_date'] ?? null)
            ? substr(trim((string)$payload['end_date']), 0, 10)
            : '',
        'agents' => $agents,
        'pattern_type' => is_array($payload['pattern'] ?? null)
            ? substr(trim((string)($payload['pattern']['type'] ?? '')), 0, 80)
            : '',
        'confirmation_matches' => ($input['confirmation'] ?? null) === 'APPLY TEMPLATE',
        'conflict_policy' => is_array($input['options'] ?? null)
            ? (string)($input['options']['conflict_policy'] ?? 'block')
            : 'block',
    ];
}

function template_commit_audit(
    \mysqli $conn,
    int $actorId,
    string $action,
    ?int $targetId,
    ?array $before,
    array $after,
    string $reason
): void {
    // Expected actions: shift_assignment.template.commit_attempt and shift_assignment.template.commit.
    if (!function_exists('tracs_table_exists') || !\tracs_table_exists($conn, 'tracs_user_activity_logs')) {
        throw new \RuntimeException('Template commit audit storage is unavailable.');
    }

    $beforeJson = $before === null ? null : json_encode(
        \tracs_scrub_sensitive($before),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    $afterJson = json_encode(
        \tracs_scrub_sensitive($after),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if (($before !== null && $beforeJson === false) || $afterJson === false) {
        throw new \RuntimeException('Unable to serialize template commit audit.');
    }

    $ip = function_exists('tracs_client_ip') ? \tracs_client_ip() : '';
    $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    $stmt = $conn->prepare("
        INSERT INTO tracs_user_activity_logs
          (actor_user_id, target_type, target_id, action, before_data, after_data, reason, ip_address, user_agent, created_at)
        VALUES (?, 'shift_assignment_template', ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    if (!$stmt) {
        throw new \RuntimeException('Unable to prepare template commit audit.');
    }
    $stmt->bind_param(
        'iissssss',
        $actorId,
        $targetId,
        $action,
        $beforeJson,
        $afterJson,
        $reason,
        $ip,
        $agent
    );
    if (!$stmt->execute()) {
        $stmt->close();
        throw new \RuntimeException('Unable to write template commit audit.');
    }
    $stmt->close();
}

function template_commit_assignment_input(array $item): array
{
    return [
        'user_id' => (int)($item['agent']['id'] ?? 0),
        'assignment_date' => (string)($item['date'] ?? ''),
        'assignment_type' => (string)($item['shift_type'] ?? 'regular_shift'),
        'start_time' => (string)($item['start_time'] ?? ''),
        'end_time' => (string)($item['end_time'] ?? ''),
        'shift_template_id' => (int)($item['shift_template_id'] ?? 0) ?: null,
        'break_minutes' => (int)($item['break_minutes'] ?? 0),
        'status' => 'assigned',
        'notes' => 'Generated from controlled template commit.',
        'source' => 'monthly_template',
        'monthly_template_id' => null,
    ];
}

function template_commit_data(
    array $input,
    array $agents,
    array $templates,
    array $assignmentTypes,
    array $holidays,
    array $existingAssignments,
    array $settings,
    callable $workload,
    callable $jumpshiftWarnings,
    callable $conflictWarnings,
    callable $coverageWarnings,
    callable $save
): array {
    $commit = template_commit_input($input);
    // Never trust client preview items blindly; recompute preview server-side.
    $preview = template_preview_data(
        $commit['preview_payload'],
        $agents,
        $templates,
        $assignmentTypes,
        $holidays,
        $existingAssignments,
        $settings,
        $workload,
        $jumpshiftWarnings,
        $conflictWarnings,
        $coverageWarnings
    );
    $normalized = template_preview_input(
        $commit['preview_payload'],
        $agents,
        $templates,
        $assignmentTypes,
        $holidays
    );

    if (($preview['summary']['total_assignments'] ?? 0) < 1) {
        throw new \TRACS\Api\RequestValidationException(
            'Validation failed.',
            ['preview_payload' => 'Template commit must create at least one assignment.']
        );
    }

    if (($preview['summary']['conflicts'] ?? 0) > 0 || ($preview['summary']['blocked_items'] ?? 0) > 0) {
        return [
            'blocked' => true,
            'data' => [
                'conflicts' => $preview['conflicts'],
                'blocked_items' => $preview['blocked_items'],
                'warnings' => $preview['warnings'],
            ],
        ];
    }

    $createdIds = [];
    $created = [];
    foreach ($normalized['items'] as $item) {
        $result = $save(template_commit_assignment_input($item));
        $id = (int)($result['id'] ?? 0);
        if ($id < 1) {
            throw new \RuntimeException('Template commit did not return a created assignment id.');
        }
        $createdIds[] = $id;
        $created[] = [
            'id' => $id,
            'agent_id' => (int)($item['agent']['id'] ?? 0),
            'assignment_date' => (string)($item['date'] ?? ''),
            'shift_type' => (string)($item['shift_type'] ?? ''),
            'start_time' => (string)($item['start_time'] ?? ''),
            'end_time' => (string)($item['end_time_display'] ?? $item['end_time'] ?? ''),
            'shift_template_id' => (int)($item['shift_template_id'] ?? 0) ?: null,
        ];
    }

    return [
        'blocked' => false,
        'data' => [
            'created_assignment_ids' => $createdIds,
            'created_count' => count($createdIds),
            'created_assignments' => $created,
            'warnings' => $preview['warnings'],
            'skipped_items' => [],
            'rollback' => [
                'type' => 'created_assignment_ids',
                'ids' => $createdIds,
            ],
        ],
        'audit' => [
            'range' => $preview['range'],
            'agents' => array_values(array_map(
                static fn(array $agent): int => (int)($agent['id'] ?? 0),
                $normalized['agents']
            )),
            'generated_assignments_count' => count($createdIds),
            'created_assignment_ids' => $createdIds,
            'warnings' => $preview['warnings'],
            'conflicts' => $preview['conflicts'],
            'skipped_items' => [],
            'blocked_items' => $preview['blocked_items'],
            'confirmation' => 'APPLY TEMPLATE',
            'rollback' => [
                'type' => 'created_assignment_ids',
                'ids' => $createdIds,
            ],
        ],
    ];
}
