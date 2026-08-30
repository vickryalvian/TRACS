import { displayDateInput, isoDateInput } from './shiftDates.js';
import { applyShiftPreset } from './shiftCreate.js';

const TIME_PATTERN = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

export function initialEditDraft(assignment = {}) {
  assignment = assignment ?? {};
  return {
    id: Number(assignment.id ?? 0),
    agent_id: String(assignment.agent?.id ?? ''),
    assignment_date: displayDateInput(assignment.assignment_date ?? ''),
    shift_type: String(assignment.type ?? ''),
    shift_preset: '',
    start_time: String(assignment.shift?.start_time ?? ''),
    end_time: String(
      assignment.shift?.end_time_display
      ?? assignment.shift?.end_time
      ?? '',
    ),
    shift_template_id: assignment.shift?.template_id
      ? String(assignment.shift.template_id)
      : '',
    break_minutes: String(assignment.break_minutes ?? 0),
    status: String(assignment.status ?? 'assigned'),
  };
}

export function applyEditShiftPreset(draft, preset, definitions = []) {
  return applyShiftPreset(draft, preset, definitions);
}

function comparablePayload(draft) {
  return {
    agent_id: Number(draft.agent_id),
    assignment_date: isoDateInput(draft.assignment_date),
    shift_type: String(draft.shift_type ?? '').trim(),
    start_time: String(draft.start_time ?? '').trim(),
    end_time: String(draft.end_time ?? '').trim(),
    shift_template_id: /^[1-9]\d*$/.test(String(draft.shift_template_id ?? ''))
      ? Number(draft.shift_template_id)
      : null,
    break_minutes: Number(draft.break_minutes || 0),
    status: String(draft.status ?? '').trim(),
  };
}

export function validateEditDraft(draft, initialDraft) {
  const errors = {};
  const assignmentId = Number(draft.id ?? 0);
  const payload = comparablePayload(draft);

  if (!Number.isInteger(assignmentId) || assignmentId <= 0) {
    errors.id = 'Assignment ID is missing.';
  }
  if (!/^[1-9]\d*$/.test(String(draft.agent_id ?? ''))) {
    errors.agent_id = 'Select an agent.';
  }
  if (!payload.assignment_date) {
    errors.assignment_date = 'Use dd-mm-yyyy.';
  }
  if (!payload.shift_type) {
    errors.shift_type = 'Select an assignment type.';
  }
  if (!TIME_PATTERN.test(payload.start_time)) {
    errors.start_time = 'Use HH:MM.';
  }
  if (payload.end_time !== '24:00' && !TIME_PATTERN.test(payload.end_time)) {
    errors.end_time = 'Use HH:MM or 24:00.';
  }
  if (payload.start_time
      && payload.end_time
      && payload.start_time === (payload.end_time === '24:00' ? '00:00' : payload.end_time)) {
    errors.end_time = 'Shift duration cannot be zero.';
  }
  if (!/^\d+$/.test(String(draft.break_minutes ?? ''))
      || Number(draft.break_minutes) > 720) {
    errors.break_minutes = 'Use 0 to 720 minutes.';
  }

  if (Object.keys(errors).length) {
    return { errors, payload: null, changed: false };
  }

  const initialPayload = comparablePayload(initialDraft);
  const changedPayload = Object.fromEntries(
    Object.entries(payload).filter(([key, value]) => initialPayload[key] !== value),
  );
  if (!Object.keys(changedPayload).length) {
    return {
      errors: { form: 'Change at least one field before saving.' },
      payload: null,
      changed: false,
    };
  }

  return { errors: {}, payload: changedPayload, changed: true };
}

export function updatedAssignmentMatchesFilters(assignment, filters) {
  if (!assignment) {
    return false;
  }
  if (assignment.assignment_date < filters.start_date
      || assignment.assignment_date > filters.end_date) {
    return false;
  }
  if (filters.agent_id && String(assignment.agent_id) !== String(filters.agent_id)) {
    return false;
  }
  if (filters.shift_type && assignment.shift_type !== filters.shift_type) {
    return false;
  }
  if (filters.status && assignment.status !== filters.status) {
    return false;
  }
  return true;
}
