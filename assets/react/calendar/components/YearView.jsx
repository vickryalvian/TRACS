import React from 'react';
import { MonthMiniCalendar } from './MonthMiniCalendar';

export function YearView(props) {
  return (
    <div className="cal:grid cal:grid-cols-1 cal:gap-4 cal:md:grid-cols-2 cal:xl:grid-cols-3">
      {Array.from({ length: 12 }, (_, month) => (
        <MonthMiniCalendar key={month} {...props} month={month} />
      ))}
    </div>
  );
}
