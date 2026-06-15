<?php
declare(strict_types=1);

function pilot_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function pilot_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    pilot_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$preview = pilot_source('public/shift-assignment-react-preview.php');
$accessControl = pilot_source('core/access_control.php');
$header = pilot_source('public/includes/header.php');
$frontendApi = pilot_source('frontend/src/modules/shift-assignment/api.js');
$contextRoute = pilot_source('public/api/v1/shift-assignment/context.php');
$assignmentsRoute = pilot_source('public/api/v1/shift-assignment/assignments.php');

$authPosition = strpos($preview, "require_once __DIR__ . '/auth/auth_check.php'");
$viewPosition = strpos($preview, "tracs_require_page_permission(\$conn, 'shifts.view')");
$pilotPosition = strpos($preview, 'tracs_require_super_admin_page($conn)');

pilot_assert($authPosition !== false, 'Pilot preview no longer requires authentication.');
pilot_assert($viewPosition !== false, 'Pilot preview no longer requires shifts.view.');
pilot_assert($pilotPosition !== false, 'Pilot preview no longer requires exact Super Admin access.');
pilot_assert(
    $authPosition < $viewPosition && $viewPosition < $pilotPosition,
    'Pilot access checks must run after authentication and shifts.view.'
);
pilot_assert(
    str_contains($accessControl, "(string)(\$user['role_slug'] ?? '') === 'super_admin'"),
    'Super Admin pilot guard is no longer exact-role.'
);
pilot_assert(
    str_contains($accessControl, "'super_admin_only'"),
    'Super Admin pilot denials are no longer audit-characterized.'
);
pilot_assert(
    str_contains($preview, 'React Preview Pilot — Create/Edit/Delete and Template Preview actions are'),
    'Pilot controlled-create banner changed.'
);
pilot_assert(
    str_contains($preview, 'remains the production source of'),
    'Pilot banner no longer identifies the legacy source of truth.'
);
pilot_assert(
    str_contains($preview, "\$active_page = 'shift-assignment-react-preview'"),
    'Pilot preview route identity changed.'
);
pilot_assert(
    !str_contains($header, 'shift-assignment-react-preview.php'),
    'Pilot preview was exposed in production navigation.'
);

foreach ([
    '/api/v1/context.php',
    '/api/v1/shift-assignment/context.php',
    '/api/v1/shift-assignment/assignments.php',
] as $route) {
    pilot_assert(
        str_contains($frontendApi, $route),
        "Pilot React client no longer uses approved resource {$route}."
    );
}

pilot_assert(str_contains($contextRoute, "methods: ['GET']"), 'Context route is no longer GET-only.');
pilot_assert(
    str_contains($contextRoute, "permissions: ['shifts.view']"),
    'Context route no longer requires shifts.view.'
);
pilot_assert(
    str_contains($assignmentsRoute, "methods: ['GET', 'POST']")
        && str_contains($assignmentsRoute, "require_permission(\$conn, 'shifts.view'"),
    'Assignments route no longer preserves protected GET behavior.'
);

pilot_assert(
    substr_count($frontendApi, "method: 'POST'") === 2
        && substr_count($frontendApi, "method: 'PATCH'") === 1
        && substr_count($frontendApi, "method: 'DELETE'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"]PUT|\.(put)\s*\()/i', $frontendApi),
    'Pilot React client mutation allowlist changed.'
);
foreach (['PATCH', 'DELETE'] as $method) {
    pilot_assert(
        !str_contains($preview, $method),
        "Pilot preview unexpectedly contains {$method} behavior."
    );
}

echo "TRACS Shift Assignment internal pilot access checks passed.\n";
