import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';

function errorTitle(status) {
  if (status === 401) return 'Session expired';
  if (status === 403) return 'Permission denied';
  if (status === 422) return 'Check the selected filters';
  return 'Shift Assignment could not load';
}

export function ShiftErrorState({ error, onRetry }) {
  const fieldErrors =
    error?.errors && !Array.isArray(error.errors) ? Object.values(error.errors) : error?.errors ?? [];

  return (
    <Card className="tr:border-tracs-danger-border tr:bg-tracs-danger-soft">
      <strong className="tr:text-sm tr:text-tracs-danger">{errorTitle(error?.status)}</strong>
      <p className="tr:mt-tracs-2 tr:text-sm tr:text-tracs-secondary">
        {error?.message || 'The server did not return a usable response.'}
      </p>
      {fieldErrors.length ? (
        <ul className="tr:mt-tracs-3 tr:list-disc tr:space-y-1 tr:pl-tracs-5 tr:text-xs tr:text-tracs-secondary">
          {fieldErrors.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      ) : null}
      {error?.status !== 403 ? (
        <Button className="tr:mt-tracs-4" onClick={onRetry} size="compact" variant="secondary">
          Try again
        </Button>
      ) : null}
    </Card>
  );
}
