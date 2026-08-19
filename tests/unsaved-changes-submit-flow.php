<?php
declare(strict_types=1);

function submit_flow_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$guard = file_get_contents(__DIR__ . '/../public/assets/unsaved-changes-guard.js');
$abuse = file_get_contents(__DIR__ . '/../public/assets/abuse-reports.js');
$abusePage = file_get_contents(__DIR__ . '/../public/abuse-reports.php');

submit_flow_assert($guard !== false && $abuse !== false && $abusePage !== false, 'Unable to read dirty-state sources.');
submit_flow_assert(
    str_contains($guard, 'function handleSubmit(event)')
        && str_contains($guard, 'if (event.defaultPrevented) return;')
        && str_contains($guard, "document.addEventListener('submit', handleSubmit);")
        && !str_contains($guard, "document.addEventListener('submit', handleSubmit, true);"),
    'The dirty-state guard must let JavaScript save handlers prevent navigation before it intervenes.'
);
submit_flow_assert(
    str_contains($abuse, 'function closeDetail()')
        && str_contains($abuse, 'requestModalClose(modal, close)')
        && str_contains($abuse, 'if (state.saveInFlight) return null;')
        && str_contains($abuse, 'if (!payload.id) closeDetail();')
        && !str_contains($abuse, 'options.refill')
        && str_contains($abuse, 'refreshDetailSideData(data?.report)')
        && str_contains($abuse, "markDetailSaved($('#abuseNoteForm'))")
        && str_contains($abuse, "markDetailSaved($('#abuseEvidenceForm'))")
        && !str_contains($abuse, 'saveAndAddAnother')
        && !str_contains($abusePage, 'Save and Add Another')
        && str_contains($abusePage, 'data-unsaved-no-auto-save')
        && str_contains($guard, "modal.matches('[data-unsaved-no-auto-save]') ? null"),
    'The Abuse Report modal must guard closes, serialize saves, and preserve unrelated dirty fields.'
);

echo "TRACS unsaved-changes submit flow checks passed.\n";
