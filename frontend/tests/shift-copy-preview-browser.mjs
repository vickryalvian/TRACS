import { chromium } from 'playwright';
import { spawn, spawnSync } from 'node:child_process';
import { setTimeout as delay } from 'node:timers/promises';

const root = new URL('../..', import.meta.url).pathname;
const database = process.env.TRACS_TEST_DB_NAME || 'tracs_phase40_test';
const port = Number(process.env.TRACS_COPY_BROWSER_PORT || 8788);
const baseUrl = `http://127.0.0.1:${port}`;
const chromePath = process.env.TRACS_CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

function fail(message) {
  throw new Error(message);
}

function safeDatabase(name) {
  return /^[A-Za-z0-9_]*(test|local|dev|disposable|staging)[A-Za-z0-9_]*$/i.test(name);
}

function run(command, args) {
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
      fail('PHP copy-preview browser server exited before it was reachable.');
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
  fail(`PHP copy-preview browser server was not reachable at ${baseUrl}.`);
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
  fail('TRACS_ENV=test is required for copy-preview browser validation.');
}
if (process.env.TRACS_ALLOW_MUTATION_TESTS !== '1') {
  fail('TRACS_ALLOW_MUTATION_TESTS=1 is required for copy-preview browser validation.');
}
if (!safeDatabase(database)) {
  fail('TRACS_TEST_DB_NAME must contain test/local/dev/disposable/staging.');
}

let server;
let browser;

try {
  const setup = JSON.parse(run('php', ['tests/shift-assignment-create-ui-browser-environment.php', 'setup']));
  if (setup.database !== database) {
    fail('Copy-preview browser setup returned an unexpected database name.');
  }

  for (const [assignmentDate, startTime, endTime, notes] of [
    ['2026-07-01', '00:00', '08:00', 'Phase 40 source Shift 1'],
    ['2026-07-02', '08:00', '16:00', 'Phase 40 source Shift 2'],
    ['2026-07-03', '16:00', '24:00', 'Phase 40 source Shift 3'],
  ]) {
    apiHarness('POST', 9731, 'valid', {}, {
      agent_id: 9733,
      assignment_date: assignmentDate,
      shift_type: 'regular_shift',
      start_time: startTime,
      end_time: endTime,
      status: 'assigned',
      notes,
    }, 'assignments');
  }

  const beforeCounts = {
    assignments: Number(mysql('SELECT COUNT(*) FROM shift_assignments')),
    warnings: Number(mysql('SELECT COUNT(*) FROM shift_warnings')),
    audits: Number(mysql('SELECT COUNT(*) FROM assignment_audit_logs')),
  };

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

  browser = await chromium.launch({ executablePath: chromePath, headless: true });
  const page = await browser.newPage();
  const consoleErrors = [];
  const failedRequests = [];
  const calledUrls = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      consoleErrors.push(message.text());
    }
  });
  page.on('request', (request) => calledUrls.push(request.url()));
  page.on('requestfailed', (request) => {
    failedRequests.push(`${request.method()} ${request.url()} ${request.failure()?.errorText || ''}`);
  });

  const sessionResponse = await page.goto(`${baseUrl}/__test/shift-assignment-auth-session.php?user_id=9731`, { waitUntil: 'networkidle' });
  if (!sessionResponse || sessionResponse.status() !== 200) {
    fail('Unable to establish guarded test authenticated session.');
  }

  await page.goto(`${baseUrl}/shift-assignment-react-preview.php`, { waitUntil: 'networkidle' });
  await page.getByText('React Preview Pilot', { exact: true }).waitFor();
  await page.getByRole('button', { name: 'Copy Schedule Preview' }).click();

  const dialog = page.getByRole('dialog', { name: 'Copy Schedule Preview' });
  await dialog.waitFor();
  await page.getByText('Preview only - this will not create or modify assignments.').first().waitFor();

  await dialog.locator('input[name="source_start_date"]').fill('');
  await dialog.getByRole('button', { name: 'Generate Copy Preview' }).click();
  await dialog.getByText('Use dd-mm-yyyy.').first().waitFor();
  if ((await dialog.locator('input[name="source_start_date"]').getAttribute('aria-invalid')) !== 'true') {
    fail('Missing source date did not mark the field invalid.');
  }

  await dialog.locator('input[name="source_start_date"]').fill('01-07-2026');
  await dialog.locator('input[name="source_end_date"]').fill('03-07-2026');
  await dialog.locator('input[name="target_start_date"]').fill('01-07-2026');
  await dialog.locator('input[name="target_end_date"]').fill('03-07-2026');
  await dialog.getByRole('button', { name: 'Generate Copy Preview' }).click();
  await dialog.getByText('Source and target ranges must be different.').waitFor();

  await dialog.locator('input[name="target_start_date"]').fill('01-08-2026');
  await dialog.locator('input[name="target_end_date"]').fill('04-08-2026');
  await dialog.getByRole('button', { name: 'Generate Copy Preview' }).click();
  await dialog.getByText('Source and target ranges must have the same length.').waitFor();

  await dialog.locator('input[name="source_end_date"]').fill('15-08-2026');
  await dialog.locator('input[name="target_end_date"]').fill('15-09-2026');
  await dialog.getByRole('button', { name: 'Generate Copy Preview' }).click();
  await dialog.getByText('Source range cannot exceed 35 days.').waitFor();

  await dialog.locator('input[name="source_start_date"]').fill('01-07-2026');
  await dialog.locator('input[name="source_end_date"]').fill('03-07-2026');
  await dialog.locator('input[name="target_start_date"]').fill('01-08-2026');
  await dialog.locator('input[name="target_end_date"]').fill('03-08-2026');
  await dialog.getByRole('button', { name: 'Generate Copy Preview' }).click();
  await page.getByText('Copy preview generated').waitFor();
  await dialog.getByRole('heading', { name: 'Preview items' }).waitFor();
  await dialog.getByText('16:00-24:00').waitFor();
  await dialog.locator('input[name="target_end_date"]').fill('04-08-2026');
  await dialog.getByText('Date options changed after the last preview.').waitFor();
  await dialog.locator('input[name="target_end_date"]').fill('03-08-2026');

  const afterPreviewCounts = {
    assignments: Number(mysql('SELECT COUNT(*) FROM shift_assignments')),
    warnings: Number(mysql('SELECT COUNT(*) FROM shift_warnings')),
    audits: Number(mysql('SELECT COUNT(*) FROM assignment_audit_logs')),
  };
  if (JSON.stringify(afterPreviewCounts) !== JSON.stringify(beforeCounts)) {
    fail(`Copy preview mutated persisted counts: before=${JSON.stringify(beforeCounts)} after=${JSON.stringify(afterPreviewCounts)}`);
  }

  apiHarness('POST', 9731, 'valid', {}, {
    agent_id: 9733,
    assignment_date: '2026-08-01',
    shift_type: 'regular_shift',
    start_time: '00:00',
    end_time: '08:00',
    status: 'assigned',
    notes: 'Phase 40 target conflict',
  }, 'assignments');
  await dialog.getByRole('button', { name: 'Generate Copy Preview' }).click();
  await dialog.getByRole('heading', { name: 'Conflicts' }).waitFor();
  await dialog.getByRole('heading', { name: 'Blocked items' }).waitFor();

  if (!calledUrls.some((url) => url.includes('copy-preview.php'))) {
    fail('Copy Preview browser flow did not call copy-preview.php.');
  }
  if (calledUrls.some((url) => url.includes('copy-commit.php'))) {
    fail('Copy Preview browser flow unexpectedly called copy-commit.php.');
  }
  const html = await page.content();
  for (const forbiddenUi of ['Apply Copy', 'Commit Copy', 'Paste Schedule', 'Rollback Template', 'Undo Template']) {
    if (html.includes(forbiddenUi)) {
      fail(`Copy Preview browser flow found forbidden UI: ${forbiddenUi}.`);
    }
  }

  assertNoConsoleErrors(consoleErrors, failedRequests);

  console.log(JSON.stringify({
    success: true,
    database,
    base_url: baseUrl,
    user: 'phase17-super',
    source_assignments: 3,
    no_mutation_counts: afterPreviewCounts,
    copy_preview_called: true,
    copy_commit_called: false,
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
  } catch {
    // cleanup failure is surfaced by disposable count checks in the caller
  }
}
