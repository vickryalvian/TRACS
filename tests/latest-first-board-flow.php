<?php
declare(strict_types=1);

function latest_first_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$abusePage = file_get_contents(__DIR__ . '/../public/abuse-reports.php');
$abuseScript = file_get_contents(__DIR__ . '/../public/assets/abuse-reports.js');
$abuseStyle = file_get_contents(__DIR__ . '/../public/assets/abuse-reports.css');
$abuseModel = file_get_contents(__DIR__ . '/../modules/abuse-report/model.php');
$casesPage = file_get_contents(__DIR__ . '/../public/cases.php');
$tracsScript = file_get_contents(__DIR__ . '/../public/assets/tracs.js');

latest_first_assert(!in_array(false, [$abusePage, $abuseScript, $abuseStyle, $abuseModel, $casesPage, $tracsScript], true), 'Unable to read board sources.');
latest_first_assert(
    !str_contains($abusePage, 'data-column-summary')
        && !str_contains($abuseScript, 'data-column-summary')
        && preg_match('/\.abuse-column-head\s*\{[^}]*flex:\s*0 0 44px;[^}]*height:\s*44px;/s', $abuseStyle) === 1
        && str_contains($abuseScript, "sort: { field: 'age', dir: 'desc' }")
        && str_contains($abuseScript, "boardOrder: 'activity'")
        && str_contains($abuseScript, 'a.last_activity_at || a.updated_at || a.created_at')
        && str_contains($abuseScript, "source === 'drag_drop' ? 'manual' : 'activity'")
        && str_contains($abuseModel, 'COALESCE(le.created_at, r.updated_at, r.created_at) DESC'),
    'Abuse reports must use one column count and newest activity ordering.'
);
latest_first_assert(
    str_contains($casesPage, "\$_GET['sort'] ?? 'updated'")
        && str_contains($casesPage, 'data-board-order="updated" class="is-active"')
        && str_contains($tracsScript, "sort: 'updated'")
        && str_contains($tracsScript, "boardOrder: 'updated'")
        && str_contains($tracsScript, "function sortBoardColumn(items,mode='updated')"),
    'Cases must default both list and board views to newest updated ordering.'
);

echo "TRACS latest-first board flow checks passed.\n";
