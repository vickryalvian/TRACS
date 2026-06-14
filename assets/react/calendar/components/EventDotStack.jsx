import React from 'react';
import { TYPE_TONES } from '../utils/events';
import { cx } from './CalendarPrimitives';

const dots = {
  blue: 'cal:bg-tracs-accent',
  green: 'cal:bg-tracs-success',
  purple: 'cal:bg-tracs-purple',
  amber: 'cal:bg-tracs-warning',
  red: 'cal:bg-tracs-danger',
  orange: 'cal:bg-tracs-orange',
};

export function EventDotStack({ events, limit = 4 }) {
  if (!events?.length) return null;
  const visible = events.slice(0, limit);
  const remaining = events.length - visible.length;
  return (
    <span className="cal:flex cal:h-3 cal:items-center cal:justify-center cal:gap-0.5" aria-label={`${events.length} events`}>
      {visible.map((event, index) => (
        <i
          key={`${event.id}-${index}`}
          className={cx('cal:block cal:size-1.5 cal:rounded-full', dots[TYPE_TONES[event.type] || 'blue'])}
        />
      ))}
      {remaining > 0 ? <b className="cal:ml-0.5 cal:text-[8px] cal:text-tracs-muted">+{remaining}</b> : null}
    </span>
  );
}
