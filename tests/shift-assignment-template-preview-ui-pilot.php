<?php
declare(strict_types=1);

function template_preview_ui_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function template_preview_ui_source(string $path): string
{
    $source = file_get_contents(__DIR__ . '/../' . $path);
    template_preview_ui_assert($source !== false, "Unable to read {$path}.");
    return $source;
}

$app = template_preview_ui_source('frontend/src/modules/shift-assignment/ShiftAssignmentApp.jsx');
$toolbar = template_preview_ui_source('frontend/src/modules/shift-assignment/components/ShiftToolbar.jsx');
$modal = template_preview_ui_source('frontend/src/modules/shift-assignment/components/ShiftTemplatePreviewModal.jsx');
$api = template_preview_ui_source('frontend/src/modules/shift-assignment/api.js');
$utils = template_preview_ui_source('frontend/src/modules/shift-assignment/utils/shiftTemplatePreview.js');
$context = template_preview_ui_source('api/v1/shift-assignment/context.php');
$publicContext = template_preview_ui_source('public/api/v1/shift-assignment/context.php');
$header = template_preview_ui_source('public/includes/header.php');
$previewPage = template_preview_ui_source('public/shift-assignment-react-preview.php');

template_preview_ui_assert(
    str_contains($app, 'ShiftTemplatePreviewModal')
        && str_contains($app, 'allowed_actions?.preview_template')
        && str_contains($toolbar, 'Preview Template')
        && str_contains($toolbar, 'canTemplatePreview'),
    'Template Preview UI is not gated from the server-issued capability.'
);

template_preview_ui_assert(
    str_contains($api, 'previewShiftTemplate')
        && str_contains($api, '/api/v1/shift-assignment/templates/preview.php')
        && str_contains($api, "method: 'POST'")
        && str_contains($api, 'csrfToken: csrf.token'),
    'Template Preview API client does not call the approved preview route with CSRF.'
);

template_preview_ui_assert(
    str_contains($modal, 'Preview only')
        && str_contains($modal, 'No assignments were created or modified.')
        && str_contains($modal, 'Preview stays non-mutating')
        && str_contains($modal, 'Generate Preview')
        && str_contains($modal, 'Preview items')
        && str_contains($modal, 'Warnings')
        && str_contains($modal, 'Conflicts')
        && str_contains($modal, 'Blocked items'),
    'Template Preview modal is missing preview-only copy or result sections.'
);

foreach ([
    'Save template',
    'Copy to month',
    'copy-commit.php',
    'copy-preview.php',
] as $forbidden) {
    template_preview_ui_assert(
        !str_contains($modal, $forbidden)
            && !str_contains($api, $forbidden),
        "Template Preview UI unexpectedly exposes copy/paste control {$forbidden}."
    );
}

foreach ([
    'initialTemplatePreviewDraft',
    'validateTemplatePreviewDraft',
    'shift_3',
    '24:00',
    'Preview range cannot exceed 35 days.',
    'templatePreviewErrorMessage',
] as $requiredUtility) {
    template_preview_ui_assert(
        str_contains($utils, $requiredUtility),
        "Template Preview utility missing {$requiredUtility}."
    );
}

template_preview_ui_assert(
    str_contains($context, 'preview_template')
        && str_contains($publicContext, "'template_preview'")
        && str_contains($publicContext, 'shifts.manage'),
    'Template Preview capability is not exposed by the protected context.'
);

template_preview_ui_assert(
    !str_contains($header, 'shift-assignment-react-preview.php')
        && str_contains($previewPage, 'Template Preview is non-mutating')
        && str_contains($previewPage, 'controlled backend commit')
        && str_contains($previewPage, 'no copy, overtime'),
    'Preview remains direct URL only and explicitly non-copy/commit.'
);

echo "TRACS Shift Assignment template preview UI pilot checks passed.\n";
