<?php

function tracs_insight_quick_actions(mysqli $conn, array $monitoring, array $context = []): array {
    return [
        'key' => 'quick_actions',
        'title' => 'Quick Actions',
        'icon' => 'zap',
        'type' => 'actions_row',
        'status' => null,
        'items' => [
            [
                'id' => 'view-logs',
                'label' => 'View Sanitized Logs',
                'icon' => 'scroll-text',
                'kind' => 'anchor',
                'target_id' => 'serverLogList',
            ],
            [
                'id' => 'billing-portal',
                'label' => 'Open Billing Portal',
                'icon' => 'external-link',
                'kind' => 'link',
                'href' => 'https://my.idcloudhost.com',
            ],
            [
                'id' => 'download-diagnostics',
                'label' => 'Download Diagnostics',
                'icon' => 'download',
                'kind' => 'download',
            ],
        ],
    ];
}
