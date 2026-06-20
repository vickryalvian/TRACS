<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/_response.php';
require_once __DIR__ . '/../api/v1/shift-assignment/assignment.php';

function delete_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$existing = [
    'id' => 801,
    'user_id' => 31,
    'assignment_date' => '2026-07-10',
    'start_datetime' => '2026-07-10 16:00:00',
    'end_datetime' => '2026-07-11 00:00:00',
    'shift_template_id' => 3,
    'break_minutes' => 0,
    'calculated_duration_minutes' => 480,
    'assignment_type' => 'regular_shift',
    'status' => 'confirmed',
    'source' => 'manual',
    'monthly_template_id' => 0,
    'is_cross_day' => true,
];
$deletedId = 0;
$data = \TRACS\Api\V1\ShiftAssignment\delete_assignment_data(
    $existing,
    static function (int $id) use (&$deletedId): bool {
        $deletedId = $id;
        return true;
    }
);
delete_contract_assert(
    $deletedId === 801 && $data === ['assignment_id' => 801],
    'Delete data contract changed.'
);
$summary = \TRACS\Api\V1\ShiftAssignment\delete_assignment_safe_summary($existing);
delete_contract_assert(
    $summary['display_range'] === '16:00-24:00'
        && $summary['assignment_date_display'] === '10-07-2026'
        && !array_key_exists('notes', $summary),
    'Before-delete safe audit summary changed.'
);

foreach ([[], ['id' => ''], ['id' => 0], ['id' => ['801']]] as $query) {
    try {
        \TRACS\Api\V1\ShiftAssignment\delete_assignment_id($query);
        delete_contract_assert(false, 'Invalid delete assignment ID was accepted.');
    } catch (\TRACS\Api\RequestValidationException $error) {
        delete_contract_assert(isset($error->errors['id']), 'Delete ID error changed.');
    }
}
delete_contract_assert(
    \TRACS\Api\V1\ShiftAssignment\delete_assignment_id(['id' => '801']) === 801,
    'Valid delete assignment ID no longer parses.'
);

foreach ([
    array_replace($existing, ['source' => 'monthly_template']),
    array_replace($existing, ['monthly_template_id' => 88]),
] as $protected) {
    try {
        \TRACS\Api\V1\ShiftAssignment\delete_assignment_data(
            $protected,
            static fn(int $id): bool => true
        );
        delete_contract_assert(false, 'Template-owned assignment delete was accepted.');
    } catch (\DomainException $error) {
        delete_contract_assert(
            str_contains($error->getMessage(), 'Template-generated'),
            'Protected delete message changed.'
        );
    }
}

$route = file_get_contents(__DIR__ . '/../public/api/v1/shift-assignment/assignment.php');
$service = file_get_contents(__DIR__ . '/../modules/shifting-assignment/ShiftingAssignmentService.php');
$frontendApi = file_get_contents(__DIR__ . '/../frontend/src/modules/shift-assignment/api.js');
$preview = file_get_contents(__DIR__ . '/../public/shift-assignment-react-preview.php');
$header = file_get_contents(__DIR__ . '/../public/includes/header.php');
delete_contract_assert(
    $route !== false && $service !== false && $frontendApi !== false
        && $preview !== false && $header !== false,
    'Delete contract source is unreadable.'
);
delete_contract_assert(
    str_contains($route, "methods: ['PATCH', 'DELETE']")
        && str_contains($route, "require_exact_role(\$conn, 'super_admin'")
        && str_contains($route, "'shifts.manage'")
        && str_contains($route, "'shift_assignment.delete'")
        && str_contains($route, "'Shift assignment deleted.'")
        && str_contains($route, '409')
        && str_contains($route, '404')
        && str_contains($route, '422'),
    'Delete route lost method, authorization, audit, or status enforcement.'
);
delete_contract_assert(
    str_contains($service, 'public function deleteAssignment(int $id): bool')
        && str_contains($service, 'writeRequiredAssignmentAudit(')
        && str_contains($service, 'Assignment audit storage is unavailable.')
        && str_contains($service, "'_dependents'")
        && str_contains($service, 'getDeleteDependentSnapshot')
        && str_contains($service, 'hasMonthlyTemplateItemLink')
        && str_contains($service, 'DELETE FROM shift_warnings WHERE shift_assignment_id=?')
        && str_contains($service, 'DELETE FROM shift_assignments WHERE id=? LIMIT 1')
        && str_contains($service, 'begin_transaction()')
        && str_contains($service, 'rollback()'),
    'Delete service transaction or before-delete audit changed.'
);
delete_contract_assert(
    substr_count($frontendApi, "method: 'POST'") === 3
        && substr_count($frontendApi, "method: 'PATCH'") === 1
        && substr_count($frontendApi, "method: 'DELETE'") === 1,
    'React preview delete API caller changed.'
);
delete_contract_assert(
    str_contains($preview, 'Template Preview is non-mutating')
        && str_contains($preview, 'Apply Template uses')
        && str_contains($preview, 'controlled backend commit')
        && str_contains($preview, 'no copy, overtime')
        && !str_contains($header, 'shift-assignment-react-preview.php'),
    'Preview warning or navigation isolation changed.'
);

echo "TRACS Shift Assignment delete API contract checks passed.\n";
