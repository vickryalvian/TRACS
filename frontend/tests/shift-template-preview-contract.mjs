import assert from 'node:assert/strict';
import { createApiClient } from '../src/lib/apiClient.js';
import {
  applyTemplatePreviewPreset,
  initialTemplatePreviewDraft,
  templatePreviewErrorMessage,
  templatePreviewFieldErrorsFromApi,
  validateTemplatePreviewDraft,
} from '../src/modules/shift-assignment/utils/shiftTemplatePreview.js';

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const context = {
  shift_definitions: [
    { key: 'shift_1', start_time: '00:00', end_time: '08:00', display_range: '00:00-08:00' },
    { key: 'shift_2', start_time: '08:00', end_time: '16:00', display_range: '08:00-16:00' },
    { key: 'shift_3', start_time: '16:00', end_time: '00:00', display_range: '16:00-24:00' },
  ],
  filters: {
    agents: [{ id: 91, name: 'Pilot Agent' }],
    shift_templates: [
      { id: 1, is_active: true, start_time: '00:00', end_time: '08:00' },
      { id: 2, is_active: true, start_time: '08:00', end_time: '16:00' },
      { id: 3, is_active: true, start_time: '16:00', end_time: '00:00' },
    ],
  },
};

const draft = applyTemplatePreviewPreset(
  {
    ...initialTemplatePreviewDraft({ start_date: '2026-07-01', end_date: '2026-07-07' }),
    agent_id: '91',
    shift_preset: 'shift_3',
    day_of_week: '5',
  },
  'shift_3',
  context.shift_definitions,
  context.filters.shift_templates,
);
assert.equal(draft.shift_template_id, '3');

const valid = validateTemplatePreviewDraft(draft, context);
assert.deepEqual(valid.errors, {});
assert.equal(valid.payload.start_date, '2026-07-01');
assert.equal(valid.payload.end_date, '2026-07-07');
assert.equal(valid.payload.agents[0], 91);
assert.equal(valid.payload.pattern.type, 'weekly_rotation');
assert.equal(valid.payload.pattern.items[0].end_time, '24:00');
assert.equal(valid.payload.pattern.items[0].shift_template_id, 3);

const invalid = validateTemplatePreviewDraft({
  ...draft,
  start_date: '2026/07/01',
  end_date: '15-08-2026',
  agent_id: '',
}, context);
assert.equal(invalid.payload, null);
assert.equal(invalid.errors.start_date, 'Use dd-mm-yyyy.');
assert.equal(invalid.errors.agent_id, 'Select a scoped active agent.');

const tooLong = validateTemplatePreviewDraft({
  ...draft,
  start_date: '01-07-2026',
  end_date: '15-08-2026',
}, context);
assert.equal(tooLong.payload, null);
assert.equal(tooLong.errors.end_date, 'Preview range cannot exceed 35 days.');

assert.deepEqual(templatePreviewFieldErrorsFromApi([
  { field: 'agents.0', message: 'Agent is not active or not in scope.' },
  { field: 'pattern.items.0.end_time', message: 'End time must use HH:MM or 24:00.' },
]), {
  agent_id: 'Agent is not active or not in scope.',
  shift_preset: 'End time must use HH:MM or 24:00.',
});

let previewOptions;
const previewClient = createApiClient({
  fetchImpl: async (url, options) => {
    previewOptions = { url, options };
    return jsonResponse({
      success: true,
      message: 'Template preview generated.',
      data: {
        items: [{ preview_id: -1 }],
        summary: { total_assignments: 1, warnings: 1, conflicts: 1, blocked_items: 1 },
        warnings: [{ type: 'weekly_hours', message: 'Preview workload warning.' }],
        conflicts: [{ type: 'overlap', message: 'Overlap.' }],
        blocked_items: [{ preview_id: -1, reason: 'Overlap.' }],
      },
      errors: [],
      meta: { request_id: 'template-preview-contract' },
    });
  },
});

const preview = await previewClient.request('/api/v1/shift-assignment/templates/preview.php', {
  method: 'POST',
  body: valid.payload,
  csrfToken: 'pilot-csrf-token',
  csrfHeaderName: 'X-CSRF-Token',
});
assert.equal(previewOptions.url, '/api/v1/shift-assignment/templates/preview.php');
assert.equal(previewOptions.options.method, 'POST');
assert.equal(previewOptions.options.headers.get('X-CSRF-Token'), 'pilot-csrf-token');
assert.equal(JSON.parse(previewOptions.options.body).pattern.items[0].end_time, '24:00');
assert.equal(preview.data.summary.total_assignments, 1);

for (const status of [401, 403, 405, 422]) {
  const client = createApiClient({
    fetchImpl: async () => jsonResponse({
      success: false,
      message: `Controlled error ${status}.`,
      data: {},
      errors: status === 422
        ? [{ field: 'agents.0', message: 'Agent is required.' }]
        : [],
      meta: { request_id: `template-preview-error-${status}` },
    }, status),
  });
  await assert.rejects(
    client.request('/api/v1/shift-assignment/templates/preview.php', {
      method: 'POST',
      body: valid.payload,
      csrfToken: 'pilot-csrf-token',
    }),
    (error) => error.status === status,
  );
}

assert.match(templatePreviewErrorMessage(new TypeError('Failed to fetch')), /network request failed/i);
assert.match(templatePreviewErrorMessage({ status: 401 }), /session expired/i);
assert.match(templatePreviewErrorMessage({ status: 403 }), /request was denied/i);
assert.match(templatePreviewErrorMessage({ status: 405 }), /not available/i);
assert.match(templatePreviewErrorMessage({ status: 422, errors: [{ field: 'agents.0' }] }), /highlighted fields/i);

console.log('TRACS controlled Shift Assignment template preview UI contracts passed.');
