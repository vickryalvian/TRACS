<?php

/*
 * Real ICMP check for Infrastructure Pulse's "Add Server" mock/real registry.
 * Mirrors the intentionally conservative posture in core/server_monitoring.php:
 * fixed/bounded inputs, no shell string interpolation, hard wall-clock limits,
 * unavailable-on-failure preferred over a wider attack surface.
 */

function tracs_infra_ping_host_is_valid(string $host): bool {
    if ($host === '' || mb_strlen($host) > 253) {
        return false;
    }
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        return true;
    }
    // RFC 1123 hostname: dot-separated labels, letters/digits/hyphens, no leading/trailing hyphen per label.
    return (bool)preg_match('/^(?!-)[A-Za-z0-9-]{1,63}(?<!-)(\.(?!-)[A-Za-z0-9-]{1,63}(?<!-))*$/', $host);
}

function tracs_infra_ping_result(string $status, ?float $latencyMs, ?float $lossPercent, ?string $message = null): array {
    return [
        'ok' => in_array($status, ['healthy', 'degraded', 'critical'], true) && $message === null,
        'status' => $status,
        'latency_ms' => $latencyMs,
        'packet_loss_percent' => $lossPercent,
        'message' => $message,
        'checked_at' => date('c'),
    ];
}

function tracs_infra_ping_classify(float $lossPercent, ?float $avgLatencyMs): string {
    if ($lossPercent >= 100 || $avgLatencyMs === null) {
        return 'critical';
    }
    if ($lossPercent >= 1 || $avgLatencyMs >= 150) {
        return 'critical';
    }
    if ($lossPercent > 0 || $avgLatencyMs >= 80) {
        return 'degraded';
    }
    return 'healthy';
}

function tracs_infra_ping_parse(string $output): array {
    $lossPercent = null;
    $avgLatencyMs = null;
    if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*packet loss/i', $output, $m)) {
        $lossPercent = (float)$m[1];
    }
    if (preg_match('/=\s*[\d.]+\/([\d.]+)\/[\d.]+(?:\/[\d.]+)?\s*ms/i', $output, $m)) {
        $avgLatencyMs = round((float)$m[1], 1);
    }
    if ($lossPercent === null) {
        return tracs_infra_ping_result('critical', null, null, 'No response parsed from network check.');
    }
    if ($lossPercent >= 100 || $avgLatencyMs === null) {
        return tracs_infra_ping_result('critical', null, $lossPercent, 'Host unreachable.');
    }
    $status = tracs_infra_ping_classify($lossPercent, $avgLatencyMs);
    return tracs_infra_ping_result($status, $avgLatencyMs, $lossPercent);
}

/**
 * Runs a bounded ICMP check via the system ping binary using an argv array
 * (proc_open never invokes a shell for array commands), so $host can never
 * reach shell interpolation regardless of its contents. $host is still
 * validated by tracs_infra_ping_host_is_valid() before this is called.
 */
function tracs_infra_ping_host(string $host, int $count, int $timeoutSeconds): array {
    $count = max(1, min(10, $count));
    $timeoutSeconds = max(1, min(30, $timeoutSeconds));
    $deadline = max(2, min(15, $count * $timeoutSeconds + 3));

    $cmd = ['ping', '-n', '-c', (string)$count, '-W', (string)$timeoutSeconds, '-w', (string)$deadline, $host];
    $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = @proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
        return tracs_infra_ping_result('critical', null, null, 'Unable to start network check.');
    }

    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $start = microtime(true);
    $hardLimit = $deadline + 3;
    while (true) {
        $status = proc_get_status($process);
        $stdout .= (string)stream_get_contents($pipes[1]);
        if (!$status['running']) {
            break;
        }
        if ((microtime(true) - $start) > $hardLimit) {
            proc_terminate($process, 9);
            break;
        }
        usleep(100000);
    }
    $stdout .= (string)stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    return tracs_infra_ping_parse($stdout);
}
