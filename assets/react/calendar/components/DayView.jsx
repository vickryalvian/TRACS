import React from 'react';
import { CalendarDays, Clock3, ExternalLink, UserRound } from 'lucide-react';
import { CalendarBadge } from './EventBadge';
import { TracsButton, TracsCard } from './CalendarPrimitives';
import { eventTime, formatDate } from '../utils/date';
import { eventTypeLabel, sourceLabel, TYPE_TONES } from '../utils/events';

const groups = [
  [['shift', 'overtime'], 'Shift Schedule'],
  [['case'], 'Cases'],
  [['meeting'], 'Meetings'],
  [['reminder', 'task', 'birthday'], 'Reminders / Tasks'],
  [['holiday'], 'Holidays'],
  [['maintenance'], 'Maintenance Notifications'],
];

export function DayView({ selectedDate, events, onOpenEvent }) {
  return (
    <div className="cal:flex cal:flex-col cal:gap-4">
      <TracsCard className="cal:flex cal:items-center cal:gap-3 cal:p-4">
        <span className="cal:flex cal:size-9 cal:items-center cal:justify-center cal:rounded-tracs cal:bg-tracs-accent-soft cal:text-tracs-accent">
          <CalendarDays className="cal:size-4" />
        </span>
        <div>
          <h2 className="cal:text-sm cal:font-semibold cal:text-tracs-primary">{formatDate(selectedDate)}</h2>
          <p className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">{events.length} scheduled item{events.length === 1 ? '' : 's'}</p>
        </div>
      </TracsCard>
      {groups.map(([types, title]) => {
        const grouped = events.filter((event) => types.includes(event.type));
        return (
          <TracsCard key={title} className="cal:overflow-hidden">
            <div className="cal:border-b cal:border-tracs-border cal:bg-tracs-surface-2 cal:px-4 cal:py-2.5">
              <h3 className="cal:font-mono cal:text-[9px] cal:font-bold cal:uppercase cal:tracking-[.1em] cal:text-tracs-muted">{title}</h3>
            </div>
            {grouped.length ? (
              <div className="cal:divide-y cal:divide-tracs-border">
                {grouped.map((event) => (
                  <button
                    type="button"
                    key={event.id}
                    onClick={() => onOpenEvent(event)}
                    className="cal:flex cal:w-full cal:items-start cal:justify-between cal:gap-4 cal:px-4 cal:py-3 cal:text-left cal:transition hover:cal:bg-tracs-surface-2 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent"
                  >
                    <div className="cal:min-w-0">
                      <div className="cal:flex cal:flex-wrap cal:items-center cal:gap-1.5">
                        <CalendarBadge tone={TYPE_TONES[event.type]}>{eventTypeLabel(event.type)}</CalendarBadge>
                        <CalendarBadge tone={event.status === 'overdue' ? 'red' : event.status === 'done' ? 'green' : 'blue'}>{event.status.replaceAll('_', ' ')}</CalendarBadge>
                      </div>
                      <strong className="cal:mt-1.5 cal:block cal:text-xs cal:text-tracs-primary">{event.title}</strong>
                      <div className="cal:mt-1 cal:flex cal:flex-wrap cal:gap-3 cal:font-mono cal:text-[9px] cal:text-tracs-muted">
                        <span className="cal:inline-flex cal:items-center cal:gap-1"><Clock3 className="cal:size-3" />{eventTime(event)}</span>
                        <span className="cal:inline-flex cal:items-center cal:gap-1"><UserRound className="cal:size-3" />{event.assignee?.name || 'Unassigned'}</span>
                        <span>{sourceLabel(event.source)}</span>
                      </div>
                    </div>
                    <span className="cal:flex cal:size-8 cal:shrink-0 cal:items-center cal:justify-center cal:rounded-tracs cal:border cal:border-tracs-border cal:bg-tracs-surface-2 cal:text-tracs-muted">
                      <ExternalLink className="cal:size-3.5" />
                    </span>
                  </button>
                ))}
              </div>
            ) : <div className="cal:px-4 cal:py-5 cal:text-center cal:text-[10px] cal:text-tracs-faint">No {title.toLowerCase()} for this date.</div>}
          </TracsCard>
        );
      })}
    </div>
  );
}
