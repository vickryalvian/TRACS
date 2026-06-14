import React from 'react';
import { LoaderCircle, Search } from 'lucide-react';

export function cx(...classes) {
  return classes.filter(Boolean).join(' ');
}

export function TracsButton({
  variant = 'ghost',
  size = 'default',
  icon: Icon,
  children,
  className = '',
  loading = false,
  ...props
}) {
  return (
    <button
      className={cx(
        'cal:inline-flex cal:items-center cal:justify-center cal:gap-1.5 cal:rounded-tracs cal:border cal:px-3 cal:font-medium cal:transition cal:focus-visible:outline-none cal:focus-visible:ring-2 cal:focus-visible:ring-tracs-accent cal:disabled:cursor-not-allowed cal:disabled:opacity-50',
        size === 'icon' ? 'cal:size-8 cal:p-0' : 'cal:min-h-8 cal:text-[11.5px]',
        variant === 'primary'
          ? 'cal:border-tracs-accent-border cal:bg-tracs-accent-soft cal:text-tracs-accent hover:cal:bg-tracs-accent-hover'
          : 'cal:border-tracs-border cal:bg-tracs-surface-2 cal:text-tracs-secondary hover:cal:border-tracs-border-strong hover:cal:bg-tracs-surface-3 hover:cal:text-tracs-primary',
        className,
      )}
      disabled={loading || props.disabled}
      {...props}
    >
      {loading ? <LoaderCircle className="cal:size-3.5 cal:animate-spin" /> : Icon ? <Icon className="cal:size-3.5" /> : null}
      {children}
    </button>
  );
}

export function TracsCard({ className = '', children, ...props }) {
  return (
    <section
      className={cx('cal:rounded-tracs-lg cal:border cal:border-tracs-border cal:bg-tracs-card cal:shadow-tracs', className)}
      {...props}
    >
      {children}
    </section>
  );
}

export const TracsInput = React.forwardRef(function TracsInput({ className = '', error, ...props }, ref) {
  return (
    <input
      ref={ref}
      className={cx(
        'cal:h-9 cal:w-full cal:rounded-tracs cal:border cal:bg-tracs-surface-2 cal:px-2.5 cal:text-xs cal:text-tracs-primary cal:outline-none cal:transition placeholder:cal:text-tracs-faint focus:cal:border-tracs-accent focus:cal:ring-2 focus:cal:ring-tracs-accent-soft',
        error ? 'cal:border-tracs-danger' : 'cal:border-tracs-border',
        className,
      )}
      {...props}
    />
  );
});

export function TracsSelect({ className = '', error, children, ...props }) {
  return (
    <select
      className={cx(
        'cal:h-9 cal:w-full cal:rounded-tracs cal:border cal:bg-tracs-surface-2 cal:px-2.5 cal:text-xs cal:text-tracs-primary cal:outline-none cal:transition focus:cal:border-tracs-accent focus:cal:ring-2 focus:cal:ring-tracs-accent-soft',
        error ? 'cal:border-tracs-danger' : 'cal:border-tracs-border',
        className,
      )}
      {...props}
    >
      {children}
    </select>
  );
}

export function TracsTextarea({ className = '', error, ...props }) {
  return (
    <textarea
      className={cx(
        'cal:min-h-20 cal:w-full cal:resize-y cal:rounded-tracs cal:border cal:bg-tracs-surface-2 cal:px-2.5 cal:py-2 cal:text-xs cal:text-tracs-primary cal:outline-none cal:transition placeholder:cal:text-tracs-faint focus:cal:border-tracs-accent focus:cal:ring-2 focus:cal:ring-tracs-accent-soft',
        error ? 'cal:border-tracs-danger' : 'cal:border-tracs-border',
        className,
      )}
      {...props}
    />
  );
}

export function SearchInput({ className = '', ...props }) {
  return (
    <label className={cx('cal:relative cal:block', className)}>
      <Search className="cal:pointer-events-none cal:absolute cal:left-2.5 cal:top-1/2 cal:size-3.5 cal:-translate-y-1/2 cal:text-tracs-muted" />
      <TracsInput className="cal:pl-8" type="search" {...props} />
    </label>
  );
}

export function Field({ label, error, hint, children }) {
  return (
    <label className="cal:flex cal:min-w-0 cal:flex-col cal:gap-1">
      <span className="cal:font-mono cal:text-[8.5px] cal:font-bold cal:uppercase cal:tracking-[.1em] cal:text-tracs-muted">{label}</span>
      {children}
      {error ? <span className="cal:text-[10px] cal:text-tracs-danger">{error}</span> : null}
      {!error && hint ? <span className="cal:text-[10px] cal:text-tracs-muted">{hint}</span> : null}
    </label>
  );
}
