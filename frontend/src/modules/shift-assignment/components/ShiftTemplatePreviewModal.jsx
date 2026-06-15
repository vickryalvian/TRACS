import { useEffect, useMemo, useRef, useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { previewShiftTemplate } from '../api';
import {
  applyTemplatePreviewPreset,
  initialTemplatePreviewDraft,
  templatePreviewErrorMessage,
  templatePreviewFieldErrorsFromApi,
  validateTemplatePreviewDraft,
} from '../utils/shiftTemplatePreview';
import { focusInvalidField } from '../utils/shiftMutation';

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
        <span className="tr:text-[10px] tr:leading-4 tr:text-tracs-danger" id={`${name}-error`}>
          {error}
        </span>
      ) : null}
    </label>
  );
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

export function ShiftTemplatePreviewModal({ context, onClose, onToast, open }) {
  const [draft, setDraft] = useState(() => initialTemplatePreviewDraft());
  const [errors, setErrors] = useState({});
  const [generating, setGenerating] = useState(false);
  const [preview, setPreview] = useState(null);
  const modalRef = useRef(null);
  const firstFieldRef = useRef(null);
  const csrf = context?.csrf ?? {};
  const filters = context?.filters ?? {};
  const initialDraft = useMemo(() => initialTemplatePreviewDraft({
    start_date: context?.defaults?.start_date,
    end_date: context?.defaults?.end_date,
  }), [context?.defaults?.end_date, context?.defaults?.start_date, open]);
  const dirty = JSON.stringify(draft) !== JSON.stringify(initialDraft);

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    setDraft((current) => applyTemplatePreviewPreset(
      { ...initialDraft, agent_id: current.agent_id || '' },
      initialDraft.shift_preset,
      context?.shift_definitions ?? [],
      filters.shift_templates ?? [],
    ));
    setErrors({});
    setGenerating(false);
    setPreview(null);
    window.setTimeout(() => firstFieldRef.current?.focus(), 0);
    return undefined;
  }, [context?.shift_definitions, filters.shift_templates, initialDraft, open]);

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    function closeOnEscape(event) {
      if (event.key === 'Escape' && !generating) {
        if (!dirty || window.confirm('Close the template preview form?')) {
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
  }

  function selectPreset(event) {
    const preset = event.target.value;
    setDraft((current) => applyTemplatePreviewPreset(
      current,
      preset,
      context?.shift_definitions ?? [],
      filters.shift_templates ?? [],
    ));
    setErrors((current) => ({ ...current, shift_preset: undefined, shift_template_id: undefined }));
  }

  function requestClose() {
    if (!generating && (!dirty || window.confirm('Close the template preview form?'))) {
      onClose();
    }
  }

  async function submit(event) {
    event.preventDefault();
    if (generating) {
      return;
    }
    const result = validateTemplatePreviewDraft(draft, context);
    setErrors(result.errors);
    if (!result.payload) {
      focusInvalidField(modalRef.current, result.errors);
      onToast({
        type: 'error',
        title: 'Template preview failed',
        message: 'Review the highlighted fields before generating a preview.',
      });
      return;
    }

    setGenerating(true);
    try {
      const response = await previewShiftTemplate(result.payload, csrf);
      setPreview(response.data);
      onToast({
        type: 'success',
        title: 'Template preview generated',
        message: 'Preview only. No assignments were created or modified.',
      });
    } catch (error) {
      const apiErrors = templatePreviewFieldErrorsFromApi(error?.errors);
      setErrors(apiErrors);
      if (Object.keys(apiErrors).length) {
        window.setTimeout(() => focusInvalidField(modalRef.current, apiErrors), 0);
      }
      onToast({
        type: 'error',
        title: 'Template preview failed',
        message: templatePreviewErrorMessage(error),
      });
    } finally {
      setGenerating(false);
    }
  }

  return (
    <div
      aria-labelledby="shift-template-preview-title"
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
            <h2 className="tr:mt-1 tr:text-lg tr:font-semibold tr:text-tracs-primary" id="shift-template-preview-title">
              Template Preview
            </h2>
            <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">
              Preview only — this will not create or modify any assignments.
            </p>
          </div>
          <button
            aria-label="Close template preview"
            className="tr:rounded-tracs tr:px-2 tr:py-1 tr:text-sm tr:text-tracs-muted tr:hover:bg-tracs-card"
            disabled={generating}
            onClick={requestClose}
            type="button"
          >
            Close
          </button>
        </header>

        <form aria-busy={generating} className="tr:min-h-0 tr:overflow-y-auto" noValidate onSubmit={submit}>
          <div className="tr:grid tr:min-w-0 tr:grid-cols-1 tr:gap-tracs-4 tr:p-tracs-5 tr:lg:grid-cols-[360px_minmax(0,1fr)]">
            <fieldset className="tr:m-0 tr:flex tr:min-w-0 tr:flex-col tr:gap-tracs-4 tr:border-0 tr:p-0" disabled={generating}>
              <div className="tr:rounded-tracs-lg tr:border tr:border-tracs-warning/30 tr:bg-tracs-warning/5 tr:p-tracs-3 tr:text-xs tr:leading-5 tr:text-tracs-secondary">
                This phase generates preview data only. Writing, applying, saving, and copy actions are intentionally unavailable.
              </div>

              <Field error={errors.agent_id} label="Agent" name="agent_id" required>
                <select
                  aria-describedby={errors.agent_id ? 'agent_id-error' : undefined}
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

              <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:sm:grid-cols-2">
                <Field error={errors.start_date} label="Start date" name="start_date" required>
                  <input className={fieldClass} name="start_date" onChange={update} placeholder="dd-mm-yyyy" value={draft.start_date} />
                </Field>
                <Field error={errors.end_date} label="End date" name="end_date" required>
                  <input className={fieldClass} name="end_date" onChange={update} placeholder="dd-mm-yyyy" value={draft.end_date} />
                </Field>
              </div>

              <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:sm:grid-cols-2">
                <Field error={errors.day_of_week} label="Pattern day" name="day_of_week" required>
                  <select className={fieldClass} name="day_of_week" onChange={update} value={draft.day_of_week}>
                    <option value="1">Monday</option>
                    <option value="2">Tuesday</option>
                    <option value="3">Wednesday</option>
                    <option value="4">Thursday</option>
                    <option value="5">Friday</option>
                    <option value="6">Saturday</option>
                    <option value="7">Sunday</option>
                  </select>
                </Field>
                <Field error={errors.shift_preset} label="Shift preset" name="shift_preset" required>
                  <select className={fieldClass} name="shift_preset" onChange={selectPreset} value={draft.shift_preset}>
                    {(context?.shift_definitions ?? []).map((shift) => (
                      <option key={shift.key} value={shift.key}>
                        {shift.name} · {shift.display_range}
                      </option>
                    ))}
                  </select>
                </Field>
              </div>

              <Field error={errors.shift_type} label="Assignment type" name="shift_type" required>
                <select className={fieldClass} name="shift_type" onChange={update} value={draft.shift_type}>
                  {(filters.assignment_types ?? []).map((type) => (
                    <option key={type.slug} value={type.slug}>{type.name}</option>
                  ))}
                </select>
              </Field>

              <Field error={errors.shift_template_id} label="Template mapping (optional)" name="shift_template_id">
                <select className={fieldClass} name="shift_template_id" onChange={update} value={draft.shift_template_id}>
                  <option value="">No template mapping</option>
                  {(filters.shift_templates ?? []).filter((item) => item.is_active).map((template) => (
                    <option key={template.id} value={template.id}>
                      {template.name} · {template.start_time}-{template.end_time}
                    </option>
                  ))}
                </select>
              </Field>

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
                  <div className="tr:grid tr:grid-cols-2 tr:gap-tracs-2 tr:lg:grid-cols-5">
                    {[
                      ['Assignments', preview.summary?.total_assignments ?? 0],
                      ['Agents', preview.summary?.agents ?? 0],
                      ['Warnings', preview.summary?.warnings ?? 0],
                      ['Conflicts', preview.summary?.conflicts ?? 0],
                      ['Blocked', preview.summary?.blocked_items ?? 0],
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
                        <table className="tr:w-full tr:min-w-[560px] tr:text-left tr:text-xs">
                          <thead className="tr:text-[10px] tr:uppercase tr:text-tracs-muted">
                            <tr>
                              <th className="tr:p-2">Date</th>
                              <th className="tr:p-2">Agent</th>
                              <th className="tr:p-2">Shift</th>
                              <th className="tr:p-2">Type</th>
                              <th className="tr:p-2">Status</th>
                            </tr>
                          </thead>
                          <tbody>
                            {preview.items.map((item) => (
                              <tr className="tr:border-t tr:border-tracs-border" key={item.preview_id}>
                                <td className="tr:p-2">{item.assignment_date_display || item.assignment_date}</td>
                                <td className="tr:p-2">{item.agent?.name}</td>
                                <td className="tr:p-2">{item.shift?.display_range}</td>
                                <td className="tr:p-2">{item.type_name || item.type}</td>
                                <td className="tr:p-2">Preview only</td>
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
                <div className="tr:flex tr:min-h-64 tr:flex-col tr:items-center tr:justify-center tr:rounded-tracs-lg tr:border tr:border-dashed tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-5 tr:text-center">
                  <p className="tr:text-sm tr:font-semibold tr:text-tracs-primary">No preview generated yet</p>
                  <p className="tr:mt-1 tr:max-w-md tr:text-xs tr:leading-5 tr:text-tracs-muted">
                    Choose an agent, range, pattern day, and shift preset. The result will stay inside this modal and will not refresh or change the live schedule.
                  </p>
                </div>
              )}
            </div>
          </div>

          <footer className="tr:flex tr:flex-col tr:gap-tracs-2 tr:border-t tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-5 tr:py-tracs-4 tr:sm:flex-row tr:sm:items-center tr:sm:justify-end">
            <Button disabled={generating} onClick={requestClose} type="button" variant="quiet">
              Close
            </Button>
            <Button disabled={generating} type="submit" variant="primary">
              {generating ? 'Generating preview...' : 'Generate Preview'}
            </Button>
          </footer>
        </form>
      </section>
    </div>
  );
}
