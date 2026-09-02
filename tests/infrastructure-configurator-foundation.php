<?php
require_once __DIR__ . '/../modules/infrastructure-configurator/repositories.php';
require_once __DIR__ . '/../modules/infrastructure-configurator/pricing.php';
require_once __DIR__ . '/../modules/infrastructure-configurator/parser.php';

function infra_configurator_expect(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$products = tracs_infra_configurator_product_types();
infra_configurator_expect(count($products) === 4, 'Expected four product types.');
infra_configurator_expect(
    array_column($products, 'id') === ['DEDICATED_SERVER', 'VIRTUAL_MACHINE', 'COLOCATION', 'CUSTOM_SERVICE'],
    'Unexpected product type order.'
);

$book = tracs_infra_configurator_price_book();
infra_configurator_expect($book['currency'] === 'IDR', 'Expected IDR price book.');
infra_configurator_expect($book['id'] === 'TRACS-INFRA-BETA-2026.08', 'Unexpected beta price book id.');
infra_configurator_expect($book['tax_rate'] === 0.11, 'Unexpected PPN rate.');

$summary = tracs_infra_configurator_summary_totals([
    'recurring_charges' => [['amount' => 2700000], ['amount' => 1000000]],
    'one_time_charges' => [['amount' => 500000]],
]);
infra_configurator_expect($summary['mrc'] === 3700000.0, 'Unexpected MRC total.');
infra_configurator_expect($summary['otc'] === 500000.0, 'Unexpected OTC total.');
infra_configurator_expect(tracs_infra_configurator_money(2700000) === 'Rp2.700.000', 'Unexpected IDR format.');

$rates = tracs_infra_configurator_rate_cards();
infra_configurator_expect(count($rates['dedicated_server']['cpu_options']) >= 3, 'Expected dedicated CPU references.');
infra_configurator_expect($rates['dedicated_server']['cpu_options'][0]['mrc'] === 2700000, 'Unexpected beta CPU reference.');
infra_configurator_expect($rates['dedicated_server']['ram_options'][0]['mrc'] === 1000000, 'Unexpected beta RAM reference.');
infra_configurator_expect(count($rates['dedicated_server']['storage_options']) >= 15, 'Expected workbook dedicated storage references.');
infra_configurator_expect(count($rates['virtual_machine']['storage_tiers']) >= 3, 'Expected VM storage tiers.');
infra_configurator_expect($rates['virtual_machine']['vcpu_price'] === 50000, 'Unexpected workbook VPS CPU unit price.');
infra_configurator_expect(count($rates['shared_addons']) >= 14, 'Expected shared add-on references.');

$capacity = tracs_infra_configurator_storage_capacity(['drives' => 2, 'drive_gb' => 2000], 'RAID_1');
infra_configurator_expect($capacity['raw_gb'] === 4000, 'Unexpected raw storage capacity.');
infra_configurator_expect($capacity['usable_gb'] === 2000, 'Unexpected RAID 1 usable capacity.');

$raidSix = tracs_infra_configurator_storage_capacity(['drives' => 4, 'drive_gb' => 960], 'RAID_6');
infra_configurator_expect($raidSix['usable_gb'] === 1920, 'Unexpected RAID 6 usable capacity.');

$vmMrc = tracs_infra_configurator_vm_mrc(
    2,
    4,
    100,
    ['price_per_gb' => 1000],
    ['mrc' => 350000],
    150000,
    75000
);
infra_configurator_expect($vmMrc === 1050000.0, 'Unexpected VM MRC.');

$colo = tracs_infra_configurator_colocation_totals(
    ['rack_u_mrc' => 350000, 'ampere_mrc' => 600000, 'setup_fee' => 1000000, 'deposit_months' => 3],
    2,
    1
);
infra_configurator_expect($colo['mrc'] === 1300000.0, 'Unexpected colocation MRC.');
infra_configurator_expect($colo['otc'] === 4900000.0, 'Unexpected colocation OTC.');

$custom = tracs_infra_configurator_custom_totals(3, 'monthly', 500000);
infra_configurator_expect($custom['mrc'] === 1500000.0, 'Unexpected custom service MRC.');
infra_configurator_expect($custom['otc'] === 0.0, 'Unexpected custom service OTC.');

$cost = tracs_infra_configurator_cost_based_reference([
    'capex' => 36000000,
    'recovery_months' => 36,
    'maintenance_rate' => 0.1,
    'rack_and_power_cost' => 500000,
    'overhead_rate' => 0.1,
    'margin_rate' => 0.2,
]);
infra_configurator_expect($cost['monthly_capex'] === 1000000.0, 'Unexpected monthly CAPEX.');
infra_configurator_expect($cost['commercial_reference'] === 2112000.0, 'Unexpected commercial reference.');

$serverParsed = tracs_infra_configurator_parse_requirement('server 2TB');
infra_configurator_expect($serverParsed['product_type'] === 'DEDICATED_SERVER', 'Expected dedicated server parse.');
infra_configurator_expect($serverParsed['minimum_storage_gb'] === 2000, 'Expected server storage parse.');

$budgetParsed = tracs_infra_configurator_parse_requirement('server 2TB Rp. 8.000.000');
infra_configurator_expect($budgetParsed['budget_monthly_idr'] === 8000000, 'Expected rupiah budget parse.');

$budgetStorageParsed = tracs_infra_configurator_parse_requirement('500 GB with Rp. 10.000.000');
infra_configurator_expect($budgetStorageParsed['minimum_storage_gb'] === 500, 'Expected budget storage parse.');
infra_configurator_expect($budgetStorageParsed['budget_monthly_idr'] === 10000000, 'Expected budget-only rupiah parse.');

$vmParsed = tracs_infra_configurator_parse_requirement('Windows VM 4 core 16GB');
infra_configurator_expect($vmParsed['product_type'] === 'VIRTUAL_MACHINE', 'Expected VM parse.');
infra_configurator_expect($vmParsed['vcpu'] === 4, 'Expected VM vCPU parse.');
infra_configurator_expect($vmParsed['ram_gb'] === 16, 'Expected VM RAM parse.');
infra_configurator_expect($vmParsed['os'] === 'windows-standard', 'Expected VM OS parse.');

$coloParsed = tracs_infra_configurator_parse_requirement('colo Bogor 2U 2A');
infra_configurator_expect($coloParsed['product_type'] === 'COLOCATION', 'Expected colocation parse.');
infra_configurator_expect($coloParsed['data_center_id'] === 'bogor', 'Expected colocation DC parse.');
infra_configurator_expect($coloParsed['rack_u'] === 2, 'Expected colocation rack parse.');
infra_configurator_expect($coloParsed['ampere'] === 2, 'Expected colocation ampere parse.');

$recommendation = tracs_infra_configurator_recommend_dedicated($serverParsed, $rates);
infra_configurator_expect($recommendation['configuration']['storage_id'] === 'ssd-2x2000', 'Expected dedicated storage recommendation.');
infra_configurator_expect(in_array('RAID defaulted to RAID 1.', $recommendation['assumptions'], true), 'Expected recommendation assumption.');

$budgetRecommendation = tracs_infra_configurator_recommend_dedicated($budgetParsed, $rates);
infra_configurator_expect($budgetRecommendation['configuration']['mrc'] <= 8000000, 'Expected budget-aware recommendation.');
infra_configurator_expect($budgetRecommendation['configuration']['usable_gb'] >= 2000, 'Expected budget recommendation capacity.');

$vmBudgetRecommendation = tracs_infra_configurator_recommend_vm($budgetStorageParsed, $rates);
infra_configurator_expect($vmBudgetRecommendation['configuration']['product_type'] === 'VIRTUAL_MACHINE', 'Expected VM budget recommendation.');
infra_configurator_expect($vmBudgetRecommendation['configuration']['storage_gb'] === 500, 'Expected VM budget storage.');
infra_configurator_expect($vmBudgetRecommendation['configuration']['mrc'] <= 10000000, 'Expected VM budget limit.');

echo "Infrastructure configurator foundation checks passed.\n";
