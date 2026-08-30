import assert from 'node:assert/strict';
import { createApiClient } from '../src/lib/apiClient.js';
import {
  applyEditShiftPreset,
  initialEditDraft,
  updatedAssignmentMatchesFilters,
  validateEditDraft,
} from '../src/modules/shift-assignment/utils/shiftEdit.js';

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

const assignment = {
  id: 701,
  agent: { id: 91, name: 'Fixture Agent' },
  assignment_date: '2026-06-15',
  shift: {
    template_id: 3,
    start_time: '08:00',
    end_time: '16:00',
    end_time_display: '16:00',
  },
  break_minutes: 0,
  type: 'regular_shift',
  status: 'assigned',
};
const initial = initialEditDraft(assignment);
assert.equal(initial.assignment_date, '15-06-2026');
assert.equal(initial.agent_id, '91');
assert.equal(initialEditDraft(null).id, 0);

const unchanged = validateEditDraft(initial, initial);
assert.equal(unchanged.payload, null);
assert.equal(unchanged.errors.form, 'Change at least one field before saving.');

const preset = applyEditShiftPreset(initial, 'shift_3', [{
  key: 'shift_3',
  start_time: '16:00',
  end_time: '00:00',
  display_range: '16:00-24:00',
}]);
assert.equal(preset.start_time, '16:00');
assert.equal(preset.end_time, '24:00');

const valid = validateEditDraft({
  ...preset,
  status: 'confirmed',
}, initial);
assert.deepEqual(valid.errors, {});
assert.deepEqual(valid.payload, {
  start_time: '16:00',
  end_time: '24:00',
  status: 'confirmed',
});

const invalid = validateEditDraft({
  ...initial,
  id: 0,
  assignment_date: '31-02-2026',
  start_time: '24:00',
}, initial);
assert.equal(invalid.payload, null);
assert.equal(invalid.errors.id, 'Assignment ID is missing.');
assert.equal(invalid.errors.assignment_date, 'Use dd-mm-yyyy.');
assert.equal(invalid.errors.start_time, 'Use HH:MM.');

assert.equal(updatedAssignmentMatchesFilters({
  agent_id: 91,
  assignment_date: '2026-06-15',
  shift_type: 'regular_shift',
  status: 'confirmed',
}, {
  start_date: '2026-06-15',
  end_date: '2026-06-21',
  agent_id: '91',
  shift_type: '',
  status: 'confirmed',
}), true);
assert.equal(updatedAssignmentMatchesFilters({
  agent_id: 91,
  assignment_date: '2026-06-30',
  shift_type: 'regular_shift',
  status: 'confirmed',
}, {
  start_date: '2026-06-15',
  end_date: '2026-06-21',
  agent_id: '',
  shift_type: '',
  status: '',
}), false);

let patchOptions;
const updateClient = createApiClient({
  fetchImpl: async (url, options) => {
    patchOptions = { url, options };
    return jsonResponse({
      success: true,
      message: 'Shift assignment updated.',
      data: { assignment: { id: 701, status: 'confirmed' } },
      errors: [],
      meta: { request_id: 'edit-ui-contract' },
    });
  },
});
const updated = await updateClient.request('/api/v1/shift-assignment/assignment.php?id=701', {
  method: 'PATCH',
  body: valid.payload,
  csrfToken: 'pilot-csrf-token',
  csrfHeaderName: 'X-CSRF-Token',
});
assert.equal(patchOptions.url, '/api/v1/shift-assignment/assignment.php?id=701');
assert.equal(patchOptions.options.method, 'PATCH');
assert.equal(patchOptions.options.headers.get('X-CSRF-Token'), 'pilot-csrf-token');
assert.deepEqual(JSON.parse(patchOptions.options.body), valid.payload);
assert.equal(updated.data.assignment.status, 'confirmed');

for (const status of [401, 403, 404, 409, 422]) {
  const client = createApiClient({
    fetchImpl: async () => jsonResponse({
      success: false,
      message: `Controlled error ${status}.`,
      data: {},
      errors: status === 422
        ? [{ field: 'start_time', message: 'Start time is invalid.' }]
        : [],
      meta: { request_id: `edit-error-${status}` },
    }, status),
  });
  await assert.rejects(
    client.request('/api/v1/shift-assignment/assignment.php?id=701', {
      method: 'PATCH',
      body: valid.payload,
      csrfToken: 'pilot-csrf-token',
    }),
    (error) => error.status === status,
  );
}

console.log('TRACS controlled Shift Assignment edit UI contracts passed.');
