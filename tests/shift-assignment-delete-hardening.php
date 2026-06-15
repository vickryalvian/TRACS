<?php
declare(strict_types=1);

function delete_hardening_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function delete_hardening_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    delete_hardening_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$modal = delete_hardening_source(
    'frontend/src/modules/shift-assignment/components/ShiftDeleteModal.jsx'
);
$utils = delete_hardening_source(
    'frontend/src/modules/shift-assignment/utils/shiftDelete.js'
);
$app = delete_hardening_source(
    'frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx'
);
$api = delete_hardening_source(
    'frontend/src/modules/shift-assignment/api.js'
);
$preview = delete_hardening_source('public/shift-assignment-react-preview.php');
$header = delete_hardening_source('public/includes/header.php');

delete_hardening_assert(
    str_contains($modal, 'Assignment ID')
        && str_contains($modal, 'Agent')
        && str_contains($modal, 'Date')
        && str_contains($modal, 'Shift')
        && str_contains($modal, 'Type')
        && str_contains($modal, 'Division')
        && str_contains($modal, 'Role')
        && str_contains($modal, 'Status'),
    'Delete identity review is incomplete.'
);
delete_hardening_assert(
    str_contains($modal, 'controlled manual recovery procedure, not an instant undo')
        && str_contains($modal, 'Confirmation is case-sensitive')
        && str_contains($modal, 'aria-live="polite"')
        && str_contains($modal, 'disabled={deleting || protectedByTemplate || Boolean(confirmationError)}')
        && str_contains($modal, "deleting ? 'Deleting...' : 'Delete Assignment'"),
    'Delete warning, accessibility, or submit lock changed.'
);
delete_hardening_assert(
    str_contains($utils, "DELETE_CONFIRMATION = 'DELETE'")
        && str_contains($utils, 'value === DELETE_CONFIRMATION')
        && !str_contains($utils, '.trim()')
        && !str_contains($utils, '.toUpperCase()'),
    'Exact DELETE confirmation can be normalized or bypassed.'
);
delete_hardening_assert(
    str_contains($modal, 'deleteShiftAssignment(assignment.id, csrf)')
        && str_contains($api, "method: 'DELETE'")
        && str_contains($api, 'csrfToken: csrf.token')
        && str_contains($app, "context.shift?.allowed_actions?.delete_assignment"),
    'Delete API, CSRF, or capability gating changed.'
);
delete_hardening_assert(
    str_contains($preview, 'Create/Edit/Delete and Template Preview actions are')
        && str_contains($preview, 'hard-delete pilot')
        && !str_contains($header, 'shift-assignment-react-preview.php'),
    'Pilot isolation or warning changed.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/generate.php',
    'public/api/v1/shift-assignment/templates/copy.php',
    'public/api/v1/shift-assignment/overtime.php',
] as $forbiddenRoute) {
    delete_hardening_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Unexpected write route: {$forbiddenRoute}"
    );
}

echo "TRACS Shift Assignment delete pilot hardening checks passed.\n";
