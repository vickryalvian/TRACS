import { displayDateInput, isoDateInput, rangeForView, shiftRange } from './shiftDates.js';

const MAX_COPY_PREVIEW_DAYS = 35;

function isoDayCount(startDate, endDate) {
  const start = Date.parse(`${startDate}T00:00:00Z`);
  const end = Date.parse(`${endDate}T00:00:00Z`);
  return Number.isFinite(start) && Number.isFinite(end)
    ? Math.round((end - start) / 86_400_000) + 1
    : 0;
}

export function initialCopyPreviewDraft(defaults = {}) {
  const source = rangeForView('weekly', defaults.start_date);
  const target = shiftRange({ view: 'weekly', start_date: source.start_date, end_date: source.end_date }, 'weekly', 1);

  return {
    source_start_date: displayDateInput(source.start_date),
    source_end_date: displayDateInput(source.end_date),
    target_start_date: displayDateInput(target.start_date),
    target_end_date: displayDateInput(target.end_date),
    include_holidays: true,
    include_warnings: true,
    strict_conflict_check: true,
  };
}

export function validateCopyPreviewDraft(draft) {
  const errors = {};
  const sourceStart = isoDateInput(draft.source_start_date);
  const sourceEnd = isoDateInput(draft.source_end_date);
  const targetStart = isoDateInput(draft.target_start_date);
  const targetEnd = isoDateInput(draft.target_end_date);

  if (!sourceStart) {
    errors.source_start_date = 'Use dd-mm-yyyy.';
  }
  if (!sourceEnd) {
    errors.source_end_date = 'Use dd-mm-yyyy.';
  }
  if (!targetStart) {
    errors.target_start_date = 'Use dd-mm-yyyy.';
  }
  if (!targetEnd) {
    errors.target_end_date = 'Use dd-mm-yyyy.';
  }

  if (sourceStart && sourceEnd) {
    if (sourceEnd < sourceStart) {
      errors.source_end_date = 'Source end date must be on or after source start date.';
    } else if (isoDayCount(sourceStart, sourceEnd) > MAX_COPY_PREVIEW_DAYS) {
      errors.source_end_date = 'Source range cannot exceed 35 days.';
    }
  }
  if (targetStart && targetEnd) {
    if (targetEnd < targetStart) {
      errors.target_end_date = 'Target end date must be on or after target start date.';
    } else if (isoDayCount(targetStart, targetEnd) > MAX_COPY_PREVIEW_DAYS) {
      errors.target_end_date = 'Target range cannot exceed 35 days.';
    }
  }
  if (sourceStart && sourceEnd && targetStart && targetEnd) {
    if (sourceStart === targetStart && sourceEnd === targetEnd) {
      errors.target_start_date = 'Source and target ranges must be different.';
    }
    if (isoDayCount(sourceStart, sourceEnd) !== isoDayCount(targetStart, targetEnd)) {
      errors.target_end_date = 'Source and target ranges must have the same length.';
    }
  }

  if (Object.keys(errors).length) {
    return { errors, payload: null };
  }

  return {
    errors: {},
    payload: {
      source_start_date: sourceStart,
      source_end_date: sourceEnd,
      target_start_date: targetStart,
      target_end_date: targetEnd,
      scope: {
        agent_ids: [],
        role_ids: [],
        division_ids: [],
      },
      options: {
        include_holidays: Boolean(draft.include_holidays),
        include_warnings: Boolean(draft.include_warnings),
        strict_conflict_check: Boolean(draft.strict_conflict_check),
      },
    },
  };
}

export function copyPreviewFieldErrorsFromApi(errors) {
  if (!Array.isArray(errors)) {
    return {};
  }

  const fieldMap = {
    source_range: 'source_end_date',
    target_range: 'target_end_date',
    date_range: 'target_start_date',
    date_range_length: 'target_end_date',
  };

  return Object.fromEntries(
    errors
      .filter((error) => error?.field && error?.message)
      .map((error) => [fieldMap[String(error.field)] ?? String(error.field), String(error.message)]),
  );
}

export function copyPreviewErrorMessage(error) {
  if (!error?.status) {
    return 'The network request failed while generating the copy preview.';
  }

  switch (error.status) {
    case 400:
      return 'The copy preview request could not be read. Review the form and try again.';
    case 401:
      return 'Your session expired. Sign in again before generating a copy preview.';
    case 403:
      return 'The copy preview request was denied. Refresh permissions and try again.';
    case 405:
      return 'Copy preview is not available from this route.';
    case 422:
      return Array.isArray(error.errors) && error.errors.length
        ? 'Review the highlighted fields before generating the copy preview.'
        : error.message || 'The copy preview did not pass server validation.';
    default:
      return error.message || 'The copy preview could not be generated.';
  }
}
