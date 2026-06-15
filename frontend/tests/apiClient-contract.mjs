import assert from 'node:assert/strict';
import { createApiClient } from '../src/lib/apiClient.js';
import {
  displayRange,
  filterDraft,
  filterQuery,
  isoDateInput,
  rangeForView,
  shiftRange,
} from '../src/modules/shift-assignment/utils/shiftDates.js';

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

let requestedUrl = '';
const successClient = createApiClient({
  fetchImpl: async (url, options) => {
    requestedUrl = url;
    assert.equal(options.method, 'GET');
    assert.equal(options.credentials, 'same-origin');
    return jsonResponse({
      success: true,
      message: 'Shift assignments loaded.',
      data: { assignments: [] },
      errors: [],
      meta: { request_id: 'frontend-contract-id' },
    });
  },
});

const success = await successClient.request('/api/v1/shift-assignment/assignments.php');
assert.equal(requestedUrl, '/api/v1/shift-assignment/assignments.php');
assert.deepEqual(success.data, { assignments: [] });
assert.equal(success.meta.request_id, 'frontend-contract-id');

const validationClient = createApiClient({
  fetchImpl: async () =>
    jsonResponse(
      {
        success: false,
        message: 'Query validation failed.',
        data: null,
        errors: { date_range: 'Weekly view supports a maximum range of seven days.' },
        meta: { request_id: 'validation-id' },
      },
      422,
    ),
});

await assert.rejects(
  validationClient.request('/api/v1/shift-assignment/assignments.php'),
  (error) => {
    assert.equal(error.status, 422);
    assert.deepEqual(error.errors, {
      date_range: 'Weekly view supports a maximum range of seven days.',
    });
    return true;
  },
);

let sessionExpired = false;
const authClient = createApiClient({
  onSessionExpired: () => {
    sessionExpired = true;
  },
  fetchImpl: async () =>
    jsonResponse(
      {
        success: false,
        message: 'Authentication is required.',
        data: null,
        errors: [],
        meta: { request_id: 'auth-id' },
      },
      401,
    ),
});

await assert.rejects(
  authClient.request('/api/v1/context.php'),
  (error) => error.status === 401,
);
assert.equal(sessionExpired, true);

let permissionDenied = false;
const permissionClient = createApiClient({
  onPermissionDenied: () => {
    permissionDenied = true;
  },
  fetchImpl: async () =>
    jsonResponse(
      {
        success: false,
        message: 'Forbidden.',
        data: null,
        errors: [],
        meta: { request_id: 'permission-id' },
      },
      403,
    ),
});

await assert.rejects(
  permissionClient.request('/api/v1/shift-assignment/context.php'),
  (error) => error.status === 403,
);
assert.equal(permissionDenied, true);

assert.deepEqual(rangeForView('daily', '2026-06-17'), {
  start_date: '2026-06-17',
  end_date: '2026-06-17',
});
assert.deepEqual(rangeForView('weekly', '2026-06-17'), {
  start_date: '2026-06-15',
  end_date: '2026-06-21',
});
assert.deepEqual(rangeForView('monthly', '2026-06-17'), {
  start_date: '2026-06-01',
  end_date: '2026-06-30',
});
assert.deepEqual(
  shiftRange(
    { start_date: '2026-06-15', end_date: '2026-06-21' },
    'weekly',
    1,
  ),
  { start_date: '2026-06-22', end_date: '2026-06-28' },
);
assert.equal(
  displayRange({ start_date: '2026-06-15', end_date: '2026-06-21' }),
  '15-06-2026 to 21-06-2026',
);
assert.equal(isoDateInput('17-06-2026'), '2026-06-17');
assert.equal(isoDateInput('31-02-2026'), '');
assert.deepEqual(
  filterDraft({
    view: 'weekly',
    start_date: '2026-06-15',
    end_date: '2026-06-21',
    agent_id: '',
  }),
  {
    view: 'weekly',
    start_date: '15-06-2026',
    end_date: '21-06-2026',
    agent_id: '',
  },
);
assert.deepEqual(
  filterQuery({
    view: 'weekly',
    start_date: '15-06-2026',
    end_date: '21-06-2026',
    agent_id: '7',
  }),
  {
    errors: {},
    filters: {
      view: 'weekly',
      start_date: '2026-06-15',
      end_date: '2026-06-21',
      agent_id: '7',
    },
  },
);
assert.deepEqual(
  filterQuery({
    view: 'weekly',
    start_date: '2026/06/15',
    end_date: '',
  }).errors,
  {
    start_date: 'Use dd-mm-yyyy.',
    end_date: 'Use dd-mm-yyyy.',
  },
);

console.log('TRACS frontend API client and Shift Assignment date contracts passed.');
