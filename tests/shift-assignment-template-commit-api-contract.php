<?php
declare(strict_types=1);

function template_commit_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function template_commit_contract_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    template_commit_contract_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

function template_commit_contract_scan(string $directory): string
{
    $root = realpath(__DIR__ . '/../' . $directory);
    template_commit_contract_assert($root !== false, "Unable to scan {$directory}.");

    $contents = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (!in_array($ext, ['js', 'jsx'], true)) {
            continue;
        }
        $contents .= "\n" . (file_get_contents($file->getPathname()) ?: '');
    }
    return $contents;
}

$publicRoute = template_commit_contract_source('public/api/v1/shift-assignment/templates/commit.php');
$internal = template_commit_contract_source('api/v1/shift-assignment/templates/commit.php');
$preview = template_commit_contract_source('api/v1/shift-assignment/templates/preview.php');
$fixture = template_commit_contract_source('tests/fixtures/shift-assignment-api-request.php');
$frontend = template_commit_contract_scan('frontend/src/modules/shift-assignment');

foreach ([
    'bootstrap($conn, methods: [\'POST\'])',
    'require_exact_role($conn, \'super_admin\'',
    'require_explicit_role_permission',
    'shifts.manage',
    'template_commit_data',
    'template_commit_audit',
    'Template commit blocked by conflicts.',
    'Template applied.',
    'template_commit_cleanup_created',
] as $required) {
    template_commit_contract_assert(
        str_contains($publicRoute, $required),
        "Commit route missing {$required}."
    );
}

foreach ([
    'APPLY TEMPLATE',
    'Only block conflict policy is supported.',
    'Never trust client preview items blindly',
    'template_preview_data',
    'template_preview_input',
    'created_assignment_ids',
    'created_count',
    'rollback',
    'shift_assignment.template.commit',
    'shift_assignment.template.commit_attempt',
    'tracs_user_activity_logs',
    'source\' => \'monthly_template\'',
] as $required) {
    template_commit_contract_assert(
        str_contains($internal, $required),
        "Commit helper missing {$required}."
    );
}

template_commit_contract_assert(
    str_contains($preview, 'template_preview_data')
        && str_contains($preview, 'template_preview_input'),
    'Commit no longer reuses preview validation helpers.'
);

template_commit_contract_assert(
    str_contains($fixture, "'templates/commit'"),
    'API request fixture does not allow the commit route.'
);

foreach ([
    'commitShiftTemplate',
    'Commit Template',
] as $forbiddenFrontend) {
    template_commit_contract_assert(
        !str_contains($frontend, $forbiddenFrontend),
        "React commit UI/caller unexpectedly exists: {$forbiddenFrontend}."
    );
}

template_commit_contract_assert(
    str_contains($frontend, '/api/v1/shift-assignment/templates/commit.php')
        && str_contains($frontend, 'applyShiftTemplatePreview')
        && str_contains($frontend, 'APPLY TEMPLATE')
        && str_contains($frontend, 'Rollback targeting is based on the created assignment IDs'),
    'Controlled React template apply UI evidence is missing.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    template_commit_contract_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 32 unexpectedly created {$forbiddenRoute}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
] as $implementedCopyPreviewRoute) {
    template_commit_contract_assert(
        is_file(__DIR__ . '/../' . $implementedCopyPreviewRoute),
        "Phase 39 copy-preview route is missing: {$implementedCopyPreviewRoute}."
    );
}

echo "TRACS Shift Assignment template commit API source contract checks passed.\n";
