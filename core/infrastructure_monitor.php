<?php

/*
 * Background monitoring cycle for Infrastructure Pulse real (ICMP) targets.
 * Invoked by bin/tracs-infrastructure-monitor.php from cron — this is what
 * makes checks continuous instead of depending on a browser tab staying
 * open. See core/infrastructure_ping.php for the actual check mechanics and
 * core/infrastructure_servers.php for persistence.
 */

require_once __DIR__ . '/infrastructure_ping.php';
require_once __DIR__ . '/infrastructure_servers.php';
require_once __DIR__ . '/dobby_notifications.php';

function tracs_infra_monitor_log(mysqli $conn, string $line): void {
    // Cron output is captured by shell redirection per bin/tracs-infrastructure-monitor.php's
    // suggested crontab entry; nothing DB-side needed beyond the samples themselves.
    error_log($line);
}

function tracs_infra_monitor_due_servers(mysqli $conn): array {
    $servers = array_filter(
        tracs_infra_server_list_active($conn),
        static fn(array $row): bool => ($row['method'] ?? '') === 'icmp' && trim((string)($row['target_host'] ?? '')) !== ''
    );
    $now = time();
    return array_values(array_filter($servers, static function (array $row) use ($now): bool {
        $intervalSeconds = max(30, (int)($row['interval_seconds'] ?? 60));
        if (empty($row['last_checked_at'])) {
            return true;
        }
        $lastChecked = strtotime((string)$row['last_checked_at']);
        if ($lastChecked === false) {
            return true;
        }
        return ($now - $lastChecked) >= $intervalSeconds;
    }));
}

/**
 * Runs one pass: checks every real ICMP server whose interval has elapsed,
 * records results (cache + history row), and prunes old history. Guarded by
 * a MySQL advisory lock (matching core/notifications.php's scheduler
 * pattern) so an overlapping cron invocation is a no-op instead of running
 * checks twice.
 */
function tracs_infra_monitor_run(mysqli $conn): array {
    $locked = false;
    $lockResult = $conn->query("SELECT GET_LOCK('tracs_infrastructure_monitor', 0) AS locked");
    if ($lockResult) {
        $locked = (int)(($lockResult->fetch_assoc()['locked'] ?? 0)) === 1;
    }
    if (!$locked) {
        return ['status' => 'locked', 'checked' => 0, 'pruned' => 0];
    }

    try {
        $due = tracs_infra_monitor_due_servers($conn);
        $checked = 0;
        foreach ($due as $server) {
            $code = (string)$server['code'];
            $result = tracs_infra_ping_host(
                (string)$server['target_host'],
                (int)($server['packet_count'] ?? 4),
                (int)($server['timeout_seconds'] ?? 5)
            );
            $previousStatus = isset($server['last_status']) ? (string)$server['last_status'] : null;
            tracs_infra_server_record_check(
                $conn,
                $code,
                (string)$result['status'],
                $result['latency_ms'] !== null ? (float)$result['latency_ms'] : null,
                $result['packet_loss_percent'] !== null ? (float)$result['packet_loss_percent'] : null,
                (string)$result['checked_at']
            );
            tracs_dobby_notify_monitoring_transition($conn, $server, $previousStatus, $result);
            $checked++;
            tracs_infra_monitor_log($conn, sprintf(
                '[%s] checked %s (%s) -> %s%s',
                date('c'),
                $code,
                $server['target_host'],
                $result['status'],
                $result['latency_ms'] !== null ? sprintf(' %sms', $result['latency_ms']) : ''
            ));
        }
        $pruned = tracs_infra_prune_history($conn, 30);
        return ['status' => 'ok', 'checked' => $checked, 'pruned' => $pruned];
    } finally {
        $conn->query("SELECT RELEASE_LOCK('tracs_infrastructure_monitor')");
    }
}
