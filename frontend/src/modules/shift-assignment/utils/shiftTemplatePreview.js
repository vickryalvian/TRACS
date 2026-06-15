import { displayDateInput, isoDateInput, rangeForView } from './shiftDates.js';

const TIME_PATTERN = /^(?:[01]\d|2[0-3]):[0-5]\d$/;
const MAX_PREVIEW_DAYS = 35;
export const TEMPLATE_PREVIEW_SHIFT_PRESETS = ['shift_1', 'shift_2', 'shift_3'];

export function initialTemplatePreviewDraft(defaults = {}) {
  const range = rangeForView('weekly', defaults.start_date);

  return {
    start_date: displayDateInput(defaults.start_date ?? range.start_date),
    end_date: displayDateInput(defaults.end_date ?? range.end_date),
    agent_id: '',
    shift_preset: 'shift_1',
    day_of_week: '1',
    shift_type: 'regular_shift',
    shift_template_id: '',
    include_holidays: true,
    include_warnings: true,
    strict_conflict_check: true,
  };
}

export function applyTemplatePreviewPreset(draft, preset, definitions = [], templates = []) {
  const definition = (definitions ?? []).find((item) => String(item.key) === String(preset));
  const matchingTemplate = (templates ?? []).find((template) => (
    template.is_active
      && template.start_time === definition?.start_time
      && (template.end_time === definition?.end_time
        || (definition?.display_range?.endsWith('24:00') && template.end_time === '00:00'))
  ));

  return {
    ...draft,
    shift_preset: preset,
    shift_template_id: matchingTemplate?.id ? String(matchingTemplate.id) : '',
  };
}

function isoDayDifference(startDate, endDate) {
  const start = Date.parse(`${startDate}T00:00:00Z`);
  const end = Date.parse(`${endDate}T00:00:00Z`);
  return Number.isFinite(start) && Number.isFinite(end)
    ? Math.round((end - start) / 86_400_000) + 1
    : 0;
}

export function validateTemplatePreviewDraft(draft, context = {}) {
  const errors = {};
  const startDate = isoDateInput(draft.start_date);
  const endDate = isoDateInput(draft.end_date);
  const agents = context?.filters?.agents ?? [];
  const definitions = context?.shift_definitions ?? [];
  const definition = definitions.find((item) => String(item.key) === String(draft.shift_preset));
  const startTime = definition?.start_time ?? '';
  const endTime = definition?.display_range?.split('-')[1] ?? definition?.end_time ?? '';

  if (!startDate) {
    errors.start_date = 'Use dd-mm-yyyy.';
  }
  if (!endDate) {
    errors.end_date = 'Use dd-mm-yyyy.';
  }
  if (startDate && endDate) {
    if (endDate < startDate) {
      errors.end_date = 'End date must be on or after start date.';
    } else if (isoDayDifference(startDate, endDate) > MAX_PREVIEW_DAYS) {
      errors.end_date = 'Preview range cannot exceed 35 days.';
    }
  }
  if (!agents.some((agent) => String(agent.id) === String(draft.agent_id))) {
    errors.agent_id = 'Select a scoped active agent.';
  }
  if (!definition) {
    errors.shift_preset = 'Select Shift 1, Shift 2, or Shift 3.';
  } else if (!TEMPLATE_PREVIEW_SHIFT_PRESETS.includes(String(definition.key))) {
    errors.shift_preset = 'Select Shift 1, Shift 2, or Shift 3.';
  }
  if (!/^[1-7]$/.test(String(draft.day_of_week ?? ''))) {
    errors.day_of_week = 'Select a day of week.';
  }
  if (!String(draft.shift_type ?? '').trim()) {
    errors.shift_type = 'Select an assignment type.';
  }
  if (!TIME_PATTERN.test(startTime)) {
    errors.shift_preset = 'Shift preset has an invalid start time.';
  }
  if (endTime !== '24:00' && !TIME_PATTERN.test(endTime)) {
    errors.shift_preset = 'Shift preset has an invalid end time.';
  }

  if (Object.keys(errors).length) {
    return { errors, payload: null };
  }

  const item = {
    day_of_week: Number(draft.day_of_week),
    shift_type: String(draft.shift_type),
    start_time: startTime,
    end_time: endTime,
    break_minutes: 0,
  };
  if (/^[1-9]\d*$/.test(String(draft.shift_template_id ?? ''))) {
    item.shift_template_id = Number(draft.shift_template_id);
  }

  return {
    errors: {},
    payload: {
      start_date: startDate,
      end_date: endDate,
      agents: [Number(draft.agent_id)],
      pattern: {
        type: 'weekly_rotation',
        items: [item],
      },
      options: {
        include_holidays: Boolean(draft.include_holidays),
        include_warnings: Boolean(draft.include_warnings),
        strict_conflict_check: Boolean(draft.strict_conflict_check),
      },
    },
  };
}

export function templatePreviewFieldErrorsFromApi(errors) {
  if (!Array.isArray(errors)) {
    return {};
  }

  const fieldMap = {
    'pattern.items.0.day_of_week': 'day_of_week',
    'pattern.items.0.shift_type': 'shift_type',
    'pattern.items.0.start_time': 'shift_preset',
    'pattern.items.0.end_time': 'shift_preset',
    'pattern.items.0.shift_template_id': 'shift_template_id',
    'agents.0': 'agent_id',
    date_range: 'end_date',
  };

  return Object.fromEntries(
    errors
      .filter((error) => error?.field && error?.message)
      .map((error) => [fieldMap[String(error.field)] ?? String(error.field), String(error.message)]),
  );
}

export function templatePreviewErrorMessage(error) {
  if (!error?.status) {
    return 'The network request failed while generating the template preview.';
  }

  switch (error.status) {
    case 400:
      return 'The preview request could not be read. Review the form and try again.';
    case 401:
      return 'Your session expired. Sign in again before generating a template preview.';
    case 403:
      return 'The template preview request was denied. Refresh permissions and try again.';
    case 405:
      return 'Template preview is not available from this route.';
    case 422:
      return Array.isArray(error.errors) && error.errors.length
        ? 'Review the highlighted fields before generating the preview.'
        : error.message || 'The template preview did not pass server validation.';
    default:
      return error.message || 'The template preview could not be generated.';
  }
}
