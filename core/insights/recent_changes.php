<?php

function tracs_insight_recent_changes(mysqli $conn, array $monitoring, array $context = []): array {
    $items = [];

    $versions = $monitoring['versions'] ?? [];
    $lastDeployAt = $versions['last_deploy_at'] ?? null;
    if ($lastDeployAt) {
        $commit = $versions['commit'] ? substr((string)$versions['commit'], 0, 12) : null;
        $environment = ucfirst((string)($_ENV['APP_ENV'] ?? 'unknown'));
        $text = 'Deployed ' . tracs_insight_relative_time($lastDeployAt) . ' to ' . $environment
            . ($commit ? (', commit ' . $commit) : '') . '.';
        $items[] = ['text' => $text];
    }

    $entries = $monitoring['logs']['entries'] ?? [];
    $newestIssue = null;
    foreach (array_reverse($entries) as $entry) {
        if (in_array($entry['severity'] ?? '', ['critical', 'error'], true)) {
            $newestIssue = $entry;
            break;
        }
    }
    if ($newestIssue !== null) {
        $when = $newestIssue['timestamp'] ?: 'recently';
        $items[] = ['text' => 'Newest detected issue (' . $when . '): ' . $newestIssue['message']];
    }

    return [
        'key' => 'recent_changes',
        'title' => 'Recent Changes',
        'icon' => 'history',
        'type' => 'list',
        'status' => $newestIssue !== null ? 'warning' : 'healthy',
        'empty_state' => 'No recent changes detected.',
        'items' => $items,
    ];
}

function tracs_insight_relative_time(string $isoDate): string {
    $timestamp = strtotime($isoDate);
    if ($timestamp === false) {
        return $isoDate;
    }
    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $minutes = (int)floor($diff / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago';
    }
    if ($diff < 86400) {
        $hours = (int)floor($diff / 3600);
        return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago';
    }
    $days = (int)floor($diff / 86400);
    return $days . ' day' . ($days === 1 ? '' : 's') . ' ago';
}
