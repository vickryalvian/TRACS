import { classNames } from '../../lib/classNames';

export function EmptyState({ action, className, description, title = 'No results' }) {
  return (
    <div
      className={classNames(
        'tr:flex tr:min-h-40 tr:flex-col tr:items-center tr:justify-center tr:gap-tracs-2 tr:rounded-tracs tr:border tr:border-dashed tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-tracs-6 tr:text-center',
        className,
      )}
      role="status"
    >
      <strong className="tr:text-sm tr:text-tracs-primary">{title}</strong>
      {description ? (
        <p className="tr:max-w-md tr:text-sm tr:leading-6 tr:text-tracs-muted">{description}</p>
      ) : null}
      {action}
    </div>
  );
}
