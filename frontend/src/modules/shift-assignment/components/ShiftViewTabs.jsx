const views = [
  ['daily', 'Daily'],
  ['weekly', 'Weekly'],
  ['monthly', 'Monthly'],
];

export function ShiftViewTabs({ onChange, value }) {
  return (
    <div
      aria-label="Schedule view"
      className="tr:inline-flex tr:rounded-tracs tr:border tr:border-tracs-border tr:bg-tracs-surface-2 tr:p-1"
      role="tablist"
    >
      {views.map(([key, label]) => (
        <button
          aria-selected={value === key}
          className={`tr:min-h-8 tr:rounded-tracs-sm tr:px-tracs-3 tr:text-xs tr:font-semibold tr:transition ${
            value === key
              ? 'tr:bg-tracs-accent tr:text-white'
              : 'tr:text-tracs-secondary tr:hover:bg-tracs-surface-3 tr:hover:text-tracs-primary'
          }`}
          key={key}
          onClick={() => onChange(key)}
          role="tab"
          type="button"
        >
          {label}
        </button>
      ))}
    </div>
  );
}
