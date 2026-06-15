import { Button } from '../../../components/ui/Button';
import { displayRange } from '../utils/shiftDates';
import { ShiftViewTabs } from './ShiftViewTabs';

export function ShiftToolbar({ canCreate, filters, onCreate, onMove, onToday, onViewChange }) {
  return (
    <header className="tr:overflow-hidden tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-card tr:shadow-tracs-card">
      <div className="tr:flex tr:flex-col tr:gap-tracs-3 tr:p-tracs-4 tr:xl:flex-row tr:xl:items-center tr:xl:justify-between">
        <div>
          <p className="tr:font-mono tr:text-[9px] tr:font-bold tr:uppercase tr:tracking-[.1em] tr:text-tracs-accent">
            React pilot · controlled create
          </p>
          <h1 className="tr:mt-tracs-1 tr:text-xl tr:font-semibold tr:tracking-[-.02em] tr:text-tracs-primary">
            Shift Assignment
          </h1>
          <p className="tr:mt-tracs-1 tr:text-sm tr:text-tracs-secondary">
            Scoped schedules, workload, holidays, and warnings from the protected PHP APIs.
          </p>
        </div>

        <div className="tr:flex tr:min-w-0 tr:flex-wrap tr:items-center tr:gap-tracs-2">
          {canCreate ? (
            <Button onClick={onCreate} size="compact" variant="primary">
              Add Assignment
            </Button>
          ) : null}
          <ShiftViewTabs onChange={onViewChange} value={filters.view} />
          <Button onClick={() => onMove(-1)} size="compact" variant="quiet">
            Previous
          </Button>
          <Button onClick={onToday} size="compact" variant="secondary">
            Today
          </Button>
          <Button onClick={() => onMove(1)} size="compact" variant="quiet">
            Next
          </Button>
        </div>
      </div>

      <div className="tr:flex tr:flex-wrap tr:items-center tr:justify-between tr:gap-tracs-2 tr:border-t tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-4 tr:py-tracs-2">
        <strong className="tr:text-xs tr:text-tracs-primary">{displayRange(filters)}</strong>
        <span className="tr:font-mono tr:text-[9px] tr:text-tracs-muted">
          Asia/Jakarta · UI dates dd-mm-yyyy
        </span>
      </div>
    </header>
  );
}
