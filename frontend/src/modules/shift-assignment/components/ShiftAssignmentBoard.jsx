import { Badge } from '../../../components/ui/Badge';
import { minutesLabel, statusTone } from '../utils/shiftFormatters';

export function ShiftAssignmentBoard({ assignments, canEdit = false, onEdit }) {
  return (
    <div className="tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:md:hidden">
      {assignments.map((assignment) => (
        <article
          className="tr:min-w-0 tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-3"
          key={assignment.id}
        >
          <div className="tr:flex tr:items-start tr:justify-between tr:gap-tracs-2">
            <div>
              <strong className="tr:block tr:break-words tr:text-sm tr:text-tracs-primary">
                {assignment.agent.name}
              </strong>
              <span className="tr:font-mono tr:text-[9px] tr:text-tracs-muted">
                {assignment.assignment_date_display} · {assignment.shift.display_range}
              </span>
            </div>
            <Badge variant={statusTone(assignment.status)}>
              {assignment.status.replaceAll('_', ' ')}
            </Badge>
          </div>
          {assignment.is_overtime || assignment.is_holiday ? (
            <div className="tr:mt-tracs-2 tr:flex tr:flex-wrap tr:gap-1">
              {assignment.is_overtime ? <Badge variant="warning">Overtime</Badge> : null}
              {assignment.is_holiday ? <Badge variant="info">Holiday</Badge> : null}
            </div>
          ) : null}
          <div className="tr:mt-tracs-3 tr:grid tr:grid-cols-2 tr:gap-tracs-2 tr:text-xs">
            <span className="tr:text-tracs-secondary">{assignment.shift.name}</span>
            <span className="tr:text-right tr:text-tracs-secondary">
              {minutesLabel(assignment.duration_minutes)}
            </span>
            <span className="tr:capitalize tr:text-tracs-muted">
              {assignment.type_name || assignment.type.replaceAll('_', ' ')}
            </span>
            <span className="tr:break-words tr:text-right tr:text-tracs-muted">
              {assignment.division.name}
            </span>
          </div>
          {canEdit ? (
            <button
              className="tr:mt-tracs-3 tr:w-full tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-tracs-3 tr:py-tracs-2 tr:text-xs tr:font-semibold tr:text-tracs-accent"
              onClick={() => onEdit?.(assignment)}
              type="button"
            >
              Edit Assignment
            </button>
          ) : null}
        </article>
      ))}
    </div>
  );
}
