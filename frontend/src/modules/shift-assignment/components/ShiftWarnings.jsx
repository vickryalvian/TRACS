import { Badge } from '../../../components/ui/Badge';
import { Card } from '../../../components/ui/Card';

const warningTone = {
  jumpshift: 'warning',
  conflict: 'danger',
  coverage_gap: 'info',
};

export function ShiftWarnings({ warnings }) {
  return (
    <Card className="tr:min-w-0 tr:p-0">
      <div className="tr:flex tr:items-center tr:justify-between tr:border-b tr:border-tracs-border tr:bg-tracs-surface-2 tr:px-tracs-4 tr:py-tracs-3">
        <div>
          <h2 className="tr:text-sm tr:font-semibold tr:text-tracs-primary">Schedule warnings</h2>
          <p className="tr:mt-1 tr:text-xs tr:text-tracs-muted">
            Existing PHP conflict, rest, and coverage calculations.
          </p>
        </div>
        <Badge variant={warnings.length ? 'warning' : 'success'}>
          {warnings.length || 'Clear'}
        </Badge>
      </div>
      {warnings.length ? (
        <div className="tr:divide-y tr:divide-tracs-border">
          {warnings.map((warning, index) => (
            <div className="tr:flex tr:min-w-0 tr:items-start tr:gap-tracs-3 tr:px-tracs-4 tr:py-tracs-3" key={`${warning.type}-${index}`}>
              <Badge variant={warningTone[warning.type] ?? 'neutral'}>
                {warning.type.replaceAll('_', ' ')}
              </Badge>
              <p className="tr:min-w-0 tr:break-words tr:text-xs tr:leading-5 tr:text-tracs-secondary">
                {warning.message}
              </p>
            </div>
          ))}
        </div>
      ) : (
        <p className="tr:px-tracs-4 tr:py-tracs-5 tr:text-sm tr:text-tracs-muted">
          No scoped warnings for this range.
        </p>
      )}
    </Card>
  );
}
