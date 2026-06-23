<?php
declare(strict_types=1);

function copy_commit_gate_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function copy_commit_gate_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    copy_commit_gate_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

function copy_commit_gate_scan(string $directory, array $extensions): string
{
    $root = realpath(__DIR__ . '/../' . $directory);
    if ($root === false) {
        return '';
    }

    $contents = '';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
            continue;
        }
        $contents .= "\n" . (file_get_contents($file->getPathname()) ?: '');
    }

    return $contents;
}

$templateContract = copy_commit_gate_source('docs/shift-assignment-template-api-contract.md');
$writeContract = copy_commit_gate_source('docs/shift-assignment-write-api-contract.md');
$frontendPlan = copy_commit_gate_source('docs/frontend-migration-plan.md');
$parity = copy_commit_gate_source('docs/shift-assignment-preview-parity.md');
$reactArchitecture = copy_commit_gate_source('docs/react-tailwind-architecture.md');
$securityInventory = copy_commit_gate_source('docs/API_SECURITY_INVENTORY.md');
$testing = copy_commit_gate_source('TESTING.md');
$roadmap = copy_commit_gate_source('REFACTOR_ROADMAP.md');
$rollback = copy_commit_gate_source('ROLLBACK.md');
$frontendModule = copy_commit_gate_scan('frontend/src/modules/shift-assignment', ['js', 'jsx', 'mjs', 'css']);
$frontendApi = copy_commit_gate_source('frontend/src/modules/shift-assignment/api.js');
$apiContext = copy_commit_gate_source('api/v1/shift-assignment/context.php');
$publicContext = copy_commit_gate_source('public/api/v1/shift-assignment/context.php');
$apiSource = copy_commit_gate_scan('api/v1/shift-assignment', ['php']);
$publicApiSource = copy_commit_gate_scan('public/api/v1/shift-assignment', ['php']);

foreach ([
    'Phase 42 Copy Commit Contract Gate',
    'POST /api/v1/shift-assignment/templates/copy-commit.php',
    'APPLY COPY',
    'case sensitive',
    'whitespace sensitive',
    'no fuzzy match',
    'never trust browser preview results',
    'never trust preview counts',
    'never trust preview IDs',
    'conflict_policy=block',
    'return `409`',
    'atomic',
    'all-or-nothing',
    'no `template_batch_id` or `copy_batch_id`',
    'created_assignment_ids',
    'actor user id',
    'rollback targeting',
    'fail closed',
] as $requiredContractNeedle) {
    copy_commit_gate_assert(
        str_contains($templateContract, $requiredContractNeedle),
        "Copy commit template contract missing {$requiredContractNeedle}."
    );
}

foreach ([
    'Phase 42 Copy Commit Contract Gate',
    'APPLY COPY',
    'server-side preview recomputation',
    'final conflict re-check',
    'atomic all-or-nothing',
    'created assignment IDs',
    'rollback targeting',
] as $requiredDocNeedle) {
    copy_commit_gate_assert(
        str_contains($writeContract, $requiredDocNeedle)
            && str_contains($frontendPlan, $requiredDocNeedle)
            && str_contains($testing, $requiredDocNeedle)
            && str_contains($roadmap, $requiredDocNeedle),
        "Canonical Phase 42 docs missing {$requiredDocNeedle}."
    );
}

copy_commit_gate_assert(
    str_contains($parity, 'Phase 42 copy-commit contract gate')
        && str_contains($reactArchitecture, 'Phase 42 Copy Commit UI Gate')
        && str_contains($securityInventory, 'Phase 42 contract gate requires exact `APPLY COPY`')
        && str_contains($rollback, 'Phase 42 Copy Commit Contract Gate Rollback'),
    'Phase 42 parity, architecture, security, or rollback docs are incomplete.'
);

foreach ([
    'public/api/v1/shift-assignment/templates/copy-commit.php',
    'api/v1/shift-assignment/templates/copy-commit.php',
] as $forbiddenRoute) {
    copy_commit_gate_assert(
        !is_file(__DIR__ . '/../' . $forbiddenRoute),
        "Phase 42 unexpectedly created {$forbiddenRoute}."
    );
}

foreach ([
    'public/api/v1/shift-assignment/templates/copy-preview.php',
    'api/v1/shift-assignment/templates/copy-preview.php',
] as $requiredPreviewRoute) {
    copy_commit_gate_assert(
        is_file(__DIR__ . '/../' . $requiredPreviewRoute),
        "Copy preview route unexpectedly missing: {$requiredPreviewRoute}."
    );
}

foreach ([
    '/api/v1/shift-assignment/templates/copy-commit.php',
    'applyCopy',
    'commitCopy',
    'copyCommit',
    'APPLY COPY',
    'Apply Copy',
    'Commit Copy',
    'Paste Schedule',
    'Save Copied Schedule',
    'Generate Copied Schedule',
    'Rollback Template',
    'Undo Copy',
] as $forbiddenFrontendNeedle) {
    copy_commit_gate_assert(
        !str_contains($frontendModule, $forbiddenFrontendNeedle)
            && !str_contains($frontendApi, $forbiddenFrontendNeedle),
        "Phase 42 unexpectedly added React copy commit/apply behavior: {$forbiddenFrontendNeedle}."
    );
}

foreach ([
    'copy_commit',
    'apply_copy',
    'paste_schedule',
    'copyCommit',
    'applyCopy',
    'shifts.template.copy_commit',
] as $forbiddenCapabilityNeedle) {
    copy_commit_gate_assert(
        !str_contains($apiContext, $forbiddenCapabilityNeedle)
            && !str_contains($publicContext, $forbiddenCapabilityNeedle),
        "Phase 42 unexpectedly exposed copy commit capability: {$forbiddenCapabilityNeedle}."
    );
}

foreach ([
    'copy_commit_data',
    'copy_commit_input',
    'copy_commit_assignments',
    'apply_copy',
    'shift_assignment.copy.commit',
] as $forbiddenBackendNeedle) {
    copy_commit_gate_assert(
        !str_contains($apiSource, $forbiddenBackendNeedle)
            && !str_contains($publicApiSource, $forbiddenBackendNeedle),
        "Phase 42 unexpectedly added backend copy commit behavior: {$forbiddenBackendNeedle}."
    );
}

copy_commit_gate_assert(
    str_contains($frontendModule, 'Copy Schedule Preview')
        && str_contains($frontendApi, '/api/v1/shift-assignment/templates/copy-preview.php'),
    'Existing Copy Schedule Preview UI/caller no longer appears intact.'
);

echo "TRACS Shift Assignment copy commit contract gate checks passed.\n";
