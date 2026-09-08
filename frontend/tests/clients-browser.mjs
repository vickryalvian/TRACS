import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFile, mkdir } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const publicRoot = fileURLToPath(new URL('../../public/', import.meta.url));
const manifest = JSON.parse(await readFile(`${publicRoot}assets/react-dist/.vite/manifest.json`, 'utf8'));
const entry = manifest['src/modules/clients/main.jsx'];
const css = new Set(entry.css || []);
for (const key of entry.imports || []) for (const path of manifest[key].css || []) css.add(path);
const html = `<!doctype html><html data-theme="dark"><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/tracs.css">${[...css].map((path) => `<link rel="stylesheet" href="/assets/react-dist/${path}">`).join('')}</head><body><div class="shell"><div class="body-row"><main class="main"><div class="main-inner"><div id="tracs-clients-root"></div></div></main></div></div><script type="module" src="/assets/react-dist/${entry.file}"></script></body></html>`;
const detail = { id: 1, company_name: 'PT Alpha Network', client_code: 'CL-ALPHA', status: 'active', owner_name: 'Owner One', owner_user_id: 1, service_count: 2, addon_count: 0, mrr_amount: 200000, total_paid_amount: 100000, outstanding_amount: 300000, attention_level: 'warning', attention_reason: 'Renewal within 30 days', next_action: 'Review renewal', nearest_renewal_date: '2026-09-20', contacts: [{ id: 1, name: 'Primary PIC', email: 'primary@example.test', role_title: 'Operations', is_primary: 1 }, { id: 2, name: 'Billing PIC', email: 'billing@example.test', role_title: 'Billing', is_primary: 0 }], services: [{ id: 1, service_name: 'Dedicated Server', renewal_date: '2026-09-20', status: 'active', price: 100000, billing_cycle: 'monthly', addons: [] }], billing: [], followups: [], activity: [] };
const records = Array.from({ length: 31 }, (_, index) => ({ ...detail, id: index + 1, company_name: index ? `PT Client ${String(index).padStart(2, '0')}` : detail.company_name, primary_contact_name: 'Primary PIC' }));
let empty = false;
let listFailure = false;
const requests = [];
const mutations = [];
const server = createServer(async (request, response) => {
  const url = new URL(request.url, 'http://localhost');
  try {
    if (url.pathname.startsWith('/api/')) {
      requests.push(url);
      let data;
      if (url.pathname.endsWith('/context.php')) data = { schema_ready: true, user: { id: 1, name: 'Owner One' }, users: [{ id: 1, name: 'Owner One' }], allowed_actions: { manage: true, view_all: true } };
      else if (request.method === 'POST' || request.method === 'PATCH') {
        let body = ''; for await (const chunk of request) body += chunk;
        mutations.push(JSON.parse(body));
        data = { ...detail, ...JSON.parse(body) };
      } else if (url.pathname.endsWith('/client.php')) {
        if (url.searchParams.get('id') === '999') { response.writeHead(404, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ success: false, message: 'Client not found.' })); return; }
        data = detail;
      } else {
        if (listFailure) { response.writeHead(500, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ success: false, message: 'Clients could not be loaded.' })); return; }
        const rows = empty ? [] : records.filter((c) => c.company_name.toLowerCase().includes((url.searchParams.get('q') || '').toLowerCase()));
        const page = Number(url.searchParams.get('page') || 1);
        data = { clients: rows.slice((page - 1) * 25, page * 25), total: rows.length, available_total: empty ? 0 : records.length, page, page_size: 25, summary: { action_required: rows.length, invoice_this_week: 2, renewal_soon: 3, total_paid_amount: 100000, outstanding_amount: 300000, mrr_amount: 200000 }, attention: rows.slice(0, 5) };
      }
      response.writeHead(200, { 'Content-Type': 'application/json' }); response.end(JSON.stringify({ success: true, data })); return;
    }
    if (['/clients.php', '/client-detail.php'].includes(url.pathname)) { response.writeHead(200, { 'Content-Type': 'text/html' }); response.end(html); return; }
    if (!url.pathname.startsWith('/assets/') || url.pathname.includes('..')) { response.writeHead(404); response.end(); return; }
    response.setHeader('Content-Type', url.pathname.endsWith('.css') ? 'text/css' : 'application/javascript');
    response.end(await readFile(publicRoot + url.pathname.slice(1)));
  } catch (error) { response.writeHead(500); response.end(error.message); }
});
await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const browser = await chromium.launch({ executablePath: process.env.TRACS_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome', headless: true });
const page = await browser.newPage({ viewport: { width: 1440, height: 1050 } });
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
const output = process.env.TRACS_CLIENTS_SCREENSHOTS || '/tmp/tracs-clients-qa';
await mkdir(output, { recursive: true });
try {
  await page.goto(`${base}/clients.php`);
  await page.getByRole('heading', { name: '31 Clients', exact: true }).waitFor();
  assert.equal(await page.getByRole('dialog').count(), 0);
  assert.equal(requests.filter((u) => u.pathname.endsWith('/client.php')).length, 0, 'List automatically loaded a detail');
  await page.screenshot({ path: `${output}/desktop-list.png`, fullPage: true });
  assert.notEqual(await page.locator('.client-stat-cell').first().evaluate((el) => getComputedStyle(el).color), 'rgb(0, 0, 0)');
  await page.evaluate(() => document.documentElement.dataset.theme = 'light');
  await page.screenshot({ path: `${output}/desktop-light.png`, fullPage: true });
  await page.evaluate(() => document.documentElement.dataset.theme = 'dark');
  await page.getByRole('button', { name: 'Next', exact: true }).click();
  await page.getByText('26–31 of 31').waitFor();
  assert.match(page.url(), /page=2/);
  await page.getByRole('button', { name: 'Company', exact: true }).click();
  await page.waitForFunction(() => location.search.includes('sort=company_name') && !location.search.includes('page=2'));
  await page.getByLabel('Search', { exact: true }).fill('No matching company');
  await page.getByRole('heading', { name: 'No clients match your filters' }).waitFor();
  assert.equal(await page.getByLabel('Search', { exact: true }).evaluate((element) => element === document.activeElement), true, 'Filter update lost input focus');
  await page.reload();
  await page.getByRole('heading', { name: 'No clients match your filters' }).waitFor();
  await page.getByRole('button', { name: 'Clear filters', exact: true }).first().click();
  await page.getByRole('heading', { name: '31 Clients', exact: true }).waitFor();
  await page.getByRole('button', { name: /Services Renewing/ }).click();
  await page.waitForFunction(() => location.search.includes('signal=renewal'));
  await page.locator('.clients-roster a').first().click();
  await page.getByRole('heading', { name: detail.company_name, exact: true }).waitFor();
  assert.match(page.url(), /client-detail.php/);
  await page.getByRole('button', { name: 'Services', exact: true }).click();
  await page.getByLabel('Service Name', { exact: true }).waitFor();
  await page.reload();
  await page.getByLabel('Service Name', { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Overview', exact: true }).click();
  await page.getByRole('button', { name: 'Add PIC', exact: true }).click();
  let drawer = page.getByRole('dialog', { name: 'Add PIC', exact: true });
  await drawer.getByLabel('Name', { exact: true }).fill('Legal Contact');
  await drawer.getByLabel('Role', { exact: true }).fill('Legal');
  await drawer.getByRole('button', { name: 'Save PIC', exact: true }).click();
  await drawer.waitFor({ state: 'hidden' });
  assert.equal(mutations.at(-1).action, 'save_contact');
  await page.getByRole('link', { name: 'Back to Clients' }).click();
  await page.getByRole('heading', { name: '31 Clients', exact: true }).waitFor();
  assert.match(page.url(), /signal=renewal/);
  await page.getByRole('button', { name: 'Add Client', exact: true }).click();
  drawer = page.getByRole('dialog', { name: 'Add Client', exact: true });
  await drawer.waitFor();
  await page.screenshot({ path: `${output}/desktop-drawer.png`, fullPage: true });
  await page.keyboard.press('Escape');
  await drawer.waitFor({ state: 'hidden' });
  assert.equal(await page.getByRole('button', { name: 'Add Client', exact: true }).evaluate((element) => element === document.activeElement), true);
  await page.getByRole('button', { name: 'Add Client', exact: true }).click();
  await drawer.waitFor();
  await page.mouse.click(120, 120);
  await drawer.waitFor({ state: 'hidden' });
  assert.equal(await page.getByRole('button', { name: 'Add Client', exact: true }).evaluate((element) => element === document.activeElement), true, 'Backdrop close did not restore Add Client focus');
  await page.getByRole('button', { name: 'Add Client', exact: true }).click();
  await drawer.waitFor();
  await drawer.getByLabel('Company', { exact: true }).fill('New Client');
  await drawer.getByRole('button', { name: 'Save & Add Services' }).click();
  await page.getByLabel('Service Name', { exact: true }).waitFor();
  assert.match(page.url(), /tab=services/);
  await page.getByRole('link', { name: 'Back to Clients' }).click();
  await page.getByRole('button', { name: 'Add Client', exact: true }).click();
  await drawer.getByLabel('Company', { exact: true }).fill('Finish Later Client');
  await drawer.getByRole('button', { name: 'Save & Finish Later' }).click();
  await drawer.waitFor({ state: 'hidden' });
  assert.match(page.url(), /clients.php/);
  await page.getByRole('link', { name: 'Go to client' }).click();
  await page.getByRole('heading', { name: detail.company_name, exact: true }).waitFor();
  await page.screenshot({ path: `${output}/desktop-detail.png`, fullPage: true });
  await page.setViewportSize({ width: 390, height: 844 });
  await page.getByRole('link', { name: 'Back to Clients' }).click();
  await page.getByRole('heading', { name: '31 Clients', exact: true }).waitFor();
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Mobile page overflow');
  await page.screenshot({ path: `${output}/mobile-list.png`, fullPage: true });
  await page.getByRole('button', { name: 'More Filters' }).click();
  assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true, 'Mobile filters overflow');
  await page.getByRole('button', { name: 'Add Client', exact: true }).click();
  await drawer.waitFor();
  await page.screenshot({ path: `${output}/mobile-drawer.png`, fullPage: true });
  await page.keyboard.press('Escape');
  empty = true;
  await page.reload();
  await page.getByRole('heading', { name: 'No clients tracked yet' }).waitFor();
  await page.screenshot({ path: `${output}/mobile-empty.png`, fullPage: true });
  listFailure = true;
  await page.reload();
  await page.getByRole('alert').waitFor();
  listFailure = false;
  await page.getByRole('button', { name: 'Retry', exact: true }).click();
  await page.getByRole('alert').waitFor({ state: 'hidden' });
  await page.goto(`${base}/client-detail.php?id=999`);
  await page.getByRole('alert').waitFor();
  assert.deepEqual(errors, []);
  console.log(`Clients browser flow passed. Desktop/mobile screenshots: ${output}`);
} finally { await browser.close(); await new Promise((resolve) => server.close(resolve)); }
