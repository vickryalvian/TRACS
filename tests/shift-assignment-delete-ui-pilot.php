<?php
declare(strict_types=1);

function delete_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function delete_ui_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    delete_ui_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$app = delete_ui_source('frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx');
$table = delete_ui_source('frontend/src/modules/shift-assignment/components/ShiftAssignmentTable.jsx');
$board = delete_ui_source('frontend/src/modules/shift-assignment/components/ShiftAssignmentBoard.jsx');
$modal = delete_ui_source('frontend/src/modules/shift-assignment/components/ShiftDeleteModal.jsx');
$deleteUtils = delete_ui_source('frontend/src/modules/shift-assignment/utils/shiftDelete.js');
$api = delete_ui_source('frontend/src/modules/shift-assignment/api.js');
$context = delete_ui_source('api/v1/shift-assignment/context.php');
$contextRoute = delete_ui_source('public/api/v1/shift-assignment/context.php');
$preview = delete_ui_source('public/shift-assignment-react-preview.php');
$header = delete_ui_source('public/includes/header.php');

delete_ui_assert(
    str_contains($app, "context.shift?.allowed_actions?.delete_assignment")
        && str_contains($table, 'canDelete')
        && str_contains($board, 'canDelete')
        && str_contains($table, 'onDelete?.(assignment)')
        && str_contains($board, 'onDelete?.(assignment)'),
    'Delete actions are not gated by backend context.'
);
delete_ui_assert(
    str_contains($modal, 'deleteShiftAssignment(assignment.id, csrf)')
        && str_contains($modal, "deleting ? 'Deleting...' : 'Delete Assignment'")
        && str_contains($modal, 'This action hard-deletes the assignment.')
        && str_contains($modal, 'Audit-backed restoration has been validated')
        && str_contains($modal, 'Template-linked assignments cannot be deleted')
        && str_contains($deleteUtils, "DELETE_CONFIRMATION = 'DELETE'")
        && str_contains($deleteUtils, 'value === DELETE_CONFIRMATION'),
    'Delete modal confirmation or hard-delete warning changed.'
);
delete_ui_assert(
    substr_count($api, "method: 'POST'") === 2
        && substr_count($api, "method: 'PATCH'") === 1
        && substr_count($api, "method: 'DELETE'") === 1
        && str_contains($api, 'csrfToken: csrf.token')
        && str_contains($api, 'csrfHeaderName: csrf.header'),
    'Frontend mutation methods or CSRF handoff changed.'
);
delete_ui_assert(
    str_contains($context, "'delete_assignment' => \$controlledDelete")
        && str_contains($contextRoute, "'delete' => (string)(\$context['user']['role_slug'] ?? '') === 'super_admin'")
        && str_contains($contextRoute, "'shifts.manage'"),
    'Backend delete capability is not exact-Super-Admin plus shifts.manage.'
);
delete_ui_assert(
    str_contains($preview, 'Create/Edit/Delete and Template Preview actions are')
        && str_contains($preview, 'hard-delete pilot')
        && str_contains($preview, 'Template Preview is non-mutating')
        && str_contains($preview, 'template commit, copy, overtime')
        && !str_contains($header, 'shift-assignment-react-preview.php'),
    'Pilot warning or navigation isolation changed.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/generate.php',
    'public/api/v1/shift-assignment/templates/copy.php',
    'public/api/v1/shift-assignment/overtime.php',
] as $forbiddenRoute) {
    delete_ui_assert(!is_file(__DIR__ . '/../' . $forbiddenRoute), "Unexpected route: {$forbiddenRoute}");
}

echo "TRACS Shift Assignment controlled delete UI pilot checks passed.\n";
