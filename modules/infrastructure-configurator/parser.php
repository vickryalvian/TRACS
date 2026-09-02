<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_infra_configurator_parse_requirement(string $text): array {
    $normalized = strtolower(trim($text));
    $result = [
        'product_type' => null,
        'minimum_storage_gb' => null,
        'ram_gb' => null,
        'vcpu' => null,
        'storage_tier' => null,
        'os' => null,
        'data_center_id' => null,
        'rack_u' => null,
        'ampere' => null,
        'budget_monthly_idr' => null,
    ];

    if ($normalized === '') {
        return $result;
    }

    if (preg_match('/\b(colo|colocation)\b/', $normalized)) {
        $result['product_type'] = 'COLOCATION';
    } elseif (preg_match('/\b(vm|virtual machine)\b/', $normalized)) {
        $result['product_type'] = 'VIRTUAL_MACHINE';
    } elseif (preg_match('/\b(server|dedicated)\b/', $normalized)) {
        $result['product_type'] = 'DEDICATED_SERVER';
    }

    if (preg_match('/\b(windows)\b/', $normalized)) {
        $result['os'] = 'windows-standard';
    } elseif (preg_match('/\b(linux)\b/', $normalized)) {
        $result['os'] = 'linux';
    }

    if (preg_match('/\b(nvme)\b/', $normalized)) {
        $result['storage_tier'] = 'nvme';
    } elseif (preg_match('/\b(ssd)\b/', $normalized)) {
        $result['storage_tier'] = 'standard';
    }

    if (preg_match('/\b(\d+)\s*(?:v?cpu|core|cores)\b/', $normalized, $match)) {
        $result['vcpu'] = (int)$match[1];
    }

    $budgetMatches = [];
    preg_match_all('/(rp\\.?\\s*)?(\d{1,3}(?:[.,]\d{3})+|\d+(?:[.,]\d+)?)\\s*(juta|jt|mio)?\\b/', $normalized, $budgetMatches, PREG_SET_ORDER);
    foreach ($budgetMatches as $match) {
        if (empty($match[1]) && empty($match[3])) {
            continue;
        }
        $rawBudget = str_replace([',', '.'], '', $match[2]);
        $budget = (int)$rawBudget;
        if (!empty($match[3]) && $budget < 1000000) {
            $budget *= 1000000;
        }
        $result['budget_monthly_idr'] = $budget;
        break;
    }

    if (preg_match('/\b(\d+)\s*gb\s*ram\b/', $normalized, $match)) {
        $result['ram_gb'] = (int)$match[1];
    } elseif ($result['product_type'] === 'VIRTUAL_MACHINE' && preg_match('/\b(?:v?cpu|core|cores)\b\s*(\d+)\s*gb\b/', $normalized, $match)) {
        $result['ram_gb'] = (int)$match[1];
    } elseif ($result['product_type'] === 'VIRTUAL_MACHINE' && preg_match('/\b\d+\s*(?:v?cpu|core|cores)\s+(\d+)\s*gb\b/', $normalized, $match)) {
        $result['ram_gb'] = (int)$match[1];
    }

    $storageMatches = [];
    preg_match_all('/\b(\d+(?:\.\d+)?)\s*(tb|gb)\b/', $normalized, $storageMatches, PREG_SET_ORDER);
    foreach ($storageMatches as $match) {
        $value = (float)$match[1];
        $gb = $match[2] === 'tb' ? (int)round($value * 1000) : (int)round($value);
        $token = $match[0];

        if (str_contains($token, 'gb') && $gb === $result['ram_gb']) {
            continue;
        }

        if ($match[2] === 'tb' || $result['budget_monthly_idr'] || preg_match('/\b' . preg_quote($match[0], '/') . '\s*(ssd|sas|nvme|storage|disk)\b/', $normalized)) {
            $result['minimum_storage_gb'] = $gb;
        }
    }

    if ($result['product_type'] === 'COLOCATION') {
        $dataCenters = [
            'bogor' => 'bogor',
            'idc' => 'idc',
            'dci' => 'dci',
            'bali' => 'bali',
            'drb' => 'drb-bddc',
            'bddc' => 'drb-bddc',
        ];
        foreach ($dataCenters as $needle => $id) {
            if (str_contains($normalized, $needle)) {
                $result['data_center_id'] = $id;
                break;
            }
        }
        if (preg_match('/\b(\d+)\s*u\b/', $normalized, $match)) {
            $result['rack_u'] = (int)$match[1];
        }
        if (preg_match('/\b(\d+)\s*(?:a|ampere)\b/', $normalized, $match)) {
            $result['ampere'] = (int)$match[1];
        }
    }

    return $result;
}

function tracs_infra_configurator_recommend_dedicated(array $parsed, array $rateCards): array {
    $card = $rateCards['dedicated_server'] ?? [];
    $cpuOptions = $card['cpu_options'] ?? [];
    $ramOptions = $card['ram_options'] ?? [];
    $storageOptions = $card['storage_options'] ?? [];
    $raidId = 'RAID_1';
    $minimumStorageGb = max(0, (int)($parsed['minimum_storage_gb'] ?? 0));
    $budget = isset($parsed['budget_monthly_idr']) ? max(0, (int)$parsed['budget_monthly_idr']) : 0;
    $preferredTier = $parsed['storage_tier'] === 'nvme' ? 'NVMe' : 'SSD';
    $assumptions = [];

    $matches = [];
    foreach ($cpuOptions as $cpu) {
        foreach ($ramOptions as $ram) {
            if (!empty($parsed['ram_gb']) && (int)($ram['gb'] ?? 0) < (int)$parsed['ram_gb']) {
                continue;
            }
            foreach ($storageOptions as $storage) {
                if ($preferredTier && strcasecmp((string)($storage['type'] ?? ''), $preferredTier) !== 0) {
                    continue;
                }
                $capacity = tracs_infra_configurator_storage_capacity($storage, $raidId);
                $mrc = (float)($cpu['mrc'] ?? 0) + (float)($ram['mrc'] ?? 0) + (float)($storage['mrc'] ?? 0);
                if ($minimumStorageGb > 0 && $capacity['usable_gb'] < $minimumStorageGb) {
                    continue;
                }
                if ($budget > 0 && $mrc > $budget) {
                    continue;
                }
                $matches[] = [
                    'cpu' => $cpu,
                    'ram' => $ram,
                    'storage' => $storage,
                    'capacity' => $capacity,
                    'mrc' => $mrc,
                ];
            }
        }
    }

    usort($matches, function (array $a, array $b) use ($minimumStorageGb): int {
        if ($minimumStorageGb > 0) {
            return ($a['mrc'] <=> $b['mrc']) ?: ($a['capacity']['usable_gb'] <=> $b['capacity']['usable_gb']);
        }
        return ($b['mrc'] <=> $a['mrc'])
            ?: ((int)($b['ram']['gb'] ?? 0) <=> (int)($a['ram']['gb'] ?? 0))
            ?: ($b['capacity']['usable_gb'] <=> $a['capacity']['usable_gb']);
    });

    if (!$matches) {
        return ['configuration' => null, 'assumptions' => [], 'alternatives' => [], 'error' => 'No valid configuration found.'];
    }

    $best = $matches[0];
    if (empty($parsed['ram_gb']) && $budget === 0) {
        $assumptions[] = 'RAM defaulted to 64 GB.';
    } elseif (empty($parsed['ram_gb']) && $budget > 0) {
        $assumptions[] = 'RAM optimized within the monthly budget.';
    }
    if (empty($parsed['storage_tier'])) {
        $assumptions[] = 'Storage type defaulted to SSD.';
    }
    $assumptions[] = 'RAID defaulted to RAID 1.';
    $assumptions[] = $budget > 0 ? 'CPU optimized within the monthly budget.' : 'CPU defaulted to 2 x Intel Xeon E5-2620 v4.';

    return [
        'configuration' => [
            'product_type' => 'DEDICATED_SERVER',
            'cpu_id' => $best['cpu']['id'] ?? null,
            'ram_id' => $best['ram']['id'] ?? null,
            'storage_id' => $best['storage']['id'] ?? null,
            'raid_id' => $raidId,
            'mrc' => $best['mrc'],
            'raw_gb' => $best['capacity']['raw_gb'],
            'usable_gb' => $best['capacity']['usable_gb'],
        ],
        'assumptions' => $assumptions,
        'alternatives' => array_slice(array_values($matches), 1, 2),
        'error' => null,
    ];
}

function tracs_infra_configurator_recommend_vm(array $parsed, array $rateCards): array {
    $card = $rateCards['virtual_machine'] ?? [];
    $storageTiers = $card['storage_tiers'] ?? [];
    $osOptions = $card['os_options'] ?? [];
    $tier = tracs_infra_configurator_pick_by_id($storageTiers, $parsed['storage_tier'] ?: 'standard');
    $os = tracs_infra_configurator_pick_by_id($osOptions, $parsed['os'] ?: 'linux');
    $budget = isset($parsed['budget_monthly_idr']) ? max(0, (int)$parsed['budget_monthly_idr']) : 0;
    $storageGb = max(1, (int)($parsed['minimum_storage_gb'] ?? 100));
    $vcpuChoices = empty($parsed['vcpu']) ? [2, 4, 8, 16, 32, 64] : [(int)$parsed['vcpu']];
    $ramChoices = empty($parsed['ram_gb']) ? [4, 8, 16, 32, 64, 128, 256, 512] : [(int)$parsed['ram_gb']];
    $matches = [];

    foreach ($vcpuChoices as $vcpu) {
        foreach ($ramChoices as $ramGb) {
            $mrc = tracs_infra_configurator_vm_mrc(
                $vcpu,
                $ramGb,
                $storageGb,
                $tier,
                $os,
                (float)($card['vcpu_price'] ?? 0),
                (float)($card['ram_price_per_gb'] ?? 0)
            );
            if ($budget > 0 && $mrc > $budget) {
                continue;
            }
            $matches[] = ['vcpu' => $vcpu, 'ram_gb' => $ramGb, 'storage_gb' => $storageGb, 'tier' => $tier, 'os' => $os, 'mrc' => $mrc];
        }
    }

    usort($matches, fn(array $a, array $b): int => ($b['mrc'] <=> $a['mrc']) ?: ($b['vcpu'] <=> $a['vcpu']) ?: ($b['ram_gb'] <=> $a['ram_gb']));

    if (!$matches) {
        return ['configuration' => null, 'assumptions' => [], 'error' => 'No valid configuration found.'];
    }

    $best = $matches[0];
    return [
        'configuration' => [
            'product_type' => 'VIRTUAL_MACHINE',
            'vcpu' => $best['vcpu'],
            'ram_gb' => $best['ram_gb'],
            'storage_gb' => $best['storage_gb'],
            'storage_tier' => $best['tier']['id'] ?? null,
            'os' => $best['os']['id'] ?? null,
            'mrc' => $best['mrc'],
        ],
        'assumptions' => array_values(array_filter([
            empty($parsed['vcpu']) && $budget > 0 ? 'vCPU optimized within the monthly budget.' : null,
            empty($parsed['ram_gb']) && $budget > 0 ? 'RAM optimized within the monthly budget.' : null,
            empty($parsed['minimum_storage_gb']) ? 'Storage defaulted to 100 GB.' : null,
            empty($parsed['os']) ? 'Operating system defaulted to Linux.' : null,
        ])),
        'error' => null,
    ];
}

function tracs_infra_configurator_pick_ram_id(?int $requestedGb, array $ramOptions): ?string {
    if ($requestedGb === null) {
        return $ramOptions[0]['id'] ?? null;
    }

    foreach ($ramOptions as $ram) {
        if ((int)($ram['gb'] ?? 0) >= $requestedGb) {
            return $ram['id'];
        }
    }

    return $ramOptions ? end($ramOptions)['id'] : null;
}

function tracs_infra_configurator_pick_by_id(array $items, ?string $id): array {
    foreach ($items as $item) {
        if (($item['id'] ?? null) === $id) {
            return $item;
        }
    }

    return $items[0] ?? [];
}
