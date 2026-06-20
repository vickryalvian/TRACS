<?php
declare(strict_types=1);

function template_commit_ui_gate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function template_commit_ui_gate_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    template_commit_ui_gate_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

function template_commit_ui_gate_scan(string $directory): string
{
    $root = realpath(__DIR__ . '/../' . $directory);
    template_commit_ui_gate_assert($root !== false, "Unable to scan {$directory}.");

    $contents = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if (!in_array(strtolower($file->getExtension()), ['js', 'jsx', 'mjs', 'css'], true)) {
            continue;
        }
        $contents .= "\n" . (file_get_contents($file->getPathname()) ?: '');
    }

    return $contents;
}

$templateContract = template_commit_ui_gate_source('docs/shift-assignment-template-api-contract.md');
$frontendPlan = template_commit_ui_gate_source('docs/frontend-migration-plan.md');
$reactArchitecture = template_commit_ui_gate_source('docs/react-tailwind-architecture.md');
$testing = template_commit_ui_gate_source('TESTING.md');
$roadmap = template_commit_ui_gate_source('REFACTOR_ROADMAP.md');
$rollback = template_commit_ui_gate_source('ROLLBACK.md');
$frontendModule = template_commit_ui_gate_scan('frontend/src/modules/shift-assignment');
$frontendTests = template_commit_ui_gate_scan('frontend/tests');
$apiClient = template_commit_ui_gate_source('frontend/src/modules/shift-assignment/api.js');

foreach ([
    'Step 1 - Configure Preview',
    'Step 2 - Review Preview',
    'Step 3 - Commit Review',
    'Step 4 - Commit Result',
    'APPLY TEMPLATE',
    'preview has conflicts',
    'preview has blocked_items',
    'preview result is stale',
    'created assignment IDs',
    'rollback evidence',
    'commitTemplatePreview(payload)',
    'No active Apply Template UI is implemented in Phase 34.',
] as $required) {
    template_commit_ui_gate_assert(
        str_contains($templateContract, $required),
        "Template commit UI safety contract missing {$required}."
    );
}

foreach ([
    'No active Apply Template UI',
    'no commit/apply/generate-save button',
    'no API caller for `templates/commit.php`',
] as $required) {
    template_commit_ui_gate_assert(
        str_contains($frontendPlan, $required)
            && str_contains($testing, $required)
            && str_contains($roadmap, $required),
        "Canonical Phase 34 docs missing {$required}."
    );
}

template_commit_ui_gate_assert(
    str_contains($rollback, 'Phase 34 Template Commit UI Gate Rollback')
        && str_contains($reactArchitecture, 'Template Commit UI Gate'),
    'Rollback or React architecture docs missing Phase 34 UI gate.'
);

foreach ([
    'commitShiftTemplate',
    'commitTemplatePreview',
    '/api/v1/shift-assignment/templates/commit.php',
    'Apply Template',
    'Commit Template',
    'APPLY TEMPLATE',
    'Copy Schedule',
    'Copy to month',
    'copy-preview.php',
    'copy-commit.php',
] as $forbiddenFrontendNeedle) {
    template_commit_ui_gate_assert(
        !str_contains($frontendModule, $forbiddenFrontendNeedle)
            && !str_contains($frontendTests, $forbiddenFrontendNeedle)
            && !str_contains($apiClient, $forbiddenFrontendNeedle),
        "React template commit/copy UI is no longer gated: {$forbiddenFrontendNeedle}."
    );
}

template_commit_ui_gate_assert(
    str_contains($frontendModule, 'Preview only')
        && str_contains($frontendModule, 'Writing, applying, saving, and copy actions are intentionally unavailable')
        && str_contains($apiClient, '/api/v1/shift-assignment/templates/preview.php'),
    'Template Preview UI no longer appears preview-only.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    template_commit_ui_gate_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 34 unexpectedly created {$forbiddenRoute}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/commit.php',
    'api/v1/shift-assignment/templates/commit.php',
] as $requiredRoute) {
    template_commit_ui_gate_assert(
        is_file(__DIR__ . '/../' . $requiredRoute),
        "Backend commit endpoint unexpectedly missing: {$requiredRoute}."
    );
}

echo "TRACS Shift Assignment template commit UI gate checks passed.\n";
