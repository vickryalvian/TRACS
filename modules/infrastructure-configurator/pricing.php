<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_infra_configurator_money(?float $value): string {
    return $value === null ? '—' : 'Rp' . number_format($value, 0, ',', '.');
}

function tracs_infra_configurator_empty_summary(): array {
    return [
        'currency' => 'IDR',
        'recurring_charges' => [],
        'one_time_charges' => [],
        'mrc' => 0,
        'otc' => 0,
    ];
}

function tracs_infra_configurator_summary_totals(array $summary): array {
    $recurring = $summary['recurring_charges'] ?? [];
    $oneTime = $summary['one_time_charges'] ?? [];

    return [
        'mrc' => array_sum(array_map(fn($item) => (float)($item['amount'] ?? 0), is_array($recurring) ? $recurring : [])),
        'otc' => array_sum(array_map(fn($item) => (float)($item['amount'] ?? 0), is_array($oneTime) ? $oneTime : [])),
    ];
}
