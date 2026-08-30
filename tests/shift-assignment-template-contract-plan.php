<?php
declare(strict_types=1);

function template_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function template_contract_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    template_contract_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

function template_contract_scan(string $directory): string
{
    $root = realpath(__DIR__ . '/../' . $directory);
    template_contract_assert($root !== false, "Unable to scan {$directory}.");

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

$templateContract = template_contract_source('docs/shift-assignment-template-api-contract.md');
$writeContract = template_contract_source('docs/shift-assignment-write-api-contract.md');
$apiContract = template_contract_source('docs/shift-assignment-api-contract.md');
$testing = template_contract_source('TESTING.md');
$securityInventory = template_contract_source('docs/API_SECURITY_INVENTORY.md');
$migration = template_contract_source('config/migrations/2026_06_08_shifting_assignment.sql');
$service = template_contract_source('modules/shifting-assignment/ShiftingAssignmentService.php');
$preview = template_contract_source('public/shift-assignment-react-preview.php');
$frontendModule = template_contract_scan('frontend/src/modules/shift-assignment');

foreach ([
    'POST /api/v1/shift-assignment/templates/preview.php',
    'POST /api/v1/shift-assignment/templates/commit.php',
    'POST /api/v1/shift-assignment/templates/copy-preview.php',
    'POST /api/v1/shift-assignment/templates/copy-commit.php',
    'non-mutating',
    'preview-before-commit',
    'X-CSRF-Token',
    'shifts.template.preview',
    'shifts.template.commit',
    'shifts.template.copy_preview',
    'shifts.template.copy_commit',
    'exact `super_admin` plus explicit `shifts.manage`',
    'up.sql',
    'down.sql',
    'hard delete',
    'rollback',
    'Shift 3 `16:00-24:00`',
    'weekly',
    'jumpshift',
    'holiday',
    'overtime',
] as $required) {
    template_contract_assert(
        str_contains($templateContract, $required),
        "Template contract missing {$required}."
    );
}

template_contract_assert(
    str_contains($templateContract, 'must not insert, update, delete, archive, apply')
        && str_contains($templateContract, 'mark a draft')
        && str_contains($service, 'UPDATE shift_monthly_templates SET status=')
        && str_contains($service, 'previewed'),
    'Legacy preview side effect or non-mutating future-preview requirement is missing.'
);

foreach ([
    'shift_assignments.source',
    'shift_assignments.monthly_template_id',
    'shift_monthly_templates',
    'shift_monthly_template_items',
    'generated_assignment_id',
    'assignment_audit_logs.action',
] as $schemaFinding) {
    template_contract_assert(
        str_contains($templateContract, $schemaFinding),
        "Schema investigation missing {$schemaFinding}."
    );
}

template_contract_assert(
    str_contains($migration, "`source` ENUM('manual','monthly_template','copy','replacement')")
        && str_contains($migration, '`monthly_template_id`')
        && str_contains($migration, '`shift_monthly_templates`')
        && str_contains($migration, '`generated_assignment_id`')
        && str_contains($migration, "'template_applied'"),
    'Migration evidence for template ownership or audit enum changed.'
);

foreach ([
    'templates/preview.php',
    'templates/commit.php',
    'templates/copy-preview.php',
    'templates/copy-commit.php',
] as $routeName) {
    template_contract_assert(
        str_contains($writeContract, $routeName)
            && str_contains($apiContract, $routeName)
            && str_contains($testing, $routeName)
            && str_contains($securityInventory, $routeName),
        "Canonical docs do not all reference {$routeName}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/preview.php',
    'api/v1/shift-assignment/templates/preview.php',
] as $approvedPreviewRoute) {
    template_contract_assert(
        is_file(__DIR__ . '/../' . $approvedPreviewRoute),
        "Phase 28 approved preview route is missing: {$approvedPreviewRoute}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $plannedRoute) {
    template_contract_assert(
        !is_file(__DIR__ . '/../' . $plannedRoute),
        "Planning unexpectedly created {$plannedRoute}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
] as $implementedCopyPreviewRoute) {
    template_contract_assert(
        is_file(__DIR__ . '/../' . $implementedCopyPreviewRoute),
        "Phase 39 copy-preview route is missing: {$implementedCopyPreviewRoute}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/commit.php',
    'api/v1/shift-assignment/templates/commit.php',
] as $implementedRoute) {
    template_contract_assert(
        is_file(__DIR__ . '/../' . $implementedRoute),
        "Phase 32 commit route is missing: {$implementedRoute}."
    );
}

foreach ([
    'Template Generator',
    'copy-commit.php',
] as $forbiddenFrontendNeedle) {
    template_contract_assert(
        !str_contains($frontendModule, $forbiddenFrontendNeedle),
        "Phase 27 unexpectedly added frontend template UI/caller: {$forbiddenFrontendNeedle}."
    );
}

template_contract_assert(
    str_contains($frontendModule, 'Copy Schedule Preview')
        && str_contains($frontendModule, 'copy-preview.php'),
    'Phase 40 Copy Schedule Preview UI/caller is missing.'
);

template_contract_assert(
    str_contains($preview, 'Create/Edit/Delete, Template Preview/Apply, and')
        && str_contains($preview, 'Copy Schedule Preview actions')
        && str_contains($preview, 'Template Preview is non-mutating')
        && str_contains($preview, 'controlled backend commit')
        && str_contains($preview, 'Copy Schedule Preview is non-mutating')
        && str_contains($preview, 'commit/apply behavior'),
    'React preview pilot banner changed unexpectedly.'
);

echo "TRACS Shift Assignment template API contract planning checks passed.\n";
