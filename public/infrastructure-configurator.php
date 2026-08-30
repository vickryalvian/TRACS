<?php
require_once __DIR__ . '/../core/security/csrf.php';
tracs_start_session();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/auth/auth_check.php';
require_once __DIR__ . '/../core/access_control.php';
tracs_require_page_permission($conn, 'dashboard.view');
require_once __DIR__ . '/../modules/alert-ticker/controller.php';
require_once __DIR__ . '/../modules/infrastructure-configurator/controller.php';
require_once __DIR__ . '/includes/page_helpers.php';

$uid = (int)($_SESSION['user_id'] ?? 0);
$user_email = $_SESSION['user_email'] ?? 'operator@tracs.local';

$TC = new AlertTickerController($conn, $uid);
$ticker_items = $TC->formatAlertsForTicker();
$critical_count = 0;

$IC = new InfrastructureConfiguratorController();
$view_model = $IC->getInitialViewModel();
$price_book = $view_model['price_book'];
$products = $view_model['products'];
$pricing_summary = $view_model['pricing_summary'];

$page_title = 'Infrastructure Configurator';
$active_page = 'infrastructure-configurator';
include __DIR__ . '/includes/header.php';

$infra_configurator_js_v = @filemtime(__DIR__ . '/assets/infrastructure-configurator.js') ?: time();
?>
<main class="main">
  <div class="main-inner infra-configurator-page" data-infra-configurator-page>
    <div class="topbar infra-configurator-topbar">
      <div class="topbar-left">
        <div class="infra-configurator-title-row">
          <div class="page-title">Infrastructure Configurator</div>
          <span class="badge b-warning"><span class="badge-dot"></span>Beta</span>
        </div>
        <div class="page-sub">Reference infrastructure estimates for Sales. Existing TRACS workflows stay unchanged.</div>
      </div>
      <div class="infra-configurator-meta">
        <span class="panel-meta">Price book</span>
        <strong><?=esc($price_book['id'] ?? 'TRACS-INFRA-BETA')?></strong>
      </div>
    </div>

    <section class="infra-configurator-grid">
      <section class="panel infra-configurator-builder" aria-labelledby="infraConfiguratorBuilderTitle">
        <div class="panel-head">
          <div>
            <span class="panel-title" id="infraConfiguratorBuilderTitle">Product Type</span>
            <div class="panel-meta">Choose a product to start the beta configuration.</div>
          </div>
        </div>

        <div class="infra-product-grid" role="list">
          <?php foreach ($products as $index => $product): ?>
          <button
            type="button"
            class="infra-product-card <?=$index === 0 ? 'is-active' : ''?>"
            data-infra-product="<?=esc($product['id'])?>"
            role="listitem"
            aria-pressed="<?=$index === 0 ? 'true' : 'false'?>"
          >
            <i data-lucide="<?=esc($product['icon'])?>" class="icon-md"></i>
            <span><?=esc($product['label'])?></span>
            <small><?=esc($product['description'])?></small>
          </button>
          <?php endforeach; ?>
        </div>

        <div class="infra-configurator-placeholder" data-infra-product-panel aria-live="polite">
          <div class="empty">
            <div class="empty-ic">◇</div>
            <div class="empty-t" data-infra-panel-title>Dedicated Server configurator</div>
            <div class="empty-s" data-infra-panel-copy>Manual CPU, RAM, storage, and RAID fields arrive in Phase 2.</div>
          </div>
        </div>
      </section>

      <aside class="panel infra-pricing-summary" aria-labelledby="infraPricingSummaryTitle">
        <div class="panel-head">
          <div>
            <span class="panel-title" id="infraPricingSummaryTitle">Pricing Summary</span>
            <div class="panel-meta">Recurring and one-time charges stay separate.</div>
          </div>
        </div>

        <div class="infra-summary-section">
          <div class="infra-summary-label">Recurring Charges</div>
          <div class="infra-summary-empty">No recurring charges selected.</div>
          <div class="infra-summary-total">
            <span>MRC</span>
            <strong><?=esc(tracs_infra_configurator_money((float)$pricing_summary['mrc']))?></strong>
          </div>
        </div>

        <div class="infra-summary-section">
          <div class="infra-summary-label">One-Time Charges</div>
          <div class="infra-summary-empty">No one-time charges selected.</div>
          <div class="infra-summary-total">
            <span>OTC</span>
            <strong><?=esc(tracs_infra_configurator_money((float)$pricing_summary['otc']))?></strong>
          </div>
        </div>

        <div class="infra-price-book">
          <span><?=esc($price_book['name'] ?? 'Beta Reference')?></span>
          <strong><?=esc($price_book['currency'] ?? 'IDR')?> · v<?=esc($price_book['version'] ?? 'beta')?></strong>
        </div>
      </aside>
    </section>
  </div>
</main>

<script src="assets/infrastructure-configurator.js?v=<?=$infra_configurator_js_v?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
