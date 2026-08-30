import { classNames } from '../../lib/classNames';

const variants = {
  neutral: 'tr:border-tracs-border tr:bg-tracs-surface-2 tr:text-tracs-secondary',
  info: 'tr:border-tracs-info-border tr:bg-tracs-info-soft tr:text-tracs-info',
  success: 'tr:border-tracs-success-border tr:bg-tracs-success-soft tr:text-tracs-success',
  warning: 'tr:border-tracs-warning-border tr:bg-tracs-warning-soft tr:text-tracs-warning',
  danger: 'tr:border-tracs-danger-border tr:bg-tracs-danger-soft tr:text-tracs-danger',
};

export function Badge({ children, className, variant = 'neutral', ...props }) {
  return (
    <span
      className={classNames(
        'tr:inline-flex tr:min-h-6 tr:items-center tr:rounded-full tr:border tr:px-tracs-2 tr:py-0.5 tr:text-xs tr:font-semibold',
        variants[variant] ?? variants.neutral,
        className,
      )}
      {...props}
    >
      {children}
    </span>
  );
}
