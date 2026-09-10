(function () {
  'use strict';
  const root = document.querySelector('[data-sales-configurator]');
  if (!root) return;
  const $ = (selector) => root.querySelector(selector);
  const service = $('[data-service]');
  const period = $('[data-period]');
  const nodes = $('[data-nodes]');
  const status = $('[data-status]');
  const calculationStatus = $('[data-calculation-status]');
  const labels = { monthly: 'Monthly', annual: 'Annual', one_time: 'One-Time' };
  const currency = new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0, maximumFractionDigits: 2 });
  const money = (value) => currency.format(value);
  const round = (value) => Math.round((value + Number.EPSILON) * 100) / 100;
  let catalog = null;
  let lines = [];
  let sequence = 0;
  let timer;
  let loading = false;
  const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const activeItems = () => (catalog?.items || []).filter((item) => item.active);
  const available = () => activeItems().filter((item) => item.service_type === service.value && item.billing_period === period.value);
  const categories = () => [...new Set(available().map((item) => item.category))];
  const selected = (line) => available().find((item) => item.id === line.id && item.category === line.category);
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
    $('[data-lines]').innerHTML = lines.map((line, index) => {
      const item = selected(line);
      const options = available().filter((option) => option.category === line.category);
      return `<div class="sales-line" data-line="${index}">
        <label class="sales-field">Category<select class="form-select" data-category>${cats.map((category) => `<option ${category === line.category ? 'selected' : ''}>${esc(category)}</option>`).join('')}</select></label>
        <label class="sales-field">Item<select class="form-select" data-item><option value="0">Select ${esc(line.category)}</option>${options.map((option) => `<option value="${option.id}" ${option.id === line.id ? 'selected' : ''}>${esc(option.name)}</option>`).join('')}</select></label>
        <output class="sales-price">${item ? esc(money(item.price * line.quantity)) : '-'}</output>
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
    const subtotal = chosen.reduce((sum, line) => sum + selected(line).price * line.quantity, 0);
    const beforeTax = round(subtotal * count);
    if (!Number.isFinite(beforeTax) || beforeTax > 1e14) { display(null); calculationStatus.textContent = 'Total exceeds the supported amount.'; return; }
    const tax = round(beforeTax * catalog.tax_rate);
    const totals = { subtotal_per_node: round(subtotal), subtotal_before_tax: beforeTax, tax, grand_total: round(beforeTax + tax) };
    display(totals);
    calculationStatus.textContent = chosen.length ? 'Checking current prices...' : 'No items selected.';
    if (!chosen.length) return;
    timer = setTimeout(async () => {
      try {
        const verified = await request('calculate', { service_type: service.value, billing_period: period.value, nodes: count, lines: chosen });
        if (current !== sequence) return;
        if (Object.keys(totals).some((key) => Math.abs(totals[key] - verified[key]) > 0.005)) {
          display(null); calculationStatus.textContent = 'Master prices changed. Refresh Prices before continuing.';
        } else {
          display(verified); calculationStatus.textContent = lines.some((line) => !line.id) ? 'Unselected rows are excluded.' : '';
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
    const items = catalog.items.filter((item) => `${item.service_type} ${item.category} ${item.name}`.toLowerCase().includes(search));
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
    service.disabled = period.disabled = $('[data-add]').disabled = true;
    try { const data = await request('catalog'); loading = false; useCatalog(data); }
    catch (error) { catalog = null; lines = []; $('[data-lines]').replaceChildren(); status.textContent = error.message; }
    finally { loading = false; $('[data-refresh]').disabled = false; }
  }

  $('[data-lines]').addEventListener('change', (event) => {
    const row = event.target.closest('[data-line]');
    if (!row) return;
    const line = lines[Number(row.dataset.line)];
    if (event.target.matches('[data-category]')) { line.category = event.target.value; line.id = 0; line.quantity = 1; }
    if (event.target.matches('[data-item]')) { line.id = Number(event.target.value); line.quantity = 1; }
    if (!event.target.matches('[data-quantity]')) {
      const selector = event.target.matches('[data-category]') ? '[data-category]' : '[data-item]';
      const index = row.dataset.line;
      renderLines(); $(`[data-line="${index}"] ${selector}`)?.focus(); calculate();
    }
  });
  $('[data-lines]').addEventListener('input', (event) => {
    if (!event.target.matches('[data-quantity]')) return;
    const row = event.target.closest('[data-line]');
    const line = lines[Number(row.dataset.line)];
    line.quantity = Number(event.target.value);
    row.querySelector('.sales-price').textContent = event.target.checkValidity() ? money(selected(line).price * line.quantity) : '-';
    calculate();
  });
  $('[data-lines]').addEventListener('click', (event) => {
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
  period.addEventListener('change', resetLines);
  nodes.addEventListener('input', calculate);
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
  const form = $('[data-item-form]');
  const dialog = $('[data-item-dialog]');
  function edit(item = {}) {
    if (!catalog) return;
    form.reset();
    const values = { id: 0, revision: 0, sort_order: 0, active: true, unit_quantity: false, service_type: service.value, billing_period: 'monthly', ...item };
    for (const [key, value] of Object.entries(values)) {
      const field = form.elements.namedItem(key);
      if (!field) continue;
      if (field.type === 'checkbox') field.checked = Boolean(value); else field.value = value ?? '';
    }
    $('[data-item-error]').textContent = '';
    dialog.showModal();
  }
  $('[data-new-item]')?.addEventListener('click', () => edit());
  $('[data-master-body]')?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-edit]');
    if (button) edit(catalog.items.find((item) => item.id === Number(button.dataset.edit)));
  });
  $('[data-cancel]')?.addEventListener('click', () => dialog.close());
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
  refresh();
}());
