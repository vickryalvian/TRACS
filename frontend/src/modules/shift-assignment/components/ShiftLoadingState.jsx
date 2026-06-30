import { LoadingState } from '../../../components/ui/LoadingState';

export function ShiftLoadingState({ label = 'Loading scoped shift assignments' }) {
  return (
    <div className="tr:flex tr:min-h-56 tr:flex-col tr:justify-center tr:px-tracs-4">
      <LoadingState className="tr:min-h-16" label={label} />
      <div aria-hidden="true" className="tr:grid tr:grid-cols-1 tr:gap-tracs-2 tr:sm:grid-cols-3">
        {[1, 2, 3].map((item) => (
          <span
            className="shift-loading-skeleton tr:block tr:h-12 tr:rounded-tracs tr:bg-tracs-surface-2"
            key={item}
          />
        ))}
      </div>
    </div>
  );
}
