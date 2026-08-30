import React from 'react';
import { EventBadge } from './EventBadge';
import { TracsCard } from './CalendarPrimitives';
import { WEEKDAYS, formatDate, weekDates } from '../utils/date';
import { WEEK_GROUPS } from '../utils/events';

export function WeekView({ selectedDate, eventIndex, onOpenEvent, onSelectDate }) {
  const days = weekDates(selectedDate);
  return (
    <TracsCard className="cal:overflow-hidden">
      <div className="calendar-week-scroll cal:min-w-0 cal:overflow-hidden">
        <div className="cal:min-w-0">
          <div className="cal:grid cal:grid-cols-[minmax(76px,.72fr)_repeat(7,minmax(0,1fr))] cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2 cal:sm:grid-cols-[minmax(104px,.78fr)_repeat(7,minmax(0,1fr))]">
            <div className="cal:min-w-0 cal:px-2 cal:py-3 cal:font-mono cal:text-[8px] cal:font-bold cal:uppercase cal:text-tracs-muted cal:sm:px-3 cal:sm:text-[9px]">Group</div>
            {days.map((day, index) => (
              <button
                key={day.iso}
                type="button"
                onClick={() => onSelectDate(day.iso)}
                className="cal:min-w-0 cal:border-l cal:border-tracs-border cal:px-1 cal:py-2 cal:text-center hover:cal:bg-tracs-surface-3 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent cal:sm:px-2"
              >
                <strong className="cal:block cal:text-[11px] cal:text-tracs-primary">{WEEKDAYS[index]}</strong>
                <span className="cal:block cal:truncate cal:font-mono cal:text-[8px] cal:text-tracs-muted cal:sm:text-[9px]">{formatDate(day.iso)}</span>
              </button>
            ))}
          </div>
          {WEEK_GROUPS.map(([type, label]) => (
            <div key={type} className="cal:grid cal:min-h-24 cal:grid-cols-[minmax(76px,.72fr)_repeat(7,minmax(0,1fr))] cal:border-b cal:border-tracs-border cal:sm:grid-cols-[minmax(104px,.78fr)_repeat(7,minmax(0,1fr))]">
              <div className="cal:min-w-0 cal:bg-tracs-surface-2 cal:px-2 cal:py-3 cal:text-[10px] cal:font-semibold cal:leading-4 cal:text-tracs-secondary cal:sm:px-3 cal:sm:text-[11px]">{label}</div>
              {days.map((day) => {
                const events = (eventIndex.get(day.iso) || []).filter((event) => {
                  if (type === 'shift') return event.type === 'shift' || event.type === 'overtime';
                  if (type === 'reminder') return event.type === 'reminder' || event.type === 'birthday';
                  return event.type === type;
                });
                return (
                  <div key={day.iso} className="cal:flex cal:min-w-0 cal:flex-col cal:gap-1 cal:overflow-hidden cal:border-l cal:border-tracs-border cal:p-1 cal:sm:p-2">
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
