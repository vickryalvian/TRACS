<?php
declare(strict_types=1);

function write_plan_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function write_plan_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    write_plan_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$contract = write_plan_source('docs/shift-assignment-write-api-contract.md');
$frontendApi = write_plan_source('frontend/src/modules/shift-assignment/api.js');
$preview = write_plan_source('public/shift-assignment-react-preview.php');
$header = write_plan_source('public/includes/header.php');
$readRoute = write_plan_source('public/api/v1/shift-assignment/assignments.php');

foreach ([
    'POST /api/v1/shift-assignment/assignments.php',
    'PATCH /api/v1/shift-assignment/assignments/{id}.php',
    'DELETE /api/v1/shift-assignment/assignments/{id}.php',
    'X-CSRF-Token',
    'shifts.create',
    'shifts.update',
    'shifts.delete',
    'shifts.template.generate',
    'shifts.template.copy',
    'shifts.overtime.create',
    'shifts.export',
    'shifts.audit.view',
    'up.sql',
    'down.sql',
] as $requiredContract) {
    write_plan_assert(
        str_contains($contract, $requiredContract),
        "Write contract is missing {$requiredContract}."
    );
}

write_plan_assert(
    str_contains($contract, 'Blocked pending delete/soft-delete decision'),
    'Assignment deletion is no longer explicitly blocked.'
);
write_plan_assert(
    str_contains($contract, 'No write test may run against production data.'),
    'Disposable-data safety rule is missing.'
);
write_plan_assert(
    str_contains($readRoute, "methods: ['GET', 'POST']"),
    'The v1 assignments route no longer exposes only the approved GET and create POST methods.'
);
write_plan_assert(
    substr_count($frontendApi, "method: 'POST'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"](PUT|PATCH|DELETE)|\.(put|patch|delete)\s*\()/i', $frontendApi),
    'The React preview API client must contain only the controlled create POST.'
);
write_plan_assert(
    str_contains($preview, 'Create action is enabled only for Super Admin')
        && str_contains($preview, "tracs_require_page_permission(\$conn, 'shifts.view')")
        && str_contains($preview, 'tracs_require_super_admin_page($conn)'),
    'The controlled-create pilot banner or access restrictions changed.'
);
write_plan_assert(
    !str_contains($header, 'shift-assignment-react-preview.php'),
    'The preview was added to global navigation.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/generate.php',
    'public/api/v1/shift-assignment/templates/copy.php',
    'public/api/v1/shift-assignment/overtime.php',
] as $plannedRoute) {
    write_plan_assert(
        !is_file(__DIR__ . '/../' . $plannedRoute),
        "Planning phase unexpectedly created {$plannedRoute}."
    );
}

write_plan_assert(
    !is_dir(__DIR__ . '/../public/api/v1/shift-assignment/assignments'),
    'Planning phase unexpectedly created assignment item write routes.'
);

$routeAllowlists = [
    'public/api/v1/shift-assignment' => ['assignments.php', 'context.php'],
    'api/v1/shift-assignment' => ['assignments.php', 'context.php'],
];
foreach ($routeAllowlists as $directory => $expectedFiles) {
    $files = array_values(array_filter(
        scandir(__DIR__ . '/../' . $directory) ?: [],
        static fn(string $file): bool => str_ends_with($file, '.php')
    ));
    sort($files);
    write_plan_assert(
        $files === $expectedFiles,
        "Planning phase changed the approved PHP route allowlist under {$directory}."
    );
}

echo "TRACS Shift Assignment write API contract planning checks passed.\n";
