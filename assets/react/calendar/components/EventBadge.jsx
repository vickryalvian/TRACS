import React from 'react';
import { cx } from './CalendarPrimitives';
import { eventTypeLabel, TYPE_TONES } from '../utils/events';

const toneClasses = {
  blue: 'cal:border-tracs-accent-border cal:bg-tracs-accent-soft cal:text-tracs-accent',
  green: 'cal:border-tracs-success-border cal:bg-tracs-success-soft cal:text-tracs-success',
  purple: 'cal:border-tracs-purple-border cal:bg-tracs-purple-soft cal:text-tracs-purple',
  amber: 'cal:border-tracs-warning-border cal:bg-tracs-warning-soft cal:text-tracs-warning',
  red: 'cal:border-tracs-danger-border cal:bg-tracs-danger-soft cal:text-tracs-danger',
  orange: 'cal:border-tracs-orange-border cal:bg-tracs-orange-soft cal:text-tracs-orange',
};

export function CalendarBadge({ tone = 'blue', children, className = '' }) {
  return (
    <span className={cx(
      'cal:inline-flex cal:items-center cal:rounded-tracs-sm cal:border cal:px-1.5 cal:py-0.5 cal:text-[9px] cal:font-bold cal:capitalize cal:leading-none',
      toneClasses[tone] || toneClasses.blue,
      className,
    )}>
      {children}
    </span>
  );
}

export function EventBadge({ event, compact = false, onClick }) {
  const tone = TYPE_TONES[event.type] || 'blue';
  return (
    <button
      type="button"
      onClick={(eventClick) => {
        eventClick.stopPropagation();
        onClick?.(event);
      }}
      className={cx(
        'cal:flex cal:w-full cal:min-w-0 cal:items-center cal:gap-1 cal:rounded-tracs-sm cal:border cal:text-left cal:transition hover:cal:brightness-110 focus-visible:cal:outline-none focus-visible:cal:ring-2 focus-visible:cal:ring-tracs-accent',
        compact ? 'cal:px-1 cal:py-0.5 cal:text-[9px]' : 'cal:px-1.5 cal:py-1 cal:text-[10px]',
        toneClasses[tone],
      )}
      title={`${eventTypeLabel(event.type)}: ${event.title}`}
    >
      <span className="cal:size-1.5 cal:shrink-0 cal:rounded-full cal:bg-current" />
      <span className="cal:truncate">{event.start_time ? `${event.start_time} ` : ''}{event.title}</span>
    </button>
  );
}
