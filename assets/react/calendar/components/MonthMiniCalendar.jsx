import React from 'react';
import { EventDotStack } from './EventDotStack';
import {
  fullDateLabel,
  handleDateGridKey,
  jakartaToday,
  MONTHS,
  WEEKDAYS,
  monthCells,
} from '../utils/date';
import { cx } from './CalendarPrimitives';

export function MonthMiniCalendar({
  year,
  month,
  eventIndex,
  selectedDate,
  onSelectDate,
  onOpenDate,
  onOpenMonth,
  onBookDate,
}) {
  const today = jakartaToday();
  return (
    <article className="cal:min-w-0 cal:rounded-tracs-lg cal:border cal:border-tracs-border cal:bg-tracs-card cal:p-3 cal:shadow-tracs">
      <button
        type="button"
        onClick={() => onOpenMonth(month)}
        className="cal:mb-3 cal:flex cal:w-full cal:items-center cal:justify-between cal:text-left focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent"
      >
        <span className="cal:text-sm cal:font-semibold cal:text-tracs-primary">{MONTHS[month]}</span>
        <span className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">{year}</span>
      </button>
      <div className="cal:grid cal:grid-cols-7 cal:gap-1" role="grid" aria-label={`${MONTHS[month]} ${year}`}>
        {WEEKDAYS.map((day) => (
          <span key={day} className="cal:pb-1 cal:text-center cal:font-mono cal:text-[8px] cal:font-bold cal:text-tracs-faint">{day[0]}</span>
        ))}
        {monthCells(year, month).map((cell) => {
          const events = eventIndex.get(cell.iso) || [];
          const holiday = events.some((event) => event.type === 'holiday');
          return (
            <button
              key={cell.iso}
              type="button"
              data-calendar-date={cell.currentMonth ? cell.iso : undefined}
              disabled={!cell.currentMonth}
              aria-hidden={!cell.currentMonth}
              tabIndex={cell.iso === selectedDate ? 0 : -1}
              onClick={() => onSelectDate(cell.iso)}
              onDoubleClick={() => onBookDate(cell.iso)}
              onKeyDown={(event) => handleDateGridKey(event, cell.iso, onSelectDate, onOpenDate)}
              className={cx(
                'cal:flex cal:h-8 cal:min-w-0 cal:flex-col cal:items-center cal:justify-center cal:rounded-tracs-sm cal:border cal:text-[10px] cal:transition cal:sm:h-7 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent',
                !cell.currentMonth && 'cal:pointer-events-none cal:opacity-0',
                cell.currentMonth && 'cal:border-transparent cal:text-tracs-secondary hover:cal:bg-tracs-surface-3',
                holiday && 'cal:bg-tracs-danger-soft cal:text-tracs-danger',
                cell.iso === today && 'cal:ring-1 cal:ring-tracs-accent',
                cell.iso === selectedDate && 'cal:border-tracs-accent cal:bg-tracs-accent cal:text-white',
              )}
              title={events.length ? `${events.length} event(s): ${events.slice(0, 3).map((event) => event.title).join(', ')}` : undefined}
              aria-label={`${fullDateLabel(cell.iso)}, ${events.length} events`}
            >
              <span className="cal:leading-none">{cell.day}</span>
              <EventDotStack events={events} limit={3} />
            </button>
          );
        })}
      </div>
    </article>
  );
}
