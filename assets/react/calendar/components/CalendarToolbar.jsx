import React from 'react';
import { Filter, RotateCcw } from 'lucide-react';
import { EVENT_TYPES, STATUSES } from '../utils/events';
import { SearchInput, TracsButton, TracsInput, TracsSelect } from './CalendarPrimitives';

function FilterFields({ filters, setFilter, metadata, compact = false }) {
  return (
    <>
      <SearchInput
        className={compact ? '' : 'cal:min-w-44 cal:flex-1'}
        value={filters.search}
        onChange={(event) => setFilter('search', event.target.value)}
        placeholder="Search title, owner, notes"
        aria-label="Search calendar"
      />
      <div className="cal:flex cal:items-center cal:gap-1">
        <TracsInput
          className="cal:w-[108px] cal:font-mono"
          value={filters.startDate}
          onChange={(event) => setFilter('startDate', event.target.value)}
          placeholder="dd-mm-yyyy"
          aria-label="Start date"
          inputMode="numeric"
        />
        <span className="cal:text-tracs-faint">–</span>
        <TracsInput
          className="cal:w-[108px] cal:font-mono"
          value={filters.endDate}
          onChange={(event) => setFilter('endDate', event.target.value)}
          placeholder="dd-mm-yyyy"
          aria-label="End date"
          inputMode="numeric"
        />
      </div>
      <TracsSelect value={filters.type} onChange={(event) => setFilter('type', event.target.value)} aria-label="Event type">
        {EVENT_TYPES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
      </TracsSelect>
      <TracsSelect value={filters.status} onChange={(event) => setFilter('status', event.target.value)} aria-label="Status">
        {STATUSES.map(([value, label]) => <option key={value} value={value}>{label}</option>)}
      </TracsSelect>
      <TracsSelect value={filters.user} onChange={(event) => setFilter('user', event.target.value)} aria-label="Agent or user">
        <option value="all">All agents</option>
        {(metadata?.users || []).map((user) => <option key={user.id} value={String(user.id)}>{user.name}</option>)}
      </TracsSelect>
      <TracsSelect value={filters.division} onChange={(event) => setFilter('division', event.target.value)} aria-label="Division">
        <option value="all">All divisions</option>
        {(metadata?.divisions || []).map((division) => <option key={division.id} value={String(division.id)}>{division.name}</option>)}
      </TracsSelect>
      <TracsSelect value={filters.role} onChange={(event) => setFilter('role', event.target.value)} aria-label="Role">
        <option value="all">All roles</option>
        {(metadata?.roles || []).map((role) => <option key={role.slug} value={role.slug}>{role.name}</option>)}
      </TracsSelect>
      <TracsSelect value={filters.priority} onChange={(event) => setFilter('priority', event.target.value)} aria-label="Priority">
        <option value="all">All priorities</option>
        <option value="critical">Critical</option><option value="high">High</option>
        <option value="medium">Medium</option><option value="low">Low</option>
      </TracsSelect>
      <TracsSelect value={filters.source} onChange={(event) => setFilter('source', event.target.value)} aria-label="Source module">
        <option value="all">All sources</option>
        <option value="cases">Cases</option><option value="shifts">Shifts</option>
        <option value="meetings">Meetings</option><option value="reminders">Reminders</option>
        <option value="tasks">Checklist</option><option value="holidays">Holidays</option>
        <option value="notifications">Notifications</option><option value="calendar">Calendar</option>
      </TracsSelect>
    </>
  );
}

export function CalendarToolbar({ filters, setFilter, metadata, onReset, onOpenDrawer, resultCount }) {
  return (
    <div className="cal:rounded-tracs-lg cal:border cal:border-tracs-border cal:bg-tracs-card cal:p-3 cal:shadow-tracs">
      <div className="cal:hidden cal:items-center cal:gap-2 cal:2xl:flex">
        <FilterFields filters={filters} setFilter={setFilter} metadata={metadata} />
        <TracsButton size="icon" icon={RotateCcw} onClick={onReset} aria-label="Reset filters" />
      </div>
      <div className="cal:flex cal:items-center cal:justify-between cal:gap-3 cal:2xl:hidden">
        <SearchInput
          className="cal:flex-1"
          value={filters.search}
          onChange={(event) => setFilter('search', event.target.value)}
          placeholder="Search calendar"
          aria-label="Search calendar"
        />
        <TracsButton icon={Filter} onClick={onOpenDrawer}>Filters</TracsButton>
      </div>
      <p className="cal:mt-2 cal:font-mono cal:text-[9px] cal:text-tracs-muted">{resultCount} matching calendar items</p>
    </div>
  );
}

export { FilterFields };
