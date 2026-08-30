export function firstFieldError(errors) {
  return Object.keys(errors ?? {}).find((field) => field !== 'form') ?? '';
}

export function focusInvalidField(modal, errors) {
  const field = firstFieldError(errors);
  if (!field || !modal) {
    return false;
  }

  const control = modal.querySelector(`[name="${field}"]`);
  if (!control) {
    return false;
  }

  control.focus();
  control.scrollIntoView?.({ block: 'center', behavior: 'smooth' });
  return true;
}

export function mutationErrorMessage(error, action) {
  const verb = action === 'delete'
    ? 'deleting'
    : action === 'edit'
    ? 'updating'
    : 'creating';
  const fallback = action === 'delete'
    ? 'The assignment could not be deleted.'
    : action === 'edit'
    ? 'The assignment could not be updated.'
    : 'The assignment could not be created.';

  if (!error?.status) {
    return `The network request failed while ${verb} the assignment.`;
  }

  switch (error?.status) {
    case 400:
      return 'The request could not be read. Review the form and try again.';
    case 401:
      return `Your session expired. Sign in again before ${verb} an assignment.`;
    case 403:
      return `The ${action === 'delete' ? 'delete' : action === 'edit' ? 'update' : 'create'} request was denied. Refresh permissions and try again.`;
    case 404:
      return ['edit', 'delete'].includes(action)
        ? 'This assignment no longer exists. Refresh the schedule.'
        : fallback;
    case 405:
      return 'This assignment action is not available. Refresh the preview and try again.';
    case 409:
      return action === 'delete'
        ? 'This assignment is protected by a monthly template and cannot be deleted here.'
        : action === 'edit'
        ? 'This update conflicts with an existing schedule.'
        : 'This assignment conflicts with an existing schedule.';
    case 422:
      return Array.isArray(error?.errors) && error.errors.length
        ? 'Review the highlighted fields before saving.'
        : error?.message || 'The assignment did not pass server validation.';
    default:
      return error?.message || fallback;
  }
}
