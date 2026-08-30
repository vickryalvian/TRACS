<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
require_once __DIR__ . '/repositories.php';
require_once __DIR__ . '/pricing.php';

class InfrastructureConfiguratorController {
    public function getInitialViewModel(): array {
        $summary = tracs_infra_configurator_empty_summary();
        $totals = tracs_infra_configurator_summary_totals($summary);

        return [
            'price_book' => tracs_infra_configurator_price_book(),
            'products' => tracs_infra_configurator_product_types(),
            'pricing_summary' => array_merge($summary, $totals),
        ];
    }
}
