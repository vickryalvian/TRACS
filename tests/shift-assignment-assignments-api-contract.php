<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_response.php';
require_once __DIR__ . '/../api/v1/shift-assignment/assignments.php';

function assignments_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$today = new DateTimeImmutable('2026-06-17', new DateTimeZone('Asia/Jakarta'));

$weekly = \TRACS\Api\V1\ShiftAssignment\assignments_query([], $today);
assignments_contract_assert(
    $weekly['view'] === 'weekly'
        && $weekly['start'] === '2026-06-15'
        && $weekly['end'] === '2026-06-21',
    'Default weekly range changed.'
);

$daily = \TRACS\Api\V1\ShiftAssignment\assignments_query([
    'view' => 'daily',
    'start_date' => '2026-06-18',
    'end_date' => '2026-06-18',
    'agent_id' => '12',
    'division' => '3',
    'role' => 'agent',
    'shift_type' => 'regular_shift',
    'status' => 'assigned',
], $today);
assignments_contract_assert(
    $daily['user_id'] === 12
        && $daily['division_id'] === 3
        && $daily['assignment_type'] === 'regular_shift',
    'Validated query mapping changed.'
);

$monthly = \TRACS\Api\V1\ShiftAssignment\assignments_query([
    'view' => 'monthly',
], $today);
assignments_contract_assert(
    $monthly['start'] === '2026-06-01' && $monthly['end'] === '2026-06-30',
    'Default monthly range changed.'
);

$invalidCases = [
    [['view' => 'yearly'], 'view'],
    [['start_date' => '18-06-2026', 'end_date' => '18-06-2026'], 'start_date'],
    [['start_date' => '2026-06-18'], 'date_range'],
    [['view' => 'daily', 'start_date' => '2026-06-18', 'end_date' => '2026-06-19'], 'date_range'],
    [['view' => 'weekly', 'start_date' => '2026-06-01', 'end_date' => '2026-06-08'], 'date_range'],
    [['view' => 'monthly', 'start_date' => '2026-06-01', 'end_date' => '2026-07-01'], 'date_range'],
    [['agent_id' => '0'], 'agent_id'],
    [['division' => '3 OR 1=1'], 'division'],
    [['role' => 'root'], 'role'],
    [['shift_type' => 'unknown'], 'shift_type'],
    [['status' => 'deleted'], 'status'],
    [['role' => ['agent']], 'role'],
];

foreach ($invalidCases as [$input, $expectedKey]) {
    try {
        \TRACS\Api\V1\ShiftAssignment\assignments_query($input, $today);
        assignments_contract_assert(false, "Invalid {$expectedKey} query was accepted.");
    } catch (\TRACS\Api\RequestValidationException $error) {
        assignments_contract_assert(
            isset($error->errors[$expectedKey]),
            "Invalid query did not report {$expectedKey}."
        );
    }
}

$agents = \TRACS\Api\V1\ShiftAssignment\filter_agents_by_role([
    ['id' => 1, 'role_slug' => 'agent'],
    ['id' => 2, 'role_slug' => 'supervisor'],
], 'agent');
assignments_contract_assert(
    count($agents) === 1 && (int)$agents[0]['id'] === 1,
    'Role filter mapping changed.'
);

$data = \TRACS\Api\V1\ShiftAssignment\assignments_data(
    $daily,
    [[
        'id' => 51,
        'user_id' => 12,
        'agent_name' => 'Agent One',
        'agent_email' => 'must-not-leak@example.test',
        'division_id' => 3,
        'division_name' => 'Operations',
        'shift_template_id' => 1,
        'shift_name' => 'Shift 3',
        'color_label' => '#4f46e5',
        'assignment_date' => '2026-06-18',
        'start_datetime' => '2026-06-18 16:00:00',
        'end_datetime' => '2026-06-19 00:00:00',
        'is_cross_day' => true,
        'break_minutes' => 0,
        'calculated_duration_minutes' => 480,
        'assignment_type' => 'regular_shift',
        'assignment_type_name' => 'Regular Shift',
        'status' => 'assigned',
        'approval_status' => 'not_required',
        'source' => 'monthly_template',
        'is_overtime' => false,
        'is_holiday_assignment' => false,
        'availability_status' => 'available',
        'notes' => 'must-not-leak',
        'created_by' => 99,
        'updated_by' => 99,
        'approved_by' => 99,
    ]],
    [[
        'user_id' => 12,
        'agent_name' => 'Agent One',
        'division_name' => 'Operations',
        'working_days' => 1,
        'total_minutes' => 480,
        'regular_minutes' => 480,
        'overtime_minutes' => 0,
        'holiday_minutes' => 0,
        'standby_minutes' => 0,
        'target_minutes' => 2400,
        'difference_minutes' => -1920,
        'minimum_rest_minutes' => null,
        'jumpshift_count' => 0,
        'conflict_count' => 0,
        'status' => 'Under Target',
        'email' => 'must-not-leak@example.test',
    ]],
    [[
        'type' => 'jumpshift',
        'user_id' => 12,
        'agent_name' => 'Agent One',
        'previous_assignment_id' => 50,
        'next_assignment_id' => 51,
        'rest_minutes' => 420,
        'message' => '[JUMPSHIFT] Agent One only has 7h rest before the next shift.',
        'internal_path' => '/must/not/leak',
    ]],
    [[
        'id' => 7,
        'holiday_date' => '2026-06-18',
        'holiday_name' => 'Test Holiday',
        'holiday_type' => 'national',
        'notes' => 'must-not-leak',
        'source' => 'database',
    ]]
);

$payload = \TRACS\Api\response_payload(
    true,
    'Shift assignments loaded.',
    $data,
    [],
    ['request_id' => 'assignments-request-id']
);
$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$decoded = json_decode((string)$json, true);

assignments_contract_assert(
    array_keys($decoded) === ['success', 'message', 'data', 'errors', 'meta'],
    'Assignments response envelope keys changed.'
);
assignments_contract_assert(
    $decoded['data']['range'] === [
        'start_date' => '2026-06-18',
        'end_date' => '2026-06-18',
        'start_date_display' => '18-06-2026',
        'end_date_display' => '18-06-2026',
    ],
    'Assignments date contract changed.'
);
assignments_contract_assert(
    $decoded['data']['assignments'][0]['shift']['display_range'] === '16:00-24:00',
    'Shift 3 display contract changed.'
);
assignments_contract_assert(
    $decoded['data']['assignments'][0]['shift']['end_time'] === '00:00'
        && $decoded['data']['assignments'][0]['shift']['is_cross_day'] === true,
    'Shift 3 raw storage contract changed.'
);
assignments_contract_assert(
    $decoded['data']['summary']['assignment_count'] === 1
        && $decoded['data']['summary']['total_minutes'] === 480,
    'Assignments summary changed.'
);
assignments_contract_assert(
    $decoded['data']['warnings'][0]['rest_minutes'] === 420,
    'Jumpshift warning contract changed.'
);
assignments_contract_assert(
    $decoded['meta']['request_id'] === 'assignments-request-id',
    'Assignments request ID is missing.'
);

foreach ([
    'agent_email', 'email', 'password', 'two_factor_secret', 'notes', 'created_by',
    'updated_by', 'approved_by', 'internal_path',
] as $sensitiveKey) {
    assignments_contract_assert(
        !str_contains((string)$json, '"' . $sensitiveKey . '"'),
        "Unapproved field {$sensitiveKey} leaked into the assignments contract."
    );
}

$route = file_get_contents(__DIR__ . '/../public/api/v1/shift-assignment/assignments.php');
assignments_contract_assert($route !== false, 'Assignments public route is unreadable.');
assignments_contract_assert(
    str_contains($route, "require_permission(\$conn, 'shifts.view'"),
    'Assignments GET route no longer enforces shifts.view.'
);
assignments_contract_assert(
    str_contains($route, "methods: ['GET', 'POST']"),
    'Assignments route no longer preserves GET and controlled POST methods.'
);
assignments_contract_assert(
    str_contains($route, "\$method === 'GET' ? 422 : 400")
        && str_contains($route, "\$method === 'GET' ? \$error->errors : []"),
    'Assignments GET validation no longer preserves the Phase 7 422 contract.'
);
assignments_contract_assert(
    substr_count($route, "if (\$method !== 'POST')") >= 3,
    'Read exceptions may be handled as create failures.'
);
foreach (['PATCH', 'DELETE'] as $method) {
    assignments_contract_assert(
        !str_contains($route, "'{$method}'"),
        "Assignments route unexpectedly supports {$method}."
    );
}

echo "TRACS Shift Assignment assignments API contract checks passed.\n";
