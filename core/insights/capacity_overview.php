<?php

function tracs_insight_capacity_overview(mysqli $conn, array $monitoring, array $context = []): array {
    $metrics = $monitoring['metrics'] ?? [];
    $disk = $metrics['disk'] ?? null;
    $diskFree = $metrics['disk_free'] ?? null;
    $memory = $metrics['memory'] ?? null;

    $ramPercent = $memory['percent'] ?? null;
    $ramHeadroom = $ramPercent === null ? null : round(max(0, 100 - $ramPercent), 1);

    $status = $disk['status'] ?? 'unavailable';

    return [
        'key' => 'capacity',
        'title' => 'Capacity Overview',
        'icon' => 'trending-up',
        'type' => 'kv',
        'status' => $status,
        'score_input' => tracs_insight_status_score($status),
        'items' => [
            [
                'label' => 'Disk Usage',
                'value' => $disk['display'] ?? 'Unavailable',
            ],
            [
                'label' => 'Remaining Capacity',
                'value' => $diskFree['display'] ?? 'Unavailable',
            ],
            [
                'label' => 'RAM Headroom',
                'value' => $ramHeadroom === null ? 'Unavailable' : $ramHeadroom . '%',
            ],
            [
                'label' => 'Storage Growth',
                'value' => 'Stable',
            ],
        ],
    ];
}
