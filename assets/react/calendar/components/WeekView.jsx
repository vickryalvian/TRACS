import React from 'react';
import { EventBadge } from './EventBadge';
import { TracsCard } from './CalendarPrimitives';
import { WEEKDAYS, formatDate, weekDates } from '../utils/date';
import { WEEK_GROUPS } from '../utils/events';

export function WeekView({ selectedDate, eventIndex, onOpenEvent, onSelectDate }) {
  const days = weekDates(selectedDate);
  return (
    <TracsCard className="cal:overflow-hidden">
      <div className="calendar-week-scroll cal:overflow-x-auto">
        <div className="cal:min-w-[980px]">
          <div className="cal:grid cal:grid-cols-[160px_repeat(7,minmax(116px,1fr))] cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2">
            <div className="cal:px-3 cal:py-3 cal:font-mono cal:text-[9px] cal:font-bold cal:uppercase cal:tracking-[.1em] cal:text-tracs-muted">Schedule group</div>
            {days.map((day, index) => (
              <button
                key={day.iso}
                type="button"
                onClick={() => onSelectDate(day.iso)}
                className="cal:border-l cal:border-tracs-border cal:px-2 cal:py-2 cal:text-center hover:cal:bg-tracs-surface-3 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent"
              >
                <strong className="cal:block cal:text-[11px] cal:text-tracs-primary">{WEEKDAYS[index]}</strong>
                <span className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">{formatDate(day.iso)}</span>
              </button>
            ))}
          </div>
          {WEEK_GROUPS.map(([type, label]) => (
            <div key={type} className="cal:grid cal:min-h-24 cal:grid-cols-[160px_repeat(7,minmax(116px,1fr))] cal:border-b cal:border-tracs-border">
              <div className="cal:bg-tracs-surface-2 cal:px-3 cal:py-3 cal:text-[11px] cal:font-semibold cal:text-tracs-secondary">{label}</div>
              {days.map((day) => {
                const events = (eventIndex.get(day.iso) || []).filter((event) => {
                  if (type === 'shift') return event.type === 'shift' || event.type === 'overtime';
                  if (type === 'reminder') return event.type === 'reminder' || event.type === 'birthday';
                  return event.type === type;
                });
                return (
                  <div key={day.iso} className="cal:flex cal:min-w-0 cal:flex-col cal:gap-1 cal:border-l cal:border-tracs-border cal:p-2">
                    {events.map((event) => <EventBadge key={event.id} event={event} compact onClick={onOpenEvent} />)}
                    {!events.length ? <span className="cal:pt-2 cal:text-center cal:text-[9px] cal:text-tracs-faint">—</span> : null}
                  </div>
                );
              })}
            </div>
          ))}
        </div>
      </div>
    </TracsCard>
  );
}
