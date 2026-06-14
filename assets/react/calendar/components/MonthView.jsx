import React from 'react';
import { EventBadge } from './EventBadge';
import { TracsCard, cx } from './CalendarPrimitives';
import {
  handleDateGridKey,
  jakartaToday,
  MONTHS,
  WEEKDAYS,
  monthCells,
} from '../utils/date';

export function MonthView({
  year,
  month,
  eventIndex,
  selectedDate,
  onSelectDate,
  onOpenDate,
  onOpenEvent,
  onBookDate,
}) {
  const today = jakartaToday();
  return (
    <TracsCard className="cal:overflow-hidden">
      <div className="cal:flex cal:items-center cal:justify-between cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2 cal:px-4 cal:py-3">
        <h2 className="cal:text-sm cal:font-semibold cal:text-tracs-primary">{MONTHS[month]} {year}</h2>
        <span className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">Click a date for summary · double-click to book</span>
      </div>
      <div className="cal:grid cal:grid-cols-7 cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2">
        {WEEKDAYS.map((day) => (
          <span key={day} className="cal:px-2 cal:py-2 cal:text-center cal:font-mono cal:text-[9px] cal:font-bold cal:text-tracs-muted">{day}</span>
        ))}
      </div>
      <div className="cal:grid cal:grid-cols-7">
        {monthCells(year, month).map((cell) => {
          const events = eventIndex.get(cell.iso) || [];
          const holiday = events.some((event) => event.type === 'holiday');
          const visible = events.slice(0, 3);
          return (
            <div
              key={cell.iso}
              role="gridcell"
              data-calendar-date={cell.iso}
              tabIndex={cell.currentMonth ? 0 : -1}
              onClick={() => onSelectDate(cell.iso)}
              onDoubleClick={() => onBookDate(cell.iso)}
              onKeyDown={(event) => handleDateGridKey(event, cell.iso, onSelectDate, onOpenDate)}
              className={cx(
                'cal:min-h-28 cal:min-w-0 cal:border-b cal:border-r cal:border-tracs-border cal:p-2 cal:text-left cal:transition focus-visible:cal:z-10 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent',
                cell.currentMonth ? 'cal:bg-tracs-card hover:cal:bg-tracs-surface-2' : 'cal:bg-tracs-page cal:opacity-35',
                holiday && cell.currentMonth && 'cal:bg-tracs-danger-soft/40',
                cell.iso === selectedDate && 'cal:bg-tracs-accent-soft',
              )}
            >
              <span className={cx(
                'cal:mb-2 cal:flex cal:size-6 cal:items-center cal:justify-center cal:rounded-tracs-sm cal:text-[11px] cal:font-semibold',
                cell.iso === today && 'cal:ring-1 cal:ring-tracs-accent cal:text-tracs-accent',
                cell.iso === selectedDate && 'cal:bg-tracs-accent cal:text-white',
                holiday && cell.iso !== selectedDate && 'cal:text-tracs-danger',
              )}>{cell.day}</span>
              <div className="cal:flex cal:flex-col cal:gap-1">
                {visible.map((event) => <EventBadge key={event.id} event={event} compact onClick={onOpenEvent} />)}
                {events.length > 3 ? (
                  <span
                    role="button"
                    tabIndex={0}
                    onClick={(event) => {
                      event.stopPropagation();
                      onOpenDate(cell.iso);
                    }}
                    className="cal:px-1 cal:text-[9px] cal:font-semibold cal:text-tracs-accent"
                  >
                    +{events.length - 3} more
                  </span>
                ) : null}
              </div>
            </div>
          );
        })}
      </div>
    </TracsCard>
  );
}
