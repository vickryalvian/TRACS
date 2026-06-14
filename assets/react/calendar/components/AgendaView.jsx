import React from 'react';
import { CalendarClock, Clock3, UserRound } from 'lucide-react';
import { CalendarBadge } from './EventBadge';
import { TracsCard } from './CalendarPrimitives';
import { addDays, eventTime, formatDate, fromISO, jakartaToday, sameWeek, toISO } from '../utils/date';
import { eventTypeLabel, sourceLabel, TYPE_TONES } from '../utils/events';

export function AgendaView({ events, onOpenEvent }) {
  const today = jakartaToday();
  const tomorrow = toISO(addDays(today, 1));
  const weekAnchor = fromISO(today);
  const future = events.filter((event) => event.date >= today);
  const isThisWeek = (event) => sameWeek(event.date, weekAnchor);
  const sections = [
    ['Today', future.filter((event) => event.date === today)],
    ['Tomorrow', future.filter((event) => event.date === tomorrow)],
    ['This Week', future.filter((event) => event.date !== today && event.date !== tomorrow && isThisWeek(event))],
    ['Upcoming', future.filter((event) => event.date !== today && event.date !== tomorrow && !isThisWeek(event))],
  ];
  return (
    <div className="cal:flex cal:flex-col cal:gap-4">
      {sections.map(([label, items]) => (
        <TracsCard key={label} className="cal:overflow-hidden">
          <div className="cal:flex cal:items-center cal:justify-between cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2 cal:px-4 cal:py-2.5">
            <h2 className="cal:text-xs cal:font-semibold cal:text-tracs-primary">{label}</h2>
            <span className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">{items.length}</span>
          </div>
          {items.length ? (
            <div className="cal:divide-y cal:divide-tracs-border">
              {items.map((event) => (
                <button
                  type="button"
                  key={event.id}
                  onClick={() => onOpenEvent(event)}
                  className="cal:grid cal:w-full cal:grid-cols-[92px_minmax(0,1fr)_auto] cal:items-center cal:gap-3 cal:px-4 cal:py-3 cal:text-left hover:cal:bg-tracs-surface-2 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent"
                >
                  <div>
                    <strong className="cal:block cal:font-mono cal:text-[10px] cal:text-tracs-primary">{formatDate(event.date)}</strong>
                    <span className="cal:mt-1 cal:inline-flex cal:items-center cal:gap-1 cal:font-mono cal:text-[9px] cal:text-tracs-muted"><Clock3 className="cal:size-3" />{eventTime(event)}</span>
                  </div>
                  <div className="cal:min-w-0">
                    <strong className="cal:block cal:truncate cal:text-xs cal:text-tracs-primary">{event.title}</strong>
                    <span className="cal:mt-1 cal:flex cal:items-center cal:gap-1 cal:text-[9px] cal:text-tracs-muted"><UserRound className="cal:size-3" />{event.assignee?.name || sourceLabel(event.source)}</span>
                  </div>
                  <CalendarBadge tone={TYPE_TONES[event.type]}>{eventTypeLabel(event.type)}</CalendarBadge>
                </button>
              ))}
            </div>
          ) : (
            <div className="cal:flex cal:items-center cal:justify-center cal:gap-2 cal:px-4 cal:py-6 cal:text-[10px] cal:text-tracs-faint">
              <CalendarClock className="cal:size-4" />No items in this group.
            </div>
          )}
        </TracsCard>
      ))}
    </div>
  );
}
