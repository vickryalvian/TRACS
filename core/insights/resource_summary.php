<?php

function tracs_insight_resource_summary(mysqli $conn, array $monitoring, array $context = []): array {
    $metrics = $monitoring['metrics'] ?? [];
    $worst = 'healthy';
    $lines = [];

    foreach (tracs_insight_resource_sentences('CPU usage', $metrics['cpu'] ?? null, 'investigating running processes or scaling CPU resources') as $line) {
        $lines[] = $line;
    }
    $worst = tracs_insight_worse_status($worst, $metrics['cpu']['status'] ?? 'unavailable');

    foreach (tracs_insight_resource_sentences('RAM utilization', $metrics['memory'] ?? null, 'investigating running services or increasing available memory') as $line) {
        $lines[] = $line;
    }
    $worst = tracs_insight_worse_status($worst, $metrics['memory']['status'] ?? 'unavailable');

    foreach (tracs_insight_resource_sentences('Disk usage', $metrics['disk'] ?? null, 'clearing unused files or increasing storage capacity') as $line) {
        $lines[] = $line;
    }
    $worst = tracs_insight_worse_status($worst, $metrics['disk']['status'] ?? 'unavailable');

    $storageWarnings = [];
    foreach (['project_size', 'uploads_size', 'logs_size', 'backups_size', 'database_size'] as $key) {
        $metric = $metrics[$key] ?? null;
        $status = $metric['status'] ?? 'unavailable';
        if (in_array($status, ['warning', 'critical'], true)) {
            $storageWarnings[] = $metric['recommendation'] ?? (($metric['label'] ?? 'Storage') . ' usage is elevated.');
            $worst = tracs_insight_worse_status($worst, $status);
        }
    }
    if ($storageWarnings) {
        foreach ($storageWarnings as $warning) {
            $lines[] = ['text' => $warning];
        }
    } else {
        $lines[] = ['text' => 'No storage issues detected.'];
    }

    return [
        'key' => 'resource_summary',
        'title' => 'Resource Summary',
        'icon' => 'activity',
        'type' => 'list',
        'status' => $worst,
        'score_input' => tracs_insight_status_score($worst),
        'items' => $lines,
    ];
}

function tracs_insight_resource_sentences(string $label, ?array $metric, string $remedyHint): array {
    $status = $metric['status'] ?? 'unavailable';
    if ($status === 'unavailable') {
        return [['text' => $label . ' data is currently unavailable.']];
    }
    if ($status === 'healthy') {
        return [['text' => $label . ' is within normal limits.']];
    }
    $threshold = $status === 'critical' ? 85 : 70;
    return [
        ['text' => $label . ' exceeds ' . $threshold . '%.'],
        ['text' => 'Consider ' . $remedyHint . '.'],
    ];
}
