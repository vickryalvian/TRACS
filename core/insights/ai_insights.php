<?php

function tracs_insight_ai_insights(mysqli $conn, array $monitoring, array $context = []): array {
    return [
        'key' => 'ai_insights',
        'title' => 'AI Insights',
        'icon' => 'sparkles',
        'type' => 'placeholder',
        'status' => null,
        'items' => [
            ['text' => 'Trend-based recommendations are planned for a future release, for example: disk usage has increased over the past week, logs are growing unusually fast, or RAM consumption is higher than a recent baseline.'],
        ],
    ];
}
