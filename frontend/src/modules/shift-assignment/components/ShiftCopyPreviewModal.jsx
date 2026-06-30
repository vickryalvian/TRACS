import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { previewCopySchedule } from '../api';
import {
  copyPreviewErrorMessage,
  copyPreviewFieldErrorsFromApi,
  initialCopyPreviewDraft,
  validateCopyPreviewDraft,
} from '../utils/shiftCopyPreview';
import { focusInvalidField } from '../utils/shiftMutation';

const fieldClass =
  'tr:min-h-9 tr:w-full tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-tracs-3 tr:text-xs tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-accent tr:focus:ring-2 tr:focus:ring-tracs-accent-soft';

function Field({ children, error, help, label, name, required = false }) {
  return (
    <label className="tr:flex tr:min-w-0 tr:flex-col tr:gap-1">
      <span className="tr:text-xs tr:font-semibold tr:text-tracs-secondary">
        {label}
        {required ? <span aria-hidden="true" className="shift-required-mark">*</span> : null}
      </span>
      {children}
      {help ? (
        <span className="tr:text-[10px] tr:leading-4 tr:text-tracs-muted" id={`${name}-help`}>
          {help}
        </span>
      ) : null}
      {error ? (
        <span className="tr:text-[10px] tr:leading-4 tr:text-tracs-danger" id={`${name}-error`} role="alert">
          {error}
        </span>
      ) : null}
    </label>
  );
}

function describedBy(name, error) {
  return error ? `${name}-help ${name}-error` : `${name}-help`;
}

function ResultList({ empty, items, title, tone = 'neutral' }) {
  const toneClass = tone === 'danger'
    ? 'tr:border-tracs-danger/30 tr:bg-tracs-danger/5'
    : tone === 'warning'
    ? 'tr:border-tracs-warning/30 tr:bg-tracs-warning/5'
    : 'tr:border-tracs-border tr:bg-tracs-surface-2';

  return (
    <section className={`tr:rounded-tracs-lg tr:border tr:p-tracs-3 ${toneClass}`}>
      <h3 className="tr:text-xs tr:font-semibold tr:text-tracs-primary">{title}</h3>
      {items?.length ? (
        <div className="tr:mt-tracs-2 tr:flex tr:flex-col tr:gap-tracs-2">
          {items.map((item, index) => (
            <div className="tr:rounded-tracs tr:bg-tracs-card tr:p-tracs-2 tr:text-xs tr:text-tracs-secondary" key={`${title}-${index}`}>
              <p className="tr:font-semibold tr:text-tracs-primary">
                {item.message || item.reason || item.type || `Item ${index + 1}`}
              </p>
              {item.source_assignment_id ? (
                <p className="tr:mt-1 tr:font-mono tr:text-[10px]">Source assignment: {item.source_assignment_id}</p>
              ) : null}
              {item.date ? <p className="tr:mt-1">Date: {item.date_display || item.date}</p> : null}
              {item.preview_id ? <p className="tr:mt-1 tr:font-mono tr:text-[10px]">Preview ID: {item.preview_id}</p> : null}
            </div>
          ))}
        </div>
      ) : (
        <p className="tr:mt-tracs-2 tr:text-xs tr:text-tracs-muted">{empty}</p>
      )}
    </section>
  );
}

export function ShiftCopyPreviewModal({ context, onClose, onToast, open }) {
  const [draft, setDraft] = useState(() => initialCopyPreviewDraft());
  const [errors, setErrors] = useState({});
  const [formMessage, setFormMessage] = useState(null);
  const [generating, setGenerating] = useState(false);
  const [preview, setPreview] = useState(null);
  const [stalePreview, setStalePreview] = useState(false);
  const modalRef = useRef(null);
  const firstFieldRef = useRef(null);
  const csrf = context?.csrf ?? {};
  const initialDraft = useMemo(() => initialCopyPreviewDraft({
    start_date: context?.defaults?.start_date,
  }), [context?.defaults?.start_date, open]);
  const dirty = JSON.stringify(draft) !== JSON.stringify(initialDraft);

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    setDraft(initialDraft);
    setErrors({});
    setFormMessage(null);
    setGenerating(false);
    setPreview(null);
    setStalePreview(false);
    window.setTimeout(() => firstFieldRef.current?.focus(), 0);
    return undefined;
  }, [initialDraft, open]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    function closeOnEscape(event) {
      if (event.key === 'Escape' && !generating) {
        if (!dirty || window.confirm('Close the copy schedule preview form?')) {
          onClose();
        }
      }
    }
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [dirty, generating, onClose, open]);

  if (!open) {
    return null;
  }

  function update(event) {
    const { checked, name, type, value } = event.target;
    setDraft((current) => ({ ...current, [name]: type === 'checkbox' ? checked : value }));
    setErrors((current) => ({ ...current, [name]: undefined }));
    setFormMessage(null);
    if (preview) {
      setStalePreview(true);
    }
  }

  function requestClose() {
    if (!generating && (!dirty || window.confirm('Close the copy schedule preview form?'))) {
      onClose();
    }
  }

  async function submit(event) {
    event.preventDefault();
    if (generating) {
      return;
    }
    const result = validateCopyPreviewDraft(draft);
    setErrors(result.errors);
    setFormMessage(null);
    if (!result.payload) {
      setStalePreview(Boolean(preview));
      setFormMessage('Review the highlighted fields before generating a copy preview.');
      focusInvalidField(modalRef.current, result.errors);
      onToast({
        type: 'error',
        title: 'Copy preview failed',
        message: 'Review the highlighted fields before generating a copy preview.',
      });
      return;
    }

    setGenerating(true);
    try {
      const response = await previewCopySchedule(result.payload, csrf);
      setPreview(response.data);
      setStalePreview(false);
      setFormMessage(null);
      onToast({
        type: 'success',
        title: 'Copy preview generated',
        message: 'Preview only. No assignments were created or modified.',
      });
    } catch (error) {
      const apiErrors = copyPreviewFieldErrorsFromApi(error?.errors);
      setErrors(apiErrors);
      setFormMessage(copyPreviewErrorMessage(error));
      if (Object.keys(apiErrors).length) {
        window.setTimeout(() => focusInvalidField(modalRef.current, apiErrors), 0);
      }
      onToast({
        type: 'error',
        title: 'Copy preview failed',
        message: copyPreviewErrorMessage(error),
      });
    } finally {
      setGenerating(false);
    }
  }

  return (
    <div
      aria-labelledby="shift-copy-preview-title"
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
        className="shift-create-modal tr:flex tr:max-h-[94vh] tr:w-full tr:max-w-5xl tr:flex-col tr:overflow-hidden tr:rounded-t-tracs-xl tr:border tr:border-tracs-border tr:bg-tracs-card tr:shadow-tracs-modal tr:sm:rounded-tracs-xl"
        ref={modalRef}
      >
        <header className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-3 tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-5 tr:py-tracs-4">
          <div>
            <p className="tr:font-mono tr:text-[9px] tr:font-bold tr:uppercase tr:tracking-[.1em] tr:text-tracs-accent">
              Controlled Super Admin pilot
            </p>
            <h2 className="tr:mt-1 tr:text-lg tr:font-semibold tr:text-tracs-primary" id="shift-copy-preview-title">
              Copy Schedule Preview
            </h2>
            <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">
              Preview only - this will not create or modify assignments.
            </p>
          </div>
          <button
            aria-label="Close copy schedule preview"
            className="tr:rounded-tracs tr:px-2 tr:py-1 tr:text-sm tr:text-tracs-muted tr:hover:bg-tracs-card"
            disabled={generating}
            onClick={requestClose}
            type="button"
          >
            Close
          </button>
        </header>

        <form aria-busy={generating} className="tr:min-h-0 tr:overflow-y-auto" data-unsaved-ignore noValidate onSubmit={submit}>
          <div className="tr:grid tr:min-w-0 tr:grid-cols-1 tr:gap-tracs-4 tr:p-tracs-5 tr:lg:grid-cols-[360px_minmax(0,1fr)]">
            <fieldset className="tr:m-0 tr:flex tr:min-w-0 tr:flex-col tr:gap-tracs-4 tr:border-0 tr:p-0" disabled={generating}>
              <div className="tr:rounded-tracs-lg tr:border tr:border-tracs-warning/30 tr:bg-tracs-warning/5 tr:p-tracs-3 tr:text-xs tr:leading-5 tr:text-tracs-secondary">
                This preview reads source assignments and checks the target range. It never writes copied assignments and does not expose copy commit controls.
              </div>

              {formMessage ? (
                <div
                  className="tr:rounded-tracs-lg tr:border tr:border-tracs-danger/30 tr:bg-tracs-danger/5 tr:p-tracs-3 tr:text-xs tr:leading-5 tr:text-tracs-danger"
                  role="alert"
                >
                  {formMessage}
                </div>
              ) : null}

              {stalePreview ? (
                <div
                  className="tr:rounded-tracs-lg tr:border tr:border-tracs-warning/30 tr:bg-tracs-warning/5 tr:p-tracs-3 tr:text-xs tr:leading-5 tr:text-tracs-secondary"
                  role="status"
                >
                  Date options changed after the last preview. Generate a new copy preview before relying on the results below.
                </div>
              ) : null}

              <div className="tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3">
                <h3 className="tr:text-xs tr:font-semibold tr:text-tracs-primary">Source range</h3>
                <div className="tr:mt-tracs-2 tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:sm:grid-cols-2">
                  <Field error={errors.source_start_date} help="Use dd-mm-yyyy." label="Source start date" name="source_start_date" required>
                    <input aria-describedby={describedBy('source_start_date', errors.source_start_date)} aria-invalid={Boolean(errors.source_start_date)} className={fieldClass} name="source_start_date" onChange={update} placeholder="dd-mm-yyyy" ref={firstFieldRef} value={draft.source_start_date} />
                  </Field>
                  <Field error={errors.source_end_date} help="Use dd-mm-yyyy." label="Source end date" name="source_end_date" required>
                    <input aria-describedby={describedBy('source_end_date', errors.source_end_date)} aria-invalid={Boolean(errors.source_end_date)} className={fieldClass} name="source_end_date" onChange={update} placeholder="dd-mm-yyyy" value={draft.source_end_date} />
                  </Field>
                </div>
              </div>

              <div className="tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3">
                <h3 className="tr:text-xs tr:font-semibold tr:text-tracs-primary">Target range</h3>
                <div className="tr:mt-tracs-2 tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:sm:grid-cols-2">
                  <Field error={errors.target_start_date} help="Use dd-mm-yyyy." label="Target start date" name="target_start_date" required>
                    <input aria-describedby={describedBy('target_start_date', errors.target_start_date)} aria-invalid={Boolean(errors.target_start_date)} className={fieldClass} name="target_start_date" onChange={update} placeholder="dd-mm-yyyy" value={draft.target_start_date} />
                  </Field>
                  <Field error={errors.target_end_date} help="Use dd-mm-yyyy." label="Target end date" name="target_end_date" required>
                    <input aria-describedby={describedBy('target_end_date', errors.target_end_date)} aria-invalid={Boolean(errors.target_end_date)} className={fieldClass} name="target_end_date" onChange={update} placeholder="dd-mm-yyyy" value={draft.target_end_date} />
                  </Field>
                </div>
              </div>

              <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:text-xs tr:text-tracs-secondary">
                <label className="tr:flex tr:items-center tr:gap-2">
                  <input checked={draft.include_holidays} name="include_holidays" onChange={update} type="checkbox" />
                  Include holiday advisories
                </label>
                <label className="tr:flex tr:items-center tr:gap-2">
                  <input checked={draft.include_warnings} name="include_warnings" onChange={update} type="checkbox" />
                  Include warning checks
                </label>
                <label className="tr:flex tr:items-center tr:gap-2">
                  <input checked={draft.strict_conflict_check} name="strict_conflict_check" onChange={update} type="checkbox" />
                  Strict conflict check
                </label>
              </div>
            </fieldset>

            <div className="tr:flex tr:min-w-0 tr:flex-col tr:gap-tracs-3">
              {preview ? (
                <>
                  <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:lg:grid-cols-2">
                    <section className="tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3">
                      <h3 className="tr:text-xs tr:font-semibold tr:text-tracs-primary">Source Range</h3>
                      <p className="tr:mt-1 tr:text-xs tr:text-tracs-secondary">
                        {preview.source_range?.start_date_display || preview.source_range?.start_date} to {preview.source_range?.end_date_display || preview.source_range?.end_date}
                      </p>
                      <p className="tr:mt-1 tr:font-mono tr:text-[10px] tr:text-tracs-muted">
                        Source assignments: {preview.summary?.source_assignments ?? 0}
                      </p>
                    </section>
                    <section className="tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3">
                      <h3 className="tr:text-xs tr:font-semibold tr:text-tracs-primary">Target Range</h3>
                      <p className="tr:mt-1 tr:text-xs tr:text-tracs-secondary">
                        {preview.target_range?.start_date_display || preview.target_range?.start_date} to {preview.target_range?.end_date_display || preview.target_range?.end_date}
                      </p>
                      <p className="tr:mt-1 tr:font-mono tr:text-[10px] tr:text-tracs-muted">
                        Preview assignments: {preview.summary?.preview_assignments ?? 0}
                      </p>
                    </section>
                  </div>

                  <div className="tr:grid tr:grid-cols-2 tr:gap-tracs-2 tr:lg:grid-cols-5">
                    {[
                      ['Agents', preview.summary?.agents ?? 0],
                      ['Warnings', preview.summary?.warnings ?? 0],
                      ['Conflicts', preview.summary?.conflicts ?? 0],
                      ['Blocked', preview.summary?.blocked_items ?? 0],
                      ['Preview only', preview.summary?.preview_assignments ?? 0],
                    ].map(([label, value]) => (
                      <div className="tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3" key={label}>
                        <p className="tr:font-mono tr:text-[9px] tr:uppercase tr:text-tracs-muted">{label}</p>
                        <p className="tr:mt-1 tr:text-lg tr:font-semibold tr:text-tracs-primary">{value}</p>
                      </div>
                    ))}
                  </div>

                  <section className="tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-card tr:p-tracs-3">
                    <h3 className="tr:text-xs tr:font-semibold tr:text-tracs-primary">Preview items</h3>
                    <div className="tr:mt-tracs-2 tr:max-h-64 tr:overflow-auto">
                      {(preview.items ?? []).length ? (
                        <table className="tr:w-full tr:min-w-[640px] tr:text-left tr:text-xs">
                          <thead className="tr:text-[10px] tr:uppercase tr:text-tracs-muted">
                            <tr>
                              <th className="tr:p-2">Target date</th>
                              <th className="tr:p-2">Agent</th>
                              <th className="tr:p-2">Shift</th>
                              <th className="tr:p-2">Type</th>
                              <th className="tr:p-2">Division</th>
                              <th className="tr:p-2">Source</th>
                            </tr>
                          </thead>
                          <tbody>
                            {preview.items.map((item) => (
                              <tr className="tr:border-t tr:border-tracs-border" key={item.preview_id}>
                                <td className="tr:p-2">{item.assignment_date_display || item.assignment_date}</td>
                                <td className="tr:p-2">{item.agent?.name}</td>
                                <td className="tr:p-2">{item.shift?.display_range}</td>
                                <td className="tr:p-2">{item.type_name || item.type}</td>
                                <td className="tr:p-2">{item.division?.name || 'Unassigned'}</td>
                                <td className="tr:p-2 tr:font-mono tr:text-[10px]">#{item.source_assignment_id}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      ) : (
                        <p className="tr:text-xs tr:text-tracs-muted">No preview items returned.</p>
                      )}
                    </div>
                  </section>

                  <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:xl:grid-cols-3">
                    <ResultList empty="No warnings returned." items={preview.warnings ?? []} title="Warnings" tone="warning" />
                    <ResultList empty="No conflicts returned." items={preview.conflicts ?? []} title="Conflicts" tone="danger" />
                    <ResultList empty="No blocked items returned." items={preview.blocked_items ?? []} title="Blocked items" tone="danger" />
                  </div>
                </>
              ) : (
                <section className="tr:flex tr:min-h-72 tr:flex-col tr:items-center tr:justify-center tr:rounded-tracs-lg tr:border tr:border-dashed tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-5 tr:text-center">
                  <h3 className="tr:text-sm tr:font-semibold tr:text-tracs-primary">No copy preview yet</h3>
                  <p className="tr:mt-2 tr:max-w-md tr:text-xs tr:leading-5 tr:text-tracs-muted">
                    Choose matching source and target ranges, then generate a preview. The assignment list will not change.
                  </p>
                </section>
              )}
            </div>
          </div>

          <footer className="tr:flex tr:flex-col tr:gap-tracs-2 tr:border-t tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-5 tr:py-tracs-4 tr:sm:flex-row tr:sm:items-center tr:sm:justify-between">
            <p className="tr:text-xs tr:text-tracs-muted">
              Preview only - this will not create or modify assignments.
            </p>
            <div className="tr:flex tr:flex-wrap tr:justify-end tr:gap-tracs-2">
              <Button disabled={generating} onClick={requestClose} type="button" variant="secondary">
                Close
              </Button>
              <Button disabled={generating} type="submit" variant="primary">
                {generating ? 'Generating copy preview...' : 'Generate Copy Preview'}
              </Button>
            </div>
          </footer>
        </form>
      </section>
    </div>
  );
}
