<?php
declare(strict_types=1);

function candidate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function candidate_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    candidate_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$app = candidate_source('frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx');
$filters = candidate_source('frontend/src/modules/shift-assignment/components/ShiftFilterBar.jsx');
$dates = candidate_source('frontend/src/modules/shift-assignment/utils/shiftDates.js');
$assignmentsHook = candidate_source('frontend/src/modules/shift-assignment/hooks/useShiftAssignments.js');
$preview = candidate_source('public/shift-assignment-react-preview.php');
$header = candidate_source('public/includes/header.php');
$frontendApi = candidate_source('frontend/src/modules/shift-assignment/api.js');

candidate_assert(
    str_contains($preview, "tracs_require_page_permission(\$conn, 'shifts.view')")
        && str_contains($preview, 'tracs_require_super_admin_page($conn)'),
    'Production candidate pilot access is no longer Super Admin plus shifts.view.'
);
candidate_assert(
    !str_contains($header, 'shift-assignment-react-preview.php'),
    'Production candidate was added to global navigation.'
);
candidate_assert(
    str_contains($app, '<ShiftOperationalNotices')
        && str_contains($app, '<ShiftAssignmentTable')
        && str_contains($app, '<ShiftAssignmentBoard'),
    'Production candidate read-only responsive sections changed.'
);
candidate_assert(
    str_contains($filters, 'placeholder="dd-mm-yyyy"')
        && str_contains($filters, 'type="submit"')
        && !str_contains($filters, 'label="Role"'),
    'Production candidate filter/date behavior changed.'
);
candidate_assert(
    str_contains($dates, 'export function filterQuery')
        && str_contains($dates, 'export function isoDateInput'),
    'Display-to-ISO date boundary changed.'
);
candidate_assert(
    str_contains($assignmentsHook, 'new AbortController()')
        && str_contains($assignmentsHook, 'controller.abort()'),
    'Stale Shift Assignment request cancellation changed.'
);
candidate_assert(
    substr_count($frontendApi, "method: 'POST'") === 3
        && substr_count($frontendApi, "method: 'PATCH'") === 1
        && substr_count($frontendApi, "method: 'DELETE'") === 1
        && !preg_match('/\b(method\s*:\s*[\'"]PUT|\.(put)\s*\()/i', $frontendApi),
    'Production candidate frontend API mutation allowlist changed.'
);
foreach ([
    '/api/v1/context.php',
    '/api/v1/shift-assignment/context.php',
    '/api/v1/shift-assignment/assignments.php',
] as $route) {
    candidate_assert(str_contains($frontendApi, $route), "Approved GET route missing: {$route}");
}

echo "TRACS Shift Assignment read-only production candidate checks passed.\n";
