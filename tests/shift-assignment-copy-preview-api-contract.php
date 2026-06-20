<?php
declare(strict_types=1);

function copy_preview_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function copy_preview_contract_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    copy_preview_contract_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$publicRoute = copy_preview_contract_source('public/api/v1/shift-assignment/templates/copy-preview.php');
$helper = copy_preview_contract_source('api/v1/shift-assignment/templates/copy-preview.php');
$harness = copy_preview_contract_source('tests/fixtures/shift-assignment-api-request.php');
$docs = copy_preview_contract_source('docs/shift-assignment-template-api-contract.md');
$frontend = '';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        __DIR__ . '/../frontend/src/modules/shift-assignment',
        FilesystemIterator::SKIP_DOTS
    )
);
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile()) {
        $frontend .= "\n" . (file_get_contents($file->getPathname()) ?: '');
    }
}

foreach ([
    "bootstrap(\$conn, methods: ['POST'])",
    "require_exact_role(\$conn, 'super_admin'",
    'require_explicit_role_permission',
    "'shifts.manage'",
    'shifts.template.copy_preview',
    'get_request_json',
    'copy_preview_data',
    "'Copy schedule preview generated.'",
] as $required) {
    copy_preview_contract_assert(
        str_contains($publicRoute, $required),
        "Copy-preview route is missing {$required}."
    );
}

foreach ([
    'saveAssignment(',
    'template_commit_data',
    'template_commit_audit',
    'applyMonthlyTemplate',
    'INSERT INTO',
    'UPDATE ',
    'DELETE FROM',
] as $forbidden) {
    copy_preview_contract_assert(
        !str_contains($publicRoute, $forbidden)
            && !str_contains($helper, $forbidden),
        "Copy-preview source contains forbidden mutating behavior: {$forbidden}"
    );
}

foreach ([
    'copy_preview_input',
    'copy_preview_data',
    'copy_preview_assignment_rows',
    'copy_preview_filter_source_assignments',
    'copy_preview_holiday_warnings',
    'copy_preview_note_warnings',
    'source_start_date',
    'target_start_date',
    'Source and target ranges must have the same length.',
    'Source and target ranges must be different.',
    'Copy preview supports a maximum range of 35 days.',
    '24:00',
    'source_assignment_id',
    'copy_preview',
] as $requiredHelper) {
    copy_preview_contract_assert(
        str_contains($helper, $requiredHelper),
        "Copy-preview helper missing {$requiredHelper}."
    );
}

copy_preview_contract_assert(
    str_contains($harness, "'templates/copy-preview'"),
    'Disposable request harness does not allow the copy-preview resource.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
] as $implementedRoute) {
    copy_preview_contract_assert(
        is_file(__DIR__ . '/../' . $implementedRoute),
        "Copy-preview route is missing: {$implementedRoute}"
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    copy_preview_contract_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 39 unexpectedly created {$forbiddenRoute}."
    );
}

foreach ([
    'Copy Schedule',
    'Copy Schedule Preview',
    'APPLY COPY',
    'copy-preview.php',
    'copy-commit.php',
] as $forbiddenFrontend) {
    copy_preview_contract_assert(
        !str_contains($frontend, $forbiddenFrontend),
        "Unexpected React copy UI/caller exists: {$forbiddenFrontend}"
    );
}

copy_preview_contract_assert(
    str_contains($docs, 'POST /api/v1/shift-assignment/templates/copy-preview.php')
        && str_contains($docs, 'Copy Schedule Preview API')
        && str_contains($docs, 'Non-mutating preview guarantee'),
    'Copy-preview documentation is incomplete.'
);

echo "TRACS Shift Assignment copy-preview API source contract checks passed.\n";
