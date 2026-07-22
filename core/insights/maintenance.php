<?php

function tracs_insight_maintenance(mysqli $conn, array $monitoring, array $context = []): array {
    $items = [];

    [$phpStatus] = tracs_insight_php_support_status(PHP_VERSION);
    if ($phpStatus === 'warning') {
        $eol = tracs_insight_php_eol(PHP_VERSION);
        $items[] = [
            'title' => 'PHP version is approaching End-of-Life.',
            'detail' => $eol ? ('Security support ends ' . $eol . '. Plan an upgrade before then.') : 'Plan an upgrade before security support ends.',
            'severity' => 'warning',
        ];
    }

    $status = 'healthy';
    foreach ($items as $item) {
        $status = tracs_insight_worse_status($status, $item['severity']);
    }

    return [
        'key' => 'maintenance',
        'title' => 'Maintenance',
        'icon' => 'wrench',
        'type' => 'warnings',
        'status' => $status,
        'empty_state' => 'Nothing scheduled. No planned maintenance right now.',
        'items' => $items,
    ];
}
