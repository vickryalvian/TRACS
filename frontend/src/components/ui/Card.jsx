import { classNames } from '../../lib/classNames';

export function Card({ as: Component = 'section', children, className, ...props }) {
  return (
    <Component
      className={classNames(
        'tr:rounded-tracs-lg tr:border tr:border-tracs-border tr:bg-tracs-card tr:p-tracs-5 tr:shadow-tracs-card',
        className,
      )}
      {...props}
    >
      {children}
    </Component>
  );
}
