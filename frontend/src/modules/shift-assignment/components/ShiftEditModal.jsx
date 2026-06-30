import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { updateShiftAssignment } from '../api';
import { fieldErrorsFromApi } from '../utils/shiftCreate';
import {
  applyEditShiftPreset,
  initialEditDraft,
  validateEditDraft,
} from '../utils/shiftEdit';
import {
  focusInvalidField,
  mutationErrorMessage,
} from '../utils/shiftMutation';

const fieldClass =
  'tr:min-h-9 tr:w-full tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-tracs-3 tr:text-xs tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-accent tr:focus:ring-2 tr:focus:ring-tracs-accent-soft';

function Field({ children, error, label, name, required = false }) {
  return (
    <label className="tr:flex tr:min-w-0 tr:flex-col tr:gap-1">
      <span className="tr:text-xs tr:font-semibold tr:text-tracs-secondary">
        {label}
        {required ? <span aria-hidden="true" className="shift-required-mark">*</span> : null}
      </span>
      {children}
      {error ? (
        <span className="tr:text-[10px] tr:leading-4 tr:text-tracs-danger" id={`edit-${name}-error`}>
          {error}
        </span>
      ) : null}
    </label>
  );
}

export function ShiftEditModal({
  assignment,
  context,
  onClose,
  onToast,
  onUpdated,
  open,
}) {
  const initialDraft = useMemo(() => initialEditDraft(assignment), [assignment]);
  const [draft, setDraft] = useState(initialDraft);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const firstFieldRef = useRef(null);
  const modalRef = useRef(null);
  const dirty = JSON.stringify(draft) !== JSON.stringify(initialDraft);
  const filters = context?.filters ?? {};
  const csrf = context?.csrf ?? {};

  useEffect(() => {
    if (!open) {
      return;
    }
    setDraft(initialDraft);
    setErrors({});
    setSaving(false);
    window.setTimeout(() => firstFieldRef.current?.focus(), 0);
  }, [initialDraft, open]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    function warnBeforeUnload(event) {
      if (dirty) {
        event.preventDefault();
        event.returnValue = '';
      }
    }
    window.addEventListener('beforeunload', warnBeforeUnload);
    return () => window.removeEventListener('beforeunload', warnBeforeUnload);
  }, [dirty, open]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    function closeOnEscape(event) {
      if (event.key === 'Escape' && !saving
          && (!dirty || window.confirm('Discard unsaved assignment changes?'))) {
        onClose();
      }
    }
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [dirty, onClose, open, saving]);

  if (!open || !assignment) {
    return null;
  }

  function update(event) {
    const { name, value } = event.target;
    setDraft((current) => ({ ...current, [name]: value }));
    setErrors((current) => ({ ...current, [name]: undefined, form: undefined }));
  }

  function selectPreset(event) {
    setDraft((current) => applyEditShiftPreset(
      current,
      event.target.value,
      context?.shift_definitions ?? [],
    ));
    setErrors((current) => ({
      ...current,
      start_time: undefined,
      end_time: undefined,
      form: undefined,
    }));
  }

  function requestClose() {
    if (!saving && (!dirty || window.confirm('Discard unsaved assignment changes?'))) {
      onClose();
    }
  }

  async function submit(event) {
    event.preventDefault();
    if (saving) {
      return;
    }

    const result = validateEditDraft(draft, initialDraft);
    setErrors(result.errors);
    if (!result.payload) {
      focusInvalidField(modalRef.current, result.errors);
      onToast({
        type: 'error',
        title: result.errors.form ? 'Update not sent' : 'Update failed',
        message: result.errors.form || 'Review the highlighted fields before saving.',
      });
      return;
    }

    setSaving(true);
    try {
      const response = await updateShiftAssignment(draft.id, result.payload, csrf);
      await onUpdated(response.data?.assignment, response.message);
      onClose();
    } catch (error) {
      const apiErrors = fieldErrorsFromApi(error?.errors);
      setErrors(apiErrors);
      if (Object.keys(apiErrors).length) {
        window.setTimeout(() => focusInvalidField(modalRef.current, apiErrors), 0);
      }
      const message = mutationErrorMessage(error, 'edit');
      onToast({ type: 'error', title: 'Update failed', message });
    } finally {
      setSaving(false);
    }
  }

  return (
    <div
      aria-labelledby="shift-edit-title"
      aria-modal="true"
      className="shift-create-backdrop tr:fixed tr:inset-0 tr:z-[60] tr:flex tr:items-end tr:justify-center tr:bg-black/55 tr:p-0 tr:sm:items-center tr:sm:p-tracs-4"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          requestClose();
        }
      }}
      role="dialog"
    >
      <section
        className="shift-create-modal tr:flex tr:max-h-[94vh] tr:w-full tr:max-w-2xl tr:flex-col tr:overflow-hidden tr:rounded-t-tracs-xl tr:border tr:border-tracs-border tr:bg-tracs-card tr:shadow-tracs-modal tr:sm:rounded-tracs-xl"
        ref={modalRef}
      >
        <header className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-3 tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-5 tr:py-tracs-4">
          <div>
            <p className="tr:font-mono tr:text-[9px] tr:font-bold tr:uppercase tr:tracking-[.1em] tr:text-tracs-accent">
              Controlled Super Admin pilot
            </p>
            <h2 className="tr:mt-1 tr:text-lg tr:font-semibold tr:text-tracs-primary" id="shift-edit-title">
              Edit Assignment
            </h2>
            <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">
              Assignment #{assignment.id}. Backend scope, CSRF, conflicts, and audit logging remain authoritative.
            </p>
          </div>
          <button
            aria-label="Close edit assignment"
            className="tr:rounded-tracs tr:px-2 tr:py-1 tr:text-sm tr:text-tracs-muted tr:hover:bg-tracs-card"
            disabled={saving}
            onClick={requestClose}
            type="button"
          >
            Close
          </button>
        </header>

        <form
          aria-busy={saving}
          className="tr:min-h-0 tr:overflow-y-auto"
          noValidate
          onSubmit={submit}
        >
          <fieldset
            className="tr:m-0 tr:grid tr:min-w-0 tr:grid-cols-1 tr:gap-tracs-4 tr:border-0 tr:p-tracs-5 tr:sm:grid-cols-2"
            disabled={saving}
          >
            {errors.form ? (
              <p className="tr:rounded-tracs tr:border tr:border-tracs-warning/40 tr:bg-tracs-warning-soft tr:p-tracs-3 tr:text-xs tr:text-tracs-warning tr:sm:col-span-2">
                {errors.form}
              </p>
            ) : null}
            <Field error={errors.agent_id} label="Agent" name="agent_id" required>
              <select
                aria-describedby={errors.agent_id ? 'edit-agent_id-error' : undefined}
                aria-invalid={Boolean(errors.agent_id)}
                aria-required="true"
                className={fieldClass}
                name="agent_id"
                onChange={update}
                ref={firstFieldRef}
                value={draft.agent_id}
              >
                <option value="">Select scoped agent</option>
                {(filters.agents ?? []).map((agent) => (
                  <option key={agent.id} value={agent.id}>
                    {agent.name}{agent.division_name ? ` · ${agent.division_name}` : ''}
                  </option>
                ))}
              </select>
            </Field>

            <Field error={errors.assignment_date} label="Assignment date" name="assignment_date" required>
              <input
                aria-describedby={errors.assignment_date ? 'edit-assignment_date-error' : undefined}
                aria-invalid={Boolean(errors.assignment_date)}
                aria-required="true"
                autoComplete="off"
                className={fieldClass}
                inputMode="numeric"
                name="assignment_date"
                onChange={update}
                placeholder="dd-mm-yyyy"
                value={draft.assignment_date}
              />
            </Field>

            <Field error={errors.shift_type} label="Assignment type" name="shift_type" required>
              <select
                aria-describedby={errors.shift_type ? 'edit-shift_type-error' : undefined}
                aria-invalid={Boolean(errors.shift_type)}
                aria-required="true"
                className={fieldClass}
                name="shift_type"
                onChange={update}
                value={draft.shift_type}
              >
                {(filters.assignment_types ?? []).map((type) => (
                  <option key={type.slug} value={type.slug}>{type.name}</option>
                ))}
              </select>
            </Field>

            <Field label="Shift preset" name="shift_preset">
              <select className={fieldClass} name="shift_preset" onChange={selectPreset} value={draft.shift_preset}>
                <option value="">Custom time</option>
                {(context?.shift_definitions ?? []).map((shift) => (
                  <option key={shift.key} value={shift.key}>
                    {shift.name} · {shift.display_range}
                  </option>
                ))}
              </select>
            </Field>

            <Field error={errors.start_time} label="Start time" name="start_time" required>
              <input
                aria-describedby={errors.start_time ? 'edit-start_time-error' : undefined}
                aria-invalid={Boolean(errors.start_time)}
                aria-required="true"
                className={fieldClass}
                name="start_time"
                onChange={update}
                value={draft.start_time}
              />
            </Field>

            <Field error={errors.end_time} label="End time" name="end_time" required>
              <input
                aria-describedby={errors.end_time ? 'edit-end_time-error' : undefined}
                aria-invalid={Boolean(errors.end_time)}
                aria-required="true"
                className={fieldClass}
                name="end_time"
                onChange={update}
                value={draft.end_time}
              />
            </Field>

            <Field label="Template (optional)" name="shift_template_id">
              <select className={fieldClass} name="shift_template_id" onChange={update} value={draft.shift_template_id}>
                <option value="">Custom/no template</option>
                {(filters.shift_templates ?? []).filter((item) => item.is_active).map((template) => (
                  <option key={template.id} value={template.id}>
                    {template.name} · {template.start_time}-{template.end_time}
                  </option>
                ))}
              </select>
            </Field>

            <Field error={errors.status} label="Status" name="status" required>
              <select
                aria-describedby={errors.status ? 'edit-status-error' : undefined}
                aria-invalid={Boolean(errors.status)}
                aria-required="true"
                className={fieldClass}
                name="status"
                onChange={update}
                value={draft.status}
              >
                {(filters.statuses ?? []).map((status) => (
                  <option key={status} value={status}>{status.replaceAll('_', ' ')}</option>
                ))}
              </select>
            </Field>

            <Field error={errors.break_minutes} label="Break minutes" name="break_minutes">
              <input
                aria-describedby={errors.break_minutes ? 'edit-break_minutes-error' : undefined}
                aria-invalid={Boolean(errors.break_minutes)}
                className={fieldClass}
                inputMode="numeric"
                name="break_minutes"
                onChange={update}
                value={draft.break_minutes}
              />
            </Field>
          </fieldset>

          <footer className="tr:flex tr:flex-wrap tr:items-center tr:justify-between tr:gap-tracs-3 tr:border-t tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-5 tr:py-tracs-3">
            <span className="tr:text-[10px] tr:text-tracs-muted">
              Only changed allowlisted fields are sent. Dates display as dd-mm-yyyy.
            </span>
            <div className="tr:flex tr:items-center tr:gap-tracs-2">
              <Button disabled={saving} onClick={requestClose} variant="quiet">
                Cancel
              </Button>
              <Button disabled={saving} type="submit" variant="primary">
                {saving ? 'Saving...' : 'Save Changes'}
              </Button>
            </div>
          </footer>
        </form>
      </section>
    </div>
  );
}
