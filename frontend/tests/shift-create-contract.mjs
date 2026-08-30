import assert from 'node:assert/strict';
import { createApiClient } from '../src/lib/apiClient.js';
import {
  applyShiftPreset,
  createdAssignmentMatchesFilters,
  fieldErrorsFromApi,
  initialCreateDraft,
  validateCreateDraft,
} from '../src/modules/shift-assignment/utils/shiftCreate.js';

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const preset = applyShiftPreset(initialCreateDraft(), 'shift_3', [{
  key: 'shift_3',
  start_time: '16:00',
  end_time: '00:00',
  display_range: '16:00-24:00',
}]);
assert.equal(preset.start_time, '16:00');
assert.equal(preset.end_time, '24:00');

const invalid = validateCreateDraft({
  ...preset,
  assignment_date: '31-02-2026',
  agent_id: '',
});
assert.equal(invalid.payload, null);
assert.equal(invalid.errors.agent_id, 'Select an agent.');
assert.equal(invalid.errors.assignment_date, 'Use dd-mm-yyyy.');

const valid = validateCreateDraft({
  ...preset,
  agent_id: '91',
  assignment_date: '15-06-2026',
  shift_type: 'regular_shift',
  break_minutes: '0',
  status: 'assigned',
});
assert.deepEqual(valid.errors, {});
assert.equal(valid.payload.assignment_date, '2026-06-15');
assert.equal(valid.payload.end_time, '24:00');

assert.deepEqual(fieldErrorsFromApi([
  { field: 'agent_id', message: 'Agent is required.' },
]), { agent_id: 'Agent is required.' });

assert.equal(createdAssignmentMatchesFilters({
  agent_id: 91,
  assignment_date: '2026-06-15',
  shift_type: 'regular_shift',
  status: 'assigned',
}, {
  start_date: '2026-06-15',
  end_date: '2026-06-21',
  agent_id: '91',
  shift_type: '',
  status: '',
}), true);
assert.equal(createdAssignmentMatchesFilters({
  agent_id: 91,
  assignment_date: '2026-06-30',
  shift_type: 'regular_shift',
  status: 'assigned',
}, {
  start_date: '2026-06-15',
  end_date: '2026-06-21',
  agent_id: '',
  shift_type: '',
  status: '',
}), false);

let postOptions;
const createClient = createApiClient({
  fetchImpl: async (url, options) => {
    postOptions = { url, options };
    return jsonResponse({
      success: true,
      message: 'Shift assignment created.',
      data: { assignment: { id: 501 } },
      errors: [],
      meta: { request_id: 'create-ui-contract' },
    }, 201);
  },
});
const created = await createClient.request('/api/v1/shift-assignment/assignments.php', {
  method: 'POST',
  body: valid.payload,
  csrfToken: 'pilot-csrf-token',
  csrfHeaderName: 'X-CSRF-Token',
});
assert.equal(postOptions.url, '/api/v1/shift-assignment/assignments.php');
assert.equal(postOptions.options.method, 'POST');
assert.equal(postOptions.options.headers.get('X-CSRF-Token'), 'pilot-csrf-token');
assert.equal(JSON.parse(postOptions.options.body).end_time, '24:00');
assert.equal(created.data.assignment.id, 501);

for (const status of [401, 403, 409, 422]) {
  const client = createApiClient({
    fetchImpl: async () => jsonResponse({
      success: false,
      message: `Controlled error ${status}.`,
      data: {},
      errors: status === 422
        ? [{ field: 'agent_id', message: 'Agent is required.' }]
        : [],
      meta: { request_id: `error-${status}` },
    }, status),
  });
  await assert.rejects(
    client.request('/api/v1/shift-assignment/assignments.php', {
      method: 'POST',
      body: valid.payload,
      csrfToken: 'pilot-csrf-token',
    }),
    (error) => error.status === status,
  );
}

console.log('TRACS controlled Shift Assignment create UI contracts passed.');
