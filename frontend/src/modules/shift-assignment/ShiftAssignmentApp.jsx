import { useMemo, useState } from 'react';
import { Card } from '../../components/ui/Card';
import { ShiftAssignmentBoard } from './components/ShiftAssignmentBoard';
import { ShiftAssignmentTable } from './components/ShiftAssignmentTable';
import { ShiftEmptyState } from './components/ShiftEmptyState';
import { ShiftErrorState } from './components/ShiftErrorState';
import { ShiftFilterBar } from './components/ShiftFilterBar';
import { ShiftLoadingState } from './components/ShiftLoadingState';
import { ShiftSummaryCards } from './components/ShiftSummaryCards';
import { ShiftToolbar } from './components/ShiftToolbar';
import { ShiftWarnings } from './components/ShiftWarnings';
import { useShiftAssignmentContext } from './hooks/useShiftAssignmentContext';
import { useShiftAssignments } from './hooks/useShiftAssignments';
import { rangeForView, shiftRange } from './utils/shiftDates';

const initialRange = rangeForView('weekly');

const initialFilters = {
  view: 'weekly',
  ...initialRange,
  agent_id: '',
  role: '',
  division: '',
  shift_type: '',
  status: '',
};

export function ShiftAssignmentApp() {
  const [filters, setFilters] = useState(initialFilters);
  const context = useShiftAssignmentContext();
  const requestFilters = useMemo(() => filters, [filters]);
  const assignments = useShiftAssignments(requestFilters, Boolean(context.shift));

  function updateFilter(event) {
    const { name, value } = event.target;
    setFilters((current) => ({ ...current, [name]: value }));
  }

  function changeView(view) {
    setFilters((current) => ({
      ...current,
      view,
      ...rangeForView(view, current.start_date),
    }));
  }

  function moveRange(direction) {
    setFilters((current) => ({
      ...current,
      ...shiftRange(current, current.view, direction),
    }));
  }

  function useToday() {
    setFilters((current) => ({
      ...current,
      ...rangeForView(current.view),
    }));
  }

  function resetFilters() {
    setFilters((current) => ({
      ...initialFilters,
      view: current.view,
      ...rangeForView(current.view),
    }));
  }

  const theme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';

  return (
    <main
      className="tracs-react-root shift-assignment-react-shell tr:min-h-full tr:text-tracs-primary"
      data-theme={theme}
    >
      <div className="tr:flex tr:flex-col tr:gap-tracs-4 tr:pb-tracs-6">
        <ShiftToolbar
          filters={filters}
          onMove={moveRange}
          onToday={useToday}
          onViewChange={changeView}
        />

        {context.loading ? (
          <ShiftLoadingState label="Loading Shift Assignment permissions and filters" />
        ) : context.error ? (
          <ShiftErrorState error={context.error} onRetry={context.retry} />
        ) : (
          <>
            <ShiftSummaryCards summary={assignments.data?.summary} />
            <ShiftFilterBar
              context={context.shift}
              filters={filters}
              onChange={updateFilter}
              onReset={resetFilters}
            />

            <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-4 tr:xl:grid-cols-[minmax(0,1fr)_340px]">
              <Card className="tr:min-w-0 tr:p-0">
                <div className="tr:flex tr:items-center tr:justify-between tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-4 tr:py-tracs-3">
                  <div>
                    <h2 className="tr:text-sm tr:font-semibold tr:text-tracs-primary">
                      {filters.view[0].toUpperCase() + filters.view.slice(1)} assignments
                    </h2>
                    <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">
                      Read-only pilot · {context.global?.user?.name || 'Authenticated user'}
                    </p>
                  </div>
                  <span className="tr:font-mono tr:text-[9px] tr:text-tracs-muted">
                    {assignments.data?.summary?.assignment_count ?? 0} records
                  </span>
                </div>

                {assignments.loading ? (
                  <ShiftLoadingState />
                ) : assignments.error ? (
                  <div className="tr:p-tracs-4">
                    <ShiftErrorState error={assignments.error} onRetry={assignments.retry} />
                  </div>
                ) : assignments.data?.assignments?.length ? (
                  <>
                    <ShiftAssignmentTable assignments={assignments.data.assignments} />
                    <div className="tr:p-tracs-3 tr:md:hidden">
                      <ShiftAssignmentBoard assignments={assignments.data.assignments} />
                    </div>
                  </>
                ) : (
                  <div className="tr:p-tracs-4">
                    <ShiftEmptyState />
                  </div>
                )}
              </Card>

              <ShiftWarnings warnings={assignments.data?.warnings ?? []} />
            </div>
          </>
        )}
      </div>
    </main>
  );
}
