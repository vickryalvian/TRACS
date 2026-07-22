<?php

const TRACS_INSIGHT_PHP_EOL_WARNING_DAYS = 90;

function tracs_insight_security_maintenance(mysqli $conn, array $monitoring, array $context = []): array {
    $root = tracs_monitoring_project_root();
    $rows = [];
    $statuses = [];

    $https = tracs_insight_is_https();
    $rows[] = tracs_insight_badge_row('HTTPS Enabled', $https ? 'healthy' : 'warning', $https ? 'Enabled' : 'Not Detected');
    $statuses[] = $https ? 'healthy' : 'warning';

    [$phpStatus, $phpText] = tracs_insight_php_support_status(PHP_VERSION);
    $rows[] = tracs_insight_badge_row('PHP Version Supported', $phpStatus, $phpText);
    $statuses[] = $phpStatus;

    $dbOk = false;
    try {
        $dbOk = (bool)$conn->ping();
    } catch (Throwable) {
        $dbOk = false;
    }
    $rows[] = tracs_insight_badge_row('Database Reachable', $dbOk ? 'healthy' : 'critical', $dbOk ? 'Reachable' : 'Unreachable');
    $statuses[] = $dbOk ? 'healthy' : 'critical';

    $nginxDetected = tracs_monitoring_nginx_version() !== null;
    $rows[] = tracs_insight_badge_row('Nginx Running', $nginxDetected ? 'healthy' : 'unavailable', $nginxDetected ? 'Running' : 'Unconfirmed');
    if ($nginxDetected) {
        $statuses[] = 'healthy';
    }

    $storageWritable = is_writable($root) && is_writable($root . '/logs');
    $rows[] = tracs_insight_badge_row('Storage Writable', $storageWritable ? 'healthy' : 'critical', $storageWritable ? 'Writable' : 'Read-only');
    $statuses[] = $storageWritable ? 'healthy' : 'critical';

    $billingStatus = $context['sections']['billing']['status'] ?? null;
    $apiHealthy = $dbOk && $billingStatus !== 'unavailable';
    $rows[] = tracs_insight_badge_row('API Connection Healthy', $apiHealthy ? 'healthy' : 'warning', $apiHealthy ? 'Healthy' : 'Degraded');
    $statuses[] = $apiHealthy ? 'healthy' : 'warning';

    $overall = tracs_insight_badges_overall($statuses);

    return [
        'key' => 'security',
        'title' => 'Security & Maintenance',
        'icon' => 'shield-check',
        'type' => 'badges',
        'status' => $overall,
        'score_input' => tracs_insight_status_score($overall),
        'items' => $rows,
    ];
}

function tracs_insight_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function tracs_insight_php_support_status(string $version): array {
    $eol = tracs_insight_php_eol($version);
    if ($eol === null) {
        return ['healthy', 'Supported'];
    }
    $daysLeft = (strtotime($eol) - time()) / 86400;
    if ($daysLeft < 0) {
        return ['critical', 'End-of-Life'];
    }
    if ($daysLeft < TRACS_INSIGHT_PHP_EOL_WARNING_DAYS) {
        return ['warning', 'Approaching End-of-Life'];
    }
    return ['healthy', 'Supported'];
}

function tracs_insight_badge_row(string $label, string $status, string $text): array {
    return [
        'label' => $label,
        'badge_text' => $text,
        'badge_class' => tracs_insight_badge_class($status),
    ];
}

function tracs_insight_badge_class(string $status): string {
    return match ($status) {
        'critical' => 'b-critical',
        'warning' => 'b-warning',
        'healthy' => 'b-active',
        default => 'b-done',
    };
}

function tracs_insight_badges_overall(array $statuses): string {
    if (in_array('critical', $statuses, true)) {
        return 'critical';
    }
    if (in_array('warning', $statuses, true)) {
        return 'warning';
    }
    return 'healthy';
}
