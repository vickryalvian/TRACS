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

abuse_performance_assert(!in_array(false, [$page, $notifications, $script, $style], true), 'Unable to read abuse report performance sources.');
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
abuse_performance_assert(str_contains($style, 'content-visibility: auto'), 'Expected off-screen abuse cards to skip layout and paint work where supported.');

echo "TRACS abuse report performance contract passed.\n";
