import React from 'react';
import { ArrowLeft, CalendarPlus, ChevronLeft, ChevronRight, LocateFixed } from 'lucide-react';
import { TracsButton } from './CalendarPrimitives';

const views = ['Year', 'Month', 'Week', 'Day', 'Agenda'];

export function CalendarHeader({
  year,
  view,
  onView,
  onYear,
  onToday,
  onBook,
  canCreate,
}) {
  return (
    <header className="calendar-sticky-header cal:sticky cal:top-0 cal:z-30 cal:-mx-4 cal:border-b cal:border-tracs-border cal:bg-tracs-page/95 cal:px-4 cal:pb-3 cal:pt-3 cal:backdrop-blur-md">
      <div className="cal:flex cal:flex-col cal:gap-3 cal:xl:flex-row cal:xl:items-center cal:xl:justify-between">
        <div className="cal:flex cal:min-w-0 cal:items-center cal:gap-3">
          <a
            href="index.php"
            className="cal:flex cal:size-9 cal:shrink-0 cal:items-center cal:justify-center cal:rounded-tracs cal:border cal:border-tracs-border cal:bg-tracs-surface-2 cal:text-tracs-secondary cal:transition hover:cal:bg-tracs-surface-3 hover:cal:text-tracs-primary focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent"
            aria-label="Back to dashboard"
          >
            <ArrowLeft className="cal:size-4" />
          </a>
          <div className="cal:min-w-0">
            <h1 className="cal:text-xl cal:font-semibold cal:tracking-[-.02em] cal:text-tracs-primary">Calendar</h1>
            <p className="cal:truncate cal:font-mono cal:text-[10px] cal:text-tracs-muted">Holidays, cases, shifts, meetings, reminders & schedules</p>
          </div>
        </div>

        <div className="cal:flex cal:flex-wrap cal:items-center cal:gap-2">
          <div className="cal:flex cal:items-center cal:rounded-tracs cal:border cal:border-tracs-border cal:bg-tracs-card cal:p-0.5">
            <TracsButton size="icon" className="cal:border-transparent cal:bg-transparent" icon={ChevronLeft} onClick={() => onYear(year - 1)} aria-label="Previous year" />
            <strong className="cal:min-w-16 cal:text-center cal:font-mono cal:text-xs cal:text-tracs-primary">{year}</strong>
            <TracsButton size="icon" className="cal:border-transparent cal:bg-transparent" icon={ChevronRight} onClick={() => onYear(year + 1)} aria-label="Next year" />
          </div>
          <TracsButton icon={LocateFixed} onClick={onToday}>Today</TracsButton>
          {canCreate ? <TracsButton variant="primary" icon={CalendarPlus} onClick={onBook}>Book Schedule</TracsButton> : null}
        </div>
      </div>

      <div className="cal:mt-3 cal:flex cal:w-full cal:overflow-x-auto cal:rounded-tracs cal:border cal:border-tracs-border cal:bg-tracs-card cal:p-0.5 cal:sm:w-fit">
        {views.map((item) => {
          const value = item.toLowerCase();
          return (
            <button
              key={value}
              type="button"
              onClick={() => onView(value)}
              className={`cal:min-w-16 cal:rounded-tracs-sm cal:px-3 cal:py-1.5 cal:text-[11px] cal:font-semibold cal:transition focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent ${
                view === value
                  ? 'cal:bg-tracs-accent-soft cal:text-tracs-accent'
                  : 'cal:text-tracs-muted hover:cal:bg-tracs-surface-3 hover:cal:text-tracs-primary'
              }`}
              aria-pressed={view === value}
            >
              {item}
            </button>
          );
        })}
      </div>
    </header>
  );
}
