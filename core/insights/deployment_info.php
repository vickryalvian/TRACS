<?php

function tracs_insight_deployment_info(mysqli $conn, array $monitoring, array $context = []): array {
    $versions = $monitoring['versions'] ?? [];
    $lastDeployAt = $versions['last_deploy_at'] ?? null;
    $environment = (string)($_ENV['APP_ENV'] ?? 'unknown');

    $status = $lastDeployAt ? 'healthy' : 'unavailable';

    return [
        'key' => 'deployment',
        'title' => 'Deployment Information',
        'icon' => 'server-cog',
        'type' => 'kv',
        'status' => $status,
        'score_input' => $lastDeployAt ? 100.0 : 50.0,
        'items' => [
            ['label' => 'TRACS Version', 'value' => $versions['app'] ?? 'Unavailable'],
            ['label' => 'Current Commit', 'value' => $versions['commit'] ? substr((string)$versions['commit'], 0, 12) : 'Unavailable'],
            ['label' => 'Last Deployment', 'value' => $lastDeployAt ? date('Y-m-d H:i', strtotime($lastDeployAt)) : 'Unavailable'],
            ['label' => 'Environment', 'value' => ucfirst($environment)],
            ['label' => 'Deployment Status', 'value' => $lastDeployAt ? 'Deployed' : 'Unknown'],
        ],
    ];
}
