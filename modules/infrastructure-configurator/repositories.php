<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_infra_configurator_price_book(): array {
    return [
        'id' => 'TRACS-INFRA-BETA-2026.08',
        'name' => 'TRACS Infrastructure Beta Reference',
        'version' => '2026.08',
        'currency' => 'IDR',
        'effective_from' => '2026-08-01',
        'effective_until' => null,
    ];
}

function tracs_infra_configurator_product_types(): array {
    return [
        [
            'id' => 'DEDICATED_SERVER',
            'label' => 'Dedicated Server',
            'description' => 'CPU, RAM, storage, and RAID reference configuration.',
            'icon' => 'server',
        ],
        [
            'id' => 'VIRTUAL_MACHINE',
            'label' => 'Virtual Machine',
            'description' => 'vCPU, RAM, storage tier, and operating system estimate.',
            'icon' => 'box',
        ],
        [
            'id' => 'COLOCATION',
            'label' => 'Colocation',
            'description' => 'Data center, rack unit, power, setup, and deposit view.',
            'icon' => 'building-2',
        ],
        [
            'id' => 'CUSTOM_SERVICE',
            'label' => 'Custom Service',
            'description' => 'Manual or reference services outside standard products.',
            'icon' => 'wrench',
        ],
    ];
}
