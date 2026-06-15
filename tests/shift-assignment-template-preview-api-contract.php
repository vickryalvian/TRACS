<?php
declare(strict_types=1);

function template_preview_contract_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function template_preview_contract_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    template_preview_contract_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$publicRoute = template_preview_contract_source('public/api/v1/shift-assignment/templates/preview.php');
$helper = template_preview_contract_source('api/v1/shift-assignment/templates/preview.php');
$harness = template_preview_contract_source('tests/fixtures/shift-assignment-api-request.php');
$docs = template_preview_contract_source('docs/shift-assignment-template-api-contract.md');
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
    'get_request_json',
    'template_preview_data',
    "'Template preview generated.'",
] as $required) {
    template_preview_contract_assert(
        str_contains($publicRoute, $required),
        "Preview route is missing {$required}."
    );
}

foreach ([
    'saveAssignment(',
    'saveMonthlyTemplate',
    'previewMonthlyTemplate',
    'applyMonthlyTemplate',
    'duplicateMonthlyTemplate',
    'write_audit_log',
    'INSERT INTO',
    'UPDATE ',
    'DELETE FROM',
] as $forbidden) {
    template_preview_contract_assert(
        !str_contains($publicRoute, $forbidden),
        "Preview route contains forbidden mutating behavior: {$forbidden}"
    );
}

foreach ([
    'template_preview_input',
    'template_preview_assignment_rows',
    'template_preview_conflicts',
    'template_preview_warnings',
    'weekly_rotation',
    'Template preview supports a maximum range of 35 days.',
    '24:00',
    'template_preview_blocked_items',
] as $requiredHelper) {
    template_preview_contract_assert(
        str_contains($helper, $requiredHelper),
        "Preview helper missing {$requiredHelper}."
    );
}

template_preview_contract_assert(
    str_contains($harness, "'templates/preview'")
        && str_contains($harness, "/api/v1/shift-assignment/' . \$resource . '.php"),
    'Disposable request harness does not allow the template preview resource.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/commit.php',
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/commit.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    template_preview_contract_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Unexpected template/copy route exists: {$forbiddenRoute}"
    );
}

foreach ([
    'Template Generator',
    'Copy Schedule',
    'copy-preview.php',
    'copy-commit.php',
    'templates/commit.php',
] as $forbiddenFrontend) {
    template_preview_contract_assert(
        !str_contains($frontend, $forbiddenFrontend),
        "Unexpected React template UI/caller exists: {$forbiddenFrontend}"
    );
}

template_preview_contract_assert(
    str_contains($docs, 'POST /api/v1/shift-assignment/templates/preview.php')
        && str_contains($docs, 'non-mutating')
        && str_contains($docs, 'must not insert, update, delete, archive, apply'),
    'Template preview documentation no longer describes the no-mutation contract.'
);

echo "TRACS Shift Assignment template preview API source contract checks passed.\n";
