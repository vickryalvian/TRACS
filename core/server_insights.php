<?php

require_once __DIR__ . '/server_monitoring.php';
require_once __DIR__ . '/insights/reference_data.php';
require_once __DIR__ . '/insights/ssl_certificate.php';
require_once __DIR__ . '/insights/billing.php';
require_once __DIR__ . '/insights/active_warnings.php';
require_once __DIR__ . '/insights/maintenance.php';
require_once __DIR__ . '/insights/recent_changes.php';
require_once __DIR__ . '/insights/quick_actions.php';
require_once __DIR__ . '/insights/ai_insights.php';

// Registered providers, in display order. Add a new provider by dropping a
// file in core/insights/ and adding one line here — the aggregator, the
// isolation/fallback behavior, and the frontend renderer never need to change.
function tracs_insight_providers(): array {
    return [
        'billing' => 'tracs_insight_billing',
        'active_warnings' => 'tracs_insight_active_warnings',
        'maintenance' => 'tracs_insight_maintenance',
        'recent_changes' => 'tracs_insight_recent_changes',
        'quick_actions' => 'tracs_insight_quick_actions',
        'ai_insights' => 'tracs_insight_ai_insights',
    ];
}

function tracs_collect_server_insights(mysqli $conn, array $monitoring): array {
    $context = ['sections' => []];

    foreach (tracs_insight_providers() as $key => $callable) {
        try {
            $section = $callable($conn, $monitoring, $context);
        } catch (Throwable $error) {
            error_log('TRACS server insight "' . $key . '" failed: ' . $error->getMessage());
            $section = tracs_insight_fallback_section($key);
        }
        $context['sections'][$key] = $section;
    }

    return array_values($context['sections']);
}

function tracs_insight_fallback_section(string $key): array {
    return [
        'key' => $key,
        'title' => ucwords(str_replace('_', ' ', $key)),
        'icon' => 'alert-triangle',
        'type' => 'list',
        'status' => 'unavailable',
        'items' => [['text' => 'This insight is temporarily unavailable.']],
    ];
}

function tracs_insight_worse_status(string $a, string $b): string {
    $rank = ['unavailable' => 0, 'healthy' => 1, 'warning' => 2, 'critical' => 3];
    return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
}
