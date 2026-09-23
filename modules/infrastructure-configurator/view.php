<?php
require_once __DIR__ . '/../../core/security/direct_access.php';
tracs_deny_direct_script_access(__FILE__);
?>
<main class="main">
 <div class="main-inner infra-configurator-page" data-sales-configurator data-unsaved-ignore data-unsaved-hide-bar>
  <div class="topbar"><div class="topbar-left"><div class="page-title">Sales Configurator</div></div><div class="topbar-right sales-actions"><button class="btn" type="button" data-open-templates><i data-lucide="files" class="icon-sm"></i>Templates</button><?php if ($can_manage): ?><button class="btn" type="button" data-open-master><i data-lucide="database" class="icon-sm"></i>Pricing Matrix</button><?php endif; ?><button class="btn sales-refresh" type="button" data-refresh title="Refresh prices" aria-label="Refresh prices"><i data-lucide="refresh-cw" class="icon-sm"></i></button></div></div>
  <p class="sales-status" data-status role="status">Loading Pricing Matrix...</p>
  <noscript><p class="empty">JavaScript is required to use the calculator.</p></noscript>
  <section id="sales-calculator">
   <div class="sales-toolbar sales-service-toolbar">
    <label class="sales-field">Service<select class="form-select" data-service disabled></select></label>
    <label class="sales-field">Billing Period<select class="form-select" data-period disabled></select></label>
   </div>
   <div class="sales-workspace">
    <section class="sales-builder" aria-label="Configuration items">
     <div class="sales-builder-head"><h2 data-configuration-title>Configuration</h2><div class="sales-builder-utilities"><button type="button" class="btn" data-copy-specs disabled><i data-lucide="copy" class="icon-sm"></i>Copy Specs</button><button type="button" class="btn" data-clear-configuration disabled>Clear Configuration</button></div></div>
     <div data-lines></div><button type="button" class="btn" data-add disabled><i data-lucide="plus" class="icon-sm"></i>Add Item</button> <button type="button" class="btn" data-add-custom disabled><i data-lucide="square-pen" class="icon-sm"></i>Add Custom Item</button>
    </section>
    <aside class="sales-totals" aria-label="Calculation totals">
     <label class="sales-field">Quantity / Nodes<input class="form-input" data-nodes type="number" min="1" max="10000" step="1" value="1" required></label>
     <div class="sales-margin-controls">
      <label class="sales-field"><span data-margin-value-label>Margin (%)</span><input class="form-input" data-margin-value type="number" min="0" max="1000" step="any" value="30" required></label>
      <label class="sales-field"><span class="sales-sr-only">Margin mode</span><select class="form-select" data-margin-mode aria-label="Margin mode"><option value="percentage">%</option><option value="amount">Rp</option></select></label>
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
  <dialog class="sales-dialog sales-template-dialog" data-template-dialog aria-labelledby="sales-template-title">
   <div class="sales-dialog-frame">
    <div class="sales-dialog-head"><h2 id="sales-template-title">Saved Templates</h2><button type="button" class="btn" data-close-templates aria-label="Close templates" title="Close"><i data-lucide="x" class="icon-sm"></i></button></div>
    <div class="sales-dialog-body">
     <section class="sales-template-section" aria-label="Browse saved templates">
      <label class="sales-field">My Templates<select class="form-select" data-template-select disabled><option value="">No templates</option></select></label>
      <div class="sales-template-preview" data-template-preview>
       <h3>Template Preview</h3>
       <p class="empty">Select a template to preview its configuration.</p>
      </div>
      <div class="sales-dialog-actions sales-template-actions"><button type="button" class="btn primary" data-load-template disabled><i data-lucide="folder-open" class="icon-sm"></i>Load Template</button><button type="button" class="btn" data-delete-template disabled><i data-lucide="trash-2" class="icon-sm"></i>Delete</button></div>
     </section>
     <section class="sales-template-section sales-template-save" aria-label="Save current configuration">
      <h3>Save Current Configuration</h3>
      <label class="sales-field">Template Name<input class="form-input" data-template-name maxlength="150"></label>
     </section>
    </div>
    <div class="sales-dialog-footer"><p data-template-status role="status" class="sales-master-count"></p><div class="sales-dialog-actions"><button type="button" class="btn" data-save-template><i data-lucide="save" class="icon-sm"></i>Save New</button><button type="button" class="btn primary" data-update-template disabled>Update Selected</button></div></div>
   </div>
  </dialog>
  <?php if ($can_manage): ?>
  <dialog class="sales-dialog sales-master-dialog" data-master-dialog aria-labelledby="sales-master-title">
   <div class="sales-dialog-frame">
    <div class="sales-dialog-head">
     <div class="sales-dialog-title"><h2 id="sales-master-title">Pricing Matrix</h2><p>Manage pricing references for services and configurations.</p></div>
     <div class="sales-dialog-head-actions">
      <form data-tax-form class="sales-tax-form" aria-label="PPN setting"><span class="sales-tax-display" data-tax-display>PPN 0%</span><button type="button" class="btn sales-tax-edit" data-edit-tax>Edit</button><label class="sales-field sales-tax-edit-field" hidden><span class="sales-sr-only">PPN (%)</span><input class="form-input" name="tax" type="number" min="0" max="100" step="0.0001" required></label><button type="submit" class="btn" data-save-tax hidden>Save</button><button type="button" class="btn" data-cancel-tax hidden>Cancel</button></form>
      <button type="button" class="btn primary" data-new-item><i data-lucide="plus" class="icon-sm"></i>Add Item</button>
      <button type="button" class="btn" data-close-master aria-label="Close Pricing Matrix" title="Close"><i data-lucide="x" class="icon-sm"></i></button>
     </div>
    </div>
    <div class="sales-dialog-body" id="sales-master">
     <div class="sales-toolbar sales-master-toolbar">
      <h3>Search &amp; Filter</h3>
      <label class="sales-field sales-master-search"><span class="sales-sr-only">Search service or item</span><span class="sales-search-control"><i data-lucide="search" class="icon-sm" aria-hidden="true"></i><input class="form-input" type="search" data-master-search placeholder="Search service or item..."></span></label>
     </div>
     <div class="sales-master-filters">
      <label class="sales-field">Service<select class="form-select" data-filter-service><option value="">All services</option></select></label>
      <label class="sales-field">Category<select class="form-select" data-filter-category><option value="">All categories</option></select></label>
      <label class="sales-field">Status<select class="form-select" data-filter-status><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></label>
      <button type="button" class="btn" data-clear-filters>Clear Filters</button>
     </div>
     <p class="sales-master-count" data-master-count role="status"></p>
     <div class="sales-table-scroll"><table class="sales-master-table"><thead><tr>
      <th><button type="button" class="table-sort-button" data-master-sort="service" aria-label="Sort by service and category"><span>Service / Category</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
      <th><button type="button" class="table-sort-button" data-master-sort="item" aria-label="Sort by item"><span>Item</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
      <th><button type="button" class="table-sort-button" data-master-sort="price" aria-label="Sort by price"><span>Price</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
      <th><button type="button" class="table-sort-button" data-master-sort="billing" aria-label="Sort by billing"><span>Billing</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
      <th><button type="button" class="table-sort-button" data-master-sort="status" aria-label="Sort by status"><span>Status</span><i data-lucide="chevrons-up-down" class="icon-xs table-sort-icon" aria-hidden="true"></i></button></th>
      <th><span class="sales-sr-only">Actions</span></th>
     </tr></thead><tbody data-master-body></tbody></table></div>
    </div>
   </div>
  </dialog>
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
     </div>
     <input type="hidden" name="sort_order" required>
     <div class="sales-dialog-checks"><label><input type="checkbox" name="active"> Active</label><label><input type="checkbox" name="unit_quantity"> Per-unit quantity</label></div>
     </div>
     <div class="sales-dialog-footer"><p data-item-error role="alert"></p><div class="sales-dialog-actions"><button type="button" class="btn" data-cancel>Cancel</button><button type="submit" class="btn primary">Save Item</button></div></div>
    </form>
   </dialog>
  <?php endif; ?>
 </div>
</main>
<script src="assets/infrastructure-configurator.js?v=<?= (int)filemtime(__DIR__ . '/../../public/assets/infrastructure-configurator.js') ?>" defer></script>
