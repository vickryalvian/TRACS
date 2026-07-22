<?php

function tracs_insight_active_warnings(mysqli $conn, array $monitoring, array $context = []): array {
    $root = tracs_monitoring_project_root();
    $metrics = $monitoring['metrics'] ?? [];
    $warnings = [];

    $billingWarning = tracs_insight_billing_warning($context['sections']['billing'] ?? []);
    if ($billingWarning !== null) {
        $warnings[] = $billingWarning;
    }

    $dbOk = false;
    try {
        $dbOk = (bool)$conn->ping();
    } catch (Throwable) {
        $dbOk = false;
    }
    if (!$dbOk) {
        $warnings[] = [
            'title' => 'Database is unreachable.',
            'detail' => 'Investigate the database connection immediately.',
            'severity' => 'critical',
        ];
    }

    if (!(is_writable($root) && is_writable($root . '/logs'))) {
        $warnings[] = [
            'title' => 'Application storage is not writable.',
            'detail' => 'Check file and directory permissions.',
            'severity' => 'critical',
        ];
    }

    $backups = $metrics['backups_size'] ?? null;
    if ($backups && ($backups['available'] ?? true) === false) {
        $warnings[] = [
            'title' => 'Database backup is unavailable.',
            'detail' => 'No backup directory could be found or read.',
            'severity' => 'warning',
        ];
    }

    if (!tracs_insight_is_https()) {
        $warnings[] = [
            'title' => 'HTTPS is not enabled.',
            'detail' => 'Traffic to this page may be unencrypted.',
            'severity' => 'warning',
        ];
    }

    $ssl = tracs_insight_ssl_expiry();
    if ($ssl !== null) {
        if ($ssl['days_left'] <= 0) {
            $warnings[] = [
                'title' => 'SSL certificate has expired.',
                'detail' => 'Renew the certificate for ' . $ssl['host'] . ' immediately.',
                'severity' => 'critical',
            ];
        } elseif ($ssl['days_left'] <= TRACS_INSIGHT_SSL_WARNING_DAYS) {
            $warnings[] = [
                'title' => 'SSL certificate expires in ' . $ssl['days_left'] . ' day' . ($ssl['days_left'] === 1 ? '' : 's') . '.',
                'detail' => 'Renew the certificate for ' . $ssl['host'] . ' soon.',
                'severity' => 'warning',
            ];
        }
    }

    [$phpStatus] = tracs_insight_php_support_status(PHP_VERSION);
    if ($phpStatus === 'critical') {
        $warnings[] = [
            'title' => 'PHP version is End-of-Life.',
            'detail' => 'Upgrade PHP as soon as possible.',
            'severity' => 'critical',
        ];
    }

    $status = 'healthy';
    foreach ($warnings as $warning) {
        $status = tracs_insight_worse_status($status, $warning['severity']);
    }

    return [
        'key' => 'active_warnings',
        'title' => 'Active Warnings',
        'icon' => 'alert-triangle',
        'type' => 'warnings',
        'status' => $status,
        'empty_state' => 'System is healthy. No immediate action is required.',
        'items' => $warnings,
    ];
}

function tracs_insight_billing_warning(array $billing): ?array {
    $status = $billing['status'] ?? 'unavailable';
    if ($status === 'healthy' || $billing === []) {
        return null;
    }
    $title = match ($status) {
        'critical' => 'Billing balance is critically low.',
        'warning' => 'Billing balance is running low.',
        default => 'IDCloudHost API is unreachable.',
    };
    return [
        'title' => $title,
        'detail' => $billing['items']['recommendation'] ?? $billing['items']['status_label'] ?? null,
        'severity' => $status === 'unavailable' ? 'warning' : $status,
    ];
}
