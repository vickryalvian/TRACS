#!/usr/bin/env php
<?php
/**
 * TRACS Infrastructure Pulse background monitor.
 *
 * Checks every real (Network Ping) server whose configured interval has
 * elapsed, independent of any browser tab being open — this is what makes
 * Infrastructure Pulse continuous rather than tab-driven. Safe to run more
 * often than any individual server's interval; due-checking happens inside
 * tracs_infra_monitor_run().
 *
 * Suggested cron (every minute; the worker itself only checks servers whose
 * interval has actually elapsed, so this is safe to run this often):
 * * * * * /usr/bin/php /path/to/tracs/bin/tracs-infrastructure-monitor.php >> /path/to/tracs/logs/infrastructure-monitor.log 2>&1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/infrastructure_monitor.php';

$started = date('c');
$result = tracs_infra_monitor_run($conn);
$line = sprintf(
    "[%s] status=%s checked=%d pruned=%d\n",
    $started,
    (string)($result['status'] ?? 'unknown'),
    (int)($result['checked'] ?? 0),
    (int)($result['pruned'] ?? 0)
);
echo $line;
