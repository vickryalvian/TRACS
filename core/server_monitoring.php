<?php

require_once __DIR__ . '/build_signature.php';

const TRACS_MONITORING_SCAN_SECONDS = 2.5;
const TRACS_MONITORING_MAX_ENTRIES = 50000;

function tracs_monitoring_project_root(): string {
    return dirname(__DIR__);
}

function tracs_monitoring_status(?float $percent): string {
    if ($percent === null) {
        return 'unavailable';
    }
    if ($percent >= 85) {
        return 'critical';
    }
    if ($percent >= 70) {
        return 'warning';
    }
    return 'healthy';
}

function tracs_monitoring_recommendation(string $label, ?float $percent): ?string {
    if ($percent === null || $percent < 70) {
        return null;
    }
    if ($percent >= 85) {
        return $label . ' is critical. Reduce usage or increase capacity as soon as possible.';
    }
    return $label . ' is elevated. Review growth and available capacity.';
}

function tracs_monitoring_bytes(int|float|null $bytes): string {
    if ($bytes === null || $bytes < 0) {
        return 'Unavailable';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $value = (float)$bytes;
    $index = 0;
    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }
    return number_format($value, $index === 0 ? 0 : 1) . ' ' . $units[$index];
}

function tracs_monitoring_unavailable(string $label): array {
    return [
        'label' => $label,
        'available' => false,
        'value' => null,
        'display' => 'Unavailable',
        'percent' => null,
        'status' => 'unavailable',
        'recommendation' => null,
    ];
}

function tracs_monitoring_percent_metric(string $label, float $percent, array $extra = []): array {
    $percent = max(0, min(100, round($percent, 1)));
    return array_merge([
        'label' => $label,
        'available' => true,
        'value' => $percent,
        'display' => number_format($percent, 1) . '%',
        'percent' => $percent,
        'status' => tracs_monitoring_status($percent),
        'recommendation' => tracs_monitoring_recommendation($label, $percent),
    ], $extra);
}

function tracs_monitoring_size_metric(string $label, ?int $bytes, ?float $percent = null): array {
    if ($bytes === null) {
        return tracs_monitoring_unavailable($label);
    }
    $status = tracs_monitoring_status($percent);
    return [
        'label' => $label,
        'available' => true,
        'value' => $bytes,
        'display' => tracs_monitoring_bytes($bytes),
        'percent' => $percent === null ? null : round(max(0, min(100, $percent)), 1),
        'status' => $status,
        'recommendation' => tracs_monitoring_recommendation($label, $percent),
    ];
}

function tracs_monitoring_cpu(): array {
    if (!is_readable('/proc/stat')) {
        return tracs_monitoring_unavailable('CPU Usage');
    }

    $sample = static function (): ?array {
        $line = @file('/proc/stat', FILE_IGNORE_NEW_LINES)[0] ?? '';
        if (!preg_match('/^cpu\s+(.+)$/', $line, $match)) {
            return null;
        }
        $values = array_map('intval', preg_split('/\s+/', trim($match[1])) ?: []);
        if (count($values) < 4) {
            return null;
        }
        $idle = ($values[3] ?? 0) + ($values[4] ?? 0);
        return ['idle' => $idle, 'total' => array_sum($values)];
    };

    $first = $sample();
    if (!$first) {
        return tracs_monitoring_unavailable('CPU Usage');
    }
    usleep(100000);
    $second = $sample();
    if (!$second) {
        return tracs_monitoring_unavailable('CPU Usage');
    }
    $total = $second['total'] - $first['total'];
    $idle = $second['idle'] - $first['idle'];
    if ($total <= 0) {
        return tracs_monitoring_unavailable('CPU Usage');
    }
    return tracs_monitoring_percent_metric('CPU Usage', (($total - $idle) / $total) * 100);
}

function tracs_monitoring_memory(): array {
    if (!is_readable('/proc/meminfo')) {
        return tracs_monitoring_unavailable('RAM Usage');
    }
    $values = [];
    foreach (@file('/proc/meminfo', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if (preg_match('/^([A-Za-z_()]+):\s+(\d+)\s+kB$/', $line, $match)) {
            $values[$match[1]] = (int)$match[2] * 1024;
        }
    }
    $total = $values['MemTotal'] ?? 0;
    $available = $values['MemAvailable'] ?? 0;
    if ($total <= 0 || $available < 0) {
        return tracs_monitoring_unavailable('RAM Usage');
    }
    $used = max(0, $total - $available);
    return tracs_monitoring_percent_metric('RAM Usage', ($used / $total) * 100, [
        'used_bytes' => $used,
        'total_bytes' => $total,
        'detail' => tracs_monitoring_bytes($used) . ' of ' . tracs_monitoring_bytes($total),
    ]);
}

function tracs_monitoring_disk(string $root): array {
    $total = @disk_total_space($root);
    $free = @disk_free_space($root);
    if (!is_float($total) || !is_float($free) || $total <= 0) {
        return [
            'usage' => tracs_monitoring_unavailable('Disk Used'),
            'free' => tracs_monitoring_unavailable('Free Storage'),
        ];
    }
    $used = max(0, $total - $free);
    return [
        'usage' => tracs_monitoring_percent_metric('Disk Used', ($used / $total) * 100, [
            'used_bytes' => (int)$used,
            'total_bytes' => (int)$total,
            'detail' => tracs_monitoring_bytes($used) . ' of ' . tracs_monitoring_bytes($total),
        ]),
        'free' => tracs_monitoring_size_metric('Free Storage', (int)$free),
    ];
}

function tracs_monitoring_directory_size(string $path, float $deadline, array $skipNames = []): ?int {
    if (!is_dir($path) || !is_readable($path)) {
        return null;
    }

    $total = 0;
    $entries = 0;
    $stack = [$path];
    try {
        while ($stack) {
            if (microtime(true) > $deadline || $entries >= TRACS_MONITORING_MAX_ENTRIES) {
                return null;
            }
            $dir = array_pop($stack);
            $iterator = new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS);
            foreach ($iterator as $item) {
                $entries++;
                if (microtime(true) > $deadline || $entries >= TRACS_MONITORING_MAX_ENTRIES) {
                    return null;
                }
                if ($item->isLink()) {
                    continue;
                }
                if ($item->isDir()) {
                    if (in_array($item->getFilename(), $skipNames, true)) {
                        continue;
                    }
                    $stack[] = $item->getPathname();
                    continue;
                }
                if ($item->isFile()) {
                    $total += max(0, $item->getSize());
                }
            }
        }
    } catch (Throwable) {
        return null;
    }
    return $total;
}

function tracs_monitoring_uptime(): array {
    $seconds = null;
    if (is_readable('/proc/uptime')) {
        $raw = trim((string)@file_get_contents('/proc/uptime'));
        $first = explode(' ', $raw)[0] ?? '';
        if (is_numeric($first)) {
            $seconds = (int)floor((float)$first);
        }
    }
    if ($seconds === null || $seconds < 0) {
        return tracs_monitoring_unavailable('Server Uptime');
    }
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return [
        'label' => 'Server Uptime',
        'available' => true,
        'value' => $seconds,
        'display' => ($days > 0 ? $days . 'd ' : '') . $hours . 'h ' . $minutes . 'm',
        'percent' => null,
        'status' => 'healthy',
        'recommendation' => null,
    ];
}

function tracs_monitoring_database(mysqli $conn): array {
    $sizeMetric = tracs_monitoring_unavailable('Database Size');
    $version = null;
    try {
        $result = $conn->query("SELECT COALESCE(SUM(data_length + index_length), 0) AS bytes FROM information_schema.tables WHERE table_schema = DATABASE()");
        $row = $result ? $result->fetch_assoc() : null;
        if ($row && is_numeric($row['bytes'] ?? null)) {
            $sizeMetric = tracs_monitoring_size_metric('Database Size', (int)$row['bytes']);
        }
        $versionResult = $conn->query('SELECT VERSION() AS version');
        $versionRaw = (string)($versionResult?->fetch_assoc()['version'] ?? '');
        if (preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9._-]+)?/', $versionRaw, $match)) {
            $version = $match[0];
        }
    } catch (Throwable) {
        // Safe unavailable values are returned below.
    }
    return ['size' => $sizeMetric, 'version' => $version];
}

function tracs_monitoring_deployment_metadata(string $root): array {
    $metadata = [
        'last_deploy_at' => null,
        'commit' => null,
        'version' => TRACS_BUILD_VERSION,
    ];
    $file = $root . '/storage/deployment/deployment.meta';
    if (!is_file($file) || !is_readable($file) || filesize($file) > 16384) {
        return $metadata;
    }
    foreach (@file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === 'deployed_at' && preg_match('/^\d{4}-\d{2}-\d{2}T[0-9:+-]+$/', $value)) {
            $metadata['last_deploy_at'] = $value;
        } elseif ($key === 'commit' && preg_match('/^[a-f0-9]{7,40}$/i', $value)) {
            $metadata['commit'] = strtolower($value);
        } elseif ($key === 'version' && $value !== 'unknown' && preg_match('/^[A-Za-z0-9._-]{1,80}$/', $value)) {
            $metadata['version'] = $value;
        }
    }
    return $metadata;
}

function tracs_monitoring_nginx_version(): ?string {
    $software = (string)($_SERVER['SERVER_SOFTWARE'] ?? '');
    return preg_match('/\bnginx\/(\d+(?:\.\d+){1,3})\b/i', $software, $match) ? $match[1] : null;
}

function tracs_monitoring_tail_lines(string $file, int $maxLines = 30, int $maxBytes = 131072): array {
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $handle = @fopen($file, 'rb');
    if (!$handle) {
        return [];
    }
    $size = (int)filesize($file);
    $read = min($size, $maxBytes);
    if ($read > 0) {
        fseek($handle, -$read, SEEK_END);
    }
    $content = (string)fread($handle, $read);
    fclose($handle);
    $lines = preg_split('/\R/', $content) ?: [];
    if ($size > $read) {
        array_shift($lines);
    }
    return array_slice(array_values(array_filter($lines, fn($line) => trim($line) !== '')), -$maxLines);
}

function tracs_monitoring_sanitize_log_line(string $line): array {
    $line = trim(preg_replace('/[\x00-\x1F\x7F]+/', ' ', $line) ?? '');
    $timestamp = null;
    if (preg_match('/^\[?(\d{4}-\d{2}-\d{2}[ T][0-9:]+(?:\s*[A-Z+-]+)?)\]?/', $line, $match)) {
        $timestamp = substr($match[1], 0, 32);
    }
    $severity = preg_match('/\b(fatal|critical|emergency)\b/i', $line) ? 'critical'
        : (preg_match('/\b(warning|warn)\b/i', $line) ? 'warning'
        : (preg_match('/\b(notice|deprecated)\b/i', $line) ? 'notice' : 'error'));

    if (preg_match('/\b(SQLSTATE|mysqli|PDOException|database error|query failed|SELECT|INSERT|UPDATE|DELETE|authorization|cookie|password|secret|token|stack trace)\b/i', $line)) {
        $message = 'Sensitive database, credential, or stack detail redacted.';
    } else {
        $message = preg_replace('#https?://[^\s?]+(?:\?[^\s]+)?#i', '[url]', $line) ?? $line;
        $message = preg_replace('#(?<![A-Za-z0-9])/(?:[A-Za-z0-9._-]+/)+[A-Za-z0-9._-]+#', '[path]', $message) ?? $message;
        $message = preg_replace('/\b(?:\d{1,3}\.){3}\d{1,3}\b/', '[ip]', $message) ?? $message;
        $message = preg_replace('/\b[A-F0-9:]{3,}\b/i', '[ip]', $message) ?? $message;
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email]', $message) ?? $message;
        $message = preg_replace('/\b(?:user(?:name)?|account|host(?:name)?)\s*[=:]\s*[^\s,;]+/i', '[identity]', $message) ?? $message;
        $message = preg_replace('/\s+/', ' ', $message) ?? $message;
        $message = mb_substr(trim($message), 0, 320);
    }
    return ['timestamp' => $timestamp, 'severity' => $severity, 'message' => $message];
}

function tracs_monitoring_logs(string $root): array {
    $file = $root . '/logs/error.log';
    if (!is_file($file) || !is_readable($file)) {
        return ['available' => false, 'last_modified' => null, 'counts' => [], 'entries' => []];
    }
    $entries = array_map('tracs_monitoring_sanitize_log_line', tracs_monitoring_tail_lines($file));
    $counts = ['critical' => 0, 'error' => 0, 'warning' => 0, 'notice' => 0];
    foreach ($entries as $entry) {
        $severity = $entry['severity'];
        $counts[$severity] = ($counts[$severity] ?? 0) + 1;
    }
    return [
        'available' => true,
        'last_modified' => date(DATE_ATOM, (int)filemtime($file)),
        'counts' => $counts,
        'entries' => $entries,
    ];
}

function tracs_collect_server_monitoring(mysqli $conn): array {
    $root = tracs_monitoring_project_root();
    $deadline = microtime(true) + TRACS_MONITORING_SCAN_SECONDS;
    $disk = tracs_monitoring_disk($root);
    $diskTotal = (int)($disk['usage']['total_bytes'] ?? 0);

    $projectBytes = tracs_monitoring_directory_size($root, $deadline, ['.git', 'backup', 'backups', 'graphify-out']);
    $uploadsBytes = tracs_monitoring_directory_size($root . '/public/uploads', $deadline);
    $logsBytes = tracs_monitoring_directory_size($root . '/logs', $deadline);

    $backupBytes = null;
    foreach ([$root . '/backups', '/var/backups/tracs'] as $candidate) {
        if (is_dir($candidate)) {
            $backupBytes = tracs_monitoring_directory_size($candidate, $deadline);
            break;
        }
    }

    $database = tracs_monitoring_database($conn);
    $metadata = tracs_monitoring_deployment_metadata($root);
    $percentOfDisk = static fn(?int $bytes): ?float => $bytes !== null && $diskTotal > 0
        ? ($bytes / $diskTotal) * 100
        : null;

    return [
        'checked_at' => date(DATE_ATOM),
        'thresholds' => ['healthy_below' => 70, 'warning_from' => 70, 'critical_from' => 85],
        'metrics' => [
            'cpu' => tracs_monitoring_cpu(),
            'memory' => tracs_monitoring_memory(),
            'disk' => $disk['usage'],
            'disk_free' => $disk['free'],
            'project_size' => tracs_monitoring_size_metric('TRACS Folder Size', $projectBytes, $percentOfDisk($projectBytes)),
            'uploads_size' => tracs_monitoring_size_metric('Uploads Size', $uploadsBytes, $percentOfDisk($uploadsBytes)),
            'logs_size' => tracs_monitoring_size_metric('Logs Size', $logsBytes, $percentOfDisk($logsBytes)),
            'backups_size' => tracs_monitoring_size_metric('Backups Size', $backupBytes, $percentOfDisk($backupBytes)),
            'database_size' => $database['size'],
            'uptime' => tracs_monitoring_uptime(),
        ],
        'versions' => [
            'php' => PHP_VERSION,
            'database' => $database['version'],
            'nginx' => tracs_monitoring_nginx_version(),
            'app' => $metadata['version'],
            'commit' => $metadata['commit'],
            'last_deploy_at' => $metadata['last_deploy_at'],
        ],
        'logs' => tracs_monitoring_logs($root),
    ];
}
