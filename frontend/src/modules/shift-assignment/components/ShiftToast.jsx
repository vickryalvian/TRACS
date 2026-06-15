export function ShiftToast({ toast, onDismiss }) {
  if (!toast) {
    return null;
  }

  const tone = toast.type === 'success'
    ? 'tr:border-tracs-success tr:bg-tracs-success-soft tr:text-tracs-primary'
    : 'tr:border-tracs-danger tr:bg-tracs-danger-soft tr:text-tracs-primary';

  return (
    <div
      aria-live={toast.type === 'success' ? 'polite' : 'assertive'}
      className={`shift-create-toast tr:fixed tr:right-tracs-4 tr:top-tracs-4 tr:z-[70] tr:flex tr:max-w-[calc(100vw-2rem)] tr:items-start tr:gap-tracs-3 tr:rounded-tracs-lg tr:border tr:p-tracs-3 tr:shadow-tracs-modal ${tone}`}
      role={toast.type === 'success' ? 'status' : 'alert'}
    >
      <div className="tr:min-w-0 tr:flex-1">
        <strong className="tr:block tr:text-xs tr:font-semibold">
          {toast.title || (toast.type === 'success' ? 'Assignment saved' : 'Assignment action failed')}
        </strong>
        <span className="tr:mt-1 tr:block tr:text-xs tr:leading-5 tr:text-tracs-secondary">
          {toast.message}
        </span>
      </div>
      <button
        aria-label="Dismiss notification"
        className="tr:rounded-tracs tr:px-1 tr:text-sm tr:text-tracs-muted tr:hover:bg-tracs-card"
        onClick={onDismiss}
        type="button"
      >
        Close
      </button>
    </div>
  );
}
