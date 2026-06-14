import React from 'react';
import { CalendarX2, RefreshCw } from 'lucide-react';
import { TracsButton, TracsCard } from './CalendarPrimitives';

export function CalendarSkeleton() {
  return (
    <div className="cal:grid cal:grid-cols-1 cal:gap-4 cal:md:grid-cols-2 cal:xl:grid-cols-3" aria-label="Loading calendar">
      {Array.from({ length: 12 }, (_, index) => (
        <div key={index} className="cal:h-64 cal:animate-pulse cal:rounded-tracs-lg cal:border cal:border-tracs-border cal:bg-tracs-card">
          <div className="cal:m-4 cal:h-3 cal:w-24 cal:rounded-tracs-sm cal:bg-tracs-surface-3" />
          <div className="cal:mx-4 cal:mt-7 cal:grid cal:grid-cols-7 cal:gap-2">
            {Array.from({ length: 35 }, (_, cell) => <i key={cell} className="cal:aspect-square cal:rounded-tracs-sm cal:bg-tracs-surface-2" />)}
          </div>
        </div>
      ))}
    </div>
  );
}

export function CalendarEmptyState({ title = 'Nothing scheduled here', message = 'Try another date or clear the active filters.' }) {
  return (
    <TracsCard className="cal:flex cal:flex-col cal:items-center cal:justify-center cal:px-6 cal:py-16 cal:text-center">
      <CalendarX2 className="cal:size-8 cal:text-tracs-faint" />
      <h2 className="cal:mt-3 cal:text-sm cal:font-semibold cal:text-tracs-primary">{title}</h2>
      <p className="cal:mt-1 cal:max-w-md cal:text-xs cal:text-tracs-muted">{message}</p>
    </TracsCard>
  );
}

export function CalendarErrorState({ message, onRetry }) {
  return (
    <TracsCard className="cal:flex cal:flex-col cal:items-center cal:justify-center cal:px-6 cal:py-16 cal:text-center">
      <CalendarX2 className="cal:size-8 cal:text-tracs-danger" />
      <h2 className="cal:mt-3 cal:text-sm cal:font-semibold cal:text-tracs-primary">Calendar could not load</h2>
      <p className="cal:mt-1 cal:max-w-md cal:text-xs cal:text-tracs-muted">{message}</p>
      <TracsButton className="cal:mt-4" icon={RefreshCw} onClick={onRetry}>Retry</TracsButton>
    </TracsCard>
  );
}
