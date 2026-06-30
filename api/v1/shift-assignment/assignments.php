<?php
declare(strict_types=1);

namespace TRACS\Api\V1\ShiftAssignment;

require_once __DIR__ . '/../../../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../_request.php';

function assignments_query(array $query, ?\DateTimeImmutable $today = null): array
{
    $timezone = new \DateTimeZone('Asia/Jakarta');
    $today = ($today ?? new \DateTimeImmutable('today', $timezone))->setTimezone($timezone);
    $errors = [];

    foreach ($query as $key => $value) {
        if (is_array($value)) {
            $errors[(string)$key] = 'Query parameters must be scalar values.';
        }
    }
    if ($errors !== []) {
        throw new \TRACS\Api\RequestValidationException('Query validation failed.', $errors);
    }

    $view = trim((string)($query['view'] ?? 'weekly'));
    $views = ['daily', 'weekly', 'monthly'];
    if (!in_array($view, $views, true)) {
        $errors['view'] = 'View must be daily, weekly, or monthly.';
    }

    $startInput = trim((string)($query['start_date'] ?? ''));
    $endInput = trim((string)($query['end_date'] ?? ''));
    if (($startInput === '') !== ($endInput === '')) {
        $errors['date_range'] = 'Start date and end date must be provided together.';
    }

    $start = $startInput !== '' ? \TRACS\Api\safe_date_parse($startInput) : null;
    $end = $endInput !== '' ? \TRACS\Api\safe_date_parse($endInput) : null;
    if ($startInput !== '' && !$start) {
        $errors['start_date'] = 'Start date must use YYYY-MM-DD.';
    }
    if ($endInput !== '' && !$end) {
        $errors['end_date'] = 'End date must use YYYY-MM-DD.';
    }

    if (!$start && !$end && $errors === []) {
        if ($view === 'daily') {
            $start = $today;
            $end = $today;
        } elseif ($view === 'monthly') {
            $start = $today->modify('first day of this month');
            $end = $today->modify('last day of this month');
        } else {
            $start = $today->modify('monday this week');
            $end = $start->modify('+6 days');
        }
    }

    if ($start && $end) {
        if ($end < $start) {
            $errors['date_range'] = 'End date must be on or after start date.';
        } else {
            $days = (int)$start->diff($end)->days;
            if ($view === 'daily' && $days !== 0) {
                $errors['date_range'] = 'Daily view requires the same start and end date.';
            } elseif ($view === 'weekly' && $days > 6) {
                $errors['date_range'] = 'Weekly view supports a maximum range of seven days.';
            } elseif ($view === 'monthly' && (
                $start->format('Y-m') !== $end->format('Y-m') || $days > 30
            )) {
                $errors['date_range'] = 'Monthly view must stay within one calendar month.';
            }
        }
    }

    $positiveInteger = static function (string $key) use ($query, &$errors): ?int {
        $value = trim((string)($query[$key] ?? ''));
        if ($value === '') {
            return null;
        }
        if (!preg_match('/^[1-9]\d*$/', $value)) {
            $errors[$key] = str_replace('_', ' ', ucfirst($key)) . ' must be a positive integer.';
            return null;
        }
        return (int)$value;
    };

    $agentId = $positiveInteger('agent_id');
    $divisionId = $positiveInteger('division');

    $roles = ['super_admin', 'admin', 'supervisor', 'agent', 'intern', 'viewer'];
    $role = trim((string)($query['role'] ?? ''));
    if ($role !== '' && !in_array($role, $roles, true)) {
        $errors['role'] = 'Role is not supported.';
    }

    $shiftTypes = [
        'regular_shift', 'middle_shift', 'lembur', 'standby', 'replacement_shift',
        'holiday_coverage', 'emergency_coverage', 'training', 'off_leave',
    ];
    $shiftType = trim((string)($query['shift_type'] ?? ''));
    if ($shiftType !== '' && !in_array($shiftType, $shiftTypes, true)) {
        $errors['shift_type'] = 'Shift type is not supported.';
    }

    $statuses = ['assigned', 'confirmed', 'active', 'completed', 'cancelled', 'no_show', 'replaced'];
    $status = trim((string)($query['status'] ?? ''));
    if ($status !== '' && !in_array($status, $statuses, true)) {
        $errors['status'] = 'Status is not supported.';
    }

    if ($errors !== []) {
        throw new \TRACS\Api\RequestValidationException('Query validation failed.', $errors);
    }

    return [
        'view' => $view,
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'user_id' => $agentId,
        'role' => $role,
        'division_id' => $divisionId,
        'assignment_type' => $shiftType,
        'status' => $status,
    ];
}

function filter_agents_by_role(array $agents, string $role): array
{
    if ($role === '') {
        return array_values($agents);
    }

    return array_values(array_filter(
        $agents,
        static fn(array $agent): bool => (string)($agent['role_slug'] ?? '') === $role
    ));
}

function create_assignment_input(array $input): array
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

    foreach ($input as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowedFields, true)) {
            $errors[(string)$key] = 'Field is not supported.';
            continue;
        }
        $numericField = in_array($key, [
            'agent_id', 'shift_template_id', 'template_id', 'break_minutes',
        ], true);
        if ($numericField) {
            if (!is_int($value) && !is_string($value)) {
                $errors[$key] = 'Field must be an integer.';
            }
        } elseif (!is_string($value)) {
            $errors[$key] = 'Field must be a string.';
        }
    }

    foreach ([
        'agent_id' => 'Agent',
        'assignment_date' => 'Assignment date',
        'shift_type' => 'Shift type',
        'start_time' => 'Start time',
        'end_time' => 'End time',
    ] as $key => $label) {
        $value = $input[$key] ?? null;
        if ($value === null || (is_string($value) && trim($value) === '')) {
            $errors[$key] = $label . ' is required.';
        }
    }

    $agentId = trim((string)($input['agent_id'] ?? ''));
    if ($agentId !== '' && !preg_match('/^[1-9]\d*$/', $agentId)) {
        $errors['agent_id'] = 'Agent must be a positive integer.';
    }

    $assignmentDate = trim((string)($input['assignment_date'] ?? ''));
    if ($assignmentDate !== '' && !\TRACS\Api\safe_date_parse($assignmentDate)) {
        $errors['assignment_date'] = 'Assignment date must use YYYY-MM-DD.';
    }

    $assignmentTypes = [
        'regular_shift', 'middle_shift', 'lembur', 'standby', 'replacement_shift',
        'holiday_coverage', 'emergency_coverage', 'training', 'off_leave',
    ];
    $shiftType = trim((string)($input['shift_type'] ?? ''));
    if ($shiftType !== '' && !in_array($shiftType, $assignmentTypes, true)) {
        $errors['shift_type'] = 'Shift type is not supported.';
    }

    $startTime = trim((string)($input['start_time'] ?? ''));
    $endTime = trim((string)($input['end_time'] ?? ''));
    if ($startTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
        $errors['start_time'] = 'Start time must use HH:MM.';
    }
    if ($endTime !== '' && $endTime !== '24:00'
        && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
        $errors['end_time'] = 'End time must use HH:MM or 24:00.';
    }
    $serviceEndTime = $endTime === '24:00' ? '00:00' : $endTime;
    if ($startTime !== '' && $serviceEndTime !== '' && $startTime === $serviceEndTime) {
        $errors['end_time'] = 'Shift duration cannot be zero.';
    }

    $templateRaw = $input['shift_template_id'] ?? $input['template_id'] ?? '';
    $templateId = trim((string)$templateRaw);
    if (array_key_exists('shift_template_id', $input)
        && array_key_exists('template_id', $input)
        && trim((string)$input['shift_template_id']) !== trim((string)$input['template_id'])) {
        $errors['shift_template_id'] = 'Provide only one shift template value.';
    }
    if ($templateId !== '' && !preg_match('/^[1-9]\d*$/', $templateId)) {
        $errors['shift_template_id'] = 'Shift template must be a positive integer.';
    }

    $breakRaw = trim((string)($input['break_minutes'] ?? '0'));
    if (!preg_match('/^\d+$/', $breakRaw) || (int)$breakRaw > 720) {
        $errors['break_minutes'] = 'Break minutes must be between 0 and 720.';
    }

    $status = trim((string)($input['status'] ?? 'assigned'));
    if (!in_array($status, ['assigned', 'confirmed'], true)) {
        $errors['status'] = 'New assignments may be assigned or confirmed.';
    }

    $notes = trim((string)($input['notes'] ?? ''));
    $notesLength = function_exists('mb_strlen') ? mb_strlen($notes) : strlen($notes);
    if ($notesLength > 3000) {
        $errors['notes'] = 'Notes must not exceed 3000 characters.';
    }

    if ($errors !== []) {
        throw new \TRACS\Api\RequestValidationException('Validation failed.', $errors);
    }

    return [
        'user_id' => (int)$agentId,
        'assignment_date' => $assignmentDate,
        'assignment_type' => $shiftType,
        'start_time' => $startTime,
        'end_time' => $serviceEndTime,
        'shift_template_id' => $templateId === '' ? null : (int)$templateId,
        'break_minutes' => (int)$breakRaw,
        'status' => $status,
        'notes' => $notes,
        'source' => 'manual',
    ];
}

function validation_error_list(array $errors): array
{
    $result = [];
    foreach ($errors as $field => $message) {
        $result[] = [
            'field' => (string)$field,
            'message' => (string)$message,
        ];
    }
    return $result;
}

function create_assignment_audit_summary(array $input): array
{
    return [
        'agent_id' => (int)($input['user_id'] ?? 0),
        'assignment_date' => (string)($input['assignment_date'] ?? ''),
        'shift_type' => (string)($input['assignment_type'] ?? ''),
        'start_time' => (string)($input['start_time'] ?? ''),
        'end_time' => (string)($input['end_time'] ?? ''),
        'shift_template_id' => isset($input['shift_template_id'])
            ? (int)$input['shift_template_id']
            : null,
        'break_minutes' => (int)($input['break_minutes'] ?? 0),
        'status' => (string)($input['status'] ?? ''),
        'source' => 'manual',
    ];
}

function create_assignment_attempt_summary(array $input): array
{
    $scalar = static fn(string $key): string =>
        is_scalar($input[$key] ?? null) ? trim((string)$input[$key]) : '';

    return [
        'agent_id' => preg_match('/^[1-9]\d*$/', $scalar('agent_id'))
            ? (int)$scalar('agent_id')
            : null,
        'assignment_date' => substr($scalar('assignment_date'), 0, 10),
        'shift_type' => substr($scalar('shift_type'), 0, 80),
        'start_time' => substr($scalar('start_time'), 0, 5),
        'end_time' => substr($scalar('end_time'), 0, 5),
        'status' => substr($scalar('status') ?: 'assigned', 0, 20),
        'source' => 'manual',
    ];
}

function create_assignment_data(array $input, callable $save): array
{
    $normalized = create_assignment_input($input);
    $result = $save($normalized);
    $displayEnd = trim((string)($input['end_time'] ?? '')) === '24:00'
        && !empty($result['is_cross_day'])
            ? '24:00'
            : $normalized['end_time'];
    $date = \TRACS\Api\safe_date_parse($normalized['assignment_date']);

    return [
        'assignment' => [
            'id' => (int)($result['id'] ?? 0),
            'agent_id' => (int)$normalized['user_id'],
            'assignment_date' => $normalized['assignment_date'],
            'assignment_date_display' => $date?->format('d-m-Y') ?? '',
            'shift_template_id' => $normalized['shift_template_id'],
            'shift_type' => $normalized['assignment_type'],
            'start_time' => $normalized['start_time'],
            'end_time' => $normalized['end_time'],
            'end_time_display' => $displayEnd,
            'display_range' => $normalized['start_time'] . '-' . $displayEnd,
            'break_minutes' => $normalized['break_minutes'],
            'duration_minutes' => (int)($result['duration_minutes'] ?? 0),
            'status' => $normalized['status'],
            'source' => 'manual',
            'is_cross_day' => (bool)($result['is_cross_day'] ?? false),
        ],
        'warnings' => array_values(is_array($result['warnings'] ?? null)
            ? $result['warnings']
            : []),
    ];
}

function assignments_data(
    array $query,
    array $assignments,
    array $recap,
    array $warnings,
    array $holidays
): array {
    $displayDate = static function (mixed $value): string {
        $date = \TRACS\Api\safe_date_parse(substr((string)$value, 0, 10));
        return $date ? $date->format('d-m-Y') : '';
    };
    $safeName = static function (mixed $value, string $fallback): string {
        $value = trim((string)$value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $value) ?? '';
        if ($value === '' || filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $fallback;
        }
        return function_exists('mb_substr') ? mb_substr($value, 0, 120) : substr($value, 0, 120);
    };

    $assignmentRows = array_map(
        static function (array $row) use ($displayDate, $safeName): array {
            $startTime = substr((string)($row['start_datetime'] ?? ''), 11, 5);
            $endTime = substr((string)($row['end_datetime'] ?? ''), 11, 5);
            $displayEnd = !empty($row['is_cross_day']) && $startTime === '16:00' && $endTime === '00:00'
                ? '24:00'
                : $endTime;

            return [
                'id' => (int)($row['id'] ?? 0),
                'agent' => [
                    'id' => (int)($row['user_id'] ?? 0),
                    'name' => $safeName($row['agent_name'] ?? '', 'Agent'),
                ],
                'division' => [
                    'id' => (int)($row['division_id'] ?? 0),
                    'name' => (string)($row['division_name'] ?? ''),
                ],
                'shift' => [
                    'template_id' => (int)($row['shift_template_id'] ?? 0),
                    'name' => (string)($row['shift_name'] ?? 'Custom Shift'),
                    'color' => (string)($row['color_label'] ?? '#4f46e5'),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'end_time_display' => $displayEnd,
                    'display_range' => $startTime . '-' . $displayEnd,
                    'is_cross_day' => (bool)($row['is_cross_day'] ?? false),
                ],
                'assignment_date' => (string)($row['assignment_date'] ?? ''),
                'assignment_date_display' => $displayDate($row['assignment_date'] ?? ''),
                'start_datetime' => (string)($row['start_datetime'] ?? ''),
                'end_datetime' => (string)($row['end_datetime'] ?? ''),
                'break_minutes' => (int)($row['break_minutes'] ?? 0),
                'duration_minutes' => (int)($row['calculated_duration_minutes'] ?? 0),
                'type' => (string)($row['assignment_type'] ?? ''),
                'type_name' => (string)($row['assignment_type_name'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'approval_status' => (string)($row['approval_status'] ?? 'not_required'),
                'source' => (string)($row['source'] ?? 'manual'),
                'is_overtime' => (bool)($row['is_overtime'] ?? false),
                'is_holiday' => (bool)($row['is_holiday_assignment'] ?? false),
                'availability' => (string)($row['availability_status'] ?? 'available'),
            ];
        },
        $assignments
    );

    $statusCounts = [];
    $uniqueAgents = [];
    $totalMinutes = 0;
    $overtimeAssignments = 0;
    $holidayAssignments = 0;
    foreach ($assignmentRows as $row) {
        $status = $row['status'];
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        $uniqueAgents[$row['agent']['id']] = true;
        $totalMinutes += $row['duration_minutes'];
        $overtimeAssignments += $row['is_overtime'] ? 1 : 0;
        $holidayAssignments += $row['is_holiday'] ? 1 : 0;
    }
    ksort($statusCounts);

    $recapRows = array_map(
        static fn(array $row): array => [
            'agent_id' => (int)($row['user_id'] ?? 0),
            'agent_name' => $safeName($row['agent_name'] ?? '', 'Agent'),
            'division_name' => (string)($row['division_name'] ?? ''),
            'working_days' => (int)($row['working_days'] ?? 0),
            'total_minutes' => (int)($row['total_minutes'] ?? 0),
            'regular_minutes' => (int)($row['regular_minutes'] ?? 0),
            'overtime_minutes' => (int)($row['overtime_minutes'] ?? 0),
            'holiday_minutes' => (int)($row['holiday_minutes'] ?? 0),
            'standby_minutes' => (int)($row['standby_minutes'] ?? 0),
            'target_minutes' => (int)($row['target_minutes'] ?? 0),
            'difference_minutes' => (int)($row['difference_minutes'] ?? 0),
            'minimum_rest_minutes' => isset($row['minimum_rest_minutes'])
                ? (int)$row['minimum_rest_minutes']
                : null,
            'jumpshift_count' => (int)($row['jumpshift_count'] ?? 0),
            'conflict_count' => (int)($row['conflict_count'] ?? 0),
            'status' => (string)($row['status'] ?? ''),
        ],
        $recap
    );

    $warningRows = array_map(
        static function (array $row) use ($displayDate, $safeName): array {
            $result = [
                'type' => (string)($row['type'] ?? 'warning'),
                'message' => (string)($row['message'] ?? ''),
            ];
            foreach (['user_id', 'previous_assignment_id', 'next_assignment_id', 'rest_minutes',
                'assigned_agents', 'minimum_agents', 'missing_agents'] as $key) {
                if (isset($row[$key])) {
                    $result[$key] = (int)$row[$key];
                }
            }
            if (isset($row['assignment_ids']) && is_array($row['assignment_ids'])) {
                $result['assignment_ids'] = array_values(array_map('intval', $row['assignment_ids']));
            }
            if (isset($row['agent_name'])) {
                $result['agent_name'] = $safeName($row['agent_name'], 'Agent');
            }
            if (isset($row['date'])) {
                $result['date'] = (string)$row['date'];
                $result['date_display'] = $displayDate($row['date']);
            }
            if (isset($row['day_type'])) {
                $result['day_type'] = (string)$row['day_type'];
            }
            if (isset($row['start_datetime'])) {
                $result['start_datetime'] = (string)$row['start_datetime'];
            }
            if (isset($row['end_datetime'])) {
                $result['end_datetime'] = (string)$row['end_datetime'];
            }
            return $result;
        },
        $warnings
    );

    $holidayRows = array_map(
        static fn(array $row): array => [
            'id' => (int)($row['id'] ?? 0),
            'date' => (string)($row['holiday_date'] ?? ''),
            'date_display' => $displayDate($row['holiday_date'] ?? ''),
            'name' => (string)($row['holiday_name'] ?? ''),
            'type' => (string)($row['holiday_type'] ?? ''),
        ],
        $holidays
    );

    return [
        'view' => (string)$query['view'],
        'range' => [
            'start_date' => (string)$query['start'],
            'end_date' => (string)$query['end'],
            'start_date_display' => $displayDate($query['start']),
            'end_date_display' => $displayDate($query['end']),
        ],
        'assignments' => $assignmentRows,
        'summary' => [
            'assignment_count' => count($assignmentRows),
            'agent_count' => count($uniqueAgents),
            'total_minutes' => $totalMinutes,
            'overtime_assignment_count' => $overtimeAssignments,
            'holiday_assignment_count' => $holidayAssignments,
            'status_counts' => $statusCounts === [] ? (object)[] : $statusCounts,
            'workload' => $recapRows,
        ],
        'warnings' => $warningRows,
        'holidays' => $holidayRows,
    ];
}
