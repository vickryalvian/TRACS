const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || '../frontend/node_modules/playwright');
const root = path.resolve(__dirname, '..');
const markup = fs.readFileSync(path.join(root, 'modules/infrastructure-configurator/view.php'), 'utf8').replace(/<\?[\s\S]*?\?>/g, '').replace(/<script[\s\S]*?<\/script>/g, '');
const catalog = { tax_rate: 0.11, tax_revision: 1, items: [
  { id: 1, service_type: 'VPS', category: 'CPU', billing_period: 'monthly', active: true, name: 'CPU', price: 100, sort_order: 0 },
  { id: 2, service_type: 'VPS', category: 'CPU', billing_period: 'annual', active: true, name: 'CPU annual', price: 1000, sort_order: 0 },
] };
(async () => {
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    let fail = true;
    await page.route('http://tracs.test/**', route => {
      const url = new URL(route.request().url());
      if (url.pathname.startsWith('/assets/')) return route.fulfill({ path: path.join(root, 'public', url.pathname) });
      if (url.searchParams.has('action')) {
        const action = url.searchParams.get('action');
        if (action === 'save_item' && fail) return route.fulfill({ json: { success: false, message: 'Test save failure' } });
        if (action === 'calculate') {
          const input = route.request().postDataJSON();
          const subtotal = input.lines.reduce((sum, line) => sum + Number(line.override_price ?? catalog.items.find(item => item.id === line.id)?.price ?? 0) * Number(line.quantity), 0);
          const base = subtotal * Number(input.nodes);
          const margin = input.margin_mode === 'amount' ? Number(input.margin_value) : base * Number(input.margin_value) / 100;
          const beforeTax = base + margin;
          return route.fulfill({ json: { success: true, data: { subtotal_per_node: subtotal, subtotal_before_margin: base, margin, subtotal_before_tax: beforeTax, tax: beforeTax * catalog.tax_rate, grand_total: beforeTax * (1 + catalog.tax_rate) } } });
        }
        return route.fulfill({ json: { success: true, data: action === 'templates' ? [] : catalog } });
      }
      return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><link rel="stylesheet" href="/assets/tracs.css"><link rel="stylesheet" href="/assets/infrastructure-configurator.css"></head><body><a href="/dashboard.php" data-test-navigation>Dashboard</a>${markup}<script src="/assets/unsaved-changes-guard.js"></script><script src="/assets/infrastructure-configurator.js"></script></body></html>` });
    });
    await page.addInitScript(() => {
      window.__copiedText = '';
      window.__toastMessage = '';
      Object.defineProperty(navigator, 'clipboard', { configurable: true, value: { writeText: async text => { window.__copiedText = text; } } });
      window.showToast = message => { window.__toastMessage = message; };
    });
    await page.goto('http://tracs.test/configurator.php');
    await page.locator('[data-service]:not(:disabled)').waitFor();
    const dirty = () => page.evaluate(() => TRACSUnsavedChanges.isDirty());
    assert.equal(await dirty(), false);
    await page.locator('[data-item]').selectOption('1');
    await page.locator('[data-add-custom]').click();
    await page.locator('[data-custom-name]').fill('Custom support');
    await page.locator('[data-quantity]').fill('2');
    await page.locator('[data-lines] > div').last().locator('[data-edit-price]').click();
    await page.locator('[data-lines] > div').last().locator('[data-price]').fill('50');
    await page.locator('[data-lines] > div').last().locator('[data-apply-price]').click();
    await page.locator('[data-copy-specs]').click();
    assert.equal(await page.evaluate(() => window.__copiedText), 'CPU: CPU\nCustom: 2 x Custom support');
    assert.equal(await page.evaluate(() => window.__toastMessage), 'Specifications copied');
    await page.waitForFunction(() => localStorage.getItem('tracs:sales-configurator:draft:v1')?.includes('Custom support'));
    const totalBeforeReload = await page.locator('[data-total="grand_total"]').textContent();
    assert.equal(await page.locator('.tracs-unsaved-bar').isHidden(), true);
    page.once('dialog', dialog => dialog.accept());
    await page.reload();
    await page.locator('[data-service]:not(:disabled)').waitFor();
    assert.equal(await page.locator('[data-item]').inputValue(), '1');
    assert.equal(await page.locator('[data-custom-name]').inputValue(), 'Custom support');
    assert.equal(await page.locator('[data-quantity]').inputValue(), '2');
    assert.equal(await page.locator('[data-total="grand_total"]').textContent(), totalBeforeReload);
    assert.equal(await dirty(), false);
    page.once('dialog', dialog => dialog.accept());
    await page.locator('[data-clear-configuration]').click();
    assert.equal(await page.evaluate(() => localStorage.getItem('tracs:sales-configurator:draft:v1')), null);
    assert.equal(await page.locator('[data-item]').inputValue(), '0');
    assert.equal(await dirty(), false);
    await page.locator('[data-period]').selectOption('annual'); assert.equal(await dirty(), true);
    await page.locator('[data-test-navigation]').click();
    await page.locator('.tracs-unsaved-dialog-overlay:not(.hidden)').getByRole('button', { name: 'Keep Editing' }).click();
    assert.equal(new URL(page.url()).pathname, '/configurator.php');
    await page.locator('[data-period]').selectOption('monthly'); assert.equal(await dirty(), false);
    await page.locator('[data-add]').click(); assert.equal(await dirty(), true);
    await page.locator('[data-remove]').last().click(); assert.equal(await dirty(), false);
    await page.locator('[data-open-master]').click(); assert.equal(await dirty(), false);
    await page.locator('[data-new-item]').click(); assert.equal(await dirty(), false);
    await page.locator('[data-close-item]').click(); assert.equal(await dirty(), false);
    await page.locator('[data-new-item]').click();
    const editor = page.locator('[data-item-dialog]');
    await editor.locator('[name="name"]').fill('New item');
    await page.locator('[data-close-item]').click();
    const prompt = editor.locator('.tracs-unsaved-dialog-overlay:not(.hidden)');
    await prompt.getByRole('button', { name: 'Keep Editing' }).click();
    await editor.locator('[name="name"]').fill(''); assert.equal(await dirty(), false);
    await page.keyboard.press('Escape'); assert.equal(await editor.getAttribute('open'), null);
    await page.locator('[data-new-item]').click();
    await editor.locator('[name="name"]').fill('New item');
    await editor.locator('[name="category"]').fill('CPU');
    await editor.locator('[name="price"]').fill('100');
    await editor.getByRole('button', { name: 'Save Item' }).click();
    await page.locator('[data-item-error]').getByText('Test save failure').waitFor(); assert.equal(await dirty(), true);
    fail = false;
    await editor.getByRole('button', { name: 'Save Item' }).click();
    await editor.waitFor({ state: 'hidden' }); assert.equal(await dirty(), false);
    assert.deepEqual(errors, []);
    console.log('PASS: configurator draft restore/clear, copy specs, hidden banner, navigation protection, period/row revert, dialog dirty handling, and item save.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
