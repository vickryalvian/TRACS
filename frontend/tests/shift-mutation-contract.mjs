import assert from 'node:assert/strict';
import {
  firstFieldError,
  focusInvalidField,
  mutationErrorMessage,
} from '../src/modules/shift-assignment/utils/shiftMutation.js';

assert.equal(firstFieldError({ form: 'No changes.', agent_id: 'Required.' }), 'agent_id');
assert.equal(firstFieldError({ form: 'No changes.' }), '');

let focused = false;
let scrolled = false;
const control = {
  focus() {
    focused = true;
  },
  scrollIntoView(options) {
    scrolled = options.block === 'center' && options.behavior === 'smooth';
  },
};
const modal = {
  querySelector(selector) {
    return selector === '[name="assignment_date"]' ? control : null;
  },
};

assert.equal(focusInvalidField(modal, { assignment_date: 'Required.' }), true);
assert.equal(focused, true);
assert.equal(scrolled, true);
assert.equal(focusInvalidField(modal, { form: 'No changes.' }), false);

assert.match(mutationErrorMessage(new TypeError('Failed to fetch'), 'create'), /network request failed/i);
assert.match(mutationErrorMessage({ status: 401 }, 'edit'), /session expired/i);
assert.match(mutationErrorMessage({ status: 403 }, 'create'), /request was denied/i);
assert.match(mutationErrorMessage({ status: 404 }, 'edit'), /no longer exists/i);
assert.match(mutationErrorMessage({ status: 405 }, 'create'), /not available/i);
assert.match(mutationErrorMessage({ status: 409 }, 'edit'), /conflicts/i);
assert.match(mutationErrorMessage({ status: 422, errors: [{ field: 'agent_id' }] }, 'create'), /highlighted fields/i);
assert.equal(
  mutationErrorMessage({ status: 500, message: 'Safe server message.' }, 'edit'),
  'Safe server message.',
);

console.log('Shift mutation hardening contracts passed.');
