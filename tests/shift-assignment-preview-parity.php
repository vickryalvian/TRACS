<?php
declare(strict_types=1);

function parity_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function parity_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    parity_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$legacyPage = parity_source('public/shifting-assignment.php');
$previewPage = parity_source('public/shift-assignment-react-preview.php');
$header = parity_source('public/includes/header.php');
$frontendApi = parity_source('frontend/src/modules/shift-assignment/api.js');
$contextRoute = parity_source('public/api/v1/shift-assignment/context.php');
$assignmentsRoute = parity_source('public/api/v1/shift-assignment/assignments.php');
$service = parity_source('modules/shifting-assignment/ShiftingAssignmentService.php');
$permissions = parity_source('core/user_management.php');
$permissionMigration = parity_source('config/migrations/2026_06_08_shifting_assignment.sql');

foreach ([
    'legacy page' => $legacyPage,
    'React preview' => $previewPage,
] as $label => $source) {
    parity_assert(
        str_contains($source, "require_once __DIR__ . '/auth/auth_check.php'"),
        "{$label} no longer uses the normal authenticated shell."
    );
    parity_assert(
        str_contains($source, "tracs_require_page_permission(\$conn, 'shifts.view')"),
        "{$label} no longer requires shifts.view."
    );
}

parity_assert(
    str_contains($previewPage, "\$active_page = 'shift-assignment-react-preview'"),
    'Preview route identity changed and may load legacy page assets.'
);
parity_assert(
    str_contains($previewPage, 'id="tracs-shift-assignment-root"'),
    'Preview React root changed.'
);
parity_assert(
    str_contains($header, "'href' => 'shifting-assignment.php'"),
    'Production navigation no longer points to the legacy page.'
);
parity_assert(
    !str_contains($header, 'shift-assignment-react-preview.php'),
    'React preview was added to production navigation.'
);

foreach ([
    '/api/v1/context.php',
    '/api/v1/shift-assignment/context.php',
    '/api/v1/shift-assignment/assignments.php',
] as $route) {
    parity_assert(
        str_contains($frontendApi, "apiClient.request('{$route}')")
            || str_contains($frontendApi, "apiClient.request(\n    `{$route}"),
        "React preview no longer uses the approved GET resource {$route}."
    );
}
parity_assert(
    substr_count($frontendApi, "method: 'POST'") === 3
        && substr_count($frontendApi, "method: 'PATCH'") === 1
        && substr_count($frontendApi, "method: 'DELETE'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"]PUT|\.(put)\s*\()/i', $frontendApi),
    'React Shift Assignment API client mutation allowlist changed.'
);

parity_assert(
    str_contains($contextRoute, "methods: ['GET']")
        && str_contains($contextRoute, "permissions: ['shifts.view']"),
    'Context route no longer preserves protected GET behavior.'
);
parity_assert(
    str_contains($assignmentsRoute, "methods: ['GET', 'POST']")
        && str_contains($assignmentsRoute, "require_permission(\$conn, 'shifts.view'"),
    'Assignments route no longer preserves protected GET behavior.'
);

foreach ([
    "'super_admin' => \$all",
    "'admin' => array_values(array_diff(\$all",
    "'supervisor' => array_values(array_unique(array_merge(\$profile",
    "'intern' => array_values(array_unique(array_merge(\$profile",
    "'shifts.view'",
] as $characterization) {
    parity_assert(
        str_contains($permissions, $characterization),
        "Default role permission characterization changed: {$characterization}"
    );
}

foreach ([
    "r.slug IN ('super_admin','admin')",
    "r.slug = 'supervisor'",
    "r.slug IN ('agent','intern','viewer')",
    "p.permission_key = 'shifts.view'",
] as $grantRule) {
    parity_assert(
        str_contains($permissionMigration, $grantRule),
        "Shift Assignment migration grant characterization changed: {$grantRule}"
    );
}

foreach ([
    "in_array(\$role, ['agent', 'intern'], true)",
    "\$where[] = \"{\$alias}.user_id=?\"",
    "\$role === 'supervisor'",
    "\$where[] = \"{\$alias}.division_id=?\"",
] as $scopeRule) {
    parity_assert(
        str_contains($service, $scopeRule),
        "Shift Assignment service scope characterization changed: {$scopeRule}"
    );
}

foreach (['POST', 'PATCH', 'DELETE'] as $method) {
    parity_assert(
        !str_contains($previewPage, $method),
        "React preview unexpectedly contains {$method} behavior."
    );
}

echo "TRACS Shift Assignment preview parity checks passed.\n";
