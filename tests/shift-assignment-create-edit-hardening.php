<?php
declare(strict_types=1);

function hardening_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function hardening_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    hardening_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$app = hardening_source('frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx');
$create = hardening_source('frontend/src/modules/shift-assignment/components/ShiftCreateModal.jsx');
$edit = hardening_source('frontend/src/modules/shift-assignment/components/ShiftEditModal.jsx');
$mutation = hardening_source('frontend/src/modules/shift-assignment/utils/shiftMutation.js');
$hook = hardening_source('frontend/src/modules/shift-assignment/hooks/useShiftAssignments.js');
$api = hardening_source('frontend/src/modules/shift-assignment/api.js');
$preview = hardening_source('public/shift-assignment-react-preview.php');
$header = hardening_source('public/includes/header.php');
$context = hardening_source('public/api/v1/shift-assignment/context.php');

foreach ([$create, $edit] as $modal) {
    hardening_assert(
        str_contains($modal, 'aria-busy={saving}')
            && str_contains($modal, 'disabled={saving}')
            && str_contains($modal, 'focusInvalidField(modalRef.current')
            && str_contains($modal, 'mutationErrorMessage(error,')
            && str_contains($modal, 'aria-required="true"')
            && str_contains($modal, 'shift-required-mark')
            && str_contains($modal, "window.confirm('Discard unsaved assignment changes?')"),
        'Create/Edit modal hardening behavior changed.'
    );
}

hardening_assert(
    str_contains($mutation, 'case 401:')
        && str_contains($mutation, 'case 403:')
        && str_contains($mutation, 'case 404:')
        && str_contains($mutation, 'case 405:')
        && str_contains($mutation, 'case 409:')
        && str_contains($mutation, 'case 422:')
        && str_contains($mutation, 'network request failed'),
    'Shared safe mutation error mapping is incomplete.'
);
hardening_assert(
    str_contains($hook, 'return true;')
        && substr_count($hook, 'return false;') >= 3
        && substr_count($app, 'const refreshed = await assignments.refresh();') === 2
        && str_contains($app, 'Refresh the schedule to load the latest data.'),
    'Post-create/edit refresh result handling changed.'
);
hardening_assert(
    substr_count($api, "method: 'POST'") === 1
        && substr_count($api, "method: 'PATCH'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"](PUT|DELETE)|\.(put|delete)\s*\()/i', $api),
    'An unapproved frontend mutation was added.'
);
hardening_assert(
    str_contains($context, "'role_slug'] ?? '') === 'super_admin'")
        && str_contains($context, "'shifts.manage'")
        && str_contains($preview, 'Create/Edit actions are enabled only for Super')
        && str_contains($preview, 'no delete, template, copy, overtime')
        && !str_contains($header, 'shift-assignment-react-preview.php'),
    'Pilot authorization, warning, or navigation isolation changed.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/generate.php',
    'public/api/v1/shift-assignment/templates/copy.php',
    'public/api/v1/shift-assignment/overtime.php',
    'public/api/v1/shift-assignment/delete.php',
] as $forbiddenRoute) {
    hardening_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Unexpected destructive or bulk route: {$forbiddenRoute}"
    );
}

echo "TRACS Shift Assignment create/edit hardening checks passed.\n";
