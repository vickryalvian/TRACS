<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);

function tracs_infra_configurator_price_book(): array {
    return [
        'id' => 'TRACS-INFRA-BETA-2026.08',
        'name' => 'TRACS Infrastructure Beta Reference',
        'version' => '2026.08',
        'currency' => 'IDR',
        'tax_rate' => 0.11,
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

function tracs_infra_configurator_rate_cards(): array {
    return [
        'dedicated_server' => [
            'cpu_options' => [
                ['id' => 'dual-xeon-e5-2620-v4', 'label' => '2 x Intel Xeon E5-2620 v4', 'cores' => 16, 'threads' => 32, 'mrc' => 2700000],
                ['id' => 'dual-xeon-e5-2696-v4', 'label' => '2 x Intel Xeon E5-2696 v4', 'cores' => 44, 'threads' => 88, 'mrc' => 3700000],
                ['id' => 'dual-epyc-7542', 'label' => '2 x AMD EPYC 7542', 'cores' => null, 'threads' => null, 'mrc' => 4800000],
                ['id' => 'dual-epyc-7502', 'label' => '2 x AMD EPYC 7502', 'cores' => null, 'threads' => null, 'mrc' => 7000000],
            ],
            'ram_options' => [
                ['id' => 'ram-64', 'label' => '64 GB', 'gb' => 64, 'mrc' => 1000000],
                ['id' => 'ram-128', 'label' => '128 GB', 'gb' => 128, 'mrc' => 1800000],
                ['id' => 'ram-256', 'label' => '256 GB', 'gb' => 256, 'mrc' => 2500000],
                ['id' => 'ram-512', 'label' => '512 GB', 'gb' => 512, 'mrc' => 4300000],
            ],
            'storage_options' => [
                ['id' => 'ssd-2x480', 'label' => '2 x 480 GB SSD', 'drives' => 2, 'drive_gb' => 480, 'type' => 'SSD', 'mrc' => 850000],
                ['id' => 'ssd-2x960', 'label' => '2 x 960 GB SSD', 'drives' => 2, 'drive_gb' => 960, 'type' => 'SSD', 'mrc' => 1500000],
                ['id' => 'sas-2x1000', 'label' => '2 x 1 TB SAS', 'drives' => 2, 'drive_gb' => 1000, 'type' => 'SAS', 'mrc' => 1000000],
                ['id' => 'ssd-2x2000', 'label' => '2 x 2 TB SSD', 'drives' => 2, 'drive_gb' => 2000, 'type' => 'SSD', 'mrc' => 2000000],
                ['id' => 'ssd-2x4000', 'label' => '2 x 4 TB SSD', 'drives' => 2, 'drive_gb' => 4000, 'type' => 'SSD', 'mrc' => 3600000],
                ['id' => 'ssd-2x8000', 'label' => '2 x 8 TB SSD', 'drives' => 2, 'drive_gb' => 8000, 'type' => 'SSD', 'mrc' => 5460000],
                ['id' => 'ssd-6x8000', 'label' => '6 x 8 TB SSD', 'drives' => 6, 'drive_gb' => 8000, 'type' => 'SSD', 'mrc' => 11300000],
                ['id' => 'ssd-2x12000', 'label' => '2 x 12 TB SSD', 'drives' => 2, 'drive_gb' => 12000, 'type' => 'SSD', 'mrc' => 6000000],
                ['id' => 'ssd-2x16000', 'label' => '2 x 16 TB SSD', 'drives' => 2, 'drive_gb' => 16000, 'type' => 'SSD', 'mrc' => 6200000],
                ['id' => 'sas-2x6000', 'label' => '2 x 6 TB SAS', 'drives' => 2, 'drive_gb' => 6000, 'type' => 'SAS', 'mrc' => 4000000],
                ['id' => 'sas-4x6000', 'label' => '4 x 6 TB SAS', 'drives' => 4, 'drive_gb' => 6000, 'type' => 'SAS', 'mrc' => 6000000],
                ['id' => 'sas-6x6000', 'label' => '6 x 6 TB SAS', 'drives' => 6, 'drive_gb' => 6000, 'type' => 'SAS', 'mrc' => 7500000],
                ['id' => 'nvme-2x1000', 'label' => '2 x 1 TB NVMe', 'drives' => 2, 'drive_gb' => 1000, 'type' => 'NVMe', 'mrc' => 2500000],
                ['id' => 'nvme-2x2000', 'label' => '2 x 2 TB NVMe', 'drives' => 2, 'drive_gb' => 2000, 'type' => 'NVMe', 'mrc' => 3000000],
                ['id' => 'nvme-2x3840', 'label' => '2 x 3.84 TB NVMe', 'drives' => 2, 'drive_gb' => 3840, 'type' => 'NVMe', 'mrc' => 3500000],
                ['id' => 'nvme-2x7860', 'label' => '2 x 7.86 TB NVMe U2', 'drives' => 2, 'drive_gb' => 7860, 'type' => 'NVMe', 'mrc' => 4300000],
                ['id' => 'nvme-2x15360', 'label' => '2 x 15.36 TB NVMe U2', 'drives' => 2, 'drive_gb' => 15360, 'type' => 'NVMe', 'mrc' => 12100000],
            ],
            'raid_options' => [
                ['id' => 'NONE', 'label' => 'None', 'usable_factor' => 1.0],
                ['id' => 'RAID_0', 'label' => 'RAID 0', 'usable_factor' => 1.0],
                ['id' => 'RAID_1', 'label' => 'RAID 1', 'usable_factor' => 0.5],
                ['id' => 'RAID_5', 'label' => 'RAID 5', 'usable_factor' => null],
                ['id' => 'RAID_6', 'label' => 'RAID 6', 'usable_factor' => null],
            ],
        ],
        'virtual_machine' => [
            'storage_tiers' => [
                ['id' => 'standard', 'label' => 'SSD', 'price_per_gb' => 1500],
                ['id' => 'hdd', 'label' => 'HDD', 'price_per_gb' => 2000],
                ['id' => 'nvme', 'label' => 'NVMe', 'price_per_gb' => 3000],
            ],
            'os_options' => [
                ['id' => 'linux', 'label' => 'Linux', 'mrc' => 0],
                ['id' => 'windows-standard', 'label' => 'Windows Server Standard', 'mrc' => 350000],
            ],
            'vcpu_price' => 50000,
            'ram_price_per_gb' => 50000,
        ],
        'colocation' => [
            'data_centers' => [
                ['id' => 'bogor', 'label' => 'Bogor DC', 'rack_u_mrc' => 350000, 'ampere_mrc' => 600000, 'setup_fee' => 1000000, 'deposit_months' => 3],
                ['id' => 'idc', 'label' => 'IDC', 'rack_u_mrc' => 425000, 'ampere_mrc' => 700000, 'setup_fee' => 1250000, 'deposit_months' => 3],
                ['id' => 'dci', 'label' => 'DCI', 'rack_u_mrc' => 475000, 'ampere_mrc' => 800000, 'setup_fee' => 1500000, 'deposit_months' => 3],
                ['id' => 'bali', 'label' => 'Bali', 'rack_u_mrc' => 375000, 'ampere_mrc' => 650000, 'setup_fee' => 1000000, 'deposit_months' => 2],
                ['id' => 'drb-bddc', 'label' => 'DRB / BDDC', 'rack_u_mrc' => 400000, 'ampere_mrc' => 675000, 'setup_fee' => 1100000, 'deposit_months' => 2],
            ],
        ],
        'custom_service' => [
            'reference_services' => [
                ['id' => 'migration', 'label' => 'Migration Support', 'unit_price' => 1000000],
                ['id' => 'managed-backup', 'label' => 'Managed Backup', 'unit_price' => 750000],
                ['id' => 'remote-hands', 'label' => 'Remote Hands', 'unit_price' => 500000],
            ],
            'billing_types' => [
                ['id' => 'one_time', 'label' => 'One-Time'],
                ['id' => 'monthly', 'label' => 'Monthly'],
            ],
        ],
        'shared_addons' => [
            ['id' => 'windows-server-license', 'label' => 'Windows Server License', 'billing' => 'monthly', 'unit_price' => 500000],
            ['id' => 'sql-server-2-core', 'label' => 'SQL Server per 2 Core', 'billing' => 'monthly', 'unit_price' => 2500000],
            ['id' => 'cpanel-ds', 'label' => 'cPanel for DS', 'billing' => 'monthly', 'unit_price' => 500000],
            ['id' => 'bandwidth-100m', 'label' => 'Dedicated Bandwidth 100 Mbps', 'billing' => 'monthly', 'unit_price' => 5000000],
            ['id' => 'bandwidth-200m', 'label' => 'Dedicated Bandwidth 200 Mbps', 'billing' => 'monthly', 'unit_price' => 9000000],
            ['id' => 'xconnect-dci', 'label' => 'Xconnect DCI', 'billing' => 'monthly', 'unit_price' => 3500000],
            ['id' => 'additional-port', 'label' => 'Additional Port', 'billing' => 'monthly', 'unit_price' => 200000],
            ['id' => 'port-setup', 'label' => 'Port Setup Fee', 'billing' => 'one_time', 'unit_price' => 250000],
            ['id' => 'default-ds-setup', 'label' => 'Default DS Setup', 'billing' => 'one_time', 'unit_price' => 250000],
            ['id' => 'webserver-install', 'label' => 'Install WebServer', 'billing' => 'one_time', 'unit_price' => 250000],
            ['id' => 'whm-cpanel-install', 'label' => 'Install WHM cPanel', 'billing' => 'one_time', 'unit_price' => 370000],
            ['id' => 'install-cloudlinux', 'label' => 'Install CloudLinux', 'billing' => 'one_time', 'unit_price' => 150000],
            ['id' => 'install-litespeed', 'label' => 'Install Litespeed', 'billing' => 'one_time', 'unit_price' => 250000],
            ['id' => 'basic-config', 'label' => 'Basic Config', 'billing' => 'one_time', 'unit_price' => 100000],
            ['id' => 'basic-security', 'label' => 'Basic Security', 'billing' => 'one_time', 'unit_price' => 100000],
        ],
    ];
}
