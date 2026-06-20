import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createApiClient } from '../src/lib/apiClient.js';
import {
  copyPreviewErrorMessage,
  copyPreviewFieldErrorsFromApi,
  initialCopyPreviewDraft,
  validateCopyPreviewDraft,
} from '../src/modules/shift-assignment/utils/shiftCopyPreview.js';

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const validDraft = {
  ...initialCopyPreviewDraft({ start_date: '2026-07-01' }),
  source_start_date: '01-07-2026',
  source_end_date: '03-07-2026',
  target_start_date: '01-08-2026',
  target_end_date: '03-08-2026',
};
const valid = validateCopyPreviewDraft(validDraft);
assert.deepEqual(valid.errors, {});
assert.equal(valid.payload.source_start_date, '2026-07-01');
assert.equal(valid.payload.target_end_date, '2026-08-03');
assert.deepEqual(valid.payload.scope, { agent_ids: [], role_ids: [], division_ids: [] });
assert.equal(valid.payload.options.strict_conflict_check, true);

assert.equal(validateCopyPreviewDraft({ ...validDraft, source_start_date: '2026/07/01' }).errors.source_start_date, 'Use dd-mm-yyyy.');
assert.equal(validateCopyPreviewDraft({ ...validDraft, target_start_date: '01-07-2026', target_end_date: '03-07-2026' }).errors.target_start_date, 'Source and target ranges must be different.');
assert.equal(validateCopyPreviewDraft({ ...validDraft, target_end_date: '04-08-2026' }).errors.target_end_date, 'Source and target ranges must have the same length.');
assert.equal(validateCopyPreviewDraft({ ...validDraft, source_end_date: '15-08-2026', target_end_date: '15-09-2026' }).errors.source_end_date, 'Source range cannot exceed 35 days.');

assert.deepEqual(copyPreviewFieldErrorsFromApi([
  { field: 'source_range', message: 'Source range is invalid.' },
  { field: 'date_range_length', message: 'Source and target range lengths must match.' },
]), {
  source_end_date: 'Source range is invalid.',
  target_end_date: 'Source and target range lengths must match.',
});

let previewOptions;
const client = createApiClient({
  fetchImpl: async (url, options) => {
    previewOptions = { url, options };
    return jsonResponse({
      success: true,
      message: 'Copy schedule preview generated.',
      data: {
        source_range: { start_date: '2026-07-01', end_date: '2026-07-03' },
        target_range: { start_date: '2026-08-01', end_date: '2026-08-03' },
        items: [{ preview_id: 'copy-preview-1', source_assignment_id: 11 }],
        summary: {
          source_assignments: 1,
          preview_assignments: 1,
          agents: 1,
          warnings: 1,
          conflicts: 1,
          blocked_items: 1,
        },
        warnings: [{ type: 'copied_note', message: 'Note copied from source.' }],
        conflicts: [{ type: 'overlap', message: 'Target overlap.' }],
        blocked_items: [{ source_assignment_id: 11, reason: 'Target overlap.' }],
      },
      errors: [],
      meta: { request_id: 'copy-preview-contract' },
    });
  },
});

const preview = await client.request('/api/v1/shift-assignment/templates/copy-preview.php', {
  method: 'POST',
  body: valid.payload,
  csrfToken: 'pilot-csrf-token',
  csrfHeaderName: 'X-CSRF-Token',
});
assert.equal(previewOptions.url, '/api/v1/shift-assignment/templates/copy-preview.php');
assert.equal(previewOptions.options.method, 'POST');
assert.equal(previewOptions.options.headers.get('X-CSRF-Token'), 'pilot-csrf-token');
assert.equal(JSON.parse(previewOptions.options.body).target_start_date, '2026-08-01');
assert.equal(preview.data.summary.preview_assignments, 1);
assert.equal(preview.data.conflicts[0].type, 'overlap');

for (const status of [401, 403, 405, 422]) {
  const errorClient = createApiClient({
    fetchImpl: async () => jsonResponse({
      success: false,
      message: `Controlled error ${status}.`,
      data: {},
      errors: status === 422 ? [{ field: 'target_range', message: 'Target range is invalid.' }] : [],
      meta: { request_id: `copy-preview-error-${status}` },
    }, status),
  });
  await assert.rejects(
    errorClient.request('/api/v1/shift-assignment/templates/copy-preview.php', {
      method: 'POST',
      body: valid.payload,
      csrfToken: 'pilot-csrf-token',
    }),
    (error) => error.status === status && copyPreviewErrorMessage(error).length > 0,
  );
}

assert.equal(copyPreviewErrorMessage({}), 'The network request failed while generating the copy preview.');

const apiSource = readFileSync(new URL('../src/modules/shift-assignment/api.js', import.meta.url), 'utf8');
const moduleSource = readFileSync(new URL('../src/modules/shift-assignment/components/ShiftCopyPreviewModal.jsx', import.meta.url), 'utf8');
assert.match(apiSource, /templates\/copy-preview\.php/);
for (const forbidden of ['templates/copy-commit.php', 'APPLY COPY', 'Apply Copy', 'Commit Copy', 'Paste Schedule']) {
  assert.equal(apiSource.includes(forbidden), false, `${forbidden} must not exist in API client.`);
  assert.equal(moduleSource.includes(forbidden), false, `${forbidden} must not exist in copy preview UI.`);
}
assert.match(moduleSource, /Preview only - this will not create or modify assignments\./);

console.log('TRACS Shift Assignment copy preview frontend contracts passed.');
