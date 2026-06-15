import { useEffect, useState } from 'react';
import { Button } from '../../../components/ui/Button';
import { filterDraft, filterQuery } from '../utils/shiftDates';

const fieldClass =
  'tr:min-h-9 tr:w-full tr:min-w-0 tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-tracs-3 tr:text-xs tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-accent tr:focus:ring-2 tr:focus:ring-tracs-accent-soft';

function SelectField({ children, label, name, onChange, value }) {
  return (
    <label className="tr:flex tr:min-w-36 tr:flex-1 tr:flex-col tr:gap-1">
      <span className="tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted">
        {label}
      </span>
      <select className={fieldClass} name={name} onChange={onChange} value={value}>
        {children}
      </select>
    </label>
  );
}

export function ShiftFilterBar({ context, filters, onApply, onReset }) {
  const options = context?.filters ?? {};
  const [draft, setDraft] = useState(() => filterDraft(filters));
  const [errors, setErrors] = useState({});

  useEffect(() => {
    setDraft(filterDraft(filters));
    setErrors({});
  }, [filters]);

  function updateDraft(event) {
    const { name, value } = event.target;
    setDraft((current) => ({ ...current, [name]: value }));
  }

  function apply(event) {
    event.preventDefault();
    const result = filterQuery(draft);
    setErrors(result.errors);
    if (result.filters) {
      onApply(result.filters);
    }
  }

  function reset() {
    setErrors({});
    onReset();
  }

  return (
    <form
      className="tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-card tr:p-tracs-4 tr:shadow-tracs-card"
      onSubmit={apply}
    >
      <div className="tr:flex tr:flex-col tr:gap-tracs-3">
        <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-3 tr:sm:grid-cols-2 tr:xl:grid-cols-4">
          <label className="tr:flex tr:min-w-0 tr:flex-col tr:gap-1">
            <span className="tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted">
              Start date
            </span>
            <input
              aria-describedby={errors.start_date ? 'shift-start-date-error' : undefined}
              aria-invalid={Boolean(errors.start_date)}
              className={fieldClass}
              inputMode="numeric"
              name="start_date"
              onChange={updateDraft}
              placeholder="dd-mm-yyyy"
              autoComplete="off"
              type="text"
              value={draft.start_date}
            />
            {errors.start_date ? (
              <span className="tr:text-[10px] tr:text-tracs-danger" id="shift-start-date-error">
                {errors.start_date}
              </span>
            ) : null}
          </label>
          <label className="tr:flex tr:min-w-0 tr:flex-col tr:gap-1">
            <span className="tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted">
              End date
            </span>
            <input
              aria-describedby={errors.end_date ? 'shift-end-date-error' : undefined}
              aria-invalid={Boolean(errors.end_date)}
              className={fieldClass}
              inputMode="numeric"
              name="end_date"
              onChange={updateDraft}
              placeholder="dd-mm-yyyy"
              autoComplete="off"
              type="text"
              value={draft.end_date}
            />
            {errors.end_date ? (
              <span className="tr:text-[10px] tr:text-tracs-danger" id="shift-end-date-error">
                {errors.end_date}
              </span>
            ) : null}
          </label>
          <SelectField label="Agent" name="agent_id" onChange={updateDraft} value={draft.agent_id}>
            <option value="">All scoped agents</option>
            {(options.agents ?? []).map((agent) => (
              <option key={agent.id} value={agent.id}>
                {agent.name}
              </option>
            ))}
          </SelectField>
          <SelectField label="Division" name="division" onChange={updateDraft} value={draft.division}>
            <option value="">All scoped divisions</option>
            {(options.divisions ?? []).map((division) => (
              <option key={division.id} value={division.id}>
                {division.name}
              </option>
            ))}
          </SelectField>
        </div>

        <div className="tr:flex tr:flex-col tr:gap-tracs-3 tr:lg:flex-row tr:lg:items-end">
          <div className="tr:grid tr:min-w-0 tr:flex-1 tr:grid-cols-1 tr:gap-tracs-3 tr:sm:grid-cols-2">
            <SelectField label="Shift type" name="shift_type" onChange={updateDraft} value={draft.shift_type}>
              <option value="">All shift types</option>
              {(options.assignment_types ?? []).map((type) => (
                <option key={type.slug} value={type.slug}>
                  {type.name}
                </option>
              ))}
            </SelectField>
            <SelectField label="Status" name="status" onChange={updateDraft} value={draft.status}>
              <option value="">All statuses</option>
              {(options.statuses ?? []).map((status) => (
                <option key={status} value={status}>
                  {status.replaceAll('_', ' ')}
                </option>
              ))}
            </SelectField>
          </div>
          <div className="tr:flex tr:shrink-0 tr:flex-wrap tr:items-center tr:justify-end tr:gap-tracs-2">
            <span className="tr:mr-auto tr:font-mono tr:text-[9px] tr:text-tracs-muted tr:lg:mr-0">
              Applied on request
            </span>
            <Button onClick={reset} size="compact" variant="quiet">
              Reset
            </Button>
            <Button size="compact" type="submit" variant="primary">
              Apply filters
            </Button>
          </div>
        </div>
      </div>
    </form>
  );
}
