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
$rate_cards = $view_model['rate_cards'];
$pricing_summary = $view_model['pricing_summary'];

$page_title = 'Configurator';
$active_page = 'configurator';
include __DIR__ . '/includes/header.php';

$infra_configurator_js_v = @filemtime(__DIR__ . '/assets/infrastructure-configurator.js') ?: time();
?>
<main class="main">
  <div class="main-inner infra-configurator-page" data-infra-configurator-page data-unsaved-ignore>
    <div class="topbar infra-configurator-topbar">
      <div class="topbar-left">
        <div class="infra-configurator-title-row">
          <div class="page-title">Configurator</div>
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

        <div class="infra-product-grid">
          <?php foreach ($products as $index => $product): ?>
          <button
            type="button"
            class="infra-product-card <?=$index === 0 ? 'is-active' : ''?>"
            data-infra-product="<?=esc($product['id'])?>"
            aria-pressed="<?=$index === 0 ? 'true' : 'false'?>"
          >
            <i data-lucide="<?=esc($product['icon'])?>" class="icon-md"></i>
            <span><?=esc($product['label'])?></span>
            <small><?=esc($product['description'])?></small>
          </button>
          <?php endforeach; ?>
        </div>

        <div class="infra-smart-box" data-infra-smart-box>
          <label class="infra-field">
            <span>Describe Client Requirement</span>
            <textarea class="form-input" rows="2" data-infra-input="smartRequirement" placeholder="server 2TB Rp. 8.000.000"></textarea>
          </label>
          <div class="infra-smart-actions">
            <button type="button" class="btn primary" data-infra-generate>Generate</button>
            <span data-infra-smart-status aria-live="polite">Select a product type or describe the client's requirement to start.</span>
          </div>
          <div class="infra-assumptions" data-infra-assumptions hidden>
            <strong>Assumptions</strong>
            <ul data-infra-assumption-list></ul>
          </div>
          <div class="infra-recommendation" data-infra-recommendation hidden>
            <strong>Recommended Spec</strong>
            <div data-infra-recommendation-lines></div>
          </div>
        </div>

        <div class="infra-configurator-forms" data-infra-configurator-forms>
          <form class="infra-form-panel is-active" data-infra-panel="DEDICATED_SERVER">
            <div class="infra-panel-title">
              <strong>Dedicated Server</strong>
              <span>Manual CPU, RAM, storage, and RAID estimate.</span>
            </div>
            <div class="infra-field-grid">
              <label class="infra-field">
                <span>CPU</span>
                <select class="form-select" data-infra-input="dedicatedCpu">
                  <?php foreach ($rate_cards['dedicated_server']['cpu_options'] as $cpu): ?>
                    <option value="<?=esc($cpu['id'])?>"><?=esc($cpu['label'])?><?=isset($cpu['cores']) && $cpu['cores'] ? ' / ' . esc((string)$cpu['cores']) . ' cores' : ''?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="infra-field">
                <span>RAM</span>
                <select class="form-select" data-infra-input="dedicatedRam">
                  <?php foreach ($rate_cards['dedicated_server']['ram_options'] as $ram): ?>
                    <option value="<?=esc($ram['id'])?>"><?=esc($ram['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="infra-field">
                <span>Storage</span>
                <select class="form-select" data-infra-input="dedicatedStorage">
                  <?php foreach ($rate_cards['dedicated_server']['storage_options'] as $storage): ?>
                    <option value="<?=esc($storage['id'])?>"><?=esc($storage['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="infra-field">
                <span>RAID</span>
                <select class="form-select" data-infra-input="dedicatedRaid">
                  <?php foreach ($rate_cards['dedicated_server']['raid_options'] as $raid): ?>
                    <option value="<?=esc($raid['id'])?>"><?=esc($raid['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
            <div class="infra-capacity-strip">
              <div><span>Raw Capacity</span><strong data-infra-dedicated-raw>0 GB</strong></div>
              <div><span>Usable Capacity</span><strong data-infra-dedicated-usable>0 GB</strong></div>
            </div>
          </form>

          <form class="infra-form-panel" data-infra-panel="VIRTUAL_MACHINE">
            <div class="infra-panel-title">
              <strong>Virtual Machine</strong>
              <span>Manual vCPU, RAM, storage tier, and OS estimate.</span>
            </div>
            <div class="infra-field-grid">
              <label class="infra-field">
                <span>vCPU</span>
                <input class="form-input" type="number" min="1" max="64" value="2" data-infra-input="vmVcpu">
              </label>
              <label class="infra-field">
                <span>RAM GB</span>
                <input class="form-input" type="number" min="1" max="512" value="4" data-infra-input="vmRam">
              </label>
              <label class="infra-field">
                <span>Storage GB</span>
                <input class="form-input" type="number" min="1" max="20000" value="100" data-infra-input="vmStorage">
              </label>
              <label class="infra-field">
                <span>Storage Tier</span>
                <select class="form-select" data-infra-input="vmTier">
                  <?php foreach ($rate_cards['virtual_machine']['storage_tiers'] as $tier): ?>
                    <option value="<?=esc($tier['id'])?>"><?=esc($tier['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="infra-field is-wide">
                <span>Operating System</span>
                <select class="form-select" data-infra-input="vmOs">
                  <?php foreach ($rate_cards['virtual_machine']['os_options'] as $os): ?>
                    <option value="<?=esc($os['id'])?>"><?=esc($os['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
            </div>
          </form>

          <form class="infra-form-panel" data-infra-panel="COLOCATION">
            <div class="infra-panel-title">
              <strong>Colocation</strong>
              <span>Data center, rack U, power, setup, and deposit estimate.</span>
            </div>
            <div class="infra-field-grid">
              <label class="infra-field">
                <span>Data Center</span>
                <select class="form-select" data-infra-input="coloDc">
                  <?php foreach ($rate_cards['colocation']['data_centers'] as $dc): ?>
                    <option value="<?=esc($dc['id'])?>"><?=esc($dc['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="infra-field">
                <span>Rack U</span>
                <input class="form-input" type="number" min="1" max="48" value="1" data-infra-input="coloRack">
              </label>
              <label class="infra-field">
                <span>Ampere</span>
                <input class="form-input" type="number" min="1" max="32" value="1" data-infra-input="coloAmpere">
              </label>
            </div>
            <div class="infra-capacity-strip">
              <div><span>Setup Fee</span><strong data-infra-colo-setup>Rp0</strong></div>
              <div><span>Deposit</span><strong data-infra-colo-deposit>Rp0</strong></div>
            </div>
          </form>

          <form class="infra-form-panel" data-infra-panel="CUSTOM_SERVICE">
            <div class="infra-panel-title">
              <strong>Custom Service</strong>
              <span>Manual service, quantity, billing, and price estimate.</span>
            </div>
            <div class="infra-field-grid">
              <label class="infra-field">
                <span>Reference Service</span>
                <select class="form-select" data-infra-input="customService">
                  <?php foreach ($rate_cards['custom_service']['reference_services'] as $service): ?>
                    <option value="<?=esc($service['id'])?>"><?=esc($service['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="infra-field">
                <span>Service Name</span>
                <input class="form-input" type="text" value="Migration Support" data-infra-input="customName">
              </label>
              <label class="infra-field">
                <span>Quantity</span>
                <input class="form-input" type="number" min="1" max="999" value="1" data-infra-input="customQty">
              </label>
              <label class="infra-field">
                <span>Billing</span>
                <select class="form-select" data-infra-input="customBilling">
                  <?php foreach ($rate_cards['custom_service']['billing_types'] as $billing): ?>
                    <option value="<?=esc($billing['id'])?>"><?=esc($billing['label'])?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label class="infra-field">
                <span>Manual Price</span>
                <input class="form-input" type="number" min="0" step="50000" value="1000000" data-infra-input="customPrice">
              </label>
            </div>
            <div class="infra-capacity-strip">
              <div><span>Final Price</span><strong data-infra-custom-final>Rp0</strong></div>
            </div>
          </form>

          <section class="infra-addons" aria-labelledby="infraAddonsTitle">
            <div class="infra-panel-title">
              <strong id="infraAddonsTitle">Optional Add-ons</strong>
              <span>Shared Sales add-ons from the current reference workbook.</span>
            </div>
            <div class="infra-addon-grid">
              <?php foreach ($rate_cards['shared_addons'] as $addon): ?>
              <label class="infra-addon">
                <input type="checkbox" value="<?=esc($addon['id'])?>" data-infra-addon>
                <span>
                  <strong><?=esc($addon['label'])?></strong>
                  <small><?=esc($addon['billing'] === 'monthly' ? 'MRC' : 'OTC')?> / <?=esc(tracs_infra_configurator_money((float)$addon['unit_price']))?></small>
                </span>
              </label>
              <?php endforeach; ?>
            </div>
          </section>
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
          <div class="infra-summary-label">Manual Override</div>
          <div class="infra-field-grid infra-override-grid">
            <label class="infra-field">
              <span>MRC Override</span>
              <input class="form-input" type="number" min="0" step="50000" placeholder="Optional" data-infra-input="overrideMrc">
            </label>
            <label class="infra-field">
              <span>OTC Override</span>
              <input class="form-input" type="number" min="0" step="50000" placeholder="Optional" data-infra-input="overrideOtc">
            </label>
          </div>
          <div class="infra-summary-empty">Leave blank to use calculated pricing.</div>
        </div>

        <div class="infra-summary-section">
          <div class="infra-summary-label">Recurring Charges</div>
          <div class="infra-summary-lines" data-infra-summary-recurring></div>
          <div class="infra-summary-empty" data-infra-recurring-empty>No recurring charges selected.</div>
          <div class="infra-summary-total">
            <span>MRC</span>
            <strong data-infra-summary-mrc><?=esc(tracs_infra_configurator_money((float)$pricing_summary['mrc']))?></strong>
          </div>
        </div>

        <div class="infra-summary-section">
          <div class="infra-summary-label">One-Time Charges</div>
          <div class="infra-summary-lines" data-infra-summary-onetime></div>
          <div class="infra-summary-empty" data-infra-onetime-empty>No one-time charges selected.</div>
          <div class="infra-summary-total">
            <span>OTC</span>
            <strong data-infra-summary-otc><?=esc(tracs_infra_configurator_money((float)$pricing_summary['otc']))?></strong>
          </div>
        </div>

        <div class="infra-summary-section">
          <div class="infra-summary-label">Tax and Final</div>
          <div class="infra-summary-line">
            <span>PPN 11%</span>
            <strong data-infra-summary-tax>Rp0</strong>
          </div>
          <div class="infra-summary-total">
            <span>MRC + PPN</span>
            <strong data-infra-summary-mrc-final>Rp0</strong>
          </div>
          <div class="infra-summary-total">
            <span>First Invoice</span>
            <strong data-infra-summary-first-invoice>Rp0</strong>
          </div>
        </div>

        <div class="infra-price-book">
          <span><?=esc($price_book['name'] ?? 'Beta Reference')?></span>
          <strong><?=esc($price_book['currency'] ?? 'IDR')?> / v<?=esc($price_book['version'] ?? 'beta')?></strong>
        </div>
      </aside>
    </section>
  </div>
</main>

<script>
window.TRACS_INFRA_CONFIGURATOR_DATA = <?=json_encode([
    'price_book' => $price_book,
    'rate_cards' => $rate_cards,
], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?>;
</script>
<script src="assets/infrastructure-configurator.js?v=<?=$infra_configurator_js_v?>"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>
