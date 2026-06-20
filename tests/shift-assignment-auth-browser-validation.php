<?php
declare(strict_types=1);

function auth_browser_validation_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function auth_browser_validation_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    auth_browser_validation_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$sessionHarness = auth_browser_validation_source('public/__test/shift-assignment-auth-session.php');
$browserScript = auth_browser_validation_source('frontend/tests/shift-template-apply-browser.mjs');
$package = auth_browser_validation_source('frontend/package.json');
$modal = auth_browser_validation_source('frontend/src/modules/shift-assignment/components/ShiftTemplatePreviewModal.jsx');
$testing = auth_browser_validation_source('TESTING.md');
$roadmap = auth_browser_validation_source('REFACTOR_ROADMAP.md');
$rollback = auth_browser_validation_source('ROLLBACK.md');
$securityInventory = auth_browser_validation_source('docs/API_SECURITY_INVENTORY.md');

foreach ([
    'TRACS_ENV',
    'TRACS_ALLOW_MUTATION_TESTS',
    'TRACS_TEST_DB_NAME',
    'test|local|dev|disposable|staging',
    'tracs_sync_session_user',
    'tracs_rotate_csrf_token',
    'tracs_auth_state',
] as $requiredHarnessNeedle) {
    auth_browser_validation_assert(
        str_contains($sessionHarness, $requiredHarnessNeedle),
        "Test auth session harness missing {$requiredHarnessNeedle}."
    );
}

foreach ([
    'playwright',
    '/__test/shift-assignment-auth-session.php',
    '/shift-assignment-react-preview.php',
    'APPLY TEMPLATE',
    'apply template',
    'Apply Template',
    'APPLY  TEMPLATE',
    'APPLY-TEMPLATE',
    'copy-preview.php',
    'copy-commit.php',
    'Rollback Template',
    'shift_assignment.template.commit',
    'DELETE FROM shift_assignments WHERE id IN',
] as $requiredBrowserNeedle) {
    auth_browser_validation_assert(
        str_contains($browserScript, $requiredBrowserNeedle),
        "Authenticated browser validation script missing {$requiredBrowserNeedle}."
    );
}

auth_browser_validation_assert(
    str_contains($package, 'test:e2e:shift-template-apply')
        && str_contains($package, 'build:preview')
        && str_contains($package, 'playwright'),
    'Frontend package is missing the authenticated browser validation command or dev-only Playwright dependency.'
);

auth_browser_validation_assert(
    str_contains($modal, 'data-unsaved-ignore'),
    'Template preview modal must opt out of the legacy unsaved-change overlay because the React modal owns its dirty-form guard.'
);

foreach ([
    'Phase 37 Authenticated Browser Validation Gate',
    'sandboxPolicy',
    'test:e2e:shift-template-apply',
    'tracs_phase37_test',
    'copy-preview may proceed',
] as $requiredDocNeedle) {
    auth_browser_validation_assert(
        str_contains($testing, $requiredDocNeedle)
            && str_contains($roadmap, $requiredDocNeedle),
        "Phase 37 docs missing {$requiredDocNeedle}."
    );
}

auth_browser_validation_assert(
    str_contains($rollback, 'Phase 37 Authenticated Browser Validation Gate Rollback')
        && str_contains($securityInventory, 'Phase 37 live Chrome validation passed'),
    'Rollback or security inventory is missing Phase 37 browser validation evidence.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    auth_browser_validation_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 37 unexpectedly created {$forbiddenRoute}."
    );
}

echo "TRACS Shift Assignment authenticated browser validation gate checks passed.\n";
