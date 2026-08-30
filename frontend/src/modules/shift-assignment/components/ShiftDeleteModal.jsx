import { useEffect, useRef, useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { deleteShiftAssignment } from '../api';
import {
  DELETE_CONFIRMATION,
  deleteDependencyNote,
  isTemplateProtected,
  validateDeleteConfirmation,
} from '../utils/shiftDelete';
import { mutationErrorMessage } from '../utils/shiftMutation';

function Detail({ label, value }) {
  return (
    <div className="tr:min-w-0 tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3">
      <dt className="tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted">
        {label}
      </dt>
      <dd className="tr:mt-1 tr:break-words tr:text-xs tr:font-semibold tr:text-tracs-primary">
        {value || 'Not available'}
      </dd>
    </div>
  );
}

function assignmentRole(assignment) {
  return assignment.agent?.role_name
    || assignment.agent?.role
    || assignment.role?.name
    || assignment.role
    || '';
}

export function ShiftDeleteModal({
  assignment,
  context,
  onClose,
  onDeleted,
  onToast,
  open,
}) {
  const [confirmation, setConfirmation] = useState('');
  const [error, setError] = useState('');
  const [deleting, setDeleting] = useState(false);
  const confirmationRef = useRef(null);
  const csrf = context?.csrf ?? {};
  const protectedByTemplate = isTemplateProtected(assignment);
  const confirmationError = validateDeleteConfirmation(confirmation);

  useEffect(() => {
    if (!open) return;
    setConfirmation('');
    setError('');
    setDeleting(false);
    window.setTimeout(() => confirmationRef.current?.focus(), 0);
  }, [assignment?.id, open]);

  useEffect(() => {
    if (!open) return undefined;
    function closeOnEscape(event) {
      if (event.key === 'Escape' && !deleting) onClose();
    }
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [deleting, onClose, open]);

  if (!open || !assignment) return null;

  async function submit(event) {
    event.preventDefault();
    if (deleting || protectedByTemplate || confirmationError) {
      setError(protectedByTemplate
        ? 'Template-linked assignments must be handled through the template workflow.'
        : confirmationError);
      return;
    }

    setDeleting(true);
    setError('');
    try {
      const response = await deleteShiftAssignment(assignment.id, csrf);
      await onDeleted(assignment.id, response.message);
      onClose();
    } catch (requestError) {
      const message = mutationErrorMessage(requestError, 'delete');
      setError(message);
      onToast({ type: 'error', title: 'Delete assignment failed', message });
    } finally {
      setDeleting(false);
    }
  }

  return (
    <div
      aria-labelledby="shift-delete-title"
      aria-modal="true"
      className="shift-create-backdrop tr:fixed tr:inset-0 tr:z-[60] tr:flex tr:items-end tr:justify-center tr:bg-black/60 tr:p-0 tr:sm:items-center tr:sm:p-tracs-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget && !deleting) onClose();
      }}
      role="dialog"
    >
      <section className="shift-create-modal tr:flex tr:max-h-[94vh] tr:w-full tr:max-w-xl tr:flex-col tr:overflow-hidden tr:rounded-t-tracs-xl tr:border tr:border-tracs-danger/50 tr:bg-tracs-card tr:shadow-tracs-modal tr:sm:rounded-tracs-xl">
        <header className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-3 tr:border-b tr:border-tracs-danger/30 tr:bg-tracs-danger-soft tr:px-tracs-5 tr:py-tracs-4">
          <div>
            <p className="tr:font-mono tr:text-[9px] tr:font-bold tr:uppercase tr:tracking-[.1em] tr:text-tracs-danger">
              Destructive Super Admin pilot
            </p>
            <h2 className="tr:mt-1 tr:text-lg tr:font-semibold tr:text-tracs-primary" id="shift-delete-title">
              Delete Assignment
            </h2>
            <p className="tr:mt-1 tr:text-xs tr:leading-5 tr:text-tracs-secondary">
              This action hard-deletes the assignment. Audit-backed restoration has been validated,
              but restoration is a controlled manual recovery procedure, not an instant undo.
              Use this action only during controlled pilot validation.
            </p>
          </div>
          <button
            aria-label="Close delete assignment"
            className="tr:rounded-tracs tr:px-2 tr:py-1 tr:text-sm tr:text-tracs-muted tr:hover:bg-tracs-card"
            disabled={deleting}
            onClick={onClose}
            type="button"
          >
            Close
          </button>
        </header>

        <form aria-busy={deleting} className="tr:min-h-0 tr:overflow-y-auto" onSubmit={submit}>
          <fieldset className="tr:m-0 tr:border-0 tr:p-tracs-5" disabled={deleting}>
            <dl className="tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:sm:grid-cols-2">
              <Detail label="Assignment ID" value={`#${assignment.id}`} />
              <Detail label="Agent" value={assignment.agent?.name} />
              <Detail label="Date" value={assignment.assignment_date_display} />
              <Detail label="Shift" value={`${assignment.shift?.name || 'Custom Shift'} · ${assignment.shift?.display_range || ''}`} />
              <Detail label="Type" value={(assignment.type_name || assignment.type || '').replaceAll('_', ' ')} />
              <Detail label="Division" value={assignment.division?.name} />
              <Detail label="Role" value={assignmentRole(assignment)} />
              <Detail label="Status" value={(assignment.status || '').replaceAll('_', ' ')} />
            </dl>

            <div className="tr:mt-tracs-4 tr:rounded-tracs tr:border tr:border-tracs-warning/40 tr:bg-tracs-warning-soft tr:p-tracs-3 tr:text-xs tr:leading-5 tr:text-tracs-secondary">
              {deleteDependencyNote(assignment)}
            </div>

            {protectedByTemplate ? (
              <div className="tr:mt-tracs-3 tr:rounded-tracs tr:border tr:border-tracs-danger/40 tr:bg-tracs-danger-soft tr:p-tracs-3 tr:text-xs tr:font-semibold tr:text-tracs-danger">
                Template-linked assignments cannot be deleted from this pilot. The API will return 409.
              </div>
            ) : null}

            <label className="tr:mt-tracs-4 tr:flex tr:flex-col tr:gap-1">
              <span className="tr:text-xs tr:font-semibold tr:text-tracs-secondary">
                Type <span className="tr:font-mono tr:text-tracs-danger">{DELETE_CONFIRMATION}</span> to confirm
              </span>
              <input
                aria-describedby={error ? 'delete-confirmation-error' : undefined}
                aria-invalid={Boolean(error)}
                autoComplete="off"
                className="tr:min-h-9 tr:w-full tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-tracs-3 tr:font-mono tr:text-xs tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-danger tr:focus:ring-2 tr:focus:ring-tracs-danger-soft"
                name="delete_confirmation"
                onChange={(event) => {
                  setConfirmation(event.target.value);
                  setError('');
                }}
                ref={confirmationRef}
                value={confirmation}
              />
              {error ? (
                <span
                  aria-live="polite"
                  className="tr:text-[10px] tr:leading-4 tr:text-tracs-danger"
                  id="delete-confirmation-error"
                >
                  {error}
                </span>
              ) : (
                <span aria-live="polite" className="tr:text-[10px] tr:leading-4 tr:text-tracs-muted">
                  {confirmation === DELETE_CONFIRMATION
                    ? 'Confirmation accepted. Review the assignment details once more before deleting.'
                    : 'Confirmation is case-sensitive and does not ignore spaces.'}
                </span>
              )}
            </label>
          </fieldset>

          <footer className="tr:flex tr:flex-wrap tr:items-center tr:justify-between tr:gap-tracs-3 tr:border-t tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-5 tr:py-tracs-3">
            <span className="tr:text-[10px] tr:text-tracs-muted">
              Required audit snapshots are written before deletion.
            </span>
            <div className="tr:flex tr:items-center tr:gap-tracs-2">
              <Button disabled={deleting} onClick={onClose} variant="quiet">
                Cancel
              </Button>
              <Button
                disabled={deleting || protectedByTemplate || Boolean(confirmationError)}
                type="submit"
                variant="danger"
              >
                {deleting ? 'Deleting...' : 'Delete Assignment'}
              </Button>
            </div>
          </footer>
        </form>
      </section>
    </div>
  );
}
