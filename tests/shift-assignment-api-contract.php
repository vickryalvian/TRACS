<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_response.php';
require_once __DIR__ . '/../api/v1/shift-assignment/context.php';

function shift_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$data = \TRACS\Api\V1\ShiftAssignment\context_data(
    [
        'id' => 12,
        'display_name' => 'Schedule Supervisor',
        'email' => 'must-not-leak@example.test',
        'password' => 'must-not-leak',
        'role_slug' => 'supervisor',
        'role_name' => 'Supervisor / Leader',
        'two_factor_secret' => 'must-not-leak',
    ],
    [
        'manage' => true,
        'settings' => false,
        'monthly_templates' => true,
        'export' => true,
        'scope_role' => 'supervisor',
    ],
    [[
        'id' => 1,
        'shift_name' => 'Shift 1',
        'start_time' => '00:00:00',
        'end_time' => '08:00:00',
        'duration_minutes' => 480,
        'default_break_minutes' => 0,
        'is_cross_day' => 0,
        'color_label' => '#123456',
        'default_assignment_type' => 'regular_shift',
        'is_active' => 1,
        'notes' => 'must-not-leak',
    ]],
    [[
        'type_slug' => 'regular_shift',
        'type_name' => 'Regular Shift',
        'color_label' => '#123456',
        'count_as_work_hour' => 1,
        'count_as_overtime' => 0,
        'description' => 'must-not-leak',
    ]],
    [[
        'id' => 21,
        'agent_name' => 'Agent One',
        'email' => 'must-not-leak@example.test',
        'division_id' => 3,
        'division_name' => 'Operations',
        'role_slug' => 'agent',
    ]],
    [[
        'id' => 3,
        'name' => 'Operations',
        'code' => 'OPS',
        'status' => 'must-not-leak',
    ]],
    [
        'weekly_target_minutes' => 2400,
        'max_weekly_minutes' => 2880,
        'overtime_threshold_minutes' => 2700,
        'minimum_rest_between_shifts_minutes' => 480,
        'timeline_snap_minutes' => 15,
        'minimum_shift_minutes' => 60,
    ],
    ['2026-06-15', '2026-06-21'],
    'test-csrf-token'
);

$payload = \TRACS\Api\response_payload(
    true,
    'Shift Assignment context loaded.',
    $data,
    [],
    ['request_id' => 'shift-contract-request-id']
);
$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$decoded = json_decode((string)$json, true);

shift_contract_assert(
    array_keys($decoded) === ['success', 'message', 'data', 'errors', 'meta'],
    'Shift Assignment response envelope keys changed.'
);
shift_contract_assert(
    $decoded['meta']['request_id'] === 'shift-contract-request-id',
    'Shift Assignment request ID metadata is missing.'
);
shift_contract_assert(
    array_column($decoded['data']['shift_definitions'], 'display_range') === [
        '00:00-08:00',
        '08:00-16:00',
        '16:00-24:00',
    ],
    'Canonical shift display ranges changed.'
);
shift_contract_assert(
    array_column($decoded['data']['shift_definitions'], 'duration_minutes') === [480, 480, 480],
    'Canonical shifts must remain eight hours.'
);
shift_contract_assert(
    $decoded['data']['shift_definitions'][2]['end_time'] === '00:00'
        && $decoded['data']['shift_definitions'][2]['is_cross_day'] === true,
    'Shift 3 storage semantics changed.'
);
shift_contract_assert(
    $decoded['data']['allowed_actions']['delete_assignment'] === false,
    'The context must not invent an unsupported assignment-delete action.'
);
shift_contract_assert(
    $decoded['data']['allowed_actions']['manage_workload_settings'] === false
        && $decoded['data']['allowed_actions']['manage_monthly_templates'] === true,
    'Permission-to-action mapping changed.'
);
shift_contract_assert(
    $decoded['data']['defaults']['minimum_rest_minutes'] === 480,
    'The eight-hour jumpshift threshold changed.'
);
shift_contract_assert(
    $decoded['data']['defaults']['ui_date_format'] === 'dd-mm-yyyy'
        && $decoded['data']['defaults']['api_date_format'] === 'YYYY-MM-DD',
    'Date format boundary changed.'
);
shift_contract_assert(
    $decoded['data']['filters']['views'] === ['daily', 'weekly', 'monthly'],
    'Supported Shift Assignment views changed.'
);
shift_contract_assert(
    $decoded['data']['filters']['role_filter_supported'] === false,
    'The contract must characterize that no role query filter currently exists.'
);

foreach (['email', 'password', 'two_factor_secret', 'notes', 'description', 'status'] as $sensitiveKey) {
    shift_contract_assert(
        !str_contains((string)$json, '"' . $sensitiveKey . '"'),
        "Unapproved field {$sensitiveKey} leaked into the Shift Assignment context."
    );
}

$route = file_get_contents(__DIR__ . '/../public/api/v1/shift-assignment/context.php');
shift_contract_assert($route !== false, 'Shift Assignment public route is unreadable.');
shift_contract_assert(
    str_contains($route, "permissions: ['shifts.view']"),
    'Shift Assignment context no longer enforces shifts.view.'
);
shift_contract_assert(
    str_contains($route, "methods: ['GET']"),
    'Shift Assignment context no longer rejects non-GET methods.'
);
shift_contract_assert(
    str_contains($route, '\\TRACS\\Api\\json_success('),
    'Shift Assignment context no longer uses the standard response helper.'
);

$service = file_get_contents(__DIR__ . '/../modules/shifting-assignment/ShiftingAssignmentService.php');
shift_contract_assert($service !== false, 'Shift Assignment service is unreadable.');
shift_contract_assert(
    str_contains($service, "private const COUNTED_STATUSES = ['assigned', 'confirmed', 'active', 'completed']"),
    'Counted assignment statuses changed.'
);
shift_contract_assert(
    str_contains($service, '$rest < $minimum'),
    'Jumpshift comparison behavior changed.'
);
shift_contract_assert(
    str_contains($service, "'minimum_rest_between_shifts_minutes' => 480"),
    'Default jumpshift threshold changed.'
);

echo "TRACS Shift Assignment API contract checks passed.\n";
