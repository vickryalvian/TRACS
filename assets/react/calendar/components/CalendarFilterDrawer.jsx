import React, { useEffect } from 'react';
import { RotateCcw, X } from 'lucide-react';
import { FilterFields } from './CalendarToolbar';
import { TracsButton } from './CalendarPrimitives';

export function CalendarFilterDrawer({ open, onClose, filters, setFilter, metadata, onReset }) {
  useEffect(() => {
    if (!open) return undefined;
    const close = (event) => event.key === 'Escape' && onClose();
    window.addEventListener('keydown', close);
    return () => window.removeEventListener('keydown', close);
  }, [open, onClose]);
  if (!open) return null;
  return (
    <div className="cal:fixed cal:inset-0 cal:z-[12000] cal:bg-black/70" role="presentation" onMouseDown={onClose}>
      <aside
        className="cal:absolute cal:inset-y-0 cal:right-0 cal:flex cal:w-[min(92vw,380px)] cal:flex-col cal:border-l cal:border-tracs-border-strong cal:bg-tracs-card cal:shadow-tracs-lg"
        role="dialog"
        aria-modal="true"
        aria-label="Calendar filters"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <div className="cal:flex cal:items-center cal:justify-between cal:border-b cal:border-tracs-border cal:px-4 cal:py-3">
          <div>
            <h2 className="cal:text-sm cal:font-semibold cal:text-tracs-primary">Calendar Filters</h2>
            <p className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">Refine operational schedules</p>
          </div>
          <TracsButton size="icon" icon={X} onClick={onClose} aria-label="Close filters" />
        </div>
        <div className="cal:flex cal:flex-1 cal:flex-col cal:gap-3 cal:overflow-y-auto cal:p-4">
          <FilterFields compact filters={filters} setFilter={setFilter} metadata={metadata} />
        </div>
        <div className="cal:flex cal:items-center cal:justify-between cal:border-t cal:border-tracs-border cal:p-4">
          <TracsButton icon={RotateCcw} onClick={onReset}>Reset</TracsButton>
          <TracsButton variant="primary" onClick={onClose}>Apply Filters</TracsButton>
        </div>
      </aside>
    </div>
  );
}
