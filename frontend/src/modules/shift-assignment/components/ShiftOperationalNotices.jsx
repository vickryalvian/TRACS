import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';

export function ShiftOperationalNotices({ assignments, holidays }) {
  const overtime = assignments.filter((assignment) => assignment.is_overtime);
  const holidayAssignments = assignments.filter((assignment) => assignment.is_holiday);

  return (
    <Card className="tr:min-w-0 tr:p-0">
      <div className="tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-4 tr:py-tracs-3">
        <h2 className="tr:text-sm tr:font-semibold tr:text-tracs-primary">
          Holiday and overtime
        </h2>
        <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">
          Read-only indicators returned by the protected schedule API.
        </p>
      </div>

      <div className="tr:flex tr:flex-col tr:gap-tracs-3 tr:p-tracs-4">
        <div className="tr:flex tr:flex-wrap tr:gap-tracs-2">
          <Badge variant={overtime.length ? 'warning' : 'neutral'}>
            {overtime.length} overtime
          </Badge>
          <Badge variant={holidayAssignments.length ? 'info' : 'neutral'}>
            {holidayAssignments.length} holiday assignments
          </Badge>
          <Badge variant={holidays.length ? 'danger' : 'neutral'}>
            {holidays.length} holiday notices
          </Badge>
        </div>

        {holidays.length ? (
          <ul className="tr:flex tr:flex-col tr:gap-tracs-2" aria-label="Holiday notices">
            {holidays.slice(0, 4).map((holiday) => (
              <li
                className="tr:min-w-0 tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-3 tr:py-tracs-2"
                key={`${holiday.id}-${holiday.date}`}
              >
                <strong className="tr:block tr:break-words tr:text-xs tr:text-tracs-primary">
                  {holiday.name}
                </strong>
                <span className="tr:mt-1 tr:block tr:font-mono tr:text-[9px] tr:text-tracs-muted">
                  {holiday.date_display}
                </span>
              </li>
            ))}
          </ul>
        ) : (
          <p className="tr:text-xs tr:leading-5 tr:text-tracs-muted">
            No holiday notices in this scoped range.
          </p>
        )}
      </div>
    </Card>
  );
}
