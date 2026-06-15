import { Button } from '../../../components/ui/Button';
import { Card } from '../../../components/ui/Card';

function errorTitle(status) {
  if (status === 401) return 'Session expired';
  if (status === 403) return 'Permission denied';
  if (status === 422) return 'Check the selected filters';
  if (!status) return 'Network connection unavailable';
  return 'Shift Assignment could not load';
}

function errorGuidance(status) {
  if (status === 401) return 'Sign in again, then reopen this protected preview.';
  if (status === 403) return 'This account is not approved for the internal pilot.';
  if (status === 422) return 'Correct the date or filter values and retry.';
  if (!status) return 'Check the connection and retry. No schedule data was changed.';
  return 'Retry the read-only request. No schedule data was changed.';
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
      <p className="tr:mt-tracs-2 tr:text-xs tr:leading-5 tr:text-tracs-muted">
        {errorGuidance(error?.status)}
      </p>
      {fieldErrors.length ? (
        <ul className="tr:mt-tracs-3 tr:list-disc tr:space-y-1 tr:pl-tracs-5 tr:text-xs tr:text-tracs-secondary">
          {fieldErrors.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      ) : null}
      {error?.status === 401 ? (
        <a
          className="tr:mt-tracs-4 tr:inline-flex tr:min-h-8 tr:items-center tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-card tr:px-tracs-3 tr:text-xs tr:font-semibold tr:text-tracs-primary tr:focus-visible:outline-2 tr:focus-visible:outline-offset-2 tr:focus-visible:outline-tracs-accent"
          href="/login.php"
        >
          Return to login
        </a>
      ) : error?.status !== 403 ? (
        <Button className="tr:mt-tracs-4" onClick={onRetry} size="compact" variant="secondary">
          Try again
        </Button>
      ) : null}
    </Card>
  );
}
