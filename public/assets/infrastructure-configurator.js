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
  const draftKey = 'tracs:sales-configurator:draft:v1';
  let draftTimer;
  let draftReady = false;
  let lastDraft = '';
  let masterSort = { field: 'order', dir: 'asc' };
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const activeItems = () => (catalog?.items || []).filter((item) => item.active);
  const available = () => activeItems().filter((item) => item.service_type === service.value && item.billing_period === period.value);
  const categories = () => [...new Set(available().map((item) => item.category))];
  const selected = (line) => line.custom ? { name: line.name, price: 0, unit_quantity: true } : available().find((item) => item.id === line.id && item.category === line.category);
  const unitPrice = (line) => line.override_price ?? selected(line)?.price ?? 0;
  const icons = () => window.lucide?.createIcons();
  const trackedDialogs = new WeakSet();
  let configurationTracked = false;
  const configurationState = () => ({
    service: service.value,
    period: period.value,
    nodes: Number(nodes.value),
    marginMode: marginMode.value,
    marginValue: Number(marginValue.value),
    lines: lines.map((line) => ({
      ...(line.custom ? { custom: true } : {}),
      id: Number(line.id) || 0,
      category: line.category,
      ...(line.custom ? { name: line.name } : {}),
      quantity: Number(line.quantity),
      ...(line.override_price != null ? { override_price: line.override_price === '' ? '' : Number(line.override_price) } : {})
    }))
  });

  function readDraft() {
    try { return JSON.parse(localStorage.getItem(draftKey) || 'null'); }
    catch (_) { return null; }
  }

  function persistDraft() {
    if (!draftReady || !catalog) return;
    const serialized = JSON.stringify(configurationState());
    if (serialized === lastDraft) return;
    try { localStorage.setItem(draftKey, serialized); lastDraft = serialized; }
    catch (_) { /* Draft persistence is best-effort when browser storage is unavailable. */ }
  }

  function scheduleDraftSave() {
    clearTimeout(draftTimer);
    draftTimer = setTimeout(persistDraft, 250);
  }

  function clearPersistedDraft() {
    clearTimeout(draftTimer);
    try { localStorage.removeItem(draftKey); } catch (_) {}
  }
  function captureDialog(dialog) {
    const guard = window.TRACSUnsavedChanges;
    if (!dialog || !guard) return;
    if (!trackedDialogs.has(dialog)) {
      trackedDialogs.add(dialog);
      const fields=()=>Array.from(dialog.querySelectorAll('input:not([type="hidden"]), textarea, select')).filter(field => !field.matches('[data-master-search], [data-filter-service], [data-filter-category], [data-filter-status], [data-template-select]'));
      guard.trackState(dialog, () => fields().map(field => field.type === 'checkbox' ? field.checked : field.value), { active: () => dialog.open, restore: values=>fields().forEach((field,index)=>{if(field.type==='checkbox')field.checked=values[index];else field.value=values[index];}) });
    }
    guard.captureInitialState(dialog);
  }
  function closeDialog(dialog) {
    if (!dialog || dialog.querySelector('button[type="submit"]:disabled') || templateBusy) return;
    if (window.TRACSUnsavedChanges) window.TRACSUnsavedChanges.requestModalClose(dialog, () => dialog.close());
    else dialog.close();
  }

  async function request(action, input) {
    const response = await fetch(`configurator.php?action=${action}`, {
      method: input === undefined ? 'GET' : 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
      body: input === undefined ? undefined : JSON.stringify(input)
    });
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Your session or Pricing Matrix is unavailable. Reload the page to sign in again.'); }
    if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load Pricing Matrix.');
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

  function restoreDraft() {
    const draft = readDraft();
    if (!draft || typeof draft !== 'object' || !Array.isArray(draft.lines)) return false;
    if (!Array.from(service.options).some((option) => option.value === draft.service)) return false;
    service.value = draft.service;
    setPeriods(draft.period);
    if (!Array.from(period.options).some((option) => option.value === draft.period)) return false;
    period.value = draft.period;
    const count = Number(draft.nodes);
    nodes.value = Number.isInteger(count) && count >= 1 && count <= 10000 ? count : 1;
    marginMode.value = draft.marginMode === 'amount' ? 'amount' : 'percentage';
    marginValue.max = marginMode.value === 'amount' ? '1000000000000' : '1000';
    const margin = Number(draft.marginValue);
    marginValue.value = Number.isFinite(margin) && margin >= 0 && margin <= Number(marginValue.max) ? margin : 30;
    $('[data-margin-value-label]').textContent = marginMode.value === 'amount' ? 'Margin (Rp, total)' : 'Margin (%)';
    const cats = categories();
    lines = draft.lines.slice(0, 100).flatMap((raw) => {
      if (!raw || typeof raw !== 'object') return [];
      const custom = raw.custom === true;
      const category = String(raw.category ?? '').slice(0, 100);
      if (!custom && !cats.includes(category)) return [];
      const quantity = Number(raw.quantity);
      const line = {
        ...(custom ? { custom: true } : {}),
        id: custom ? 0 : Number(raw.id) || 0,
        category,
        ...(custom ? { name: String(raw.name ?? '').slice(0, 500) } : {}),
        quantity: Number.isInteger(quantity) && quantity >= 1 && quantity <= 100000 ? quantity : 1
      };
      if (!custom && line.id && !available().some((item) => item.id === line.id && item.category === category)) line.id = 0;
      if (Object.prototype.hasOwnProperty.call(raw, 'override_price')) {
        const price = raw.override_price === '' ? '' : Number(raw.override_price);
        if (price === '' || Number.isFinite(price) && price >= 0 && price <= 1e12) line.override_price = price;
      }
      return [line];
    });
    return true;
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
    $('[data-clear-configuration]').disabled = loading || !catalog;
    $('[data-lines]').innerHTML = lines.map((line, index) => {
      const item = selected(line);
      const options = available().filter((option) => option.category === line.category);
      const editing = priceDrafts.has(line);
      return `<div class="sales-line" data-line="${index}">
        <label class="sales-field"><span class="${index ? 'sales-sr-only' : 'sales-column-label'}">Category</span>${line.custom ? `<input class="form-input" data-custom-category value="${esc(line.category)}" maxlength="100" required>` : `<select class="form-select" data-category>${cats.map((category) => `<option ${category === line.category ? 'selected' : ''}>${esc(category)}</option>`).join('')}</select>`}</label>
        <label class="sales-field"><span class="${index ? 'sales-sr-only' : 'sales-column-label'}">Item</span>${line.custom ? `<input class="form-input" data-custom-name value="${esc(line.name)}" maxlength="500" placeholder="Custom item name" required>` : `<select class="form-select" data-item><option value="0">Select ${esc(line.category)}</option>${options.map((option) => `<option value="${option.id}" ${option.id === line.id ? 'selected' : ''}>${esc(option.name)}</option>`).join('')}</select>`}</label>
        <div class="sales-price-cell ${line.custom ? 'is-custom' : ''}">
          ${editing ? `<input class="form-input" data-price type="number" min="0" max="1000000000000" step="any" value="${esc(priceDrafts.get(line))}" required aria-label="Unit price for item ${index + 1}"><button type="button" class="btn" data-apply-price title="Apply price" aria-label="Apply price"><i data-lucide="check" class="icon-sm"></i></button><button type="button" class="btn" data-cancel-price title="Cancel price edit" aria-label="Cancel price edit"><i data-lucide="x" class="icon-sm"></i></button>` : `<output class="sales-price">${item && unitPrice(line) !== '' ? esc(money(Number(unitPrice(line)))) : '-'}</output>${item ? (!line.custom && line.override_price != null ? `<button type="button" class="btn" data-reset-price title="Restore Pricing Matrix price" aria-label="Restore Pricing Matrix price for item ${index + 1}"><i data-lucide="rotate-ccw" class="icon-sm"></i></button>` : `<button type="button" class="btn" data-edit-price title="Edit price" aria-label="Edit price for item ${index + 1}"><i data-lucide="pencil" class="icon-sm"></i></button>`) : ''}${line.override_price != null && line.override_price !== '' ? '<small class="sales-price-note">Custom price</small>' : ''}`}
        </div>
        <button type="button" class="btn sales-remove" data-remove aria-label="Remove item ${index + 1}" title="Remove item"><i data-lucide="trash-2" class="icon-sm"></i></button>
        ${item?.unit_quantity ? `<label class="sales-field sales-quantity">Units<input class="form-input" data-quantity type="number" min="1" max="100000" step="1" value="${line.quantity}" required></label>` : ''}
        ${item && (item.name.length > 45 || item.description) ? `<div class="sales-specification">${esc(item.name)}${item.description ? '\n' + esc(item.description) : ''}</div>` : ''}
      </div>`;
    }).join('');
    syncCopyAction();
    icons();
  }

  function syncCopyAction() {
    $('[data-copy-specs]').disabled = loading || !specificationText();
  }

  function specificationText() {
    return lines.flatMap((line) => {
      const item = selected(line);
      const category = String(line.category || '').trim();
      const name = String(item?.name || '').trim();
      const quantity = Number(line.quantity);
      if (!category || !name || !Number.isInteger(quantity) || quantity < 1) return [];
      return [`${category}: ${quantity > 1 ? `${quantity} x ` : ''}${name}`];
    }).join('\n');
  }

  async function copySpecifications(button) {
    const text = specificationText();
    if (!text) return;
    try {
      if (navigator.clipboard?.writeText) await navigator.clipboard.writeText(text);
      else {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        if (!document.execCommand('copy')) throw new Error('Copy failed');
        textarea.remove();
      }
      window.showToast?.('Specifications copied', 'success', { sourceElement: button, context: 'page' });
    } catch (_) {
      window.showToast?.('Unable to copy specifications. Please try again.', 'error', { sourceElement: button, context: 'page' });
    }
  }

  async function clearConfiguration() {
    const confirmed = window.tracsConfirm
      ? await window.tracsConfirm({ title: 'Clear configuration?', message: 'This clears the current working configuration and its local draft. Saved Templates are not affected.', confirmText: 'Clear Configuration', destructive: true })
      : window.confirm('Clear the current configuration? Saved Templates are not affected.');
    if (!confirmed) return;
    clearPersistedDraft();
    service.selectedIndex = 0;
    setPeriods('monthly');
    nodes.value = 1;
    marginMode.value = 'percentage';
    marginValue.max = '1000';
    marginValue.value = 30;
    $('[data-margin-value-label]').textContent = 'Margin (%)';
    resetLines();
    lastDraft = JSON.stringify(configurationState());
    window.TRACSUnsavedChanges?.markSaved($('#sales-calculator'));
    window.showToast?.('Configuration cleared', 'success', { context: 'page' });
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
          display(null); calculationStatus.textContent = 'Pricing Matrix changed. Refresh Prices before continuing.';
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
    items.sort(sortMasterItems);
    $('[data-master-count]').textContent = `${items.length} items`;
    body.innerHTML = items.map((item) => `<tr><td>${esc(item.service_type)}<br>${esc(item.category)}</td><td>${esc(item.name)}</td><td>${esc(money(item.price))}</td><td>${labels[item.billing_period]}</td><td>${item.active ? 'Active' : 'Inactive'}</td><td><button type="button" class="btn" data-edit="${item.id}" title="Edit item" aria-label="Edit ${esc(item.name)}"><i data-lucide="pencil" class="icon-sm"></i></button></td></tr>`).join('') || '<tr><td colspan="6">No matching items.</td></tr>';
    syncMasterSortHeaders();
    icons();
  }

  function masterSortValue(item, field) {
    if (field === 'price') return Number(item.price || 0);
    if (field === 'status') return item.active ? 0 : 1;
    if (field === 'billing') return labels[item.billing_period] || item.billing_period || '';
    if (field === 'service') return `${item.service_type || ''} ${item.category || ''}`;
    if (field === 'item') return item.name || '';
    return Number(item.sort_order || 0);
  }

  function sortMasterItems(a, b) {
    const valueA = masterSortValue(a, masterSort.field);
    const valueB = masterSortValue(b, masterSort.field);
    const diff = typeof valueA === 'number' && typeof valueB === 'number'
      ? valueA - valueB
      : String(valueA).localeCompare(String(valueB));
    return (masterSort.dir === 'asc' ? diff : -diff) || (a.sort_order - b.sort_order) || a.id - b.id;
  }

  function syncMasterSortHeaders() {
    root.querySelectorAll('[data-master-sort]').forEach((button) => {
      const active = button.dataset.masterSort === masterSort.field;
      const dir = masterSort.dir === 'asc' ? 'asc' : 'desc';
      const label = button.textContent.trim();
      const icon = button.querySelector('[data-lucide]');
      const th = button.closest('th');
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-label', active ? `Sort by ${label}, ${dir === 'asc' ? 'ascending' : 'descending'}` : `Sort by ${label}`);
      if (th) th.setAttribute('aria-sort', active ? (dir === 'asc' ? 'ascending' : 'descending') : 'none');
      if (icon) icon.setAttribute('data-lucide', active ? (dir === 'asc' ? 'chevron-up' : 'chevron-down') : 'chevrons-up-down');
    });
  }

  function nextSortOrder(serviceType, billingPeriod) {
    if (!catalog) return 0;
    const matches = catalog.items.filter((item) => item.service_type === serviceType && item.billing_period === billingPeriod);
    const max = matches.reduce((value, item) => Math.max(value, Number(item.sort_order || 0)), -1);
    return Math.min(max + 1, 1000000);
  }

  function taxPercent() {
    return Number((Number(catalog?.tax_rate || 0) * 100).toFixed(4));
  }

  function renderTaxSetting() {
    if (!catalog) return;
    const value = taxPercent();
    const display = $('[data-tax-display]');
    if (display) display.textContent = `PPN ${value}%`;
    if ($('[data-tax-form]')) $('[data-tax-form]').elements.tax.value = value;
  }

  function setTaxEditing(editing) {
    const form = $('[data-tax-form]');
    if (!form) return;
    form.querySelector('[data-tax-display]').hidden = editing;
    form.querySelector('[data-edit-tax]').hidden = editing;
    form.querySelector('.sales-tax-edit-field').hidden = !editing;
    form.querySelector('[data-save-tax]').hidden = !editing;
    form.querySelector('[data-cancel-tax]').hidden = !editing;
    if (editing) form.elements.tax.focus();
  }

  function useCatalog(data) {
    const firstLoad = !catalog;
    const oldService = service.value;
    const oldPeriod = period.value;
    let restored = false;
    catalog = data;
    fill(service, [...new Set(activeItems().map((item) => item.service_type))].map((value) => [value, value]), oldService);
    setPeriods(oldPeriod);
    $('[data-tax-label]').textContent = `${Number((catalog.tax_rate * 100).toFixed(4))}%`;
    renderTaxSetting();
    if (firstLoad) {
      restored = restoreDraft();
      if (restored) { renderLines(); calculate(); }
      else resetLines();
    } else if (service.value !== oldService || period.value !== oldPeriod) resetLines();
    else { renderLines(); calculate(); }
    renderMaster();
    if (firstLoad && !configurationTracked && window.TRACSUnsavedChanges) {
      configurationTracked = true;
      window.TRACSUnsavedChanges.trackState($('#sales-calculator'), configurationState, { restore: state => {
        service.value = state.service; setPeriods(state.period); nodes.value = state.nodes;
        marginMode.value = state.marginMode; marginValue.value = state.marginValue;
        lines = state.lines.map(({ draft, ...line }) => line); renderLines(); calculate();
      } });
    }
    if (firstLoad) {
      draftReady = true;
      lastDraft = restored ? JSON.stringify(configurationState()) : '';
    }
    status.textContent = activeItems().length ? '' : 'No active items. Contact your Pricing Matrix administrator.';
  }

  async function refresh(confirmFirst = false) {
    if (loading) return;
    if (confirmFirst && !window.confirm('Refresh prices and reload the Pricing Matrix? Current calculator selections may change if items are no longer available.')) return;
    loading = true;
    ++sequence; clearTimeout(timer); display(null);
    status.textContent = 'Loading Pricing Matrix...';
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
      renderLines(); calculate(); scheduleDraftSave();
      $(`[data-line="${index}"] [data-price], [data-line="${index}"] [data-edit-price], [data-line="${index}"] [data-reset-price]`)?.focus(); return;
    }
    const reset = event.target.closest('[data-reset-price]');
    if (reset) {
      const index = Number(reset.closest('[data-line]').dataset.line);
      delete lines[index].override_price;
      renderLines(); calculate(); scheduleDraftSave(); $(`[data-line="${index}"] [data-edit-price]`)?.focus(); return;
    }
    const button = event.target.closest('[data-remove]');
    if (!button) return;
    lines.splice(Number(button.closest('[data-line]').dataset.line), 1);
    renderLines(); calculate(); scheduleDraftSave(); $('[data-add]').focus();
  });
  $('[data-add]').addEventListener('click', () => {
    if (!categories().length || lines.length >= 100) return;
    lines.push({ category: categories().includes('Storage') ? 'Storage' : categories()[0], id: 0, quantity: 1 });
    renderLines(); calculate(); scheduleDraftSave(); $('[data-lines]').lastElementChild.querySelector('[data-item]').focus();
  });
  service.addEventListener('change', () => { setPeriods('monthly'); resetLines(); });
  $('[data-add-custom]').addEventListener('click', () => {
    if (!categories().length || lines.length >= 100) return;
    lines.push({ custom: true, id: 0, category: 'Custom', name: '', override_price: '', quantity: 1 });
    renderLines(); calculate(); scheduleDraftSave(); $('[data-lines]').lastElementChild.querySelector('[data-custom-name]').focus();
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
  $('#sales-calculator').addEventListener('input', () => queueMicrotask(() => { syncCopyAction(); scheduleDraftSave(); }));
  $('#sales-calculator').addEventListener('change', () => queueMicrotask(() => { syncCopyAction(); scheduleDraftSave(); }));
  $('[data-copy-specs]').addEventListener('click', (event) => copySpecifications(event.currentTarget));
  $('[data-clear-configuration]').addEventListener('click', clearConfiguration);
  $('[data-refresh]').addEventListener('click', () => refresh(true));
  ['input', 'change', 'click'].forEach(type => root.addEventListener(type, () => queueMicrotask(() => window.TRACSUnsavedChanges?.refresh())));
  document.addEventListener('tracs:before-unsaved-leave', persistDraft);
  root.querySelectorAll('dialog').forEach(dialog => dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(dialog); }));
  window.addEventListener('pageshow', (event) => { if (event.persisted) refresh(); });
  const masterDialog = $('[data-master-dialog]');
  const templateDialog = $('[data-template-dialog]');
  $('[data-open-master]')?.addEventListener('click', () => { if (catalog) { renderMaster(); renderTaxSetting(); setTaxEditing(false); } masterDialog?.showModal(); $('[data-master-search]')?.focus(); });
  $('[data-open-master]')?.addEventListener('click', () => captureDialog(masterDialog));
  $('[data-close-master]')?.addEventListener('click', () => closeDialog(masterDialog));
  $('[data-open-templates]')?.addEventListener('click', () => { renderTemplates(); templateDialog?.showModal(); $('[data-template-select]')?.focus(); });
  $('[data-open-templates]')?.addEventListener('click', () => captureDialog(templateDialog));
  $('[data-close-templates]')?.addEventListener('click', () => closeDialog(templateDialog));
  $('[data-master-search]')?.addEventListener('input', () => { if (catalog) renderMaster(); });
  root.querySelectorAll('[data-filter-service], [data-filter-category], [data-filter-status]').forEach((field) => field.addEventListener('change', () => { if (catalog) renderMaster(); }));
  root.querySelectorAll('[data-master-sort]').forEach((button) => {
    button.addEventListener('click', () => {
      const field = button.dataset.masterSort;
      masterSort = field === masterSort.field ? { field, dir: masterSort.dir === 'asc' ? 'desc' : 'asc' } : { field, dir: 'asc' };
      if (catalog) renderMaster();
    });
  });
  $('[data-clear-filters]')?.addEventListener('click', () => {
    $('[data-master-search]').value = '';
    root.querySelectorAll('[data-filter-service], [data-filter-category], [data-filter-status]').forEach((field) => { field.value = ''; });
    masterSort = { field: 'order', dir: 'asc' };
    if (catalog) renderMaster();
  });
  const form = $('[data-item-form]');
  const dialog = $('[data-item-dialog]');
  function updateAutoSortOrder() {
    if (!form) return;
    form.elements.sort_order.value = nextSortOrder(form.elements.service_type.value, form.elements.billing_period.value);
  }

  function edit(item = {}) {
    if (!catalog) return;
    form.reset();
    const creating = !item.id;
    const serviceType = item.service_type || $('[data-filter-service]').value || service.value;
    const billingPeriod = item.billing_period || 'monthly';
    const values = { id: 0, revision: 0, active: true, unit_quantity: false, service_type: serviceType, category: $('[data-filter-category]').value, billing_period: billingPeriod, sort_order: creating ? nextSortOrder(serviceType, billingPeriod) : 0, ...item };
    for (const [key, value] of Object.entries(values)) {
      const field = form.elements.namedItem(key);
      if (!field) continue;
      if (field.type === 'checkbox') field.checked = Boolean(value); else field.value = value ?? '';
    }
    $('[data-item-error]').textContent = '';
    $('#sales-item-title').textContent = item.id ? 'Edit Pricing Item' : 'Add Pricing Item';
    $('#sales-service-options').replaceChildren(...[...new Set(catalog.items.map((entry) => entry.service_type))].sort().map((value) => new Option(value, value)));
    updateCategorySuggestions();
    dialog.showModal();
    captureDialog(dialog);
    form.elements.name.focus();
  }
  function updateCategorySuggestions() {
    if (!catalog || !form) return;
    $('#sales-category-options').replaceChildren(...[...new Set(catalog.items.filter((item) => item.service_type === form.elements.service_type.value).map((item) => item.category))].sort().map((value) => new Option(value, value)));
  }
  form?.elements.service_type.addEventListener('input', () => { updateCategorySuggestions(); updateAutoSortOrder(); });
  form?.elements.billing_period.addEventListener('change', updateAutoSortOrder);
  $('[data-new-item]')?.addEventListener('click', () => edit());
  $('[data-master-body]')?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit]');
    if (button) edit(catalog.items.find((item) => item.id === Number(button.dataset.edit)));
  });
  $('[data-cancel]')?.addEventListener('click', () => closeDialog(dialog));
  $('[data-close-item]')?.addEventListener('click', () => closeDialog(dialog));
  form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('[type="submit"]'); button.disabled = true;
    try {
      const input = Object.fromEntries(new FormData(form));
      input.active = form.elements.active.checked;
      input.unit_quantity = form.elements.unit_quantity.checked;
      useCatalog(await request('save_item', input)); window.TRACSUnsavedChanges?.markSaved(dialog); dialog.close(); status.textContent = 'Pricing item saved.';
    } catch (error) { $('[data-item-error]').textContent = error.message; }
    finally { button.disabled = false; }
  });
  $('[data-tax-form]')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!catalog) return;
    const button = event.target.querySelector('[type="submit"]'); button.disabled = true;
    try { useCatalog(await request('save_tax', { tax_rate: Number(event.target.elements.tax.value) / 100, revision: catalog.tax_revision })); setTaxEditing(false); window.TRACSUnsavedChanges?.markSaved(masterDialog); status.textContent = 'PPN saved.'; }
    catch (error) { status.textContent = error.message; }
    finally { button.disabled = false; }
  });
  $('[data-edit-tax]')?.addEventListener('click', () => setTaxEditing(true));
  $('[data-cancel-tax]')?.addEventListener('click', () => { renderTaxSetting(); setTaxEditing(false); });
  function templateItemLabel(line) {
    if (line.custom) return line.name || 'Custom item';
    const item = catalog?.items?.find((entry) => entry.id === Number(line.id));
    return item?.name || line.name || `Item #${line.id || '-'}`;
  }

  function templateItemCategory(line) {
    if (line.custom) return line.category || 'Custom';
    const item = catalog?.items?.find((entry) => entry.id === Number(line.id));
    return item?.category || line.category || 'Additional';
  }

  function templateItemValue(line) {
    const quantity = Number(line.quantity || 1);
    const suffix = quantity > 1 ? ` x ${quantity}` : '';
    return `${templateItemLabel(line)}${suffix}`;
  }

  function templateMarginLabel(config) {
    if ((config.margin_mode || 'percentage') === 'amount') return money(Number(config.margin_value || 0));
    return `${Number(config.margin_value ?? 30)}%`;
  }

  function renderTemplatePreview() {
    const preview = $('[data-template-preview]');
    if (!preview) return;
    const template = selectedTemplate();
    if (!template) {
      preview.innerHTML = '<h3>Template Preview</h3><p class="empty">Select a template to preview its configuration.</p>';
      return;
    }
    const config = template.configuration || {};
    const rows = [
      ['Service', config.service_type || '-'],
      ['Billing', labels[config.billing_period] || config.billing_period || '-']
    ];
    const grouped = new Map();
    for (const line of config.lines || []) {
      const category = templateItemCategory(line);
      grouped.set(category, [...(grouped.get(category) || []), templateItemValue(line)]);
    }
    for (const [category, values] of grouped.entries()) rows.push([category, values.join(', ')]);
    rows.push(['Quantity', config.nodes ?? 1], ['Margin', templateMarginLabel(config)]);
    preview.innerHTML = `<h3>Template Preview</h3>${rows.length > 2 ? `<div class="sales-template-preview-list"><dl>${rows.map(([key, value]) => `<div><dt>${esc(key)}</dt><dd>${esc(value)}</dd></div>`).join('')}</dl></div>` : '<p class="empty">This template has no saved configuration.</p>'}`;
  }

  function renderTemplates() {
    const select = $('[data-template-select]');
    fill(select, [['', templates.length ? 'Select a template' : 'No saved templates'], ...templates.map((item) => [String(item.id), item.name])], select.value);
    select.disabled = !templates.length || templateBusy;
    const hasSelection = Boolean(select.value);
    $('[data-load-template]').disabled = !hasSelection || templateBusy;
    $('[data-update-template]').disabled = !hasSelection || templateBusy;
    $('[data-delete-template]').disabled = !hasSelection || templateBusy;
    renderTemplatePreview();
    icons();
  }

  function selectedTemplate() {
    return templates.find((item) => String(item.id) === $('[data-template-select]').value);
  }

  function currentTemplateConfiguration(message) {
    if (!catalog || loading) { message.textContent = 'Load Pricing Matrix before saving a template.'; return null; }
    if (lines.some((line) => line.id && !selected(line))) { message.textContent = 'Replace unavailable items before saving.'; return null; }
    return { service_type: service.value, billing_period: period.value, nodes: Number(nodes.value), margin_mode: marginMode.value, margin_value: marginValue.value, lines: lines.filter((line) => selected(line)) };
  }

  async function loadTemplates() {
    try { templates = await request('templates'); renderTemplates(); }
    catch (error) { $('[data-template-status]').textContent = 'Templates unavailable. ' + error.message; }
  }
  $('[data-template-select]').addEventListener('change', () => {
    const template = selectedTemplate();
    const apply=()=>{ if (template) $('[data-template-name]').value = template.name; renderTemplates(); captureDialog(templateDialog); };
    if(window.TRACSUnsavedChanges) window.TRACSUnsavedChanges.requestModalClose(templateDialog,apply);
    else apply();
  });
  $('[data-save-template]').addEventListener('click', async () => {
    if (templateBusy) return;
    const message = $('[data-template-status]');
    const name = $('[data-template-name]').value.trim();
    if (!name) { message.textContent = 'Enter a template name.'; $('[data-template-name]').focus(); return; }
    const configuration = currentTemplateConfiguration(message);
    if (!configuration) return;
    templateBusy = true; $('[data-save-template]').disabled = true;
    try {
      templates = await request('save_template', { name, configuration });
      window.TRACSUnsavedChanges?.markSaved($('#sales-calculator'));
      const saved = templates.find((item) => item.name === name);
      renderTemplates(); message.textContent = `Template saved: ${name}`;
      if (saved) { $('[data-template-select]').value = String(saved.id); renderTemplates(); }
      window.TRACSUnsavedChanges?.markSaved(templateDialog);
    } catch (error) { message.textContent = error.message; }
    finally { templateBusy = false; $('[data-save-template]').disabled = false; renderTemplates(); }
  });
  $('[data-update-template]').addEventListener('click', async () => {
    if (templateBusy) return;
    const template = selectedTemplate();
    const message = $('[data-template-status]');
    if (!template) { message.textContent = 'Choose a template to update.'; return; }
    const name = $('[data-template-name]').value.trim();
    if (!name) { message.textContent = 'Enter a template name.'; $('[data-template-name]').focus(); return; }
    const configuration = currentTemplateConfiguration(message);
    if (!configuration) return;
    templateBusy = true; $('[data-update-template]').disabled = true;
    try {
      templates = await request('save_template', { id: template.id, name, configuration });
      window.TRACSUnsavedChanges?.markSaved($('#sales-calculator'));
      $('[data-template-select]').value = String(template.id);
      renderTemplates(); message.textContent = `Template updated: ${name}`;
      window.TRACSUnsavedChanges?.markSaved(templateDialog);
    } catch (error) { message.textContent = error.message; }
    finally { templateBusy = false; renderTemplates(); }
  });
  $('[data-delete-template]').addEventListener('click', async () => {
    if (templateBusy) return;
    const template = selectedTemplate();
    const message = $('[data-template-status]');
    if (!template) { message.textContent = 'Choose a template to delete.'; return; }
    if (!window.confirm(`Delete template "${template.name}"?`)) return;
    templateBusy = true; $('[data-delete-template]').disabled = true;
    try {
      templates = await request('delete_template', { id: template.id });
      $('[data-template-select]').value = '';
      $('[data-template-name]').value = '';
      renderTemplates(); message.textContent = `Template deleted: ${template.name}`;
    } catch (error) { message.textContent = error.message; }
    finally { templateBusy = false; renderTemplates(); }
  });
  $('[data-load-template]').addEventListener('click', async () => {
    if (templateBusy) return;
    const template = selectedTemplate();
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
      scheduleDraftSave();
      window.TRACSUnsavedChanges?.markSaved($('#sales-calculator'));
      window.TRACSUnsavedChanges?.markSaved(templateDialog);
      templateDialog?.close();
      message.textContent = `Loaded: ${template.name}. Current Pricing Matrix prices and PPN apply.`;
    } catch (error) { message.textContent = error.message; }
    finally { templateBusy = false; renderTemplates(); }
  });
  refresh();
  loadTemplates();
}());
