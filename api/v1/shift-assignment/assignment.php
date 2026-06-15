<?php
declare(strict_types=1);

namespace TRACS\Api\V1\ShiftAssignment;

require_once __DIR__ . '/../../../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../_request.php';

function update_assignment_id(array $query): int
{
    $value = $query['id'] ?? null;
    if (is_array($value) || !preg_match('/^[1-9]\d*$/', trim((string)$value))) {
        throw new \TRACS\Api\RequestValidationException(
            'Validation failed.',
            ['id' => 'Assignment ID must be a positive integer.']
        );
    }

    return (int)$value;
}

function update_assignment_input(array $input, array $existing): array
{
    $allowedFields = [
        'agent_id',
        'assignment_date',
        'shift_type',
        'start_time',
        'end_time',
        'shift_template_id',
        'template_id',
        'break_minutes',
        'status',
        'notes',
    ];
    $errors = [];

    if ($input === []) {
        $errors['request'] = 'At least one supported update field is required.';
    }
    foreach ($input as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowedFields, true)) {
            $errors[(string)$key] = 'Field is not supported.';
            continue;
        }
        $numeric = in_array($key, [
            'agent_id', 'shift_template_id', 'template_id', 'break_minutes',
        ], true);
        if ($numeric && !is_int($value) && !is_string($value) && $value !== null) {
            $errors[$key] = 'Field must be an integer.';
        } elseif (!$numeric && !is_string($value)) {
            $errors[$key] = 'Field must be a string.';
        }
    }

    $startTime = substr((string)($existing['start_datetime'] ?? ''), 11, 5);
    $endTime = substr((string)($existing['end_datetime'] ?? ''), 11, 5);
    $merged = [
        'agent_id' => $input['agent_id'] ?? $existing['user_id'] ?? '',
        'assignment_date' => $input['assignment_date'] ?? $existing['assignment_date'] ?? '',
        'shift_type' => $input['shift_type'] ?? $existing['assignment_type'] ?? '',
        'start_time' => $input['start_time'] ?? $startTime,
        'end_time' => $input['end_time'] ?? $endTime,
        'shift_template_id' => $input['shift_template_id']
            ?? $input['template_id']
            ?? $existing['shift_template_id']
            ?? null,
        'break_minutes' => $input['break_minutes'] ?? $existing['break_minutes'] ?? 0,
        'status' => $input['status'] ?? $existing['status'] ?? 'assigned',
        'notes' => $input['notes'] ?? $existing['notes'] ?? '',
    ];

    if (array_key_exists('shift_template_id', $input)
        && array_key_exists('template_id', $input)
        && trim((string)$input['shift_template_id']) !== trim((string)$input['template_id'])) {
        $errors['shift_template_id'] = 'Provide only one shift template value.';
    }

    $agentId = trim((string)$merged['agent_id']);
    if (!preg_match('/^[1-9]\d*$/', $agentId)) {
        $errors['agent_id'] = 'Agent must be a positive integer.';
    }
    $assignmentDate = trim((string)$merged['assignment_date']);
    if (!\TRACS\Api\safe_date_parse($assignmentDate)) {
        $errors['assignment_date'] = 'Assignment date must use YYYY-MM-DD.';
    }

    $assignmentTypes = [
        'regular_shift', 'middle_shift', 'lembur', 'standby', 'replacement_shift',
        'holiday_coverage', 'emergency_coverage', 'training', 'off_leave',
    ];
    $shiftType = trim((string)$merged['shift_type']);
    if (!in_array($shiftType, $assignmentTypes, true)) {
        $errors['shift_type'] = 'Shift type is not supported.';
    }

    $startTime = trim((string)$merged['start_time']);
    $endTime = trim((string)$merged['end_time']);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
        $errors['start_time'] = 'Start time must use HH:MM.';
    }
    if ($endTime !== '24:00' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
        $errors['end_time'] = 'End time must use HH:MM or 24:00.';
    }
    $serviceEndTime = $endTime === '24:00' ? '00:00' : $endTime;
    if ($startTime !== '' && $serviceEndTime !== '' && $startTime === $serviceEndTime) {
        $errors['end_time'] = 'Shift duration cannot be zero.';
    }

    $templateId = trim((string)($merged['shift_template_id'] ?? ''));
    if ($templateId !== '' && !preg_match('/^[1-9]\d*$/', $templateId)) {
        $errors['shift_template_id'] = 'Shift template must be a positive integer.';
    }
    $breakMinutes = trim((string)$merged['break_minutes']);
    if (!preg_match('/^\d+$/', $breakMinutes) || (int)$breakMinutes > 720) {
        $errors['break_minutes'] = 'Break minutes must be between 0 and 720.';
    }

    $statuses = ['assigned', 'confirmed', 'active', 'completed', 'cancelled', 'no_show', 'replaced'];
    $status = trim((string)$merged['status']);
    if (!in_array($status, $statuses, true)) {
        $errors['status'] = 'Status is not supported.';
    }
    $notes = trim((string)$merged['notes']);
    $notesLength = function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes);
    if ($notesLength > 3000) {
        $errors['notes'] = 'Notes must not exceed 3000 characters.';
    }

    if ($errors !== []) {
        throw new \TRACS\Api\RequestValidationException('Validation failed.', $errors);
    }

    return [
        'id' => (int)($existing['id'] ?? 0),
        'user_id' => (int)$agentId,
        'assignment_date' => $assignmentDate,
        'assignment_type' => $shiftType,
        'start_time' => $startTime,
        'end_time' => $serviceEndTime,
        'shift_template_id' => $templateId === '' ? null : (int)$templateId,
        'break_minutes' => (int)$breakMinutes,
        'status' => $status,
        'notes' => $notes,
        'source' => (string)($existing['source'] ?? 'manual'),
        'monthly_template_id' => (int)($existing['monthly_template_id'] ?? 0) ?: null,
        'is_manual_duration_override' => !empty($existing['is_manual_duration_override']),
    ];
}

function update_assignment_safe_summary(array $assignment): array
{
    $startTime = substr((string)($assignment['start_datetime'] ?? ''), 11, 5);
    $endTime = substr((string)($assignment['end_datetime'] ?? ''), 11, 5);
    $isCrossDay = !empty($assignment['is_cross_day'])
        || substr((string)($assignment['end_datetime'] ?? ''), 0, 10)
            > (string)($assignment['assignment_date'] ?? '');
    $displayEnd = $isCrossDay && $startTime === '16:00' && $endTime === '00:00'
        ? '24:00'
        : $endTime;
    $date = \TRACS\Api\safe_date_parse((string)($assignment['assignment_date'] ?? ''));

    return [
        'id' => (int)($assignment['id'] ?? 0),
        'agent_id' => (int)($assignment['user_id'] ?? 0),
        'assignment_date' => (string)($assignment['assignment_date'] ?? ''),
        'assignment_date_display' => $date?->format('d-m-Y') ?? '',
        'shift_template_id' => (int)($assignment['shift_template_id'] ?? 0) ?: null,
        'shift_type' => (string)($assignment['assignment_type'] ?? ''),
        'start_time' => $startTime,
        'end_time' => $endTime,
        'end_time_display' => $displayEnd,
        'display_range' => $startTime . '-' . $displayEnd,
        'break_minutes' => (int)($assignment['break_minutes'] ?? 0),
        'duration_minutes' => (int)($assignment['calculated_duration_minutes'] ?? 0),
        'status' => (string)($assignment['status'] ?? ''),
        'source' => (string)($assignment['source'] ?? 'manual'),
        'is_cross_day' => $isCrossDay,
    ];
}

function update_assignment_changed_fields(array $before, array $after): array
{
    $changed = [];
    foreach ($after as $field => $value) {
        if ($field === 'assignment_date_display' || $field === 'end_time_display'
            || $field === 'display_range' || $field === 'duration_minutes'
            || $field === 'is_cross_day') {
            continue;
        }
        if (($before[$field] ?? null) !== $value) {
            $changed[] = $field;
        }
    }
    return $changed;
}

function update_assignment_attempt_summary(array $input): array
{
    $safe = [];
    foreach ([
        'agent_id', 'assignment_date', 'shift_type', 'start_time', 'end_time',
        'shift_template_id', 'template_id', 'break_minutes', 'status',
    ] as $field) {
        if (isset($input[$field]) && is_scalar($input[$field])) {
            $safe[$field] = substr(trim((string)$input[$field]), 0, 80);
        }
    }
    return $safe;
}

function update_assignment_data(
    array $input,
    array $existing,
    callable $save,
    callable $fetch
): array {
    $normalized = update_assignment_input($input, $existing);
    $result = $save($normalized);
    $updated = $fetch((int)$normalized['id']);
    if (!is_array($updated)) {
        throw new \RuntimeException('Assignment not found.');
    }

    return [
        'assignment' => update_assignment_safe_summary($updated),
        'warnings' => array_values(is_array($result['warnings'] ?? null)
            ? $result['warnings']
            : []),
    ];
}
