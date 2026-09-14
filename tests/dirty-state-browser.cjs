const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('../frontend/node_modules/playwright');
const root = path.resolve(__dirname, '..');
const footer = fs.readFileSync(path.join(root, 'public/includes/footer.php'), 'utf8');
const caseMarkup = footer.slice(footer.indexOf('<!-- CASE MODAL -->'), footer.indexOf('<div class="modal-overlay hidden" id="caseTicketModal">'));
const taskMarkup = footer.slice(footer.indexOf('<!-- TASK MODAL -->'), footer.indexOf('<!-- VIEW ALL CHECKLIST MODAL -->'));
const shiftMarkup = footer.slice(footer.indexOf('<!-- SHIFT REPORT MODAL -->'), footer.indexOf('<!-- TICKER MANAGER MODAL -->'));
const momMarkup = footer.slice(footer.indexOf('<!-- MOM MODALS -->'), footer.indexOf('<div class="modal-overlay hidden" id="momActionFormModal">')).replace(/<\?[\s\S]*?\?>/g, '').replace('data-case-id=""', 'data-case-id="7"');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  try {
    const page = await browser.newPage();
    await page.addInitScript(() => { window.lucide = { createIcons() {} }; });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    let failSave = false;
    let writes = 0;
    await page.route('http://tracs.test/**', async route => {
      const pathname = new URL(route.request().url()).pathname;
      if (pathname.startsWith('/assets/')) {
        const file = path.join(root, 'public', pathname);
        if (fs.existsSync(file)) return route.fulfill({ path: file });
        return route.fulfill({ status: 404, body: '' });
      }
      if (pathname.startsWith('/api/')) {
        if (/create|update/.test(pathname)) writes++;
        return route.fulfill({ json: failSave && /create|update/.test(pathname) ? { success: false, message: 'Test save failure' } : { success: true, data: { id: 7, title: 'Persisted case', status: 'active', priority: 'high', next_check_at: '2026-10-01 09:30:00', notes: 'Original notes', attachments: [] } } });
      }
      return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html><head><link rel="stylesheet" href="/assets/tracs.css"><script src="https://cdn.jsdelivr.net/npm/flatpickr"></script></head><body><main class="main"><div class="main-inner"><button id="new" onclick="openNewCase()">New Case</button><a id="navigate" href="/other">Other page</a><div data-cid="7" data-title="Stale title" data-priority="low"></div></div></main>${caseMarkup}${taskMarkup}${shiftMarkup}${momMarkup}<script src="/assets/tracs.js"></script><script src="/assets/unsaved-changes-guard.js"></script><script src="/assets/mom-functions.js"></script></body></html>` });
    });
    await page.goto('http://tracs.test/start');
    await page.goto('http://tracs.test/cases.php');
    assert.equal(await page.evaluate(() => { openNewCase(); const clean=!TRACSUnsavedChanges.isDirty(document.getElementById('caseModal')); closeModal('case'); return clean && document.getElementById('caseModal').classList.contains('hidden'); }), true, 'Immediate close must not depend on an animation frame');
    const dirty = () => page.evaluate(() => TRACSUnsavedChanges.isDirty(document.getElementById('caseModal')));
    const open = () => page.locator('#new').click();
    const modal = page.locator('#caseModal');
    const close = () => modal.locator('.modal-close').click();
    const prompt = page.locator('.tracs-unsaved-dialog-overlay:not(.hidden)');
    const leave = () => prompt.getByRole('button', { name: 'Discard Changes', exact: true }).click();
    const stay = () => prompt.getByRole('button', { name: 'Keep Editing', exact: true }).click();
    for (const exit of [close, () => modal.getByRole('button', { name: 'Cancel', exact: true }).click(), () => page.keyboard.press('Escape')]) {
      await open(); assert.equal(await dirty(), false); await exit(); await modal.waitFor({ state: 'hidden' }); assert.equal(await prompt.count(), 0);
    }
    await open(); await page.locator('#caseTitle').fill('Changed'); assert.equal(await dirty(), true); await close(); await prompt.waitFor(); await stay();
    await page.locator('#caseTitle').fill(''); assert.equal(await dirty(), false);
    await page.evaluate(() => { const field = document.getElementById('casePriority'); field.value = 'high'; field.dispatchEvent(new Event('change', { bubbles: true })); });
    assert.equal(await dirty(), true); await close(); await prompt.waitFor(); await stay();
    await page.evaluate(() => { document.getElementById('casePriority').value = 'medium'; });
    assert.equal(await dirty(), false, 'Programmatic reversion must be clean without an input event');
    await page.locator('#caseAttachments').setInputFiles({ name: 'test.png', mimeType: 'image/png', buffer: Buffer.from('image fixture') });
    assert.equal(await dirty(), true); await close(); await prompt.waitFor(); await stay();
    await modal.getByRole('button', { name: 'Remove selected image' }).click(); assert.equal(await dirty(), false);
    await page.evaluate(() => caseAddAttachmentFiles([new File(['paste'], 'paste.png', { type: 'image/png' })]));
    assert.equal(await dirty(), true, 'Pasted/dropped attachments must count');
    await modal.getByRole('button', { name: 'Remove selected image' }).click(); assert.equal(await dirty(), false);
    await close();
    await page.evaluate(() => openEditCase(7));
    await modal.waitFor({ state: 'visible' });
    assert.equal(await page.locator('#caseTitle').inputValue(), 'Persisted case');
    assert.equal(await dirty(), false, 'API hydration must be clean');
    assert.equal(await page.locator('#caseNotes').inputValue(), 'Original notes');
    await page.locator('#caseNotes').fill(''); assert.equal(await dirty(), true);
    await page.locator('#caseNotes').fill('Original notes'); assert.equal(await dirty(), false);
    await page.evaluate(() => removeExistingCaseAttachment(42)); assert.equal(await dirty(), true);
    await page.evaluate(() => { caseRemovedAttachmentIds.delete(42); }); assert.equal(await dirty(), false);
    failSave = true;
    await page.locator('#caseTitle').fill('Save fails');
    await page.locator('#caseSaveBtn').click();
    await page.waitForFunction(() => !caseSaving);
    assert.equal(await dirty(), true); assert.equal(await page.locator('#caseTitle').inputValue(), 'Save fails');
    failSave = false;
    await page.locator('#caseSaveBtn').click();
    await modal.waitFor({ state: 'hidden' }); assert.equal(await dirty(), false); assert.equal(await prompt.count(), 0);
    assert.equal(writes, 2);
    const stagedChecks = await page.evaluate(() => {
      const g=TRACSUnsavedChanges, results=[];
      openNewTask();const task=document.getElementById('taskModal');results.push(!g.isDirty(task));
      taskAddAttachmentFiles([new File(['task'],'task.png',{type:'image/png'})]);results.push(g.isDirty(task));
      removeTaskSelectedAttachment(taskSelectedAttachments[0].id);results.push(!g.isDirty(task));closeModal('task');
      openNewShiftReport();const shift=document.getElementById('shiftModal');results.push(!g.isDirty(shift));
      addShiftItem();results.push(g.isDirty(shift));removeShiftItem(shiftItems[0].uid);results.push(!g.isDirty(shift));
      shiftSummaryAddFiles([new File(['shift'],'shift.png',{type:'image/png'})]);results.push(g.isDirty(shift));
      clearShiftSummaryFiles();results.push(!g.isDirty(shift));closeModal('shift');
      openNewMOMWithCase(7);const mom=document.getElementById('momFormModal');results.push(!g.isDirty(mom));
      const item=mom.querySelector('[data-case-id="7"]');toggleMOMSuggestedCase(item,false);results.push(g.isDirty(mom));
      toggleMOMSuggestedCase(item,true);results.push(!g.isDirty(mom));closeModal('momForm');
      return results;
    });
    assert(stagedChecks.every(Boolean), 'Checklist, handover rows/images, and MoM linked cases must compare to their initialized baseline');
    const checks = await page.evaluate(() => {
      const form = document.createElement('form'); form.method = 'post';
      form.innerHTML = '<textarea id="text">Original </textarea><input id="check" type="checkbox" checked><select id="multi" multiple><option selected>a</option><option>b</option></select><input id="amount" value="100.00"><input id="sort_order" value="1"><input id="token" type="hidden" value="csrf">';
      document.querySelector('.main-inner').append(form);
      const g = TRACSUnsavedChanges; g.captureInitialState(form);
      const results=[];
      form.querySelector('#text').value='Original';results.push(g.isDirty(form));
      form.querySelector('#text').value='Original ';results.push(!g.isDirty(form));
      form.querySelector('#check').checked=false;results.push(g.isDirty(form));
      form.querySelector('#check').checked=true;results.push(!g.isDirty(form));
      form.querySelector('#multi').options[1].selected=true;results.push(g.isDirty(form));
      form.querySelector('#multi').options[1].selected=false;results.push(!g.isDirty(form));
      form.querySelector('#sort_order').value='2';results.push(g.isDirty(form));
      form.querySelector('#sort_order').disabled=true;results.push(g.isDirty(form));
      g.markSaved(form);results.push(!g.isDirty(form));
      form.querySelector('#token').value='rotated';results.push(!g.isDirty(form));
      g.beginInitialization(form);form.querySelector('#text').value='Loaded';results.push(!g.isDirty(form));
      g.finishInitialization(form);results.push(!g.isDirty(form));
      form.remove();return results;
    });
    assert(checks.every(Boolean), 'Shared guard must preserve whitespace, checkbox/multi-select reversion, business sort fields, busy drafts, token exclusions, and async initialization');
    await open(); await page.locator('#caseTitle').fill('Navigation draft');
    await page.evaluate(() => document.querySelector('#caseModal .modal-body').prepend(document.getElementById('navigate')));
    assert.equal(await page.evaluate(() => { const e = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; }), true);
    for (const navigate of [() => page.reload({ timeout: 2000, waitUntil: 'commit' }), () => page.goBack({ timeout: 2000, waitUntil: 'commit' })]) {
      const warning = page.waitForEvent('dialog');
      const navigating = navigate().catch(error => { assert.match(error.message, /ERR_ABORTED|Timeout 2000ms/); });
      const dialog = await warning; assert.equal(dialog.type(), 'beforeunload'); await dialog.dismiss(); await navigating;
      assert.equal(await dirty(), true, 'Cancelling browser navigation must keep the draft');
    }
    await page.locator('#navigate').click(); await prompt.waitFor(); await stay();
    assert.equal(await dirty(), true);
    await page.locator('#navigate').click(); await prompt.waitFor(); await leave();
    await page.waitForURL('**/other');
    await page.reload();
    assert.equal(await page.evaluate(() => { const e = new Event('beforeunload', { cancelable: true }); window.dispatchEvent(e); return e.defaultPrevented; }), false);
    assert.deepEqual(errors, []);
    console.log('PASS: Cases create X/Cancel/ESC; text/select revert; staged upload/paste/remove; async edit; persisted deletion/revert; failed/successful save; internal navigation and unload guards.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
