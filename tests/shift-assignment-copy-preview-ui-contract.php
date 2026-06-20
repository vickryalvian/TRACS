<?php
declare(strict_types=1);

function copy_preview_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function copy_preview_ui_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    copy_preview_ui_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$app = copy_preview_ui_source('frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx');
$toolbar = copy_preview_ui_source('frontend/src/modules/shift-assignment/components/ShiftToolbar.jsx');
$modal = copy_preview_ui_source('frontend/src/modules/shift-assignment/components/ShiftCopyPreviewModal.jsx');
$api = copy_preview_ui_source('frontend/src/modules/shift-assignment/api.js');
$utils = copy_preview_ui_source('frontend/src/modules/shift-assignment/utils/shiftCopyPreview.js');
$context = copy_preview_ui_source('api/v1/shift-assignment/context.php');
$publicContext = copy_preview_ui_source('public/api/v1/shift-assignment/context.php');
$previewPage = copy_preview_ui_source('public/shift-assignment-react-preview.php');

copy_preview_ui_assert(
    str_contains($app, 'ShiftCopyPreviewModal')
        && str_contains($app, 'allowed_actions?.copy_preview')
        && str_contains($toolbar, 'Copy Schedule Preview')
        && str_contains($toolbar, 'canCopyPreview'),
    'Copy Schedule Preview UI is not gated from the server-issued capability.'
);

copy_preview_ui_assert(
    str_contains($api, 'previewCopySchedule')
        && str_contains($api, '/api/v1/shift-assignment/templates/copy-preview.php')
        && str_contains($api, "method: 'POST'")
        && str_contains($api, 'csrfToken: csrf.token'),
    'Copy Schedule Preview API client does not call the approved route with CSRF.'
);

foreach ([
    'Preview only - this will not create or modify assignments.',
    'data-unsaved-ignore',
    'Source range',
    'Target range',
    'Preview items',
    'Warnings',
    'Conflicts',
    'Blocked items',
    'Generating copy preview...',
] as $requiredModalNeedle) {
    copy_preview_ui_assert(
        str_contains($modal, $requiredModalNeedle),
        "Copy Preview modal missing {$requiredModalNeedle}."
    );
}

foreach ([
    'initialCopyPreviewDraft',
    'validateCopyPreviewDraft',
    'Source and target ranges must be different.',
    'Source and target ranges must have the same length.',
    'Source range cannot exceed 35 days.',
    'copyPreviewErrorMessage',
] as $requiredUtility) {
    copy_preview_ui_assert(
        str_contains($utils, $requiredUtility),
        "Copy Preview utility missing {$requiredUtility}."
    );
}

copy_preview_ui_assert(
    str_contains($context, "'copy_preview'")
        && str_contains($publicContext, "'copy_preview'")
        && str_contains($publicContext, 'shifts.manage'),
    'Copy Preview capability is not exposed by the protected context.'
);

foreach ([
    'copy-commit.php',
    'APPLY COPY',
    'Apply Copy',
    'Commit Copy',
    'Paste Schedule',
    'Save Copied Schedule',
    'Generate Copied Schedule',
    'Rollback Template',
] as $forbiddenNeedle) {
    copy_preview_ui_assert(
        !str_contains($app, $forbiddenNeedle)
            && !str_contains($toolbar, $forbiddenNeedle)
            && !str_contains($modal, $forbiddenNeedle)
            && !str_contains($api, $forbiddenNeedle),
        "Copy Preview UI unexpectedly exposes copy commit/apply or rollback control {$forbiddenNeedle}."
    );
}

copy_preview_ui_assert(
    str_contains($previewPage, 'Copy Schedule Preview is non-mutating')
        && str_contains($previewPage, 'commit/apply behavior'),
    'Pilot banner does not describe the copy preview-only guarantee.'
);

echo "TRACS Shift Assignment copy preview UI contract checks passed.\n";
