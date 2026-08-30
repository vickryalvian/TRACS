<?php
declare(strict_types=1);

namespace TRACS\Api\V1\ShiftAssignment\Templates;

require_once __DIR__ . '/../../../../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../../_request.php';

function template_preview_input(
    array $input,
    array $agents,
    array $templates,
    array $assignmentTypes,
    array $holidays
): array {
    $allowedFields = ['start_date', 'end_date', 'pattern', 'agents', 'options'];
    $errors = [];

    foreach ($input as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowedFields, true)) {
            $errors[(string)$key] = 'Field is not supported.';
        }
    }

    $startInput = trim((string)($input['start_date'] ?? ''));
    $endInput = trim((string)($input['end_date'] ?? ''));
    $start = $startInput !== '' ? \TRACS\Api\safe_date_parse($startInput) : null;
    $end = $endInput !== '' ? \TRACS\Api\safe_date_parse($endInput) : null;

    if ($startInput === '') {
        $errors['start_date'] = 'Start date is required.';
    } elseif (!$start) {
        $errors['start_date'] = 'Start date must use YYYY-MM-DD.';
    }
    if ($endInput === '') {
        $errors['end_date'] = 'End date is required.';
    } elseif (!$end) {
        $errors['end_date'] = 'End date must use YYYY-MM-DD.';
    }
    if ($start && $end) {
        if ($end < $start) {
            $errors['date_range'] = 'End date must be on or after start date.';
        } elseif ((int)$start->diff($end)->days > 34) {
            $errors['date_range'] = 'Template preview supports a maximum range of 35 days.';
        }
    }

    $pattern = $input['pattern'] ?? null;
    if (!is_array($pattern)) {
        $errors['pattern'] = 'Pattern is required.';
        $pattern = [];
    }
    $patternType = trim((string)($pattern['type'] ?? ''));
    if ($patternType !== 'weekly_rotation') {
        $errors['pattern.type'] = 'Pattern type must be weekly_rotation.';
    }
    $patternItems = $pattern['items'] ?? null;
    if (!is_array($patternItems) || $patternItems === []) {
        $errors['pattern.items'] = 'Pattern items are required.';
        $patternItems = [];
    }

    $agentRows = [];
    foreach ($agents as $agent) {
        $agentRows[(int)($agent['id'] ?? 0)] = $agent;
    }

    $requestedAgents = $input['agents'] ?? [];
    if (!is_array($requestedAgents) || $requestedAgents === []) {
        $errors['agents'] = 'At least one agent is required.';
        $requestedAgents = [];
    }
    $agentIds = [];
    foreach ($requestedAgents as $index => $agentId) {
        if (!is_int($agentId) && !is_string($agentId)) {
            $errors["agents.{$index}"] = 'Agent must be a positive integer.';
            continue;
        }
        $agentId = trim((string)$agentId);
        if (!preg_match('/^[1-9]\d*$/', $agentId)) {
            $errors["agents.{$index}"] = 'Agent must be a positive integer.';
            continue;
        }
        $id = (int)$agentId;
        if (!isset($agentRows[$id])) {
            $errors["agents.{$index}"] = 'Agent is not active or not in scope.';
            continue;
        }
        $agentIds[$id] = true;
    }

    $templateRows = [];
    foreach ($templates as $template) {
        $id = (int)($template['id'] ?? 0);
        if ($id > 0 && !empty($template['is_active'])) {
            $templateRows[$id] = $template;
        }
    }
    $typeRows = [];
    foreach ($assignmentTypes as $type) {
        $slug = (string)($type['type_slug'] ?? '');
        if ($slug !== '') {
            $typeRows[$slug] = $type;
        }
    }
    $holidayDates = [];
    foreach ($holidays as $holiday) {
        $date = (string)($holiday['holiday_date'] ?? '');
        if ($date !== '') {
            $holidayDates[$date][] = $holiday;
        }
    }

    $items = [];
    foreach ($patternItems as $index => $item) {
        if (!is_array($item)) {
            $errors["pattern.items.{$index}"] = 'Pattern item must be an object.';
            continue;
        }

        $itemAgentIds = $agentIds;
        if (array_key_exists('agent_id', $item)) {
            $rawAgent = trim((string)$item['agent_id']);
            if (!preg_match('/^[1-9]\d*$/', $rawAgent) || !isset($agentRows[(int)$rawAgent])) {
                $errors["pattern.items.{$index}.agent_id"] = 'Item agent is not active or not in scope.';
                $itemAgentIds = [];
            } else {
                $itemAgentIds = [(int)$rawAgent => true];
            }
        }

        $dates = template_preview_item_dates($item, $start, $end, $index, $errors);
        $shiftType = trim((string)($item['shift_type'] ?? 'regular_shift'));
        if (!isset($typeRows[$shiftType])) {
            $errors["pattern.items.{$index}.shift_type"] = 'Shift type is not supported.';
        }

        $startTime = trim((string)($item['start_time'] ?? ''));
        $endTime = trim((string)($item['end_time'] ?? ''));
        if ($startTime === '' || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
            $errors["pattern.items.{$index}.start_time"] = 'Start time must use HH:MM.';
        }
        if ($endTime === '' || ($endTime !== '24:00'
            && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime))) {
            $errors["pattern.items.{$index}.end_time"] = 'End time must use HH:MM or 24:00.';
        }
        $duration = template_preview_duration_minutes($startTime, $endTime);
        if ($duration <= 0) {
            $errors["pattern.items.{$index}.end_time"] = 'Shift duration must be greater than zero.';
        }

        $templateRaw = $item['shift_template_id'] ?? $item['template_id'] ?? null;
        $templateId = null;
        if ($templateRaw !== null && trim((string)$templateRaw) !== '') {
            $rawTemplate = trim((string)$templateRaw);
            if (!preg_match('/^[1-9]\d*$/', $rawTemplate) || !isset($templateRows[(int)$rawTemplate])) {
                $errors["pattern.items.{$index}.shift_template_id"] = 'Shift template is not active or not supported.';
            } else {
                $templateId = (int)$rawTemplate;
            }
        }

        $breakRaw = trim((string)($item['break_minutes'] ?? '0'));
        if (!preg_match('/^\d+$/', $breakRaw) || (int)$breakRaw > 720) {
            $errors["pattern.items.{$index}.break_minutes"] = 'Break minutes must be between 0 and 720.';
        }

        if ($dates === [] || $itemAgentIds === []) {
            continue;
        }

        foreach ($dates as $date) {
            foreach (array_keys($itemAgentIds) as $agentId) {
                $type = $typeRows[$shiftType] ?? [];
                $template = $templateId ? ($templateRows[$templateId] ?? []) : [];
                $items[] = [
                    'client_item_id' => isset($item['client_item_id']) && is_scalar($item['client_item_id'])
                        ? substr(trim((string)$item['client_item_id']), 0, 80)
                        : 'item-' . ($index + 1),
                    'agent' => $agentRows[$agentId],
                    'date' => $date,
                    'shift_type' => $shiftType,
                    'shift_type_name' => (string)($type['type_name'] ?? str_replace('_', ' ', $shiftType)),
                    'shift_template_id' => $templateId,
                    'shift_template_name' => (string)($template['shift_name'] ?? 'Custom Shift'),
                    'color' => (string)($template['color_label'] ?? $type['color_label'] ?? '#4f46e5'),
                    'start_time' => $startTime,
                    'end_time' => $endTime === '24:00' ? '00:00' : $endTime,
                    'end_time_display' => $endTime,
                    'break_minutes' => (int)$breakRaw,
                    'duration_minutes' => max(0, $duration - (int)$breakRaw),
                    'is_cross_day' => $endTime === '24:00' || strcmp($endTime, $startTime) < 0,
                    'is_overtime' => !empty($type['count_as_overtime']),
                    'is_holiday' => isset($holidayDates[$date]) || !empty($type['count_as_holiday_hour']),
                    'holiday_names' => array_map(
                        static fn(array $holiday): string => (string)($holiday['holiday_name'] ?? ''),
                        $holidayDates[$date] ?? []
                    ),
                ];
            }
        }
    }

    if ($errors !== []) {
        throw new \TRACS\Api\RequestValidationException('Validation failed.', $errors);
    }

    return [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d'),
        'items' => $items,
        'agents' => array_values(array_intersect_key($agentRows, $agentIds)),
    ];
}

function template_preview_item_dates(
    array $item,
    ?\DateTimeImmutable $start,
    ?\DateTimeImmutable $end,
    int $index,
    array &$errors
): array {
    if (!$start || !$end) {
        return [];
    }

    $dateInput = trim((string)($item['date'] ?? ''));
    if ($dateInput !== '') {
        $date = \TRACS\Api\safe_date_parse($dateInput);
        if (!$date) {
            $errors["pattern.items.{$index}.date"] = 'Item date must use YYYY-MM-DD.';
            return [];
        }
        if ($date < $start || $date > $end) {
            $errors["pattern.items.{$index}.date"] = 'Item date must be inside the preview range.';
            return [];
        }
        return [$date->format('Y-m-d')];
    }

    $dayInput = trim((string)($item['day_of_week'] ?? ''));
    if ($dayInput === '' || !preg_match('/^[1-7]$/', $dayInput)) {
        $errors["pattern.items.{$index}.day_of_week"] = 'Day of week must be 1 through 7 or provide an item date.';
        return [];
    }

    $dates = [];
    for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
        if ((int)$cursor->format('N') === (int)$dayInput) {
            $dates[] = $cursor->format('Y-m-d');
        }
    }
    return $dates;
}

function template_preview_duration_minutes(string $startTime, string $endTime): int
{
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
        return 0;
    }
    if ($endTime !== '24:00' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $endTime)) {
        return 0;
    }

    [$startHour, $startMinute] = array_map('intval', explode(':', $startTime));
    [$endHour, $endMinute] = $endTime === '24:00'
        ? [24, 0]
        : array_map('intval', explode(':', $endTime));
    $start = ($startHour * 60) + $startMinute;
    $end = ($endHour * 60) + $endMinute;
    if ($end <= $start) {
        $end += 1440;
    }
    return $end - $start;
}

function template_preview_assignment_rows(array $items): array
{
    $rows = [];
    $id = -1;
    foreach ($items as $item) {
        $date = (string)$item['date'];
        $start = $date . ' ' . $item['start_time'] . ':00';
        $endDate = !empty($item['is_cross_day'])
            ? (new \DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d')
            : $date;
        $end = $endDate . ' ' . $item['end_time'] . ':00';
        $agent = $item['agent'];
        $rows[] = [
            'id' => $id--,
            'user_id' => (int)$agent['id'],
            'agent_name' => (string)($agent['agent_name'] ?? 'Agent'),
            'division_id' => (int)($agent['division_id'] ?? 0),
            'division_name' => (string)($agent['division_name'] ?? ''),
            'shift_template_id' => (int)($item['shift_template_id'] ?? 0),
            'shift_name' => (string)$item['shift_template_name'],
            'color_label' => (string)$item['color'],
            'assignment_date' => $date,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'is_cross_day' => (bool)$item['is_cross_day'],
            'break_minutes' => (int)$item['break_minutes'],
            'calculated_duration_minutes' => (int)$item['duration_minutes'],
            'assignment_type' => (string)$item['shift_type'],
            'assignment_type_name' => (string)$item['shift_type_name'],
            'status' => 'assigned',
            'is_overtime' => (bool)$item['is_overtime'],
            'is_holiday_assignment' => (bool)$item['is_holiday'],
            'source' => 'preview',
            'monthly_template_id' => 0,
        ];
    }
    return $rows;
}

function template_preview_data(
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
    callable $coverageWarnings
): array {
    $normalized = template_preview_input($input, $agents, $templates, $assignmentTypes, $holidays);
    $previewRows = template_preview_assignment_rows($normalized['items']);
    $combined = array_merge($existingAssignments, $previewRows);
    $conflictRows = template_preview_conflicts(
        $previewRows,
        $existingAssignments,
        $conflictWarnings($combined)
    );
    $warningRows = template_preview_warnings(
        $previewRows,
        $jumpshiftWarnings($combined, $settings),
        $coverageWarnings($combined),
        $workload($combined, $normalized['agents'], $settings, $normalized['start'], $normalized['end'])
    );
    $blocked = template_preview_blocked_items($conflictRows);

    $displayDate = static function (string $date): string {
        return \TRACS\Api\safe_date_parse($date)?->format('d-m-Y') ?? '';
    };

    return [
        'range' => [
            'start_date' => $normalized['start'],
            'end_date' => $normalized['end'],
            'start_date_display' => $displayDate($normalized['start']),
            'end_date_display' => $displayDate($normalized['end']),
        ],
        'items' => array_map(
            static fn(array $row): array => template_preview_item_payload($row),
            $previewRows
        ),
        'summary' => [
            'total_assignments' => count($previewRows),
            'agents' => count(array_unique(array_map(
                static fn(array $row): int => (int)$row['user_id'],
                $previewRows
            ))),
            'warnings' => count($warningRows),
            'conflicts' => count($conflictRows),
            'blocked_items' => count($blocked),
        ],
        'warnings' => $warningRows,
        'conflicts' => $conflictRows,
        'blocked_items' => $blocked,
    ];
}

function template_preview_item_payload(array $row): array
{
    $displayDate = \TRACS\Api\safe_date_parse((string)$row['assignment_date'])?->format('d-m-Y') ?? '';
    $startTime = substr((string)$row['start_datetime'], 11, 5);
    $endTime = substr((string)$row['end_datetime'], 11, 5);
    $displayEnd = !empty($row['is_cross_day']) && $endTime === '00:00' ? '24:00' : $endTime;

    return [
        'preview_id' => (int)$row['id'],
        'agent' => [
            'id' => (int)$row['user_id'],
            'name' => (string)$row['agent_name'],
        ],
        'division' => [
            'id' => (int)$row['division_id'],
            'name' => (string)$row['division_name'],
        ],
        'assignment_date' => (string)$row['assignment_date'],
        'assignment_date_display' => $displayDate,
        'shift' => [
            'template_id' => (int)$row['shift_template_id'],
            'name' => (string)$row['shift_name'],
            'start_time' => $startTime,
            'end_time' => $endTime,
            'end_time_display' => $displayEnd,
            'display_range' => $startTime . '-' . $displayEnd,
            'is_cross_day' => (bool)$row['is_cross_day'],
        ],
        'type' => (string)$row['assignment_type'],
        'type_name' => (string)$row['assignment_type_name'],
        'break_minutes' => (int)$row['break_minutes'],
        'duration_minutes' => (int)$row['calculated_duration_minutes'],
        'status' => 'preview',
        'source' => 'template_preview',
        'is_overtime' => (bool)$row['is_overtime'],
        'is_holiday' => (bool)$row['is_holiday_assignment'],
    ];
}

function template_preview_conflicts(
    array $previewRows,
    array $existingRows,
    array $allConflictWarnings
): array {
    $previewIds = array_fill_keys(array_map(static fn(array $row): int => (int)$row['id'], $previewRows), true);
    $conflicts = [];

    foreach ($allConflictWarnings as $warning) {
        $ids = array_map('intval', is_array($warning['assignment_ids'] ?? null) ? $warning['assignment_ids'] : []);
        if (!array_filter($ids, static fn(int $id): bool => isset($previewIds[$id]))) {
            continue;
        }
        $conflicts[] = [
            'type' => 'overlap',
            'message' => (string)($warning['message'] ?? 'Overlapping assignment detected.'),
            'preview_ids' => array_values(array_filter($ids, static fn(int $id): bool => $id < 0)),
            'assignment_ids' => array_values(array_filter($ids, static fn(int $id): bool => $id > 0)),
        ];
    }

    foreach ($previewRows as $preview) {
        foreach ($existingRows as $existing) {
            if ((int)$preview['user_id'] !== (int)$existing['user_id']) {
                continue;
            }
            if (!template_preview_ranges_overlap($preview, $existing)) {
                continue;
            }
            $conflicts[] = [
                'type' => 'existing_assignment_overlap',
                'message' => 'Preview item overlaps an existing assignment.',
                'preview_ids' => [(int)$preview['id']],
                'assignment_ids' => [(int)$existing['id']],
            ];
        }
    }

    return template_preview_unique_conflicts($conflicts);
}

function template_preview_ranges_overlap(array $a, array $b): bool
{
    if (!in_array((string)($b['status'] ?? ''), ['assigned', 'confirmed', 'active', 'completed'], true)) {
        return false;
    }
    return (string)$a['start_datetime'] < (string)$b['end_datetime']
        && (string)$a['end_datetime'] > (string)$b['start_datetime'];
}

function template_preview_unique_conflicts(array $conflicts): array
{
    $seen = [];
    $result = [];
    foreach ($conflicts as $conflict) {
        $key = (string)$conflict['type']
            . ':' . implode(',', $conflict['preview_ids'])
            . ':' . implode(',', $conflict['assignment_ids']);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $result[] = $conflict;
    }
    return $result;
}

function template_preview_warnings(
    array $previewRows,
    array $jumpshiftWarnings,
    array $coverageWarnings,
    array $workloadRows
): array {
    $previewIds = array_fill_keys(array_map(static fn(array $row): int => (int)$row['id'], $previewRows), true);
    $warnings = [];

    foreach ($jumpshiftWarnings as $warning) {
        $previous = (int)($warning['previous_assignment_id'] ?? 0);
        $next = (int)($warning['next_assignment_id'] ?? 0);
        if (!isset($previewIds[$previous]) && !isset($previewIds[$next])) {
            continue;
        }
        $warnings[] = [
            'type' => 'jumpshift',
            'message' => (string)($warning['message'] ?? 'Rest time warning detected.'),
            'agent_id' => (int)($warning['user_id'] ?? 0),
            'rest_minutes' => (int)($warning['rest_minutes'] ?? 0),
            'preview_ids' => array_values(array_filter([$previous, $next], static fn(int $id): bool => $id < 0)),
            'assignment_ids' => array_values(array_filter([$previous, $next], static fn(int $id): bool => $id > 0)),
        ];
    }

    foreach ($coverageWarnings as $warning) {
        $warnings[] = [
            'type' => (string)($warning['type'] ?? 'coverage'),
            'message' => (string)($warning['message'] ?? 'Coverage warning detected.'),
            'date' => (string)($warning['date'] ?? ''),
        ];
    }

    foreach ($workloadRows as $row) {
        $status = (string)($row['status'] ?? '');
        if (in_array($status, ['', 'balanced', 'ok'], true)) {
            continue;
        }
        $warnings[] = [
            'type' => 'weekly_hours',
            'message' => 'Preview workload is ' . $status . ' for ' . (string)($row['agent_name'] ?? 'Agent') . '.',
            'agent_id' => (int)($row['user_id'] ?? 0),
            'total_minutes' => (int)($row['total_minutes'] ?? 0),
            'target_minutes' => (int)($row['target_minutes'] ?? 0),
            'status' => $status,
        ];
    }

    return $warnings;
}

function template_preview_blocked_items(array $conflicts): array
{
    $blocked = [];
    foreach ($conflicts as $conflict) {
        foreach ($conflict['preview_ids'] ?? [] as $previewId) {
            $blocked[(int)$previewId] = [
                'preview_id' => (int)$previewId,
                'reason' => (string)($conflict['message'] ?? 'Conflict detected.'),
                'type' => (string)($conflict['type'] ?? 'conflict'),
            ];
        }
    }
    return array_values($blocked);
}
