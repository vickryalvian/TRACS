const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('../frontend/node_modules/playwright');
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
        return route.fulfill({ json: action === 'save_item' && fail ? { success: false, message: 'Test save failure' } : { success: true, data: action === 'templates' ? [] : catalog } });
      }
      return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><link rel="stylesheet" href="/assets/tracs.css"><link rel="stylesheet" href="/assets/infrastructure-configurator.css"></head><body>${markup}<script src="/assets/unsaved-changes-guard.js"></script><script src="/assets/infrastructure-configurator.js"></script></body></html>` });
    });
    await page.goto('http://tracs.test/configurator.php');
    await page.locator('[data-service]:not(:disabled)').waitFor();
    const dirty = () => page.evaluate(() => TRACSUnsavedChanges.isDirty());
    assert.equal(await dirty(), false);
    await page.locator('[data-period]').selectOption('annual'); assert.equal(await dirty(), true);
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
    console.log('PASS: configurator initialization; period/row revert; native dialog clean close, dirty Keep Editing, Escape; failed and successful item save.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
