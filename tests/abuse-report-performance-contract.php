<?php
declare(strict_types=1);

function abuse_performance_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$page = file_get_contents($root . '/public/abuse-reports.php');
$notifications = file_get_contents($root . '/core/notifications.php');
$script = file_get_contents($root . '/public/assets/abuse-reports.js');
$style = file_get_contents($root . '/public/assets/abuse-reports.css');
$model = file_get_contents($root . '/modules/abuse-report/model.php');

abuse_performance_assert(!in_array(false, [$page, $notifications, $script, $style, $model], true), 'Unable to read abuse report performance sources.');
abuse_performance_assert(
    !str_contains($page, 'tracs_notifications_schedule_abuse_sla($conn)'),
    'The abuse report page must not run notification scheduling in the request path.'
);
abuse_performance_assert(
    preg_match('/if \(\$stmt->affected_rows < 1\) \{\s*\$stmt->close\(\);\s*return null;\s*\}/s', $notifications) === 1,
    'Expected duplicate notifications to return without writing a duplicate log row.'
);
abuse_performance_assert(str_contains($script, 'requestIdleCallback(refresh, { timeout: 200 })'), 'Expected icon refresh work to be deferred and coalesced.');
abuse_performance_assert(str_contains($script, 'window.setTimeout(renderBoard, 160)'), 'Expected search rendering to be debounced.');
abuse_performance_assert(str_contains($script, 'state.listEditingId === report.id ? renderListEditor(report)'), 'Expected only the active list editor to be rendered.');
abuse_performance_assert(str_contains($script, 'function hydrateListSelect(select)'), 'Expected list select options to be populated on demand.');
abuse_performance_assert(str_contains($script, "root.addEventListener('pointerdown'"), 'Expected delegated pointer handling for lazy list select options.');
abuse_performance_assert(str_contains($script, "root.addEventListener('focusin'"), 'Expected delegated keyboard focus handling for lazy list select options.');
abuse_performance_assert(str_contains($script, 'function actionIcon(kind)'), 'Expected lightweight inline SVG action icons.');
abuse_performance_assert(str_contains($script, "icons($('#abuseListBody'))"), 'Expected list redraw icon refreshes to target the table body.');
abuse_performance_assert(str_contains($script, "icons($('#abuseBoardWrap'))"), 'Expected board redraw icon refreshes to target the active board.');
abuse_performance_assert(str_contains($script, 'const renderedRevision = { board: -1, list: -1 };'), 'Expected separate board and list render caches.');
abuse_performance_assert(str_contains($script, 'if (renderedRevision[activeView] === renderRevision) return;'), 'Expected unchanged views to skip filtering, DOM reconstruction, and icon refresh.');
abuse_performance_assert(str_contains($script, 'renderBoard({ preserveRevision: !searchPending });'), 'Expected view switches to reuse clean DOM while applying pending search changes.');
abuse_performance_assert(
    preg_match('/function updateReportInState\(report\).*?renderRevision \+= 1;/s', $script) === 1,
    'Expected report updates to invalidate cached views.'
);
abuse_performance_assert(
    preg_match('/public function getReports\(\): array \{(.*?)public function getReportById/s', $model, $listQuery) === 1
        && !str_contains($listQuery[1], 'SELECT r.*')
        && !str_contains($listQuery[1], 'tracs_users cu'),
    'Expected the initial list query to return only selected report fields and avoid the unused creator join.'
);
abuse_performance_assert(
    preg_match('/<tr class="abuse-list-edit-row" data-abuse-list-edit-row="\$\{report\.id\}" hidden>/', $script) !== 1,
    'The active abuse report editor row must not retain the hidden attribute.'
);
abuse_performance_assert(str_contains($style, 'content-visibility: auto'), 'Expected off-screen abuse cards to skip layout and paint work where supported.');

echo "TRACS abuse report performance contract passed.\n";
