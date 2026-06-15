<?php
declare(strict_types=1);

function edit_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function edit_ui_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    edit_ui_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$app = edit_ui_source('frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx');
$table = edit_ui_source('frontend/src/modules/shift-assignment/components/ShiftAssignmentTable.jsx');
$board = edit_ui_source('frontend/src/modules/shift-assignment/components/ShiftAssignmentBoard.jsx');
$modal = edit_ui_source('frontend/src/modules/shift-assignment/components/ShiftEditModal.jsx');
$mutation = edit_ui_source('frontend/src/modules/shift-assignment/utils/shiftMutation.js');
$api = edit_ui_source('frontend/src/modules/shift-assignment/api.js');
$preview = edit_ui_source('public/shift-assignment-react-preview.php');
$header = edit_ui_source('public/includes/header.php');
$context = edit_ui_source('api/v1/shift-assignment/context.php');
$contextRoute = edit_ui_source('public/api/v1/shift-assignment/context.php');
$updateRoute = edit_ui_source('public/api/v1/shift-assignment/assignment.php');

edit_ui_assert(
    str_contains($app, "context.shift?.allowed_actions?.update_assignment")
        && str_contains($table, '{canEdit ? (')
        && str_contains($board, '{canEdit ? (')
        && str_contains($table, 'Edit')
        && str_contains($board, 'Edit'),
    'Edit entry points are not gated by the backend context capability.'
);
edit_ui_assert(
    str_contains($modal, 'updateShiftAssignment(draft.id, result.payload, csrf)')
        && str_contains($modal, "saving ? 'Saving...'")
        && str_contains($modal, 'Change at least one field before saving.')
        === false
        && str_contains($modal, "window.confirm('Discard unsaved assignment changes?')")
        && str_contains($modal, "mutationErrorMessage(error, 'edit')")
        && str_contains($mutation, 'case 404:')
        && str_contains($mutation, 'case 409:')
        && str_contains($modal, 'aria-modal="true"'),
    'Controlled edit modal safety behavior changed.'
);
edit_ui_assert(
    substr_count($api, "method: 'POST'") === 2
        && substr_count($api, "method: 'PATCH'") === 1
        && substr_count($api, "method: 'DELETE'") === 1
        && str_contains($api, '/api/v1/shift-assignment/assignment.php?id=')
        && str_contains($api, 'csrfToken: csrf.token')
        && str_contains($api, 'csrfHeaderName: csrf.header')
        && !preg_match('/\b(method\s*:\s*[\'"]PUT|\.(put)\s*\()/i', $api),
    'Frontend API mutation allowlist changed.'
);
edit_ui_assert(
    str_contains($context, "'update_assignment' => \$controlledUpdate")
        && str_contains($contextRoute, "'update' => (string)(\$context['user']['role_slug'] ?? '') === 'super_admin'")
        && str_contains($contextRoute, "'shifts.manage'")
        && str_contains($updateRoute, "require_exact_role(\$conn, 'super_admin'")
        && str_contains($updateRoute, "'shifts.manage'"),
    'Backend edit capability or exact pilot authorization changed.'
);
edit_ui_assert(
    str_contains($preview, 'Create/Edit/Delete and Template Preview actions are')
        && str_contains($preview, "tracs_require_page_permission(\$conn, 'shifts.view')")
        && str_contains($preview, 'tracs_require_super_admin_page($conn)')
        && str_contains($preview, 'Template Preview is non-mutating')
        && str_contains($preview, 'template commit, copy, overtime'),
    'Preview access or controlled edit warning changed.'
);
edit_ui_assert(
    !str_contains($header, 'shift-assignment-react-preview.php'),
    'Controlled edit pilot was exposed in production navigation.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/generate.php',
    'public/api/v1/shift-assignment/templates/copy.php',
    'public/api/v1/shift-assignment/overtime.php',
] as $forbiddenRoute) {
    edit_ui_assert(!is_file(__DIR__ . '/../' . $forbiddenRoute), "Unexpected route: {$forbiddenRoute}");
}

echo "TRACS Shift Assignment controlled edit UI pilot checks passed.\n";
