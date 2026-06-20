<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_response.php';
require_once __DIR__ . '/../api/v1/shift-assignment/assignments.php';

function create_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$captured = null;
$data = \TRACS\Api\V1\ShiftAssignment\create_assignment_data(
    [
        'agent_id' => 21,
        'assignment_date' => '2026-07-01',
        'shift_type' => 'regular_shift',
        'shift_template_id' => 3,
        'start_time' => '16:00',
        'end_time' => '24:00',
        'break_minutes' => 0,
        'status' => 'assigned',
        'notes' => 'Controlled contract test',
    ],
    static function (array $input) use (&$captured): array {
        $captured = $input;
        return [
            'id' => 501,
            'warnings' => [[
                'type' => 'jumpshift',
                'message' => 'Rest time is below eight hours.',
                'rest_minutes' => 420,
            ]],
            'duration_minutes' => 480,
            'is_cross_day' => true,
            'source' => 'manual',
        ];
    }
);

create_contract_assert(
    $captured['user_id'] === 21
        && $captured['assignment_type'] === 'regular_shift'
        && $captured['end_time'] === '00:00'
        && $captured['source'] === 'manual',
    'Create input no longer maps safely to the existing service contract.'
);
$attemptSummary = \TRACS\Api\V1\ShiftAssignment\create_assignment_attempt_summary([
    'agent_id' => '21',
    'assignment_date' => '2026-07-01',
    'shift_type' => 'regular_shift',
    'start_time' => '16:00',
    'end_time' => '24:00',
    'notes' => 'must-not-enter-audit-summary',
    'password' => 'must-not-enter-audit-summary',
]);
create_contract_assert(
    $attemptSummary['agent_id'] === 21
        && $attemptSummary['end_time'] === '24:00'
        && !array_key_exists('notes', $attemptSummary)
        && !array_key_exists('password', $attemptSummary),
    'Create attempt audit summary is unsafe or incomplete.'
);
create_contract_assert(
    $data['assignment']['id'] === 501
        && $data['assignment']['assignment_date_display'] === '01-07-2026'
        && $data['assignment']['display_range'] === '16:00-24:00'
        && $data['assignment']['is_cross_day'] === true,
    'Create response no longer preserves Shift 3 or date display behavior.'
);
create_contract_assert(
    count($data['warnings']) === 1 && $data['warnings'][0]['rest_minutes'] === 420,
    'Create response no longer returns service warnings.'
);

$payload = \TRACS\Api\response_payload(
    true,
    'Shift assignment created.',
    $data,
    [],
    ['request_id' => 'create-contract-request-id']
);
$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$decoded = json_decode((string)$json, true);
create_contract_assert(
    array_keys($decoded) === ['success', 'message', 'data', 'errors', 'meta']
        && $decoded['meta']['request_id'] === 'create-contract-request-id',
    'Create response envelope changed.'
);
foreach ([
    'email', 'password', 'two_factor_secret', 'notes', 'created_by', 'updated_by',
    'approved_by', 'internal_path',
] as $sensitiveKey) {
    create_contract_assert(
        !str_contains((string)$json, '"' . $sensitiveKey . '"'),
        "Create response exposed {$sensitiveKey}."
    );
}

$validationPayload = \TRACS\Api\response_payload(
    false,
    'Validation failed.',
    (object)[],
    [['field' => 'agent_id', 'message' => 'Agent is required.']],
    ['request_id' => 'validation-request-id']
);
$validationJson = json_encode($validationPayload);
create_contract_assert(
    str_contains((string)$validationJson, '"data":{}')
        && ($validationPayload['errors'][0] ?? []) === [
            'field' => 'agent_id',
            'message' => 'Agent is required.',
        ],
    'Create validation response shape changed.'
);

$invalidCases = [
    [[], ['agent_id', 'assignment_date', 'shift_type', 'start_time', 'end_time']],
    [[
        'agent_id' => 0,
        'assignment_date' => '01-07-2026',
        'shift_type' => 'root_shift',
        'start_time' => '24:00',
        'end_time' => '08:00',
    ], ['agent_id', 'assignment_date', 'shift_type', 'start_time']],
    [[
        'agent_id' => 21,
        'assignment_date' => '2026-07-01',
        'shift_type' => 'regular_shift',
        'start_time' => '08:00',
        'end_time' => '08:00',
    ], ['end_time']],
    [[
        'agent_id' => 21,
        'assignment_date' => '2026-07-01',
        'shift_type' => 'regular_shift',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'status' => 'completed',
        'source' => 'monthly_template',
        'is_overtime' => true,
    ], ['status', 'source', 'is_overtime']],
    [[
        'agent_id' => 21,
        'assignment_date' => '2026-07-01',
        'shift_type' => 'regular_shift',
        'start_time' => '08:00',
        'end_time' => '16:00',
        'shift_template_id' => 1,
        'template_id' => 2,
    ], ['shift_template_id']],
];

foreach ($invalidCases as [$input, $expectedFields]) {
    try {
        \TRACS\Api\V1\ShiftAssignment\create_assignment_input($input);
        create_contract_assert(false, 'Invalid create payload was accepted.');
    } catch (\TRACS\Api\RequestValidationException $error) {
        foreach ($expectedFields as $field) {
            create_contract_assert(
                array_key_exists($field, $error->errors),
                "Invalid create payload did not report {$field}."
            );
        }
        $list = \TRACS\Api\V1\ShiftAssignment\validation_error_list($error->errors);
        create_contract_assert(
            array_keys($list[0] ?? []) === ['field', 'message'],
            'Validation error list shape changed.'
        );
    }
}

$route = file_get_contents(__DIR__ . '/../public/api/v1/shift-assignment/assignments.php');
$permissions = file_get_contents(__DIR__ . '/../api/_permissions.php');
$csrf = file_get_contents(__DIR__ . '/../api/_csrf.php');
$contextResource = file_get_contents(__DIR__ . '/../api/v1/shift-assignment/context.php');
$frontendApi = file_get_contents(__DIR__ . '/../frontend/src/modules/shift-assignment/api.js');
create_contract_assert(
    $route !== false
        && $permissions !== false
        && $csrf !== false
        && $contextResource !== false
        && $frontendApi !== false,
    'Create contract source is unreadable.'
);
create_contract_assert(
    str_contains($route, "methods: ['GET', 'POST']"),
    'Assignments route does not preserve GET while adding POST.'
);
create_contract_assert(
    str_contains($route, 'require_explicit_role_permission(')
        && str_contains($route, "'shifts.manage'")
        && str_contains($route, "require_exact_role(\$conn, 'super_admin'"),
    'Temporary create authorization is no longer shifts.manage plus exact Super Admin.'
);
create_contract_assert(
    str_contains($permissions, 'function require_exact_role(')
        && str_contains($permissions, 'function require_explicit_role_permission(')
        && str_contains($permissions, "json_error('Forbidden.', 403"),
    'Exact-role API denial contract changed.'
);
create_contract_assert(
    str_contains($csrf, "'csrf_validation_failed'")
        && str_contains($csrf, "'api_mutation'"),
    'Invalid mutation CSRF attempts are no longer security-audited.'
);
create_contract_assert(
    str_contains($contextResource, "\$controlledCreate = (bool)(\$capabilities['create']")
        && str_contains($contextResource, "'create_assignment' => \$controlledCreate"),
    'Context no longer reflects the controlled create authorization gate.'
);
create_contract_assert(
    str_contains($route, '\\TRACS\\Api\\get_request_json()')
        && str_contains($route, '\\TRACS\\Api\\write_audit_log(')
        && str_contains($route, "'shift_assignment.create'"),
    'Create route no longer uses JSON validation and audit helpers.'
);
create_contract_assert(
    str_contains($route, "'Shift assignment created.'")
        && str_contains($route, "\n            201\n"),
    'Create success status or message changed.'
);
create_contract_assert(
    substr_count($frontendApi, "method: 'POST'") === 3
        && substr_count($frontendApi, "method: 'PATCH'") === 1
        && substr_count($frontendApi, "method: 'DELETE'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"]PUT|\.(put)\s*\()/i', $frontendApi),
    'React preview API client mutation allowlist changed.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/generate.php',
    'public/api/v1/shift-assignment/templates/copy.php',
    'public/api/v1/shift-assignment/overtime.php',
] as $forbiddenRoute) {
    create_contract_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Unexpected write route exists: {$forbiddenRoute}"
    );
}
create_contract_assert(
    !is_dir(__DIR__ . '/../public/api/v1/shift-assignment/assignments'),
    'Update or delete assignment routes were added.'
);

echo "TRACS Shift Assignment create API contract checks passed.\n";
