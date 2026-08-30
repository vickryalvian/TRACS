<?php
declare(strict_types=1);

function browser_validation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function browser_validation_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    browser_validation_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$environment = browser_validation_source(
    'tests/shift-assignment-create-ui-browser-environment.php'
);
$preview = browser_validation_source('public/shift-assignment-react-preview.php');
$header = browser_validation_source('public/includes/header.php');
$frontendApi = browser_validation_source('frontend/src/modules/shift-assignment/api.js');
$modal = browser_validation_source(
    'frontend/src/modules/shift-assignment/components/ShiftCreateModal.jsx'
);
$mutation = browser_validation_source(
    'frontend/src/modules/shift-assignment/utils/shiftMutation.js'
);

foreach ([
    "TRACS_ENV must be exactly test",
    "TRACS_ALLOW_MUTATION_TESTS=1 is required",
    "browser database name is not safely marked",
    "DROP DATABASE IF EXISTS",
    "'setup', 'verify', 'cleanup'",
] as $guard) {
    browser_validation_assert(
        str_contains($environment, $guard),
        "Disposable browser environment guard missing: {$guard}"
    );
}
browser_validation_assert(
    str_contains($environment, '--no-data --routines --triggers')
        && !str_contains($environment, '--databases'),
    'Browser environment no longer clones schema only.'
);
browser_validation_assert(
    str_contains($preview, 'Create/Edit/Delete and Template Preview/Apply')
        && str_contains($preview, "tracs_require_page_permission(\$conn, 'shifts.view')")
        && str_contains($preview, 'tracs_require_super_admin_page($conn)')
        && str_contains($preview, "require_once __DIR__ . '/includes/page_helpers.php'"),
    'Preview pilot access or warning changed.'
);
browser_validation_assert(
    !str_contains($header, 'shift-assignment-react-preview.php'),
    'Preview was added to production navigation.'
);
browser_validation_assert(
    substr_count($frontendApi, "method: 'POST'") === 3
        && substr_count($frontendApi, "method: 'PATCH'") === 1
        && substr_count($frontendApi, "method: 'DELETE'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"]PUT|\.(put)\s*\()/i', $frontendApi),
    'Browser pilot frontend mutation allowlist changed.'
);
browser_validation_assert(
    str_contains($modal, "saving ? 'Saving...'")
        && str_contains($modal, 'createShiftAssignment(result.payload, csrf)')
        && str_contains($modal, "mutationErrorMessage(error, 'create')")
        && str_contains($mutation, 'case 409:'),
    'Create modal validation/error behavior changed.'
);

echo "TRACS Shift Assignment disposable browser validation workflow checks passed.\n";
