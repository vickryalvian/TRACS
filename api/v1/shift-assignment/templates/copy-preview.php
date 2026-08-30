<?php
declare(strict_types=1);

namespace TRACS\Api\V1\ShiftAssignment\Templates;

require_once __DIR__ . '/../../../../core/security/direct_access.php';
\tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/../../../_request.php';
require_once __DIR__ . '/preview.php';

function copy_preview_input(array $input, array $agents): array
{
    $allowed = ['source_start_date', 'source_end_date', 'target_start_date', 'target_end_date', 'scope', 'options'];
    $errors = [];
    foreach ($input as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowed, true)) {
            $errors[(string)$key] = 'Field is not supported.';
        }
    }

    $sourceStartInput = trim((string)($input['source_start_date'] ?? ''));
    $sourceEndInput = trim((string)($input['source_end_date'] ?? ''));
    $targetStartInput = trim((string)($input['target_start_date'] ?? ''));
    $targetEndInput = trim((string)($input['target_end_date'] ?? ''));

    $sourceStart = $sourceStartInput !== '' ? \TRACS\Api\safe_date_parse($sourceStartInput) : null;
    $sourceEnd = $sourceEndInput !== '' ? \TRACS\Api\safe_date_parse($sourceEndInput) : null;
    $targetStart = $targetStartInput !== '' ? \TRACS\Api\safe_date_parse($targetStartInput) : null;
    $targetEnd = $targetEndInput !== '' ? \TRACS\Api\safe_date_parse($targetEndInput) : null;

    foreach ([
        'source_start_date' => [$sourceStartInput, $sourceStart, 'Source start date'],
        'source_end_date' => [$sourceEndInput, $sourceEnd, 'Source end date'],
        'target_start_date' => [$targetStartInput, $targetStart, 'Target start date'],
        'target_end_date' => [$targetEndInput, $targetEnd, 'Target end date'],
    ] as $field => [$raw, $date, $label]) {
        if ($raw === '') {
            $errors[$field] = "{$label} is required.";
        } elseif (!$date) {
            $errors[$field] = "{$label} must use YYYY-MM-DD.";
        }
    }

    if ($sourceStart && $sourceEnd) {
        if ($sourceEnd < $sourceStart) {
            $errors['source_range'] = 'Source end date must be on or after source start date.';
        } elseif ((int)$sourceStart->diff($sourceEnd)->days > 34) {
            $errors['source_range'] = 'Copy preview supports a maximum range of 35 days.';
        }
    }
    if ($targetStart && $targetEnd) {
        if ($targetEnd < $targetStart) {
            $errors['target_range'] = 'Target end date must be on or after target start date.';
        } elseif ((int)$targetStart->diff($targetEnd)->days > 34) {
            $errors['target_range'] = 'Copy preview supports a maximum range of 35 days.';
        }
    }
    if ($sourceStart && $sourceEnd && $targetStart && $targetEnd) {
        if ($sourceStart->format('Y-m-d') === $targetStart->format('Y-m-d')
            && $sourceEnd->format('Y-m-d') === $targetEnd->format('Y-m-d')) {
            $errors['date_range'] = 'Source and target ranges must be different.';
        }
        if ((int)$sourceStart->diff($sourceEnd)->days !== (int)$targetStart->diff($targetEnd)->days) {
            $errors['date_range_length'] = 'Source and target ranges must have the same length.';
        }
    }

    $agentRows = [];
    foreach ($agents as $agent) {
        $id = (int)($agent['id'] ?? 0);
        if ($id > 0) {
            $agentRows[$id] = $agent;
        }
    }

    $scope = $input['scope'] ?? [];
    if ($scope !== [] && !is_array($scope)) {
        $errors['scope'] = 'Scope must be an object.';
        $scope = [];
    }

    $agentIds = copy_preview_positive_int_list($scope['agent_ids'] ?? [], 'scope.agent_ids', $errors);
    foreach ($agentIds as $index => $agentId) {
        if (!isset($agentRows[$agentId])) {
            $errors["scope.agent_ids.{$index}"] = 'Agent is not active or not in scope.';
        }
    }
    $divisionIds = copy_preview_positive_int_list($scope['division_ids'] ?? [], 'scope.division_ids', $errors);
    $roleIds = copy_preview_positive_int_list($scope['role_ids'] ?? [], 'scope.role_ids', $errors);
    if ($roleIds !== []) {
        $errors['scope.role_ids'] = 'Role scope is not supported by copy preview yet.';
    }

    $options = $input['options'] ?? [];
    if ($options !== [] && !is_array($options)) {
        $errors['options'] = 'Options must be an object.';
        $options = [];
    }
    foreach (['include_holidays', 'include_warnings', 'strict_conflict_check'] as $option) {
        if (array_key_exists($option, $options) && !is_bool($options[$option])) {
            $errors["options.{$option}"] = 'Option must be true or false.';
        }
    }

    if ($errors !== []) {
        throw new \TRACS\Api\RequestValidationException('Validation failed.', $errors);
    }

    return [
        'source_start' => $sourceStart->format('Y-m-d'),
        'source_end' => $sourceEnd->format('Y-m-d'),
        'target_start' => $targetStart->format('Y-m-d'),
        'target_end' => $targetEnd->format('Y-m-d'),
        'agent_ids' => array_values($agentIds),
        'division_ids' => array_values($divisionIds),
        'options' => [
            'include_holidays' => (bool)($options['include_holidays'] ?? true),
            'include_warnings' => (bool)($options['include_warnings'] ?? true),
            'strict_conflict_check' => (bool)($options['strict_conflict_check'] ?? true),
        ],
    ];
}

function copy_preview_positive_int_list(mixed $value, string $field, array &$errors): array
{
    if ($value === null || $value === '') {
        return [];
    }
    if (!is_array($value)) {
        $errors[$field] = 'Scope value must be an array.';
        return [];
    }
    $ids = [];
    foreach ($value as $index => $raw) {
        if (!is_int($raw) && !is_string($raw)) {
            $errors["{$field}.{$index}"] = 'Value must be a positive integer.';
            continue;
        }
        $raw = trim((string)$raw);
        if (!preg_match('/^[1-9]\d*$/', $raw)) {
            $errors["{$field}.{$index}"] = 'Value must be a positive integer.';
            continue;
        }
        $ids[(int)$raw] = (int)$raw;
    }
    return array_values($ids);
}

function copy_preview_data(
    array $input,
    array $agents,
    array $templates,
    array $assignmentTypes,
    array $targetHolidays,
    array $sourceAssignments,
    array $targetAssignments,
    array $settings,
    callable $workload,
    callable $jumpshiftWarnings,
    callable $conflictWarnings,
    callable $coverageWarnings
): array {
    $normalized = copy_preview_input($input, $agents);
    $agentRows = [];
    foreach ($agents as $agent) {
        $agentRows[(int)($agent['id'] ?? 0)] = $agent;
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
    foreach ($targetHolidays as $holiday) {
        $date = (string)($holiday['holiday_date'] ?? '');
        if ($date !== '') {
            $holidayDates[$date][] = $holiday;
        }
    }

    $sourceRows = copy_preview_filter_source_assignments($sourceAssignments, $normalized);
    [$previewRows, $sourceBlocked] = copy_preview_assignment_rows(
        $sourceRows,
        $normalized,
        $agentRows,
        $templateRows,
        $typeRows,
        $holidayDates
    );
    $combined = array_merge($targetAssignments, $previewRows);
    $conflictRows = template_preview_conflicts(
        $previewRows,
        $targetAssignments,
        $conflictWarnings($combined)
    );
    $warningRows = [];
    if ($normalized['options']['include_warnings']) {
        $warningRows = template_preview_warnings(
            $previewRows,
            $jumpshiftWarnings($combined, $settings),
            $coverageWarnings($combined),
            $workload($combined, copy_preview_agents_for_workload($previewRows, $agentRows), $settings, $normalized['target_start'], $normalized['target_end'])
        );
        $warningRows = array_merge($warningRows, copy_preview_holiday_warnings($previewRows));
        $warningRows = array_merge($warningRows, copy_preview_note_warnings($previewRows));
    }
    $blocked = array_merge($sourceBlocked, template_preview_blocked_items($conflictRows));

    $displayDate = static function (string $date): string {
        return \TRACS\Api\safe_date_parse($date)?->format('d-m-Y') ?? '';
    };

    return [
        'source_range' => [
            'start_date' => $normalized['source_start'],
            'end_date' => $normalized['source_end'],
            'start_date_display' => $displayDate($normalized['source_start']),
            'end_date_display' => $displayDate($normalized['source_end']),
        ],
        'target_range' => [
            'start_date' => $normalized['target_start'],
            'end_date' => $normalized['target_end'],
            'start_date_display' => $displayDate($normalized['target_start']),
            'end_date_display' => $displayDate($normalized['target_end']),
        ],
        'items' => array_map(
            static fn(array $row): array => copy_preview_item_payload($row),
            $previewRows
        ),
        'summary' => [
            'source_assignments' => count($sourceRows),
            'preview_assignments' => count($previewRows),
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

function copy_preview_filter_source_assignments(array $sourceAssignments, array $normalized): array
{
    $agentIds = array_fill_keys($normalized['agent_ids'], true);
    $divisionIds = array_fill_keys($normalized['division_ids'], true);
    $rows = [];
    foreach ($sourceAssignments as $assignment) {
        $date = (string)($assignment['assignment_date'] ?? '');
        if ($date < $normalized['source_start'] || $date > $normalized['source_end']) {
            continue;
        }
        if ($agentIds !== [] && !isset($agentIds[(int)($assignment['user_id'] ?? 0)])) {
            continue;
        }
        if ($divisionIds !== [] && !isset($divisionIds[(int)($assignment['division_id'] ?? 0)])) {
            continue;
        }
        $rows[] = $assignment;
    }
    return $rows;
}

function copy_preview_assignment_rows(
    array $sourceRows,
    array $normalized,
    array $agentRows,
    array $templateRows,
    array $typeRows,
    array $holidayDates
): array {
    $previewRows = [];
    $blocked = [];
    $id = -10000;
    $sourceStart = new \DateTimeImmutable($normalized['source_start']);
    $targetStart = new \DateTimeImmutable($normalized['target_start']);

    foreach ($sourceRows as $source) {
        $sourceId = (int)($source['id'] ?? 0);
        $agentId = (int)($source['user_id'] ?? 0);
        $typeSlug = (string)($source['assignment_type'] ?? '');
        $templateId = (int)($source['shift_template_id'] ?? 0);
        if (!isset($agentRows[$agentId])) {
            $blocked[] = copy_preview_blocked_source($sourceId, 'inactive_agent', 'Source assignment agent is not active or not in scope.');
            continue;
        }
        if (!isset($typeRows[$typeSlug])) {
            $blocked[] = copy_preview_blocked_source($sourceId, 'missing_shift_definition', 'Source assignment shift type is not active.');
            continue;
        }
        if ($templateId > 0 && !isset($templateRows[$templateId])) {
            $blocked[] = copy_preview_blocked_source($sourceId, 'missing_shift_template', 'Source assignment shift template is not active.');
            continue;
        }

        $sourceDate = \TRACS\Api\safe_date_parse((string)($source['assignment_date'] ?? ''));
        if (!$sourceDate) {
            $blocked[] = copy_preview_blocked_source($sourceId, 'invalid_source_assignment', 'Source assignment date is invalid.');
            continue;
        }
        $offsetDays = (int)$sourceStart->diff($sourceDate)->format('%r%a');
        $targetDate = $targetStart->modify(($offsetDays >= 0 ? '+' : '') . $offsetDays . ' days')->format('Y-m-d');

        $sourceStartDate = substr((string)$source['start_datetime'], 0, 10);
        $sourceEndDate = substr((string)$source['end_datetime'], 0, 10);
        $endOffsetDays = 0;
        if ($sourceStartDate !== '' && $sourceEndDate !== '') {
            $endOffsetDays = (int)(new \DateTimeImmutable($sourceStartDate))->diff(new \DateTimeImmutable($sourceEndDate))->format('%r%a');
        } elseif (!empty($source['is_cross_day'])) {
            $endOffsetDays = 1;
        }
        $targetEndDate = (new \DateTimeImmutable($targetDate))->modify(($endOffsetDays >= 0 ? '+' : '') . $endOffsetDays . ' days')->format('Y-m-d');
        $startTime = substr((string)$source['start_datetime'], 11, 5);
        $endTime = substr((string)$source['end_datetime'], 11, 5);
        $displayEnd = !empty($source['is_cross_day']) && $endTime === '00:00' ? '24:00' : $endTime;
        $type = $typeRows[$typeSlug];
        $template = $templateId > 0 ? $templateRows[$templateId] : [];
        $previewRows[] = [
            'id' => $id--,
            'source_assignment_id' => $sourceId,
            'source_assignment_date' => (string)$source['assignment_date'],
            'user_id' => $agentId,
            'agent_name' => (string)($source['agent_name'] ?? $agentRows[$agentId]['agent_name'] ?? 'Agent'),
            'division_id' => (int)($source['division_id'] ?? $agentRows[$agentId]['division_id'] ?? 0),
            'division_name' => (string)($source['division_name'] ?? $agentRows[$agentId]['division_name'] ?? ''),
            'shift_template_id' => $templateId,
            'shift_name' => (string)($source['shift_name'] ?? $template['shift_name'] ?? 'Custom Shift'),
            'color_label' => (string)($source['color_label'] ?? $template['color_label'] ?? $type['color_label'] ?? '#4f46e5'),
            'assignment_date' => $targetDate,
            'start_datetime' => $targetDate . ' ' . $startTime . ':00',
            'end_datetime' => $targetEndDate . ' ' . $endTime . ':00',
            'is_cross_day' => (bool)($source['is_cross_day'] ?? $displayEnd === '24:00'),
            'break_minutes' => (int)($source['break_minutes'] ?? 0),
            'calculated_duration_minutes' => (int)($source['calculated_duration_minutes'] ?? template_preview_duration_minutes($startTime, $displayEnd)),
            'assignment_type' => $typeSlug,
            'assignment_type_name' => (string)($source['assignment_type_name'] ?? $type['type_name'] ?? str_replace('_', ' ', $typeSlug)),
            'status' => 'assigned',
            'is_overtime' => (bool)($source['is_overtime'] ?? !empty($type['count_as_overtime'])),
            'is_holiday_assignment' => isset($holidayDates[$targetDate]) || !empty($type['count_as_holiday_hour']),
            'source' => 'copy_preview',
            'monthly_template_id' => 0,
            'notes' => (string)($source['notes'] ?? ''),
        ];
    }

    return [$previewRows, $blocked];
}

function copy_preview_blocked_source(int $sourceId, string $type, string $reason): array
{
    return [
        'source_assignment_id' => $sourceId,
        'reason' => $reason,
        'type' => $type,
    ];
}

function copy_preview_agents_for_workload(array $previewRows, array $agentRows): array
{
    $agents = [];
    foreach ($previewRows as $row) {
        $id = (int)($row['user_id'] ?? 0);
        if ($id > 0 && isset($agentRows[$id])) {
            $agents[$id] = $agentRows[$id];
        }
    }
    return array_values($agents);
}

function copy_preview_holiday_warnings(array $previewRows): array
{
    $warnings = [];
    foreach ($previewRows as $row) {
        if (empty($row['is_holiday_assignment'])) {
            continue;
        }
        $warnings[] = [
            'type' => 'holiday',
            'message' => 'Preview item falls on a holiday or holiday-counted shift type.',
            'preview_ids' => [(int)$row['id']],
            'source_assignment_id' => (int)($row['source_assignment_id'] ?? 0),
            'date' => (string)$row['assignment_date'],
        ];
    }
    return $warnings;
}

function copy_preview_note_warnings(array $previewRows): array
{
    $warnings = [];
    foreach ($previewRows as $row) {
        if (trim((string)($row['notes'] ?? '')) === '') {
            continue;
        }
        $warnings[] = [
            'type' => 'copied_notes',
            'message' => 'Source assignment notes may be stale if copied later.',
            'preview_ids' => [(int)$row['id']],
            'source_assignment_id' => (int)($row['source_assignment_id'] ?? 0),
        ];
    }
    return $warnings;
}

function copy_preview_item_payload(array $row): array
{
    $payload = template_preview_item_payload($row);
    $payload['preview_id'] = (int)$row['id'];
    $payload['source'] = 'copy_preview';
    $payload['source_assignment_id'] = (int)($row['source_assignment_id'] ?? 0);
    $payload['source_assignment_date'] = (string)($row['source_assignment_date'] ?? '');
    $payload['source_assignment_date_display'] = \TRACS\Api\safe_date_parse((string)($row['source_assignment_date'] ?? ''))?->format('d-m-Y') ?? '';
    $payload['day_of_week'] = \TRACS\Api\safe_date_parse((string)$row['assignment_date'])?->format('l') ?? '';
    return $payload;
}
