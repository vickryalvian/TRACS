<?php
declare(strict_types=1);

function create_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function create_ui_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    create_ui_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$app = create_ui_source('frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx');
$toolbar = create_ui_source('frontend/src/modules/shift-assignment/components/ShiftToolbar.jsx');
$modal = create_ui_source('frontend/src/modules/shift-assignment/components/ShiftCreateModal.jsx');
$api = create_ui_source('frontend/src/modules/shift-assignment/api.js');
$client = create_ui_source('frontend/src/lib/apiClient.js');
$preview = create_ui_source('public/shift-assignment-react-preview.php');
$header = create_ui_source('public/includes/header.php');
$context = create_ui_source('api/v1/shift-assignment/context.php');
$route = create_ui_source('public/api/v1/shift-assignment/assignments.php');

create_ui_assert(
    str_contains($app, "context.shift?.allowed_actions?.create_assignment")
        && str_contains($toolbar, '{canCreate ? (')
        && str_contains($toolbar, 'Add Assignment'),
    'Create entry point is not gated by the backend context capability.'
);
create_ui_assert(
    str_contains($modal, 'createShiftAssignment(result.payload, csrf)')
        && str_contains($modal, "saving ? 'Saving...'")
        && str_contains($modal, "window.confirm('Discard unsaved assignment changes?')")
        && str_contains($modal, 'aria-modal="true"'),
    'Controlled modal safety behavior changed.'
);
create_ui_assert(
    str_contains($api, "method: 'POST'")
        && str_contains($api, "csrfToken: csrf.token")
        && str_contains($api, "csrfHeaderName: csrf.header")
        && substr_count($api, "method: 'POST'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"](PUT|PATCH|DELETE)|\.(put|patch|delete)\s*\()/i', $api),
    'Frontend API must expose only the existing controlled create POST.'
);
create_ui_assert(
    str_contains($client, 'requestCsrfToken ?? readCsrfToken')
        && str_contains($client, 'requestCsrfHeaderName ?? csrfHeaderName'),
    'In-memory context CSRF handoff changed.'
);
create_ui_assert(
    str_contains($context, "'create_assignment' => \$controlledCreate")
        && str_contains($route, "require_exact_role(\$conn, 'super_admin'")
        && str_contains($route, "'shifts.manage'"),
    'Backend create capability or exact pilot authorization changed.'
);
create_ui_assert(
    str_contains($preview, "tracs_require_page_permission(\$conn, 'shifts.view')")
        && str_contains($preview, 'tracs_require_super_admin_page($conn)')
        && str_contains($preview, 'Create action is enabled only for Super Admin')
        && str_contains($preview, 'remains the production source of'),
    'Preview access or controlled pilot warning changed.'
);
create_ui_assert(
    !str_contains($header, 'shift-assignment-react-preview.php'),
    'Controlled create pilot was exposed in production navigation.'
);

echo "TRACS Shift Assignment controlled create UI pilot checks passed.\n";
