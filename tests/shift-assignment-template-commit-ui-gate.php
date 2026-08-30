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
    'Phase 35 Apply Template UI Pilot',
    'Phase 36 Apply Template UI Hardening',
    'blocked by browser tooling',
] as $required) {
    template_commit_ui_gate_assert(
        str_contains($templateContract, $required),
        "Template commit UI safety contract missing {$required}."
    );
}

foreach ([
    'Apply Template UI pilot',
    'exact APPLY TEMPLATE',
    'rollback evidence',
    'browser tooling',
] as $required) {
    template_commit_ui_gate_assert(
        str_contains($frontendPlan, $required)
            && str_contains($testing, $required)
            && str_contains($roadmap, $required),
        "Canonical Phase 34 docs missing {$required}."
    );
}

template_commit_ui_gate_assert(
    str_contains($rollback, 'Phase 35 Apply Template UI Pilot Rollback')
        && str_contains($rollback, 'Phase 36 Apply Template UI Hardening Rollback')
        && str_contains($reactArchitecture, 'Phase 35 Apply Template UI Pilot'),
    'Rollback or React architecture docs missing Phase 35 apply UI pilot.'
);

foreach ([
    'Copy to month',
    'copy-commit.php',
] as $forbiddenFrontendNeedle) {
    template_commit_ui_gate_assert(
        !str_contains($frontendModule, $forbiddenFrontendNeedle)
            && !str_contains($apiClient, $forbiddenFrontendNeedle),
        "React template commit/copy UI is no longer gated: {$forbiddenFrontendNeedle}."
    );
}

template_commit_ui_gate_assert(
    str_contains($frontendModule, 'Copy Schedule Preview')
        && str_contains($apiClient, '/api/v1/shift-assignment/templates/copy-preview.php'),
    'Phase 40 Copy Schedule Preview UI/caller is missing.'
);

foreach ([
    'APPLY COPY',
    'Apply Copy',
    'Commit Copy',
    'Paste Schedule',
    'Save Copied Schedule',
    'Generate Copied Schedule',
] as $forbiddenCopyCommitUi) {
    template_commit_ui_gate_assert(
        !str_contains($frontendModule, $forbiddenCopyCommitUi)
            && !str_contains($apiClient, $forbiddenCopyCommitUi),
        "Phase 40 unexpectedly added copy commit/apply UI: {$forbiddenCopyCommitUi}."
    );
}

template_commit_ui_gate_assert(
    str_contains($frontendModule, 'Apply Template')
        && str_contains($frontendModule, 'APPLY TEMPLATE')
        && str_contains($frontendModule, 'This preview is stale. Regenerate it before applying.')
        && str_contains($frontendModule, 'Rollback targeting is based on the created assignment IDs')
        && str_contains($frontendModule, 'aria-label="Apply Template confirmation"')
        && str_contains($frontendModule, 'template-apply-disabled-reason')
        && str_contains($frontendModule, 'role="alert"')
        && str_contains($apiClient, '/api/v1/shift-assignment/templates/preview.php'),
    'Controlled Template Apply UI pilot is missing expected safety copy.'
);

foreach ([
    'Rollback Assignment',
    'Undo Template',
    'Restore Template',
    'Rollback Template',
] as $forbiddenRollbackUi) {
    template_commit_ui_gate_assert(
        !str_contains($frontendModule, $forbiddenRollbackUi),
        "Phase 36 unexpectedly added rollback UI: {$forbiddenRollbackUi}."
    );
}

template_commit_ui_gate_assert(
    str_contains($apiClient, '/api/v1/shift-assignment/templates/commit.php')
        && str_contains($apiClient, 'conflict_policy')
        && str_contains($apiClient, 'confirmation'),
    'React API client is missing the controlled template commit caller.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    template_commit_ui_gate_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 35 unexpectedly created {$forbiddenRoute}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
] as $implementedCopyPreviewRoute) {
    template_commit_ui_gate_assert(
        is_file(__DIR__ . '/../' . $implementedCopyPreviewRoute),
        "Phase 39 copy-preview route is missing: {$implementedCopyPreviewRoute}."
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
