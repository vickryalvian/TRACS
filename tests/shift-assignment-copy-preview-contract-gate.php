<?php
declare(strict_types=1);

function copy_preview_gate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function copy_preview_gate_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    copy_preview_gate_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

function copy_preview_gate_scan(string $directory): string
{
    $root = realpath(__DIR__ . '/../' . $directory);
    copy_preview_gate_assert($root !== false, "Unable to scan {$directory}.");

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

$templateContract = copy_preview_gate_source('docs/shift-assignment-template-api-contract.md');
$writeContract = copy_preview_gate_source('docs/shift-assignment-write-api-contract.md');
$frontendPlan = copy_preview_gate_source('docs/frontend-migration-plan.md');
$reactArchitecture = copy_preview_gate_source('docs/react-tailwind-architecture.md');
$testing = copy_preview_gate_source('TESTING.md');
$roadmap = copy_preview_gate_source('REFACTOR_ROADMAP.md');
$rollback = copy_preview_gate_source('ROLLBACK.md');
$securityInventory = copy_preview_gate_source('docs/API_SECURITY_INVENTORY.md');
$parity = copy_preview_gate_source('docs/shift-assignment-preview-parity.md');
$frontendModule = copy_preview_gate_scan('frontend/src/modules/shift-assignment');
$apiClient = copy_preview_gate_source('frontend/src/modules/shift-assignment/api.js');

foreach ([
    'POST /api/v1/shift-assignment/templates/copy-preview.php',
    'source_start_date',
    'source_end_date',
    'target_start_date',
    'target_end_date',
    'source_range',
    'target_range',
    'source_assignments',
    'preview_assignments',
    'Non-mutating preview guarantee',
    'Source-to-target transformation rules',
    'Preserve the agent',
    'Recalculate day-of-week labels',
    'Do not copy audit IDs',
    'Do not copy old assignment IDs',
    'Shift 3 `16:00-24:00`',
    'target assignment overlap',
    'weekly hours',
    'jumpshift',
    'holiday',
    'overtime',
    'shifts.template.copy_preview',
    'Preview only - this will not create or modify assignments.',
    'APPLY COPY',
] as $requiredTemplateNeedle) {
    copy_preview_gate_assert(
        str_contains($templateContract, $requiredTemplateNeedle),
        "Copy preview contract missing {$requiredTemplateNeedle}."
    );
}

foreach ([
    'Phase 38 Copy Schedule Preview Contract Gate',
    'copy-preview.php',
    'source_start_date',
    'target_start_date',
    'Preview only - this will not create or modify assignments.',
    'APPLY COPY',
] as $requiredDocNeedle) {
    copy_preview_gate_assert(
        str_contains($writeContract, $requiredDocNeedle)
            && str_contains($frontendPlan, $requiredDocNeedle)
            && str_contains($testing, $requiredDocNeedle)
            && str_contains($roadmap, $requiredDocNeedle),
        "Canonical Phase 38 docs missing {$requiredDocNeedle}."
    );
}

copy_preview_gate_assert(
    str_contains($reactArchitecture, 'Phase 38 Copy Preview Contract Gate')
        && str_contains($rollback, 'Phase 38 Copy Preview Contract Rollback')
        && str_contains($securityInventory, 'Phase 38 contract-only')
        && str_contains($parity, 'Phase 38 copy-preview contract gate'),
    'Phase 38 architecture, rollback, security, or parity docs are incomplete.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    copy_preview_gate_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 38 unexpectedly created {$forbiddenRoute}."
    );
}

foreach ([
    'Copy Schedule',
    'Copy Schedule Preview',
    'APPLY COPY',
    'copy-preview.php',
    'copy-commit.php',
] as $forbiddenFrontendNeedle) {
    copy_preview_gate_assert(
        !str_contains($frontendModule, $forbiddenFrontendNeedle)
            && !str_contains($apiClient, $forbiddenFrontendNeedle),
        "Phase 38 unexpectedly added React copy UI/caller: {$forbiddenFrontendNeedle}."
    );
}

copy_preview_gate_assert(
    str_contains($frontendModule, 'Apply Template')
        && str_contains($apiClient, '/api/v1/shift-assignment/templates/commit.php'),
    'Existing Apply Template pilot no longer appears intact.'
);

echo "TRACS Shift Assignment copy preview contract gate checks passed.\n";
