<?php

function tracs_insight_suggested_actions(mysqli $conn, array $monitoring, array $context = []): array {
    $metrics = $monitoring['metrics'] ?? [];
    $sections = $context['sections'] ?? [];
    $actions = [];

    $billingStatus = $sections['billing']['status'] ?? 'unavailable';
    $actions = array_merge($actions, tracs_insight_billing_actions($billingStatus));

    foreach (['cpu' => 'CPU', 'memory' => 'RAM', 'disk' => 'Disk'] as $key => $label) {
        $status = $metrics[$key]['status'] ?? 'unavailable';
        if (in_array($status, ['warning', 'critical'], true)) {
            $threshold = $status === 'critical' ? 85 : 70;
            $actions[] = [
                'title' => $label . ' usage exceeds ' . $threshold . '%.',
                'detail' => 'Review ' . strtolower($label) . ' consumption and consider scaling resources.',
                'severity' => $status,
            ];
        }
    }

    $backups = $metrics['backups_size'] ?? null;
    if ($backups && ($backups['available'] ?? true) === false) {
        $actions[] = [
            'title' => 'Database backup is unavailable.',
            'detail' => 'No backup directory could be found or read.',
            'severity' => 'warning',
        ];
    }

    $securityItems = $sections['security']['items'] ?? [];

    $phpRow = tracs_insight_find_badge_row($securityItems, 'PHP Version Supported');
    if (($phpRow['badge_class'] ?? '') === 'b-warning') {
        $actions[] = [
            'title' => 'PHP version is approaching End-of-Life.',
            'detail' => 'Plan an upgrade before security support ends.',
            'severity' => 'warning',
        ];
    } elseif (($phpRow['badge_class'] ?? '') === 'b-critical') {
        $actions[] = [
            'title' => 'PHP version is End-of-Life.',
            'detail' => 'Upgrade PHP as soon as possible.',
            'severity' => 'critical',
        ];
    }

    $dbRow = tracs_insight_find_badge_row($securityItems, 'Database Reachable');
    if (($dbRow['badge_class'] ?? '') === 'b-critical') {
        $actions[] = [
            'title' => 'Database is unreachable.',
            'detail' => 'Investigate the database connection immediately.',
            'severity' => 'critical',
        ];
    }

    $storageRow = tracs_insight_find_badge_row($securityItems, 'Storage Writable');
    if (($storageRow['badge_class'] ?? '') === 'b-critical') {
        $actions[] = [
            'title' => 'Application storage is not writable.',
            'detail' => 'Check file and directory permissions.',
            'severity' => 'critical',
        ];
    }

    if (!$actions) {
        $actions[] = [
            'title' => 'No action required.',
            'detail' => 'All monitored systems are operating normally.',
            'severity' => 'healthy',
        ];
    }

    return [
        'key' => 'suggested_actions',
        'title' => 'Suggested Actions',
        'icon' => 'list-checks',
        'type' => 'actions',
        'status' => tracs_insight_badges_overall(array_column($actions, 'severity')),
        'score_input' => null,
        'items' => $actions,
    ];
}

function tracs_insight_billing_actions(string $status): array {
    return match ($status) {
        'critical' => [[
            'title' => 'Billing balance is critically low.',
            'detail' => 'Top up the IDCloudHost balance soon to avoid service interruption.',
            'severity' => 'critical',
        ]],
        'warning' => [[
            'title' => 'Billing balance is below the warning threshold.',
            'detail' => 'Consider topping up before it runs out.',
            'severity' => 'warning',
        ]],
        'unavailable' => [[
            'title' => 'Billing balance could not be verified.',
            'detail' => 'Check the IDCloudHost API key configuration.',
            'severity' => 'warning',
        ]],
        default => [],
    };
}

function tracs_insight_find_badge_row(array $items, string $label): ?array {
    foreach ($items as $item) {
        if (($item['label'] ?? '') === $label) {
            return $item;
        }
    }
    return null;
}
