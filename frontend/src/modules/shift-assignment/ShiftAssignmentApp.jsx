import { useMemo, useState } from 'react';
import { Card } from '../../components/ui/Card';
import { ShiftAssignmentBoard } from './components/ShiftAssignmentBoard';
import { ShiftAssignmentTable } from './components/ShiftAssignmentTable';
import { ShiftCreateModal } from './components/ShiftCreateModal';
import { ShiftEditModal } from './components/ShiftEditModal';
import { ShiftEmptyState } from './components/ShiftEmptyState';
import { ShiftErrorState } from './components/ShiftErrorState';
import { ShiftFilterBar } from './components/ShiftFilterBar';
import { ShiftLoadingState } from './components/ShiftLoadingState';
import { ShiftOperationalNotices } from './components/ShiftOperationalNotices';
import { ShiftSummaryCards } from './components/ShiftSummaryCards';
import { ShiftToolbar } from './components/ShiftToolbar';
import { ShiftToast } from './components/ShiftToast';
import { ShiftWarnings } from './components/ShiftWarnings';
import { useShiftAssignmentContext } from './hooks/useShiftAssignmentContext';
import { useShiftAssignments } from './hooks/useShiftAssignments';
import { rangeForView, shiftRange } from './utils/shiftDates';
import { createdAssignmentMatchesFilters } from './utils/shiftCreate';
import { updatedAssignmentMatchesFilters } from './utils/shiftEdit';

const initialRange = rangeForView('weekly');

const initialFilters = {
  view: 'weekly',
  ...initialRange,
  agent_id: '',
  division: '',
  shift_type: '',
  status: '',
};

export function ShiftAssignmentApp() {
  const [filters, setFilters] = useState(initialFilters);
  const [createOpen, setCreateOpen] = useState(false);
  const [editingAssignment, setEditingAssignment] = useState(null);
  const [toast, setToast] = useState(null);
  const context = useShiftAssignmentContext();
  const requestFilters = useMemo(() => filters, [filters]);
  const assignments = useShiftAssignments(requestFilters, Boolean(context.shift));
  const canCreate = Boolean(context.shift?.allowed_actions?.create_assignment);
  const canEdit = Boolean(context.shift?.allowed_actions?.update_assignment);

  function applyFilters(nextFilters) {
    setFilters(nextFilters);
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

  async function handleCreated(assignment, message) {
    await assignments.refresh();
    const visible = createdAssignmentMatchesFilters(assignment, filters);
    setToast({
      type: 'success',
      title: 'Assignment created',
      message: visible
        ? message || 'The assignment is now visible in the current schedule.'
        : `${message || 'Shift assignment created.'} It may be outside the current filters or date range.`,
    });
  }

  async function handleUpdated(assignment, message) {
    await assignments.refresh();
    const visible = updatedAssignmentMatchesFilters(assignment, filters);
    setToast({
      type: 'success',
      title: 'Assignment updated',
      message: visible
        ? message || 'The updated assignment is visible in the current schedule.'
        : `${message || 'Shift assignment updated.'} It may now be outside the current filters or date range.`,
    });
  }

  const theme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';

  return (
    <main
      className="tracs-react-root shift-assignment-react-shell tr:min-h-full tr:text-tracs-primary"
      data-theme={theme}
    >
      <div className="tr:flex tr:flex-col tr:gap-tracs-4 tr:pb-tracs-6">
        <ShiftToolbar
          canCreate={canCreate}
          filters={filters}
          onCreate={() => setCreateOpen(true)}
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
            <ShiftSummaryCards loading={assignments.loading} summary={assignments.data?.summary} />
            <ShiftFilterBar
              context={context.shift}
              filters={filters}
              onApply={applyFilters}
              onReset={resetFilters}
            />

            <div className="tr:grid tr:min-w-0 tr:grid-cols-1 tr:gap-tracs-4 tr:xl:grid-cols-[minmax(0,1fr)_320px]">
              <Card className="tr:min-w-0 tr:p-0">
                <div className="tr:flex tr:items-center tr:justify-between tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-4 tr:py-tracs-3">
                  <div>
                    <h2 className="tr:text-sm tr:font-semibold tr:text-tracs-primary">
                      {filters.view[0].toUpperCase() + filters.view.slice(1)} assignments
                    </h2>
                    <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">
                      Controlled create/edit pilot · {context.global?.user?.name || 'Authenticated user'}
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
                    <ShiftAssignmentTable
                      assignments={assignments.data.assignments}
                      canEdit={canEdit}
                      onEdit={setEditingAssignment}
                    />
                    <div className="tr:p-tracs-3 tr:md:hidden">
                      <ShiftAssignmentBoard
                        assignments={assignments.data.assignments}
                        canEdit={canEdit}
                        onEdit={setEditingAssignment}
                      />
                    </div>
                  </>
                ) : (
                  <div className="tr:p-tracs-4">
                    <ShiftEmptyState />
                  </div>
                )}
              </Card>

              <div className="tr:flex tr:min-w-0 tr:flex-col tr:gap-tracs-4">
                {assignments.loading ? (
                  <Card className="tr:p-tracs-3">
                    <ShiftLoadingState label="Loading warnings and operational notices" />
                  </Card>
                ) : assignments.error ? (
                  <Card className="tr:p-tracs-4">
                    <p className="tr:text-xs tr:leading-5 tr:text-tracs-muted">
                      Warnings and holiday notices are unavailable until the read-only
                      schedule request succeeds.
                    </p>
                  </Card>
                ) : (
                  <>
                    <ShiftWarnings warnings={assignments.data?.warnings ?? []} />
                    <ShiftOperationalNotices
                      assignments={assignments.data?.assignments ?? []}
                      holidays={assignments.data?.holidays ?? []}
                    />
                  </>
                )}
              </div>
            </div>
          </>
        )}
      </div>
      <ShiftCreateModal
        context={context.shift}
        onClose={() => setCreateOpen(false)}
        onCreated={handleCreated}
        onToast={setToast}
        open={createOpen && canCreate}
      />
      <ShiftEditModal
        assignment={editingAssignment}
        context={context.shift}
        onClose={() => setEditingAssignment(null)}
        onToast={setToast}
        onUpdated={handleUpdated}
        open={Boolean(editingAssignment) && canEdit}
      />
      <ShiftToast onDismiss={() => setToast(null)} toast={toast} />
    </main>
  );
}
