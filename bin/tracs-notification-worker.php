#!/usr/bin/env php
<?php
/**
 * TRACS notification scheduler.
 *
 * Suggested cron:
 * * * * * /usr/bin/php /path/to/tracs/bin/tracs-notification-worker.php >> /path/to/tracs/logs/notification-worker.log 2>&1
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/notifications.php';

$started = date('c');
$result = tracs_notifications_run_scheduler($conn);
$line = sprintf(
    "[%s] status=%s created=%d\n",
    $started,
    (string)($result['status'] ?? 'unknown'),
    (int)($result['created'] ?? 0)
);
echo $line;
