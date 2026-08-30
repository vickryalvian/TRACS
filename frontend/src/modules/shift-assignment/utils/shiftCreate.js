import { displayDateInput, isoDateInput, todayIso } from './shiftDates.js';

const TIME_PATTERN = /^(?:[01]\d|2[0-3]):[0-5]\d$/;

export function initialCreateDraft(defaults = {}) {
  return {
    agent_id: '',
    assignment_date: displayDateInput(defaults.assignment_date ?? todayIso()),
    shift_type: defaults.shift_type ?? 'regular_shift',
    shift_preset: '',
    start_time: '',
    end_time: '',
    shift_template_id: '',
    break_minutes: '0',
    status: 'assigned',
    notes: '',
  };
}

export function applyShiftPreset(draft, preset, templates = []) {
  const definition = preset
    ? (templates ?? []).find((item) => String(item.key) === String(preset))
    : null;

  return {
    ...draft,
    shift_preset: preset,
    start_time: definition?.start_time ?? '',
    end_time: definition?.display_range?.split('-')[1] ?? definition?.end_time ?? '',
  };
}

export function validateCreateDraft(draft) {
  const errors = {};
  const assignmentDate = isoDateInput(draft.assignment_date);
  const startTime = String(draft.start_time ?? '').trim();
  const endTime = String(draft.end_time ?? '').trim();

  if (!/^[1-9]\d*$/.test(String(draft.agent_id ?? ''))) {
    errors.agent_id = 'Select an agent.';
  }
  if (!assignmentDate) {
    errors.assignment_date = 'Use dd-mm-yyyy.';
  }
  if (!String(draft.shift_type ?? '').trim()) {
    errors.shift_type = 'Select an assignment type.';
  }
  if (!TIME_PATTERN.test(startTime)) {
    errors.start_time = 'Use HH:MM.';
  }
  if (endTime !== '24:00' && !TIME_PATTERN.test(endTime)) {
    errors.end_time = 'Use HH:MM or 24:00.';
  }
  if (startTime && endTime && startTime === (endTime === '24:00' ? '00:00' : endTime)) {
    errors.end_time = 'Shift duration cannot be zero.';
  }
  if (!/^\d+$/.test(String(draft.break_minutes ?? ''))
      || Number(draft.break_minutes) > 720) {
    errors.break_minutes = 'Use 0 to 720 minutes.';
  }
  if (String(draft.notes ?? '').length > 3000) {
    errors.notes = 'Notes must not exceed 3000 characters.';
  }

  if (Object.keys(errors).length) {
    return { errors, payload: null };
  }

  const payload = {
    agent_id: Number(draft.agent_id),
    assignment_date: assignmentDate,
    shift_type: String(draft.shift_type),
    start_time: startTime,
    end_time: endTime,
    break_minutes: Number(draft.break_minutes || 0),
    status: String(draft.status || 'assigned'),
    notes: String(draft.notes ?? '').trim(),
  };

  if (/^[1-9]\d*$/.test(String(draft.shift_template_id ?? ''))) {
    payload.shift_template_id = Number(draft.shift_template_id);
  }

  return { errors: {}, payload };
}

export function fieldErrorsFromApi(errors) {
  if (!Array.isArray(errors)) {
    return {};
  }

  return Object.fromEntries(
    errors
      .filter((error) => error?.field && error?.message)
      .map((error) => [String(error.field), String(error.message)]),
  );
}

export function createdAssignmentMatchesFilters(assignment, filters) {
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
