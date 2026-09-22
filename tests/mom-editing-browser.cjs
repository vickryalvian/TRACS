const assert = require('node:assert/strict');
const path = require('node:path');
const { chromium } = require('../frontend/node_modules/playwright');

const root = path.resolve(__dirname, '..');

(async () => {
  const browser = await chromium.launch({ headless: true, channel: 'chrome' });
  try {
    const page = await browser.newPage();
    await page.setContent(`<!doctype html><html><body>
      <section><button id="momAgendaCancelEdit" class="hidden"></button><button id="momAgendaSaveButton"></button><div class="section-body"><div data-mom-autosave="agenda" data-mom-id="9"><textarea id="momAgendaTopic"></textarea><textarea id="momAgendaNotes"></textarea></div><div class="agenda-item agenda-item-pending" data-agenda-id="11" data-topic="Original agenda" data-notes="Original notes" data-status="pending"></div></div></section>
      <section><button id="momNoteCancelEdit" class="hidden"></button><button id="momNoteSaveButton"></button><div class="section-body" id="momNotesArea"><div data-mom-autosave="note" data-mom-id="9"><select id="momInlineNoteType"><option value="discussion">Discussion</option><option value="insight">Insight</option></select><textarea id="momInlineNoteContent"></textarea></div><div class="discussion-note discussion-note-discussion" data-note-id="12" data-note-type="discussion" data-content="Original note"></div></div></section>
      <section><button id="momDecisionCancelEdit" class="hidden"></button><button id="momDecisionSaveButton"></button><div class="section-body"><div data-mom-autosave="decision" data-mom-id="9"><input id="momInlineDecisionText"><input id="momInlineDecisionOwner"><textarea id="momInlineDecisionRationale"></textarea></div><div class="decision-card" data-decision-id="13" data-decision="Original decision" data-rationale="Original rationale" data-owner="Original owner" data-status="pending"></div></div></section>
      <section><button id="momActionCancelEdit" class="hidden"></button><button id="momActionSaveButton"></button><div class="section-body"><div data-mom-autosave="action" data-mom-id="9"><input id="momInlineActionTitle"><input id="momInlineActionAssignee"><select id="momInlineActionPriority"><option value="medium">Medium</option><option value="high">High</option></select><input id="momInlineActionDueDate"><textarea id="momInlineActionDesc"></textarea></div><div class="action-item action-item-medium action-item-pending" data-aid="14" data-title="Original action" data-description="Original description" data-assigned-to="Original owner" data-priority="medium" data-due-date="2026-09-30" data-status="pending"></div></div></section>
      <div class="progress-fill"></div><div class="progress-text"></div>
    </body></html>`);
    await page.addScriptTag({ path: path.join(root, 'public/assets/unsaved-changes-guard.js') });
    await page.evaluate(() => {
      window._currentMOMId = 9;
      window.__requests = [];
      window.toast = () => {};
      window.tracsConfirm = (_message, callback) => callback();
      window.lucide = { createIcons() {} };
      window.api = async (_url, payload) => {
        window.__requests.push(payload);
        if (payload.action === 'update_agenda_item') return { ok: true, item: { id: 11, topic: payload.topic, notes: payload.notes, status: payload.status } };
        if (payload.action === 'update_discussion_note') return { ok: true, note: { id: 12, content: payload.content, note_type: payload.note_type } };
        if (payload.action === 'update_decision') return { ok: true, decision: { id: 13, decision: payload.decision, rationale: payload.rationale, owner: payload.owner, status: payload.status } };
        if (payload.action === 'update_action_item') return { ok: true, item: { id: 14, title: payload.title, description: payload.description, assigned_to: payload.assigned_to, priority: payload.priority, due_date: payload.due_date, status: 'pending' } };
        return { ok: false, msg: 'Unexpected action' };
      };
    });
    await page.addScriptTag({ path: path.join(root, 'public/assets/mom-functions.js') });

    const scenarios = [
      { edit: 'editAgendaItem(11)', field: '#momAgendaTopic', original: 'Original agenda', value: 'Updated agenda', save: 'saveInlineAgendaItem(9)', selector: '[data-agenda-id="11"]', data: 'topic', action: 'update_agenda_item' },
      { edit: 'editDiscussionNote(12)', field: '#momInlineNoteContent', original: 'Original note', value: 'Updated note', save: 'saveInlineDiscussionNote(9)', selector: '[data-note-id="12"]', data: 'content', action: 'update_discussion_note' },
      { edit: 'editDecision(13)', field: '#momInlineDecisionText', original: 'Original decision', value: 'Updated decision', save: 'saveInlineDecision(9)', selector: '[data-decision-id="13"]', data: 'decision', action: 'update_decision' },
      { edit: 'editActionItem(14)', field: '#momInlineActionTitle', original: 'Original action', value: 'Updated action', save: 'saveInlineActionItem(9)', selector: '[data-aid="14"]', data: 'title', action: 'update_action_item' },
    ];

    for (const scenario of scenarios) {
      await page.evaluate(scenario.edit);
      const form = page.locator(scenario.field).locator('xpath=ancestor::*[@data-mom-autosave]');
      assert.equal(await form.evaluate((node) => window.TRACSUnsavedChanges.isDirty(node)), false);
      await page.locator(scenario.field).fill(scenario.value);
      assert.equal(await form.evaluate((node) => window.TRACSUnsavedChanges.isDirty(node)), true);
      await page.locator(scenario.field).fill(scenario.original);
      assert.equal(await form.evaluate((node) => window.TRACSUnsavedChanges.isDirty(node)), false);
      await page.locator(scenario.field).fill(scenario.value);
      const before = await page.locator(scenario.selector).count();
      await page.evaluate(scenario.save);
      await page.waitForFunction((action) => window.__requests.some((request) => request.action === action), scenario.action);
      assert.equal(await page.locator(scenario.selector).count(), before, `${scenario.action} must replace, not append`);
      assert.equal(await page.locator(scenario.selector).getAttribute(`data-${scenario.data}`), scenario.value);
      assert.equal(await form.evaluate((node) => window.TRACSUnsavedChanges.isDirty(node)), false);
      const request = await page.evaluate((action) => window.__requests.find((item) => item.action === action), scenario.action);
      assert.equal(request.mom_id, 9);
    }

    console.log('PASS: MoM existing items hydrate cleanly, become dirty on edits, submit update actions with mom_id, and replace by ID.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
