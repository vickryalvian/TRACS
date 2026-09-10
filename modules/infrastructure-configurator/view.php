<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
?>
<main class="main">
 <div class="main-inner infra-configurator-page" data-sales-configurator data-unsaved-ignore>
  <div class="topbar"><div class="topbar-left"><div class="page-title">Sales Configurator</div></div><button class="btn" type="button" data-refresh><i data-lucide="refresh-cw" class="icon-sm"></i>Refresh Prices</button></div>
  <div class="sales-tabs" role="tablist" aria-label="Configurator views">
   <button class="btn" type="button" role="tab" aria-selected="true" aria-controls="sales-calculator" id="calculator-tab" data-tab="calculator">Calculator</button>
   <?php if ($can_manage): ?><button class="btn" type="button" role="tab" aria-selected="false" aria-controls="sales-master" id="master-tab" data-tab="master" tabindex="-1">Master Data</button><?php endif; ?>
  </div>
  <p class="sales-status" data-status role="status">Loading Master Data...</p>
  <noscript><p class="empty">JavaScript is required to use the calculator.</p></noscript>
  <section id="sales-calculator" role="tabpanel" aria-labelledby="calculator-tab">
   <div class="sales-toolbar">
    <label class="sales-field">Service<select class="form-select" data-service disabled></select></label>
    <label class="sales-field">Billing Period<select class="form-select" data-period disabled></select></label>
   </div>
   <div class="sales-workspace">
    <section class="sales-builder" aria-label="Configuration items"><h2 data-configuration-title>Configuration</h2><div data-lines></div><button type="button" class="btn" data-add disabled><i data-lucide="plus" class="icon-sm"></i>Add Item</button></section>
    <aside class="sales-totals" aria-label="Calculation totals">
     <label class="sales-field">Quantity / Nodes<input class="form-input" data-nodes type="number" min="1" max="10000" step="1" value="1" required></label>
     <div class="sales-margin-controls">
      <label class="sales-field">Margin<select class="form-select" data-margin-mode><option value="percentage">Percentage (%)</option><option value="amount">Amount (Rp)</option></select></label>
      <label class="sales-field"><span data-margin-value-label>Margin (%)</span><input class="form-input" data-margin-value type="number" min="0" max="1000" step="any" value="30" required></label>
     </div>
     <dl>
      <div><dt>Subtotal / Node</dt><dd data-total="subtotal_per_node">-</dd></div>
      <div><dt>Subtotal Before Margin</dt><dd data-total="subtotal_before_margin">-</dd></div>
      <div><dt>Margin</dt><dd data-total="margin">-</dd></div>
      <div><dt>Subtotal Before PPN</dt><dd data-total="subtotal_before_tax">-</dd></div>
      <div><dt>PPN <span data-tax-label></span></dt><dd data-total="tax">-</dd></div>
      <div class="sales-grand-total"><dt>Grand Total</dt><dd data-total="grand_total">-</dd></div>
     </dl><p data-calculation-status role="status"></p>
    </aside>
   </div>
  </section>
  <?php if ($can_manage): ?>
  <section id="sales-master" role="tabpanel" aria-labelledby="master-tab" hidden>
   <div class="sales-toolbar">
    <label class="sales-field">Search Master Data<input class="form-input" type="search" data-master-search></label><button type="button" class="btn primary" data-new-item>New Item</button>
    <form data-tax-form class="sales-tax-form"><label class="sales-field">PPN (%)<input class="form-input" name="tax" type="number" min="0" max="100" step="0.0001" required></label><button type="submit" class="btn">Save PPN</button></form>
   </div>
   <div class="sales-master-filters">
    <label class="sales-field">Service<select class="form-select" data-filter-service><option value="">All services</option></select></label>
    <label class="sales-field">Category<select class="form-select" data-filter-category><option value="">All categories</option></select></label>
    <label class="sales-field">Status<select class="form-select" data-filter-status><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
    <label class="sales-field">Sort By<select class="form-select" data-master-sort><option value="order">Configured order</option><option value="name">Name: A to Z</option><option value="price_asc">Price: low to high</option><option value="price_desc">Price: high to low</option></select></label>
    <button type="button" class="btn" data-clear-filters>Clear Filters</button>
   </div>
   <p class="sales-master-count" data-master-count role="status"></p>
   <div class="sales-table-scroll"><table class="sales-master-table"><thead><tr><th>Service / Category</th><th>Item</th><th>Price</th><th>Billing</th><th>Status</th><th>Order</th><th><span class="sales-sr-only">Actions</span></th></tr></thead><tbody data-master-body></tbody></table></div>
   <dialog class="sales-dialog" data-item-dialog aria-labelledby="sales-item-title">
    <form data-item-form>
     <div class="sales-dialog-head"><h2 id="sales-item-title">New Master Item</h2><button type="button" class="btn" data-close-item aria-label="Close item editor" title="Close"><i data-lucide="x" class="icon-sm"></i></button></div>
     <div class="sales-dialog-body">
     <input type="hidden" name="id"><input type="hidden" name="revision">
     <div class="sales-dialog-grid">
     <label class="sales-field">Service Type<input class="form-input" name="service_type" list="sales-service-options" maxlength="100" required></label>
     <label class="sales-field">Category<input class="form-input" name="category" list="sales-category-options" maxlength="100" required></label>
     </div>
     <datalist id="sales-service-options"></datalist><datalist id="sales-category-options"></datalist>
     <label class="sales-field">Item Name<input class="form-input" name="name" maxlength="500" required></label>
     <label class="sales-field">Specification<textarea class="form-input" name="description" maxlength="10000" rows="2"></textarea></label>
     <div class="sales-dialog-grid">
      <label class="sales-field">Price (Rp)<input class="form-input" name="price" type="number" min="0" max="1000000000000" step="any" required></label>
      <label class="sales-field">Billing Period<select class="form-select" name="billing_period"><option value="monthly">Monthly</option><option value="annual">Annual</option><option value="one_time">One-Time</option></select></label>
      <label class="sales-field">Sort Order<input class="form-input" name="sort_order" type="number" min="-1000000" max="1000000" step="1" required></label>
     </div>
     <div class="sales-dialog-checks"><label><input type="checkbox" name="active"> Active</label><label><input type="checkbox" name="unit_quantity"> Per-unit quantity</label></div>
     </div>
     <div class="sales-dialog-footer"><p data-item-error role="alert"></p><div class="sales-dialog-actions"><button type="button" class="btn" data-cancel>Cancel</button><button type="submit" class="btn primary">Save Item</button></div></div>
    </form>
   </dialog>
  </section>
  <?php endif; ?>
 </div>
</main>
<script src="assets/infrastructure-configurator.js?v=<?= (int)filemtime(__DIR__ . '/../../public/assets/infrastructure-configurator.js') ?>" defer></script>
