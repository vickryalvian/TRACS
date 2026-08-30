<?php
require_once __DIR__ . '/../modules/infrastructure-configurator/repositories.php';
require_once __DIR__ . '/../modules/infrastructure-configurator/pricing.php';

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

$summary = tracs_infra_configurator_summary_totals([
    'recurring_charges' => [['amount' => 2700000], ['amount' => 1000000]],
    'one_time_charges' => [['amount' => 500000]],
]);
infra_configurator_expect($summary['mrc'] === 3700000.0, 'Unexpected MRC total.');
infra_configurator_expect($summary['otc'] === 500000.0, 'Unexpected OTC total.');
infra_configurator_expect(tracs_infra_configurator_money(2700000) === 'Rp2.700.000', 'Unexpected IDR format.');

echo "Infrastructure configurator foundation checks passed.\n";
