import assert from 'node:assert/strict';
import { createApiClient } from '../src/lib/apiClient.js';
import {
  DELETE_CONFIRMATION,
  deleteDependencyNote,
  isTemplateProtected,
  validateDeleteConfirmation,
} from '../src/modules/shift-assignment/utils/shiftDelete.js';
import { mutationErrorMessage } from '../src/modules/shift-assignment/utils/shiftMutation.js';

function jsonResponse(payload, status = 200) {
  return new Response(JSON.stringify(payload), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

assert.equal(DELETE_CONFIRMATION, 'DELETE');
assert.match(validateDeleteConfirmation('delete'), /Type DELETE exactly/);
assert.equal(validateDeleteConfirmation('DELETE'), '');
assert.equal(isTemplateProtected({ source: 'monthly_template' }), true);
assert.equal(isTemplateProtected({ source: 'manual' }), false);
assert.match(deleteDependencyNote({ is_holiday: true, is_overtime: true }), /holiday coverage and overtime/);
assert.match(deleteDependencyNote({}), /before-delete audit snapshot/);

let deleteOptions;
const deleteClient = createApiClient({
  fetchImpl: async (url, options) => {
    deleteOptions = { url, options };
    return jsonResponse({
      success: true,
      message: 'Shift assignment deleted.',
      data: { assignment_id: 801 },
      errors: [],
      meta: { request_id: 'delete-ui-contract' },
    });
  },
});
const deleted = await deleteClient.request('/api/v1/shift-assignment/assignment.php?id=801', {
  method: 'DELETE',
  csrfToken: 'pilot-csrf-token',
  csrfHeaderName: 'X-CSRF-Token',
});
assert.equal(deleteOptions.url, '/api/v1/shift-assignment/assignment.php?id=801');
assert.equal(deleteOptions.options.method, 'DELETE');
assert.equal(deleteOptions.options.headers.get('X-CSRF-Token'), 'pilot-csrf-token');
assert.equal(deleted.data.assignment_id, 801);

for (const status of [401, 403, 404, 405, 409, 422]) {
  const client = createApiClient({
    fetchImpl: async () => jsonResponse({
      success: false,
      message: `Controlled error ${status}.`,
      data: {},
      errors: status === 422
        ? [{ field: 'id', message: 'Assignment ID is invalid.' }]
        : [],
      meta: { request_id: `delete-error-${status}` },
    }, status),
  });
  await assert.rejects(
    client.request('/api/v1/shift-assignment/assignment.php?id=801', {
      method: 'DELETE',
      csrfToken: 'pilot-csrf-token',
    }),
    (error) => error.status === status,
  );
}

assert.match(mutationErrorMessage({ status: 401 }, 'delete'), /session expired/);
assert.match(mutationErrorMessage({ status: 403 }, 'delete'), /delete request was denied/);
assert.match(mutationErrorMessage({ status: 404 }, 'delete'), /no longer exists/);
assert.match(mutationErrorMessage({ status: 409 }, 'delete'), /protected by a monthly template/);
assert.match(mutationErrorMessage({ status: 422, message: 'Validation failed.' }, 'delete'), /Validation failed/);
assert.match(mutationErrorMessage({}, 'delete'), /network request failed/);

console.log('TRACS controlled Shift Assignment delete UI contracts passed.');
