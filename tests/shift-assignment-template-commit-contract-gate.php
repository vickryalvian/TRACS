<?php
declare(strict_types=1);

function template_commit_gate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function template_commit_gate_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    template_commit_gate_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

function template_commit_gate_scan(string $directory): string
{
    $root = realpath(__DIR__ . '/../' . $directory);
    template_commit_gate_assert($root !== false, "Unable to scan {$directory}.");

    $contents = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['js', 'jsx', 'css'], true)) {
            continue;
        }
        $contents .= "\n" . (file_get_contents($file->getPathname()) ?: '');
    }

    return $contents;
}

$templateContract = template_commit_gate_source('docs/shift-assignment-template-api-contract.md');
$writeContract = template_commit_gate_source('docs/shift-assignment-write-api-contract.md');
$frontendPlan = template_commit_gate_source('docs/frontend-migration-plan.md');
$testing = template_commit_gate_source('TESTING.md');
$roadmap = template_commit_gate_source('REFACTOR_ROADMAP.md');
$rollback = template_commit_gate_source('ROLLBACK.md');
$securityInventory = template_commit_gate_source('docs/API_SECURITY_INVENTORY.md');
$migration = template_commit_gate_source('config/migrations/2026_06_08_shifting_assignment.sql');
$service = template_commit_gate_source('modules/shifting-assignment/ShiftingAssignmentService.php');
$frontendModule = template_commit_gate_scan('frontend/src/modules/shift-assignment');
$apiClient = template_commit_gate_source('frontend/src/modules/shift-assignment/api.js');

foreach ([
    'POST /api/v1/shift-assignment/templates/commit.php',
    'APPLY TEMPLATE',
    'preview-to-commit integrity',
    'Never trust client preview items blindly',
    'recompute or revalidate preview server-side',
    'conflict_policy = block',
    'exact `super_admin` plus explicit `shifts.manage`',
    'X-CSRF-Token',
    'shift_assignment.template.commit',
    'created assignment ids',
    'source=monthly_template',
    'monthly_template_id',
    'generated_assignment_id',
    'template_batch_id',
    'up.sql',
    'down.sql',
] as $required) {
    template_commit_gate_assert(
        str_contains($templateContract, $required),
        "Template commit contract missing {$required}."
    );
}

foreach ([
    'APPLY TEMPLATE',
    'preview-to-commit integrity',
    'conflict re-check',
    'bulk rollback',
] as $required) {
    template_commit_gate_assert(
        str_contains($writeContract, $required)
            && str_contains($frontendPlan, $required)
            && str_contains($testing, $required)
            && str_contains($roadmap, $required)
            && str_contains($rollback, $required),
        "Canonical Phase 30 docs missing {$required}."
    );
}

template_commit_gate_assert(
    str_contains($securityInventory, 'Future Shift Assignment v1 template commit/copy contracts'),
    'API security inventory no longer tracks future template commit/copy contracts.'
);

template_commit_gate_assert(
    str_contains($migration, '`shift_monthly_templates`')
        && str_contains($migration, '`shift_monthly_template_items`')
        && str_contains($migration, '`monthly_template_id`')
        && str_contains($migration, '`generated_assignment_id`')
        && !str_contains($migration, '`template_batch_id`'),
    'Schema evidence changed unexpectedly for template commit gate.'
);

template_commit_gate_assert(
    str_contains($service, 'applyMonthlyTemplate')
        && str_contains($service, "source' => 'monthly_template'")
        && str_contains($service, 'UPDATE shift_monthly_template_items SET generated_assignment_id=?')
        && str_contains($service, 'template_applied'),
    'Legacy template apply evidence changed unexpectedly.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/commit.php',
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/commit.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    template_commit_gate_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 30 unexpectedly created {$forbiddenRoute}."
    );
}

foreach ([
    'commitShiftTemplate',
    'copyShiftTemplate',
    '/api/v1/shift-assignment/templates/commit.php',
    '/api/v1/shift-assignment/templates/copy-preview.php',
    '/api/v1/shift-assignment/templates/copy-commit.php',
    'APPLY TEMPLATE',
    'Apply Template',
    'Commit Template',
    'Save Template',
    'Copy to month',
] as $forbiddenFrontendNeedle) {
    template_commit_gate_assert(
        !str_contains($frontendModule, $forbiddenFrontendNeedle)
            && !str_contains($apiClient, $forbiddenFrontendNeedle),
        "Phase 30 unexpectedly added frontend commit/copy behavior: {$forbiddenFrontendNeedle}."
    );
}

template_commit_gate_assert(
    str_contains($frontendModule, 'Preview only')
        && str_contains($frontendModule, 'this will not create or modify any assignments')
        && str_contains($apiClient, '/api/v1/shift-assignment/templates/preview.php'),
    'Template Preview UI no longer appears preview-only.'
);

echo "TRACS Shift Assignment template commit contract gate checks passed.\n";
