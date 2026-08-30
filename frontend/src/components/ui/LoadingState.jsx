import { classNames } from '../../lib/classNames';

export function LoadingState({ className, label = 'Loading' }) {
  return (
    <div
      aria-live="polite"
      aria-busy="true"
      className={classNames(
        'tr:flex tr:min-h-24 tr:items-center tr:justify-center tr:gap-tracs-3 tr:text-sm tr:text-tracs-secondary',
        className,
      )}
      role="status"
    >
      <span
        aria-hidden="true"
        className="tr:size-4 tr:animate-spin tr:rounded-full tr:border-2 tr:border-tracs-border tr:border-t-tracs-accent"
      />
      <span>{label}</span>
    </div>
  );
}
