import { Badge } from '../../../components/ui/Badge';
import { minutesLabel, statusTone } from '../utils/shiftFormatters';

export function ShiftAssignmentTable({ assignments }) {
  return (
    <div className="tr:hidden tr:overflow-x-auto tr:md:block">
      <table className="tr:w-full tr:min-w-[900px] tr:border-collapse tr:text-left">
        <thead className="tr:bg-tracs-surface-2">
          <tr className="tr:border-b tr:border-tracs-border">
            {['Date', 'Agent', 'Division', 'Shift', 'Type', 'Duration', 'Status'].map((label) => (
              <th
                className="tr:px-tracs-3 tr:py-tracs-2 tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted"
                key={label}
              >
                {label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {assignments.map((assignment) => (
            <tr
              className="tr:border-b tr:border-tracs-border tr:last:border-b-0 tr:hover:bg-tracs-surface-2"
              key={assignment.id}
            >
              <td className="tr:px-tracs-3 tr:py-tracs-3 tr:font-mono tr:text-[10px] tr:text-tracs-secondary">
                {assignment.assignment_date_display}
              </td>
              <td className="tr:px-tracs-3 tr:py-tracs-3 tr:text-xs tr:font-semibold tr:text-tracs-primary">
                {assignment.agent.name}
              </td>
              <td className="tr:px-tracs-3 tr:py-tracs-3 tr:text-xs tr:text-tracs-secondary">
                {assignment.division.name}
              </td>
              <td className="tr:px-tracs-3 tr:py-tracs-3">
                <strong className="tr:block tr:text-xs tr:text-tracs-primary">
                  {assignment.shift.name}
                </strong>
                <span className="tr:font-mono tr:text-[9px] tr:text-tracs-muted">
                  {assignment.shift.display_range}
                </span>
              </td>
              <td className="tr:px-tracs-3 tr:py-tracs-3 tr:text-xs tr:capitalize tr:text-tracs-secondary">
                {assignment.type_name || assignment.type.replaceAll('_', ' ')}
              </td>
              <td className="tr:px-tracs-3 tr:py-tracs-3 tr:text-xs tr:text-tracs-secondary">
                {minutesLabel(assignment.duration_minutes)}
              </td>
              <td className="tr:px-tracs-3 tr:py-tracs-3">
                <Badge variant={statusTone(assignment.status)}>
                  {assignment.status.replaceAll('_', ' ')}
                </Badge>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
