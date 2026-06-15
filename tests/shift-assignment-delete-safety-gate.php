<?php
declare(strict_types=1);

function delete_gate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function delete_gate_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    delete_gate_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$contract = delete_gate_source('docs/shift-assignment-write-api-contract.md');
$testing = delete_gate_source('TESTING.md');
$rollback = delete_gate_source('ROLLBACK.md');
$frontendApi = delete_gate_source('frontend/src/modules/shift-assignment/api.js');
$moduleSources = implode("\n", array_map(
    'delete_gate_source',
    [
        'frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx',
        'frontend/src/modules/shift-assignment/components/ShiftAssignmentTable.jsx',
        'frontend/src/modules/shift-assignment/components/ShiftAssignmentBoard.jsx',
    ]
));
$context = delete_gate_source('api/v1/shift-assignment/context.php');
$route = delete_gate_source('public/api/v1/shift-assignment/assignment.php');
$service = delete_gate_source('modules/shifting-assignment/ShiftingAssignmentService.php');
$restoreDrill = delete_gate_source('tests/shift-assignment-delete-restore-drill.php');

foreach ([
    '## Delete UI Safety Gate',
    'type exactly `DELETE`',
    'hard delete',
    '### Manual Restoration',
    'before_snapshot',
    'shift_assignment.restore',
    'logical replacement',
    '### Future Soft Delete Proposal',
    '`up.sql` and `down.sql`',
] as $requiredText) {
    delete_gate_assert(
        str_contains($contract, $requiredText),
        "Delete safety contract is missing {$requiredText}."
    );
}
delete_gate_assert(
    str_contains($contract, 'Template-owned')
        && str_contains($contract, 'assignments return `409`'),
    'Template-owned assignment 409 rule is missing.'
);
delete_gate_assert(
    str_contains($testing, '## Delete UI Safety Gate')
        && str_contains($testing, 'authenticated disposable-browser evidence')
        && str_contains($rollback, 'git revert <phase-22-commit-sha>'),
    'Testing or rollback safety gate documentation is incomplete.'
);
delete_gate_assert(
    !preg_match('/\b(method\s*:\s*[\'"]DELETE|deleteShiftAssignment|Delete Assignment)\b/i', $frontendApi)
        && !preg_match('/\b(Delete Assignment|deleteShiftAssignment)\b/i', $moduleSources),
    'React Delete UI or API caller was activated.'
);
delete_gate_assert(
    str_contains($context, "'delete_assignment' => false"),
    'Delete capability is exposed to React.'
);
delete_gate_assert(
    str_contains($route, "methods: ['PATCH', 'DELETE']")
        && str_contains($route, "require_exact_role(\$conn, 'super_admin'")
        && str_contains($route, "'shifts.manage'"),
    'DELETE endpoint protection changed.'
);
delete_gate_assert(
    str_contains($service, 'writeRequiredAssignmentAudit(')
        && str_contains($service, 'Assignment audit storage is unavailable.')
        && str_contains($service, 'rollback()'),
    'Required before-delete audit or fail-closed behavior changed.'
);
delete_gate_assert(
    str_contains($restoreDrill, 'TRACS_TEST_INCLUDE_RESTORE=1')
        && str_contains($testing, '## Phase 23 Disposable Restoration Drill')
        && str_contains($contract, '### Phase 23 Restoration Drill Result')
        && str_contains($contract, '**Delete UI remains blocked.**'),
    'Restoration drill result or dependent-record blocker is missing.'
);

echo "TRACS Shift Assignment Delete UI safety gate checks passed.\n";
