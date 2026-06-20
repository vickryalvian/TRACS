import { chromium } from 'playwright';
import { spawn, spawnSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

const root = new URL('../..', import.meta.url).pathname;
const database = process.env.TRACS_TEST_DB_NAME || 'tracs_phase37_test';
const port = Number(process.env.TRACS_BROWSER_PORT || 8787);
const baseUrl = `http://127.0.0.1:${port}`;
const chromePath = process.env.TRACS_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

function fail(message) {
  throw new Error(message);
}

function safeDatabase(name) {
  return /^[A-Za-z0-9_]*(test|local|dev|disposable|staging)[A-Za-z0-9_]*$/i.test(name);
}

function run(command, args, options = {}) {
  const result = spawnSync(command, args, {
    cwd: root,
    env: {
      ...process.env,
      TRACS_ENV: 'test',
      TRACS_ALLOW_MUTATION_TESTS: '1',
      TRACS_TEST_DB_NAME: database,
      TRACS_TEST_DB_HOST: process.env.TRACS_TEST_DB_HOST || '127.0.0.1',
      TRACS_TEST_DB_PORT: process.env.TRACS_TEST_DB_PORT || '3307',
      TRACS_TEST_DB_USER: process.env.TRACS_TEST_DB_USER || 'root',
      TRACS_TEST_DB_PASS: process.env.TRACS_TEST_DB_PASS || 'root_secret',
      DB_HOST: process.env.TRACS_TEST_DB_HOST || '127.0.0.1',
      DB_PORT: process.env.TRACS_TEST_DB_PORT || '3307',
      DB_USER: process.env.TRACS_TEST_DB_USER || 'root',
      DB_PASS: process.env.TRACS_TEST_DB_PASS || 'root_secret',
      DB_NAME: database,
    },
    encoding: 'utf8',
    ...options,
  });
  if (result.status !== 0) {
    fail(`${command} ${args.join(' ')} failed\n${result.stdout}\n${result.stderr}`);
  }
  return result.stdout.trim();
}

function mysql(sql) {
  return run('docker', [
    'exec',
    process.env.TRACS_TEST_DB_CONTAINER || 'tracs_db',
    'mysql',
    `-u${process.env.TRACS_TEST_DB_USER || 'root'}`,
    `-p${process.env.TRACS_TEST_DB_PASS || 'root_secret'}`,
    '-N',
    '-B',
    database,
    '-e',
    sql,
  ]);
}

function apiHarness(method, userId, csrfMode, query, body, resource) {
  const output = run('php', [
    'tests/fixtures/shift-assignment-api-request.php',
    method,
    String(userId),
    csrfMode,
    JSON.stringify(query || {}),
    Buffer.from(JSON.stringify(body || {})).toString('base64'),
    resource,
  ]);
  return JSON.parse(output);
}

async function waitForServer(server) {
  for (let attempt = 0; attempt < 80; attempt += 1) {
    if (server.exitCode !== null) {
      fail('PHP browser validation server exited before it was reachable.');
    }
    try {
      const response = await fetch(`${baseUrl}/login.php`);
      if (response.status < 500) {
        return;
      }
    } catch {
      // wait and retry
    }
    await delay(250);
  }
  fail(`PHP browser validation server was not reachable at ${baseUrl}.`);
}

function assertNoConsoleErrors(consoleErrors, failedRequests) {
  if (consoleErrors.length) {
    fail(`Browser console errors were reported:\n${consoleErrors.join('\n')}`);
  }
  const failed = failedRequests.filter((url) => !url.includes('/favicon.ico'));
  if (failed.length) {
    fail(`Browser request failures were reported:\n${failed.join('\n')}`);
  }
}

if (process.env.TRACS_ENV !== 'test') {
  fail('TRACS_ENV=test is required for browser validation.');
}
if (process.env.TRACS_ALLOW_MUTATION_TESTS !== '1') {
  fail('TRACS_ALLOW_MUTATION_TESTS=1 is required for browser validation.');
}
if (!safeDatabase(database)) {
  fail('TRACS_TEST_DB_NAME must contain test/local/dev/disposable/staging.');
}

let server;
let browser;
let createdIds = [];

try {
  const setup = JSON.parse(run('php', ['tests/shift-assignment-create-ui-browser-environment.php', 'setup']));
  if (setup.database !== database) {
    fail('Browser environment setup returned an unexpected database name.');
  }

  apiHarness('POST', 9731, 'valid', {}, {
    agent_id: 9733,
    assignment_date: '2026-06-21',
    shift_type: 'regular_shift',
    start_time: '00:00',
    end_time: '08:00',
    status: 'assigned',
    notes: 'Phase 37 unrelated baseline assignment',
  }, 'assignments');
  const baselineId = Number(mysql("SELECT COALESCE(MAX(id),0) FROM shift_assignments WHERE user_id=9733 AND assignment_date='2026-06-21'"));
  if (!baselineId) {
    fail('Unable to create unrelated baseline assignment.');
  }

  server = spawn('php', ['-d', 'variables_order=EGPCS', '-S', `127.0.0.1:${port}`, '-t', 'public'], {
    cwd: root,
    env: {
      ...process.env,
      TRACS_ENV: 'test',
      TRACS_ALLOW_MUTATION_TESTS: '1',
      DB_HOST: process.env.TRACS_TEST_DB_HOST || '127.0.0.1',
      DB_PORT: process.env.TRACS_TEST_DB_PORT || '3307',
      DB_USER: process.env.TRACS_TEST_DB_USER || 'root',
      DB_PASS: process.env.TRACS_TEST_DB_PASS || 'root_secret',
      DB_NAME: database,
    },
    stdio: ['ignore', 'pipe', 'pipe'],
  });
  await waitForServer(server);

  browser = await chromium.launch({
    executablePath: chromePath,
    headless: true,
  });
  const page = await browser.newPage();
  const consoleErrors = [];
  const failedRequests = [];
  const calledUrls = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });
  page.on('request', (request) => {
    calledUrls.push(request.url());
  });
  page.on('requestfailed', (request) => {
    failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`);
  });

  const sessionResponse = await page.goto(`${baseUrl}/__test/shift-assignment-auth-session.php?user_id=9731`, { waitUntil: 'networkidle' });
  if (!sessionResponse || sessionResponse.status() !== 200) {
    fail('Unable to establish guarded test authenticated session.');
  }
  const sessionJson = JSON.parse(await page.locator('body').innerText());
  if (!sessionJson.success || sessionJson.data?.role_slug !== 'super_admin') {
    fail('Guarded test session did not authenticate the expected Super Admin.');
  }

  await page.goto(`${baseUrl}/shift-assignment-react-preview.php`, { waitUntil: 'networkidle' });
  await page.getByText('React Preview Pilot', { exact: true }).waitFor();
  await page.getByText(/React Preview Pilot .* Template Preview\/Apply/).waitFor();
  if (await page.getByRole('button', { name: 'Preview Template' }).count() < 1) {
    const contextResponse = await page.evaluate(async () => {
      const response = await fetch('/api/v1/shift-assignment/context.php', { credentials: 'same-origin' });
      return { status: response.status, body: await response.text() };
    });
    fail(`Preview Template button was not visible. Context:\n${JSON.stringify(contextResponse, null, 2)}\nPage text:\n${await page.locator('body').innerText()}`);
  }
  await page.getByRole('button', { name: 'Preview Template' }).click();
  const dialog = page.getByRole('dialog', { name: 'Template Preview' });
  await dialog.waitFor();

  await dialog.locator('select[name="agent_id"]').selectOption('9733');
  await dialog.locator('input[name="start_date"]').fill('15-06-2026');
  await dialog.locator('input[name="end_date"]').fill('15-06-2026');
  await dialog.locator('select[name="day_of_week"]').selectOption('1');
  await dialog.locator('select[name="shift_preset"]').selectOption('shift_1');
  await dialog.getByRole('button', { name: 'Generate Preview' }).click();
  await page.getByText('Template preview generated').waitFor();
  await dialog.getByRole('heading', { name: 'Preview items' }).waitFor();
  await dialog.getByRole('heading', { name: 'Conflicts' }).waitFor();
  await page.getByRole('button', { name: 'Apply Template' }).waitFor();

  const applyButton = dialog.getByRole('button', { name: 'Apply Template' });
  const confirmation = dialog.getByLabel('Apply Template confirmation');
  for (const invalid of ['apply template', 'Apply Template', ' APPLY TEMPLATE', 'APPLY TEMPLATE ', 'APPLY  TEMPLATE', 'APPLY-TEMPLATE']) {
    await confirmation.fill(invalid);
    if (await applyButton.isEnabled()) {
      fail(`Apply Template button enabled for invalid confirmation: ${JSON.stringify(invalid)}`);
    }
  }
  await confirmation.fill('APPLY TEMPLATE');
  if (!(await applyButton.isEnabled())) {
    fail('Apply Template button did not enable for exact confirmation.');
  }
  await applyButton.click();
  await page.getByText(/Template applied:/).waitFor();
  await page.getByText('Request ID:').waitFor();
  await page.getByText('Rollback targeting is based on the created assignment IDs').waitFor();

  const idsText = await page.locator('text=/IDs: /').first().innerText();
  createdIds = idsText.replace(/^IDs:\s*/, '').split(',').map((value) => Number(value.trim())).filter(Boolean);
  if (!createdIds.length) {
    fail('Apply Template result did not display created assignment IDs.');
  }
  const createdCount = Number(mysql(`SELECT COUNT(*) FROM shift_assignments WHERE id IN (${createdIds.join(',')})`));
  if (createdCount !== createdIds.length) {
    fail('Browser-created template assignments were not persisted.');
  }
  const auditCount = Number(mysql(`SELECT COUNT(*) FROM tracs_user_activity_logs WHERE actor_user_id=9731 AND action='shift_assignment.template.commit' AND after_data LIKE '%${createdIds[0]}%'`));
  if (auditCount < 1) {
    fail('Template commit audit did not include browser-created IDs.');
  }
  await page.getByText('15-06-2026').first().waitFor();

  mysql(`DELETE FROM shift_warnings WHERE shift_assignment_id IN (${createdIds.join(',')})`);
  mysql(`DELETE FROM holiday_coverage_assignments WHERE shift_assignment_id IN (${createdIds.join(',')})`);
  mysql(`DELETE FROM shift_assignments WHERE id IN (${createdIds.join(',')})`);
  const rollbackRemaining = Number(mysql(`SELECT COUNT(*) FROM shift_assignments WHERE id IN (${createdIds.join(',')})`));
  const baselineRemaining = Number(mysql(`SELECT COUNT(*) FROM shift_assignments WHERE id=${baselineId}`));
  if (rollbackRemaining !== 0 || baselineRemaining !== 1) {
    fail('Rollback targeting did not remove only browser-created assignments.');
  }

  await page.getByLabel('Dismiss notification').click();
  await dialog.locator('select[name="agent_id"]').selectOption('9733');
  await dialog.locator('input[name="start_date"]').fill('21-06-2026');
  await dialog.locator('input[name="end_date"]').fill('21-06-2026');
  await dialog.locator('select[name="day_of_week"]').selectOption('7');
  await dialog.locator('select[name="shift_preset"]').selectOption('shift_1');
  await dialog.getByRole('button', { name: 'Generate Preview' }).click();
  await dialog.getByRole('heading', { name: 'Conflicts' }).waitFor();
  await page.getByText('Resolve conflicts before applying this template.').waitFor();
  if (await page.getByRole('button', { name: 'Apply Template' }).isEnabled()) {
    fail('Apply Template was enabled for a conflicting preview.');
  }

  if (calledUrls.some((url) => url.includes('copy-preview.php') || url.includes('copy-commit.php'))) {
    fail('Browser validation unexpectedly called a copy endpoint.');
  }
  const html = await page.content();
  if (html.includes('Copy schedule') || html.includes('Rollback Template') || html.includes('Undo Template')) {
    fail('Browser validation found forbidden copy/paste or rollback UI.');
  }

  assertNoConsoleErrors(consoleErrors, failedRequests);

  console.log(JSON.stringify({
    success: true,
    database,
    base_url: baseUrl,
    user: 'phase17-super',
    created_assignment_ids: createdIds,
    baseline_assignment_id: baselineId,
    audit_count: auditCount,
    rollback_remaining: rollbackRemaining,
    baseline_remaining: baselineRemaining,
    copy_endpoints_called: false,
    console_errors: 0,
    request_failures: 0,
  }, null, 2));
} finally {
  if (browser) {
    await browser.close();
  }
  if (server && server.exitCode === null) {
    server.kill('SIGTERM');
    await delay(250);
  }
  try {
    run('php', ['tests/shift-assignment-create-ui-browser-environment.php', 'cleanup']);
  } catch (error) {
    console.error(error.message);
    process.exitCode = 1;
  }
}
