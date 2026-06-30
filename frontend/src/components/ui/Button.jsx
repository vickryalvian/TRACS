import { classNames } from '../../lib/classNames';

const variants = {
  primary:
    'tr:border-tracs-accent tr:bg-tracs-accent tr:text-white tr:hover:bg-tracs-accent-strong',
  secondary:
    'tr:border-tracs-border tr:bg-tracs-card tr:text-tracs-primary tr:hover:bg-tracs-surface-2',
  danger:
    'tr:border-tracs-danger tr:bg-tracs-danger tr:text-white tr:hover:opacity-90',
  quiet:
    'tr:border-transparent tr:bg-transparent tr:text-tracs-secondary tr:hover:bg-tracs-surface-2 tr:hover:text-tracs-primary',
};

const sizes = {
  compact: 'tr:min-h-8 tr:px-tracs-3 tr:py-tracs-1 tr:text-xs',
  default: 'tr:min-h-9 tr:px-tracs-4 tr:py-tracs-2 tr:text-sm',
};

export function Button({
  children,
  className,
  size = 'default',
  type = 'button',
  variant = 'secondary',
  ...props
}) {
  return (
    <button
      className={classNames(
        'tr:inline-flex tr:items-center tr:justify-center tr:gap-tracs-2 tr:rounded-tracs tr:border tr:font-semibold tr:transition-colors tr:duration-150 tr:focus-visible:outline-2 tr:focus-visible:outline-offset-2 tr:focus-visible:outline-tracs-accent tr:disabled:cursor-not-allowed tr:disabled:opacity-55',
        variants[variant] ?? variants.secondary,
        sizes[size] ?? sizes.default,
        className,
      )}
      type={type}
      {...props}
    >
      {children}
    </button>
  );
}
