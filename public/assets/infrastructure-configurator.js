(function () {
  'use strict';
  const root = document.querySelector('[data-sales-configurator]');
  if (!root) return;
  const $ = (selector) => root.querySelector(selector);
  const service = $('[data-service]');
  const period = $('[data-period]');
  const nodes = $('[data-nodes]');
  const marginMode = $('[data-margin-mode]');
  const marginValue = $('[data-margin-value]');
  let lastBase = 0;
  let lastMargin = 0;
  const status = $('[data-status]');
  const calculationStatus = $('[data-calculation-status]');
  const labels = { monthly: 'Monthly', annual: 'Annual', one_time: 'One-Time' };
  const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2 });
  const money = (value) => currency.format(value);
  const round = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
  let catalog = null;
  let lines = [];
  const priceDrafts = new WeakMap();
  let sequence = 0;
  let timer;
  let loading = false;
  let templates = [];
  let templateBusy = false;
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const activeItems = () => (catalog?.items || []).filter((item) => item.active);
  const available = () => activeItems().filter((item) => item.service_type === service.value && item.billing_period === period.value);
  const categories = () => [...new Set(available().map((item) => item.category))];
  const selected = (line) => line.custom ? { name: line.name, price: 0, unit_quantity: true } : available().find((item) => item.id === line.id && item.category === line.category);
  const unitPrice = (line) => line.override_price ?? selected(line)?.price ?? 0;
  const icons = () => window.lucide?.createIcons();

  async function request(action, input) {
    const response = await fetch(`configurator.php?action=${action}`, {
      method: input === undefined ? 'GET' : 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
      body: input === undefined ? undefined : JSON.stringify(input)
    });
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Your session or Master Data is unavailable. Reload the page to sign in again.'); }
    if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load Master Data.');
    return result.data;
  }

  function fill(select, options, value) {
    select.replaceChildren(...options.map(([key, label]) => new Option(label, key)));
    if (options.some(([key]) => String(key) === String(value))) select.value = value;
    select.disabled = options.length === 0;
  }

  function setPeriods(value) {
    fill(period, Object.entries(labels).filter(([key]) => activeItems().some((item) => item.service_type === service.value && item.billing_period === key)), value);
  }

  function resetLines() {
    const cats = categories();
    const initial = ['CPU', 'RAM', 'Storage'].filter((category) => cats.includes(category));
    lines = (initial.length ? initial : cats.slice(0, 1)).map((category) => ({ category, id: 0, quantity: 1 }));
    renderLines();
    calculate();
  }

  function renderLines() {
    const cats = categories();
    $('[data-configuration-title]').textContent = service.value ? `Custom ${service.value}` : 'Configuration';
    $('[data-add]').disabled = loading || !cats.length || lines.length >= 100;
    $('[data-add-custom]').disabled = loading || !cats.length || lines.length >= 100;
    $('[data-lines]').innerHTML = lines.map((line, index) => {
      const item = selected(line);
      const options = available().filter((option) => option.category === line.category);
      const editing = priceDrafts.has(line);
      return `<div class="sales-line" data-line="${index}">
        <label class="sales-field"><span class="${index ? 'sales-sr-only' : 'sales-column-label'}">Category</span>${line.custom ? `<input class="form-input" data-custom-category value="${esc(line.category)}" maxlength="100" required>` : `<select class="form-select" data-category>${cats.map((category) => `<option ${category === line.category ? 'selected' : ''}>${esc(category)}</option>`).join('')}</select>`}</label>
        <label class="sales-field"><span class="${index ? 'sales-sr-only' : 'sales-column-label'}">Item</span>${line.custom ? `<input class="form-input" data-custom-name value="${esc(line.name)}" maxlength="500" placeholder="Custom item name" required>` : `<select class="form-select" data-item><option value="0">Select ${esc(line.category)}</option>${options.map((option) => `<option value="${option.id}" ${option.id === line.id ? 'selected' : ''}>${esc(option.name)}</option>`).join('')}</select>`}</label>
        <div class="sales-price-cell ${line.custom ? 'is-custom' : ''}">
          ${editing ? `<input class="form-input" data-price type="number" min="0" max="1000000000000" step="any" value="${esc(priceDrafts.get(line))}" required aria-label="Unit price for item ${index + 1}"><button type="button" class="btn" data-apply-price title="Apply price" aria-label="Apply price"><i data-lucide="check" class="icon-sm"></i></button><button type="button" class="btn" data-cancel-price title="Cancel price edit" aria-label="Cancel price edit"><i data-lucide="x" class="icon-sm"></i></button>` : `<output class="sales-price">${item && unitPrice(line) !== '' ? esc(money(Number(unitPrice(line)))) : '-'}</output>${item ? (!line.custom && line.override_price != null ? `<button type="button" class="btn" data-reset-price title="Restore Master Data price" aria-label="Restore Master Data price for item ${index + 1}"><i data-lucide="rotate-ccw" class="icon-sm"></i></button>` : `<button type="button" class="btn" data-edit-price title="Edit price" aria-label="Edit price for item ${index + 1}"><i data-lucide="pencil" class="icon-sm"></i></button>`) : ''}${line.override_price != null && line.override_price !== '' ? '<small class="sales-price-note">Custom price</small>' : ''}`}
        </div>
        <button type="button" class="btn sales-remove" data-remove aria-label="Remove item ${index + 1}" title="Remove item"><i data-lucide="trash-2" class="icon-sm"></i></button>
        ${item?.unit_quantity ? `<label class="sales-field sales-quantity">Units<input class="form-input" data-quantity type="number" min="1" max="100000" step="1" value="${line.quantity}" required></label>` : ''}
        ${item && (item.name.length > 45 || item.description) ? `<div class="sales-specification">${esc(item.name)}${item.description ? '\n' + esc(item.description) : ''}</div>` : ''}
      </div>`;
    }).join('');
    icons();
  }

  function display(totals) {
    root.querySelectorAll('[data-total]').forEach((element) => { element.textContent = totals ? money(totals[element.dataset.total]) : '-'; });
  }

  function calculate() {
    const current = ++sequence;
    clearTimeout(timer);
    if (!catalog || loading) { display(null); return; }
    const count = Number(nodes.value);
    if (!nodes.checkValidity() || !Number.isInteger(count) || lines.some((line) => !Number.isInteger(line.quantity) || line.quantity < 1 || line.quantity > 100000)) {
      display(null); calculationStatus.textContent = 'Enter a valid whole-number quantity.'; return;
    }
    if (lines.some((line) => line.id && !selected(line))) {
      display(null); calculationStatus.textContent = 'An item is no longer available. Select a replacement.'; return;
    }
    const chosen = lines.filter((line) => selected(line));
    if (chosen.some((line) => line.custom && (!line.name.trim() || !line.category.trim() || line.name.length > 500 || line.category.length > 100))) {
      display(null); calculationStatus.textContent = 'Enter a name and category for each custom item.'; return;
    }
    if (!marginValue.checkValidity() || chosen.some((line) => line.override_price != null && (line.override_price === '' || !Number.isFinite(Number(line.override_price)) || Number(line.override_price) < 0 || Number(line.override_price) > 1e12))) {
      display(null); calculationStatus.textContent = 'Enter a valid nonnegative price and margin.'; return;
    }
    const subtotal = chosen.reduce((sum, line) => sum + Number(unitPrice(line)) * line.quantity, 0);
    const base = round(subtotal * count);
    const margin = chosen.length ? round(marginMode.value === 'percentage' ? base * Number(marginValue.value) / 100 : Number(marginValue.value)) : 0;
    lastBase = base; lastMargin = margin;
    const beforeTax = round(base + margin);
    if (!Number.isFinite(beforeTax) || beforeTax > 1e14) { display(null); calculationStatus.textContent = 'Total exceeds the supported amount.'; return; }
    const tax = round(beforeTax * catalog.tax_rate);
    const totals = { subtotal_per_node: round(subtotal), subtotal_before_margin: base, margin, subtotal_before_tax: beforeTax, tax, grand_total: round(beforeTax + tax) };
    display(totals);
    calculationStatus.textContent = chosen.length ? 'Checking current prices...' : 'No items selected.';
    if (!chosen.length) return;
    timer = setTimeout(async () => {
      try {
        const verified = await request('calculate', { service_type: service.value, billing_period: period.value, nodes: count, lines: chosen, margin_mode: marginMode.value, margin_value: marginValue.value });
        if (current !== sequence) return;
        if (Object.keys(totals).some((key) => Math.abs(totals[key] - verified[key]) > 0.005)) {
          display(null); calculationStatus.textContent = 'Master prices changed. Refresh Prices before continuing.';
        } else {
          display(verified); calculationStatus.textContent = lines.some((line) => !line.id && !line.custom) ? 'Unselected rows are excluded.' : '';
        }
      } catch (error) {
        if (current !== sequence) return;
        display(null); calculationStatus.textContent = error.message;
      }
    }, 200);
  }

  function renderMaster() {
    const body = $('[data-master-body]');
    if (!body) return;
    const search = $('[data-master-search]').value.toLowerCase();
    const serviceFilter = $('[data-filter-service]');
    const categoryFilter = $('[data-filter-category]');
    fill(serviceFilter, [['', 'All services'], ...[...new Set(catalog.items.map((item) => item.service_type))].sort().map((value) => [value, value])], serviceFilter.value);
    fill(categoryFilter, [['', 'All categories'], ...[...new Set(catalog.items.filter((item) => !serviceFilter.value || item.service_type === serviceFilter.value).map((item) => item.category))].sort().map((value) => [value, value])], categoryFilter.value);
    const state = $('[data-filter-status]').value;
    const items = catalog.items.filter((item) => `${item.service_type} ${item.category} ${item.name}`.toLowerCase().includes(search)
      && (!serviceFilter.value || item.service_type === serviceFilter.value)
      && (!categoryFilter.value || item.category === categoryFilter.value)
      && (!state || item.active === (state === 'active')));
    const sort = $('[data-master-sort]').value;
    items.sort((a, b) => (sort === 'name' ? a.name.localeCompare(b.name) : sort === 'price_asc' ? a.price - b.price : sort === 'price_desc' ? b.price - a.price : a.sort_order - b.sort_order) || a.id - b.id);
    $('[data-master-count]').textContent = `${items.length} of ${catalog.items.length} items`;
    body.innerHTML = items.map((item) => `<tr><td>${esc(item.service_type)}<br>${esc(item.category)}</td><td>${esc(item.name)}</td><td>${esc(money(item.price))}</td><td>${labels[item.billing_period]}</td><td>${item.active ? 'Active' : 'Inactive'}</td><td>${item.sort_order}</td><td><button type="button" class="btn" data-edit="${item.id}" title="Edit item" aria-label="Edit ${esc(item.name)}"><i data-lucide="pencil" class="icon-sm"></i></button></td></tr>`).join('') || '<tr><td colspan="7">No matching items.</td></tr>';
    icons();
  }

  function useCatalog(data) {
    const firstLoad = !catalog;
    const oldService = service.value;
    const oldPeriod = period.value;
    catalog = data;
    fill(service, [...new Set(activeItems().map((item) => item.service_type))].map((value) => [value, value]), oldService);
    setPeriods(oldPeriod);
    $('[data-tax-label]').textContent = `${Number((catalog.tax_rate * 100).toFixed(4))}%`;
    if ($('[data-tax-form]')) $('[data-tax-form]').elements.tax.value = Number((catalog.tax_rate * 100).toFixed(4));
    if (firstLoad || service.value !== oldService || period.value !== oldPeriod) resetLines();
    else { renderLines(); calculate(); }
    renderMaster();
    status.textContent = activeItems().length ? '' : 'No active items. Contact your Master Data administrator.';
  }

  async function refresh() {
    if (loading) return;
    loading = true;
    ++sequence; clearTimeout(timer); display(null);
    status.textContent = 'Loading Master Data...';
    $('[data-refresh]').disabled = true;
    service.disabled = period.disabled = $('[data-add]').disabled = $('[data-add-custom]').disabled = true;
    try { const data = await request('catalog'); loading = false; useCatalog(data); }
    catch (error) { catalog = null; lines = []; $('[data-lines]').replaceChildren(); status.textContent = error.message; }
    finally { loading = false; $('[data-refresh]').disabled = false; }
  }

  $('[data-lines]').addEventListener('change', (event) => {
    const row = event.target.closest('[data-line]');
    if (!row) return;
    const line = lines[Number(row.dataset.line)];
    if (event.target.matches('[data-category], [data-item]')) priceDrafts.delete(line);
    if (event.target.matches('[data-category]')) { line.category = event.target.value; line.id = 0; line.quantity = 1; delete line.override_price; }
    if (event.target.matches('[data-item]')) { line.id = Number(event.target.value); line.quantity = 1; delete line.override_price; }
    if (!event.target.matches('[data-quantity], [data-price], [data-custom-name], [data-custom-category]')) {
      const selector = event.target.matches('[data-category]') ? '[data-category]' : '[data-item]';
      const index = row.dataset.line;
      renderLines(); $(`[data-line="${index}"] ${selector}`)?.focus(); calculate();
    }
  });
  $('[data-lines]').addEventListener('input', (event) => {
    if (!event.target.matches('[data-quantity], [data-price], [data-custom-name], [data-custom-category]')) return;
    const row = event.target.closest('[data-line]');
    const line = lines[Number(row.dataset.line)];
    if (event.target.matches('[data-custom-name], [data-custom-category]')) {
      line[event.target.matches('[data-custom-name]') ? 'name' : 'category'] = event.target.value;
      calculate(); return;
    }
    if (event.target.matches('[data-price]')) {
      priceDrafts.set(line, event.target.value); return;
    } else line.quantity = Number(event.target.value);
    calculate();
  });
  $('[data-lines]').addEventListener('keydown', (event) => {
    if (!event.target.matches('[data-price]') || !['Enter', 'Escape'].includes(event.key)) return;
    event.preventDefault();
    event.target.closest('[data-line]').querySelector(event.key === 'Enter' ? '[data-apply-price]' : '[data-cancel-price]').click();
  });
  $('[data-lines]').addEventListener('click', (event) => {
    const action = event.target.closest('[data-edit-price], [data-apply-price], [data-cancel-price]');
    if (action) {
      const row = action.closest('[data-line]');
      const index = Number(row.dataset.line);
      const line = lines[index];
      if (action.matches('[data-edit-price]')) priceDrafts.set(line, unitPrice(line));
      else {
        if (action.matches('[data-apply-price]')) {
          const input = row.querySelector('[data-price]');
          if (!input.reportValidity()) return;
          if (!line.custom && Number(input.value) === selected(line).price) delete line.override_price;
          else line.override_price = input.value;
        }
        priceDrafts.delete(line);
      }
      renderLines(); calculate();
      $(`[data-line="${index}"] [data-price], [data-line="${index}"] [data-edit-price], [data-line="${index}"] [data-reset-price]`)?.focus(); return;
    }
    const reset = event.target.closest('[data-reset-price]');
    if (reset) {
      const index = Number(reset.closest('[data-line]').dataset.line);
      delete lines[index].override_price;
      renderLines(); calculate(); $(`[data-line="${index}"] [data-edit-price]`)?.focus(); return;
    }
    const button = event.target.closest('[data-remove]');
    if (!button) return;
    lines.splice(Number(button.closest('[data-line]').dataset.line), 1);
    renderLines(); calculate(); $('[data-add]').focus();
  });
  $('[data-add]').addEventListener('click', () => {
    if (!categories().length || lines.length >= 100) return;
    lines.push({ category: categories().includes('Storage') ? 'Storage' : categories()[0], id: 0, quantity: 1 });
    renderLines(); calculate(); $('[data-lines]').lastElementChild.querySelector('[data-item]').focus();
  });
  service.addEventListener('change', () => { setPeriods('monthly'); resetLines(); });
  $('[data-add-custom]').addEventListener('click', () => {
    if (!categories().length || lines.length >= 100) return;
    lines.push({ custom: true, id: 0, category: 'Custom', name: '', override_price: '', quantity: 1 });
    renderLines(); calculate(); $('[data-lines]').lastElementChild.querySelector('[data-custom-name]').focus();
  });
  period.addEventListener('change', resetLines);
  nodes.addEventListener('input', calculate);
  marginValue.addEventListener('input', calculate);
  marginMode.addEventListener('change', () => {
    const amount = marginMode.value === 'amount';
    marginValue.max = amount ? '1000000000000' : '1000';
    marginValue.value = amount ? lastMargin : lastBase ? round(lastMargin / lastBase * 100) : 30;
    $('[data-margin-value-label]').textContent = amount ? 'Margin (Rp, total)' : 'Margin (%)';
    calculate();
  });
  $('[data-refresh]').addEventListener('click', refresh);
  window.addEventListener('pageshow', (event) => { if (event.persisted) refresh(); });
  root.querySelectorAll('[data-tab]').forEach((button) => {
    button.addEventListener('click', () => {
      root.querySelectorAll('[data-tab]').forEach((tab) => { const active = tab === button; tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1; $(`#sales-${tab.dataset.tab}`).hidden = !active; });
    });
    button.addEventListener('keydown', (event) => {
      if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
      event.preventDefault();
      const tabs = [...root.querySelectorAll('[data-tab]')];
      const next = event.key === 'Home' ? tabs[0] : event.key === 'End' ? tabs.at(-1) : tabs[(tabs.indexOf(button) + 1) % tabs.length];
      next.click(); next.focus();
    });
  });
  $('[data-master-search]')?.addEventListener('input', () => { if (catalog) renderMaster(); });
  root.querySelectorAll('[data-filter-service], [data-filter-category], [data-filter-status], [data-master-sort]').forEach((field) => field.addEventListener('change', () => { if (catalog) renderMaster(); }));
  $('[data-clear-filters]')?.addEventListener('click', () => {
    $('[data-master-search]').value = '';
    root.querySelectorAll('[data-filter-service], [data-filter-category], [data-filter-status]').forEach((field) => { field.value = ''; });
    $('[data-master-sort]').value = 'order';
    if (catalog) renderMaster();
  });
  const form = $('[data-item-form]');
  const dialog = $('[data-item-dialog]');
  function edit(item = {}) {
    if (!catalog) return;
    form.reset();
    const values = { id: 0, revision: 0, sort_order: 0, active: true, unit_quantity: false, service_type: $('[data-filter-service]').value || service.value, category: $('[data-filter-category]').value, billing_period: 'monthly', ...item };
    for (const [key, value] of Object.entries(values)) {
      const field = form.elements.namedItem(key);
      if (!field) continue;
      if (field.type === 'checkbox') field.checked = Boolean(value); else field.value = value ?? '';
    }
    $('[data-item-error]').textContent = '';
    $('#sales-item-title').textContent = item.id ? 'Edit Master Item' : 'New Master Item';
    $('#sales-service-options').replaceChildren(...[...new Set(catalog.items.map((entry) => entry.service_type))].sort().map((value) => new Option(value, value)));
    updateCategorySuggestions();
    dialog.showModal();
    form.elements.name.focus();
  }
  function updateCategorySuggestions() {
    if (!catalog || !form) return;
    $('#sales-category-options').replaceChildren(...[...new Set(catalog.items.filter((item) => item.service_type === form.elements.service_type.value).map((item) => item.category))].sort().map((value) => new Option(value, value)));
  }
  form?.elements.service_type.addEventListener('input', updateCategorySuggestions);
  $('[data-new-item]')?.addEventListener('click', () => edit());
  $('[data-master-body]')?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit]');
    if (button) edit(catalog.items.find((item) => item.id === Number(button.dataset.edit)));
  });
  $('[data-cancel]')?.addEventListener('click', () => dialog.close());
  $('[data-close-item]')?.addEventListener('click', () => dialog.close());
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('[type="submit"]'); button.disabled = true;
    try {
      const input = Object.fromEntries(new FormData(form));
      input.active = form.elements.active.checked;
      input.unit_quantity = form.elements.unit_quantity.checked;
      useCatalog(await request('save_item', input)); dialog.close(); status.textContent = 'Master item saved.';
    } catch (error) { $('[data-item-error]').textContent = error.message; }
    finally { button.disabled = false; }
  });
  $('[data-tax-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!catalog) return;
    const button = event.target.querySelector('[type="submit"]'); button.disabled = true;
    try { useCatalog(await request('save_tax', { tax_rate: Number(event.target.elements.tax.value) / 100, revision: catalog.tax_revision })); status.textContent = 'PPN saved.'; }
    catch (error) { status.textContent = error.message; }
    finally { button.disabled = false; }
  });
  function renderTemplates() {
    const select = $('[data-template-select]');
    fill(select, [['', templates.length ? 'Select a template' : 'No saved templates'], ...templates.map((item) => [String(item.id), item.name])], select.value);
    $('[data-load-template]').disabled = !select.value || templateBusy;
  }
  async function loadTemplates() {
    try { templates = await request('templates'); renderTemplates(); }
    catch (error) { $('[data-template-status]').textContent = 'Templates unavailable. ' + error.message; }
  }
  $('[data-template-select]').addEventListener('change', renderTemplates);
  $('[data-save-template]').addEventListener('click', async () => {
    if (templateBusy) return;
    const message = $('[data-template-status]');
    if (!catalog || loading) { message.textContent = 'Load Master Data before saving a template.'; return; }
    const name = $('[data-template-name]').value.trim();
    if (!name) { message.textContent = 'Enter a template name.'; $('[data-template-name]').focus(); return; }
    if (lines.some((line) => line.id && !selected(line))) { message.textContent = 'Replace unavailable items before saving.'; return; }
    templateBusy = true; $('[data-save-template]').disabled = true;
    try {
      const configuration = { service_type: service.value, billing_period: period.value, nodes: Number(nodes.value), margin_mode: marginMode.value, margin_value: marginValue.value, lines: lines.filter((line) => selected(line)) };
      templates = await request('save_template', { name, configuration });
      renderTemplates(); message.textContent = `Template saved: ${name}`;
    } catch (error) { message.textContent = error.message; }
    finally { templateBusy = false; $('[data-save-template]').disabled = false; renderTemplates(); }
  });
  $('[data-load-template]').addEventListener('click', async () => {
    if (templateBusy) return;
    const template = templates.find((item) => String(item.id) === $('[data-template-select]').value);
    if (!template) return;
    if (lines.some((line) => line.id || line.custom) && !window.confirm('Replace the current calculation with this template?')) return;
    templateBusy = true; $('[data-load-template]').disabled = true;
    const message = $('[data-template-status]');
    try {
      const data = await request('catalog');
      const config = template.configuration;
      if (!data.items.some((item) => item.active && item.service_type === config.service_type && item.billing_period === config.billing_period)) throw new Error('The template service or billing period is no longer available.');
      useCatalog(data);
      service.value = config.service_type; setPeriods(config.billing_period);
      nodes.value = config.nodes;
      marginMode.value = config.margin_mode || 'percentage';
      marginValue.max = marginMode.value === 'amount' ? '1000000000000' : '1000';
      marginValue.value = config.margin_value ?? 30;
      $('[data-margin-value-label]').textContent = marginMode.value === 'amount' ? 'Margin (Rp, total)' : 'Margin (%)';
      lines = JSON.parse(JSON.stringify(config.lines));
      $('[data-template-name]').value = template.name;
      renderLines(); calculate();
      message.textContent = `Loaded: ${template.name}. Current Master Data prices and PPN apply.`;
    } catch (error) { message.textContent = error.message; }
    finally { templateBusy = false; renderTemplates(); }
  });
  refresh();
  loadTemplates();
}());
