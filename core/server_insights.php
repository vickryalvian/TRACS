<?php

require_once __DIR__ . '/server_monitoring.php';
require_once __DIR__ . '/insights/reference_data.php';
require_once __DIR__ . '/insights/billing.php';
require_once __DIR__ . '/insights/resource_summary.php';
require_once __DIR__ . '/insights/capacity_overview.php';
require_once __DIR__ . '/insights/security_maintenance.php';
require_once __DIR__ . '/insights/deployment_info.php';
require_once __DIR__ . '/insights/suggested_actions.php';

// Weighted inputs for the Overall Health Score. Keys are looked up dynamically
// in tracs_insight_compute_score() — add a weight here to fold in a new signal.
const TRACS_INSIGHT_WEIGHTS = [
    'cpu' => 0.10,
    'ram' => 0.15,
    'disk' => 0.15,
    'billing' => 0.15,
    'database' => 0.15,
    'storage' => 0.10,
    'runtime' => 0.10,
    'api' => 0.10,
];

// Ordered low bound => band label. First match (highest bound the score clears) wins.
const TRACS_INSIGHT_BANDS = [
    [90, 'Excellent'],
    [75, 'Good'],
    [50, 'Fair'],
    [0, 'Poor'],
];

// Registered providers, in display order. Add a new provider by dropping a
// file in core/insights/ and adding one line here — the aggregator, the
// isolation/fallback behavior, and the frontend renderer never need to change.
function tracs_insight_providers(): array {
    return [
        'billing' => 'tracs_insight_billing',
        'resource_summary' => 'tracs_insight_resource_summary',
        'capacity' => 'tracs_insight_capacity_overview',
        'security' => 'tracs_insight_security_maintenance',
        'deployment' => 'tracs_insight_deployment_info',
        'suggested_actions' => 'tracs_insight_suggested_actions',
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

    $context['sections']['health_score'] = tracs_insight_compute_score($context['sections'], $monitoring);

    return array_values($context['sections']);
}

function tracs_insight_fallback_section(string $key): array {
    return [
        'key' => $key,
        'title' => ucwords(str_replace('_', ' ', $key)),
        'icon' => 'alert-triangle',
        'type' => 'list',
        'status' => 'unavailable',
        'score_input' => null,
        'items' => [['text' => 'This insight is temporarily unavailable.']],
    ];
}

function tracs_insight_worse_status(string $a, string $b): string {
    $rank = ['unavailable' => 0, 'healthy' => 1, 'warning' => 2, 'critical' => 3];
    return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
}

function tracs_insight_status_score(string $status): float {
    return match ($status) {
        'healthy' => 100.0,
        'warning' => 60.0,
        'critical' => 20.0,
        default => 50.0,
    };
}

function tracs_insight_status_from_badge_class(string $class): string {
    return match ($class) {
        'b-critical' => 'critical',
        'b-warning' => 'warning',
        'b-active' => 'healthy',
        default => 'unavailable',
    };
}

function tracs_insight_compute_score(array $sections, array $monitoring): array {
    $metrics = $monitoring['metrics'] ?? [];
    $securityItems = $sections['security']['items'] ?? [];

    $badgeStatus = static function (array $items, string $label): string {
        $row = tracs_insight_find_badge_row($items, $label);
        return tracs_insight_status_from_badge_class($row['badge_class'] ?? '');
    };

    $inputs = [
        'cpu' => tracs_insight_status_score($metrics['cpu']['status'] ?? 'unavailable'),
        'ram' => tracs_insight_status_score($metrics['memory']['status'] ?? 'unavailable'),
        'disk' => tracs_insight_status_score($metrics['disk']['status'] ?? 'unavailable'),
        'billing' => tracs_insight_status_score($sections['billing']['status'] ?? 'unavailable'),
        'database' => tracs_insight_status_score($badgeStatus($securityItems, 'Database Reachable')),
        'storage' => tracs_insight_status_score($badgeStatus($securityItems, 'Storage Writable')),
        'runtime' => tracs_insight_status_score($badgeStatus($securityItems, 'PHP Version Supported')),
        'api' => tracs_insight_status_score($badgeStatus($securityItems, 'API Connection Healthy')),
    ];

    $weightedSum = 0.0;
    $weightTotal = 0.0;
    foreach (TRACS_INSIGHT_WEIGHTS as $key => $weight) {
        if (!isset($inputs[$key])) {
            continue;
        }
        $weightedSum += $inputs[$key] * $weight;
        $weightTotal += $weight;
    }
    $score = $weightTotal > 0 ? (int)round($weightedSum / $weightTotal) : 0;

    $label = 'Poor';
    foreach (TRACS_INSIGHT_BANDS as [$min, $bandLabel]) {
        if ($score >= $min) {
            $label = $bandLabel;
            break;
        }
    }

    return [
        'key' => 'health_score',
        'title' => 'Overall Health Score',
        'icon' => 'gauge',
        'type' => 'score',
        'status' => $score >= 90 ? 'healthy' : ($score >= 50 ? 'warning' : 'critical'),
        'score_input' => null,
        'items' => ['score' => $score, 'label' => $label],
    ];
}
