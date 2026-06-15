import { Button } from '../../../components/ui/Button';

const fieldClass =
  'tr:min-h-9 tr:w-full tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-tracs-3 tr:text-xs tr:text-tracs-primary tr:outline-none tr:focus:border-tracs-accent tr:focus:ring-2 tr:focus:ring-tracs-accent-soft';

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

export function ShiftFilterBar({ context, filters, onChange, onReset }) {
  const options = context?.filters ?? {};

  return (
    <section className="tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-card tr:p-tracs-4 tr:shadow-tracs-card">
      <div className="tr:flex tr:flex-col tr:gap-tracs-3">
        <div className="tr:flex tr:flex-wrap tr:gap-tracs-3">
          <label className="tr:flex tr:min-w-40 tr:flex-1 tr:flex-col tr:gap-1">
            <span className="tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted">
              Start date
            </span>
            <input
              className={fieldClass}
              name="start_date"
              onChange={onChange}
              type="date"
              value={filters.start_date}
            />
          </label>
          <label className="tr:flex tr:min-w-40 tr:flex-1 tr:flex-col tr:gap-1">
            <span className="tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted">
              End date
            </span>
            <input
              className={fieldClass}
              name="end_date"
              onChange={onChange}
              type="date"
              value={filters.end_date}
            />
          </label>
          <SelectField label="Agent" name="agent_id" onChange={onChange} value={filters.agent_id}>
            <option value="">All scoped agents</option>
            {(options.agents ?? []).map((agent) => (
              <option key={agent.id} value={agent.id}>
                {agent.name}
              </option>
            ))}
          </SelectField>
          <SelectField label="Division" name="division" onChange={onChange} value={filters.division}>
            <option value="">All scoped divisions</option>
            {(options.divisions ?? []).map((division) => (
              <option key={division.id} value={division.id}>
                {division.name}
              </option>
            ))}
          </SelectField>
        </div>

        <div className="tr:flex tr:flex-wrap tr:items-end tr:gap-tracs-3">
          <SelectField label="Role" name="role" onChange={onChange} value={filters.role}>
            <option value="">All roles</option>
            {['super_admin', 'admin', 'supervisor', 'agent', 'intern', 'viewer'].map((role) => (
              <option key={role} value={role}>
                {role.replaceAll('_', ' ')}
              </option>
            ))}
          </SelectField>
          <SelectField label="Shift type" name="shift_type" onChange={onChange} value={filters.shift_type}>
            <option value="">All shift types</option>
            {(options.assignment_types ?? []).map((type) => (
              <option key={type.slug} value={type.slug}>
                {type.name}
              </option>
            ))}
          </SelectField>
          <SelectField label="Status" name="status" onChange={onChange} value={filters.status}>
            <option value="">All statuses</option>
            {(options.statuses ?? []).map((status) => (
              <option key={status} value={status}>
                {status.replaceAll('_', ' ')}
              </option>
            ))}
          </SelectField>
          <Button onClick={onReset} size="compact" variant="quiet">
            Reset filters
          </Button>
        </div>
      </div>
    </section>
  );
}
