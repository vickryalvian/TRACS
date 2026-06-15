<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_response.php';
require_once __DIR__ . '/../api/v1/shift-assignment/assignments.php';
require_once __DIR__ . '/../api/v1/shift-assignment/assignment.php';

function update_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$existing = [
    'id' => 701,
    'user_id' => 21,
    'assignment_date' => '2026-07-08',
    'start_datetime' => '2026-07-08 08:00:00',
    'end_datetime' => '2026-07-08 16:00:00',
    'shift_template_id' => 2,
    'break_minutes' => 0,
    'calculated_duration_minutes' => 480,
    'assignment_type' => 'regular_shift',
    'status' => 'assigned',
    'source' => 'manual',
    'notes' => 'Existing private note',
    'monthly_template_id' => 0,
    'is_manual_duration_override' => false,
    'is_cross_day' => false,
];
$captured = null;
$data = \TRACS\Api\V1\ShiftAssignment\update_assignment_data(
    [
        'start_time' => '16:00',
        'end_time' => '24:00',
        'status' => 'confirmed',
    ],
    $existing,
    static function (array $input) use (&$captured): array {
        $captured = $input;
        return [
            'id' => 701,
            'warnings' => [['type' => 'jumpshift', 'message' => 'Short rest.']],
            'duration_minutes' => 480,
            'is_cross_day' => true,
        ];
    },
    static fn(int $id): array => [
        'id' => $id,
        'user_id' => 21,
        'assignment_date' => '2026-07-08',
        'start_datetime' => '2026-07-08 16:00:00',
        'end_datetime' => '2026-07-09 00:00:00',
        'shift_template_id' => 2,
        'break_minutes' => 0,
        'calculated_duration_minutes' => 480,
        'assignment_type' => 'regular_shift',
        'status' => 'confirmed',
        'source' => 'manual',
        'is_cross_day' => true,
    ]
);

update_contract_assert(
    $captured['id'] === 701
        && $captured['user_id'] === 21
        && $captured['end_time'] === '00:00'
        && $captured['status'] === 'confirmed'
        && $captured['notes'] === 'Existing private note',
    'Partial update no longer merges safely into the existing service contract.'
);
update_contract_assert(
    $data['assignment']['assignment_date_display'] === '08-07-2026'
        && $data['assignment']['display_range'] === '16:00-24:00'
        && $data['assignment']['is_cross_day'] === true,
    'Update response no longer preserves Shift 3 or display date behavior.'
);
update_contract_assert(
    \TRACS\Api\V1\ShiftAssignment\update_assignment_changed_fields(
        \TRACS\Api\V1\ShiftAssignment\update_assignment_safe_summary($existing),
        $data['assignment']
    ) === ['start_time', 'end_time', 'status'],
    'Update changed-field audit summary changed.'
);
$attempt = \TRACS\Api\V1\ShiftAssignment\update_assignment_attempt_summary([
    'status' => 'confirmed',
    'notes' => 'must-not-enter-audit-summary',
    'password' => 'must-not-enter-audit-summary',
]);
update_contract_assert(
    $attempt === ['status' => 'confirmed'],
    'Update attempt summary exposes unsupported or sensitive values.'
);

foreach ([
    [[], ['request']],
    [['agent_id' => 0], ['agent_id']],
    [['assignment_date' => '08-07-2026'], ['assignment_date']],
    [['start_time' => '24:00'], ['start_time']],
    [['start_time' => '08:00', 'end_time' => '08:00'], ['end_time']],
    [['status' => 'root'], ['status']],
    [['is_overtime' => true], ['is_overtime']],
] as [$input, $expectedFields]) {
    try {
        \TRACS\Api\V1\ShiftAssignment\update_assignment_input($input, $existing);
        update_contract_assert(false, 'Invalid update payload was accepted.');
    } catch (\TRACS\Api\RequestValidationException $error) {
        foreach ($expectedFields as $field) {
            update_contract_assert(
                array_key_exists($field, $error->errors),
                "Invalid update payload did not report {$field}."
            );
        }
    }
}

foreach ([[], ['id' => ''], ['id' => 0], ['id' => ['701']]] as $query) {
    try {
        \TRACS\Api\V1\ShiftAssignment\update_assignment_id($query);
        update_contract_assert(false, 'Invalid assignment ID was accepted.');
    } catch (\TRACS\Api\RequestValidationException $error) {
        update_contract_assert(isset($error->errors['id']), 'Assignment ID error changed.');
    }
}
update_contract_assert(
    \TRACS\Api\V1\ShiftAssignment\update_assignment_id(['id' => '701']) === 701,
    'Valid assignment ID no longer parses.'
);
$customAssignment = $existing;
$customAssignment['shift_template_id'] = 0;
$customUpdate = \TRACS\Api\V1\ShiftAssignment\update_assignment_input(
    ['status' => 'confirmed'],
    $customAssignment
);
update_contract_assert(
    $customUpdate['shift_template_id'] === null
        && $customUpdate['status'] === 'confirmed',
    'Inherited custom assignment template zero is not normalized safely.'
);
$clearedTemplate = \TRACS\Api\V1\ShiftAssignment\update_assignment_input(
    ['shift_template_id' => null],
    $existing
);
update_contract_assert(
    $clearedTemplate['shift_template_id'] === null,
    'Explicit template clearing falls back to the existing template.'
);

$route = file_get_contents(__DIR__ . '/../public/api/v1/shift-assignment/assignment.php');
$getPostRoute = file_get_contents(__DIR__ . '/../public/api/v1/shift-assignment/assignments.php');
$frontendApi = file_get_contents(__DIR__ . '/../frontend/src/modules/shift-assignment/api.js');
update_contract_assert(
    $route !== false && $getPostRoute !== false && $frontendApi !== false,
    'Update contract source is unreadable.'
);
update_contract_assert(
    str_contains($route, "methods: ['PATCH', 'DELETE']")
        && str_contains($route, "require_exact_role(\$conn, 'super_admin'")
        && str_contains($route, "'shifts.manage'")
        && str_contains($route, '\\TRACS\\Api\\get_request_json()')
        && str_contains($route, "'shift_assignment.update'"),
    'Assignment route lost method, authorization, validation, or audit enforcement.'
);
update_contract_assert(
    str_contains($route, "'Shift assignment updated.'")
        && str_contains($route, '409')
        && str_contains($route, '404')
        && str_contains($route, '422'),
    'Update route status contract changed.'
);
update_contract_assert(
    str_contains($getPostRoute, "methods: ['GET', 'POST']")
        && !str_contains($getPostRoute, "'PATCH'"),
    'Existing GET/POST assignments route changed.'
);
update_contract_assert(
    substr_count($frontendApi, "method: 'POST'") === 1
        && substr_count($frontendApi, "method: 'PATCH'") === 1
        && substr_count($frontendApi, "method: 'DELETE'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"]PUT|\.(put)\s*\()/i', $frontendApi),
    'React preview mutation allowlist changed.'
);

$json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
foreach (['email', 'password', 'two_factor_secret', 'notes', 'internal_path'] as $key) {
    update_contract_assert(
        !str_contains((string)$json, '"' . $key . '"'),
        "Update response exposed {$key}."
    );
}

echo "TRACS Shift Assignment update API contract checks passed.\n";
