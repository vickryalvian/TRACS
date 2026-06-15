import { Card } from '../../../components/ui/Card';
import { minutesLabel, summaryValue } from '../utils/shiftFormatters';

export function ShiftSummaryCards({ summary }) {
  const cards = [
    ['Assignments', summaryValue(summary?.assignment_count), 'Visible in this range'],
    ['Scheduled agents', summaryValue(summary?.agent_count), 'Unique scoped agents'],
    ['Scheduled hours', minutesLabel(summary?.total_minutes), 'Before workload thresholds'],
    [
      'Need attention',
      summaryValue(
        (summary?.overtime_assignment_count ?? 0) + (summary?.holiday_assignment_count ?? 0),
      ),
      'Overtime and holiday assignments',
    ],
  ];

  return (
    <div className="tr:grid tr:grid-cols-2 tr:gap-tracs-2 tr:lg:grid-cols-4">
      {cards.map(([label, value, detail]) => (
        <Card className="tr:p-tracs-3" key={label}>
          <strong className="tr:block tr:text-base tr:text-tracs-primary">{value}</strong>
          <span className="tr:mt-1 tr:block tr:font-mono tr:text-[8px] tr:font-bold tr:uppercase tr:tracking-[.08em] tr:text-tracs-muted">
            {label}
          </span>
          <p className="tr:mt-tracs-2 tr:text-xs tr:text-tracs-secondary">{detail}</p>
        </Card>
      ))}
    </div>
  );
}
