<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_infra_configurator_money(?float $value): string {
    return $value === null ? 'Not available' : 'Rp' . number_format($value, 0, ',', '.');
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

function tracs_infra_configurator_storage_capacity(array $storage, string $raidId): array {
    $drives = max(0, (int)($storage['drives'] ?? 0));
    $driveGb = max(0, (int)($storage['drive_gb'] ?? 0));
    $rawGb = $drives * $driveGb;

    if ($raidId === 'RAID_5') {
        $usableGb = $drives > 1 ? ($drives - 1) * $driveGb : 0;
    } elseif ($raidId === 'RAID_6') {
        $usableGb = $drives > 2 ? ($drives - 2) * $driveGb : 0;
    } elseif ($raidId === 'NONE' || $raidId === 'RAID_0') {
        $usableGb = $rawGb;
    } else {
        $usableGb = (int)floor($rawGb * 0.5);
    }

    return ['raw_gb' => $rawGb, 'usable_gb' => $usableGb];
}

function tracs_infra_configurator_vm_mrc(
    int $vcpu,
    int $ramGb,
    int $storageGb,
    array $storageTier,
    array $os,
    float $vcpuPrice = 150000,
    float $ramPricePerGb = 75000
): float {
    return max(1, $vcpu) * $vcpuPrice
        + max(1, $ramGb) * $ramPricePerGb
        + max(1, $storageGb) * (float)($storageTier['price_per_gb'] ?? 0)
        + (float)($os['mrc'] ?? 0);
}

function tracs_infra_configurator_colocation_totals(array $dataCenter, int $rackU, int $ampere): array {
    $mrc = max(1, $rackU) * (float)($dataCenter['rack_u_mrc'] ?? 0)
        + max(1, $ampere) * (float)($dataCenter['ampere_mrc'] ?? 0);
    $setupFee = (float)($dataCenter['setup_fee'] ?? 0);
    $depositMonths = max(0, (int)($dataCenter['deposit_months'] ?? 0));

    return [
        'mrc' => $mrc,
        'setup_fee' => $setupFee,
        'deposit' => $mrc * $depositMonths,
        'otc' => $setupFee + ($mrc * $depositMonths),
    ];
}

function tracs_infra_configurator_custom_totals(int $quantity, string $billingType, float $unitPrice): array {
    $total = max(1, $quantity) * max(0, $unitPrice);

    return [
        'mrc' => $billingType === 'monthly' ? $total : 0.0,
        'otc' => $billingType === 'monthly' ? 0.0 : $total,
    ];
}

function tracs_infra_configurator_cost_based_reference(array $input): array {
    $capex = max(0, (float)($input['capex'] ?? 0));
    $recoveryMonths = max(1, (int)($input['recovery_months'] ?? 1));
    $maintenanceRate = max(0, (float)($input['maintenance_rate'] ?? 0));
    $rackAndPowerCost = max(0, (float)($input['rack_and_power_cost'] ?? 0));
    $overheadRate = max(0, (float)($input['overhead_rate'] ?? 0));
    $marginRate = max(0, (float)($input['margin_rate'] ?? 0));

    $monthlyCapex = $capex / $recoveryMonths;
    $maintenance = $monthlyCapex * $maintenanceRate;
    $overhead = ($monthlyCapex + $maintenance + $rackAndPowerCost) * $overheadRate;
    $internalCost = $monthlyCapex + $maintenance + $rackAndPowerCost + $overhead;
    $reference = $internalCost * (1 + $marginRate);

    return [
        'monthly_capex' => $monthlyCapex,
        'maintenance' => $maintenance,
        'rack_and_power_cost' => $rackAndPowerCost,
        'overhead' => $overhead,
        'internal_cost' => $internalCost,
        'commercial_reference' => $reference,
    ];
}
