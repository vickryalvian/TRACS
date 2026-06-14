import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { AlertTriangle, CalendarCheck2, CalendarRange, Layers3 } from 'lucide-react';
import { AgendaView } from './components/AgendaView';
import { BookScheduleModal } from './components/BookScheduleModal';
import { CalendarFilterDrawer } from './components/CalendarFilterDrawer';
import { CalendarHeader } from './components/CalendarHeader';
import { CalendarErrorState, CalendarSkeleton } from './components/CalendarStates';
import { CalendarToolbar } from './components/CalendarToolbar';
import { DayView } from './components/DayView';
import { EventDetailPanel } from './components/EventDetailPanel';
import { MonthView } from './components/MonthView';
import { TracsCard } from './components/CalendarPrimitives';
import { WeekView } from './components/WeekView';
import { YearView } from './components/YearView';
import { useCalendarData } from './hooks/useCalendarData';
import { addDays, formatDate, indexEvents, jakartaToday, parseDisplayDate, toISO } from './utils/date';

function defaultFilters(year) {
  return {
    search: '',
    startDate: `01-01-${year}`,
    endDate: `31-12-${year}`,
    type: 'all',
    status: 'all',
    user: 'all',
    division: 'all',
    role: 'all',
    priority: 'all',
    source: 'all',
  };
}

export function CalendarApp() {
  const today = jakartaToday();
  const todayYear = Number(today.slice(0, 4));
  const todayMonth = Number(today.slice(5, 7)) - 1;
  const [year, setYear] = useState(todayYear);
  const [month, setMonth] = useState(todayMonth);
  const [view, setView] = useState('year');
  const [selectedDate, setSelectedDate] = useState(today);
  const [filters, setFilters] = useState(defaultFilters(year));
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [detailOpen, setDetailOpen] = useState(false);
  const [selectedEvent, setSelectedEvent] = useState(null);
  const [bookOpen, setBookOpen] = useState(false);
  const [bookDate, setBookDate] = useState(today);
  const [editingEvent, setEditingEvent] = useState(null);
  const { events, metadata, loading, error, refresh, retry } = useCalendarData(year);

  const setFilter = useCallback((name, value) => {
    setFilters((current) => ({ ...current, [name]: value }));
  }, []);

  useEffect(() => {
    setFilters(defaultFilters(year));
    const next = year === todayYear ? today : `${year}-01-01`;
    setSelectedDate(next);
    setMonth(year === todayYear ? todayMonth : 0);
  }, [today, todayMonth, todayYear, year]);

  const filteredEvents = useMemo(() => {
    const query = filters.search.trim().toLowerCase();
    const start = parseDisplayDate(filters.startDate);
    const end = parseDisplayDate(filters.endDate);
    return events.filter((event) => {
      if (query) {
        const haystack = [
          event.title, event.notes, event.source, event.type,
          event.assignee?.name, event.division?.name,
        ].filter(Boolean).join(' ').toLowerCase();
        if (!haystack.includes(query)) return false;
      }
      if (start && (event.end_date || event.date) < start) return false;
      if (end && event.date > end) return false;
      if (filters.type !== 'all' && event.type !== filters.type) return false;
      if (filters.status !== 'all' && event.status !== filters.status) return false;
      if (filters.user !== 'all' && String(event.assignee?.id || '') !== filters.user) return false;
      if (filters.division !== 'all' && String(event.division?.id || '') !== filters.division) return false;
      if (filters.role !== 'all') {
        const matchingUsers = new Set((metadata?.users || [])
          .filter((user) => user.role_slug === filters.role)
          .map((user) => String(user.id)));
        if (!matchingUsers.has(String(event.assignee?.id || ''))) return false;
      }
      if (filters.priority !== 'all' && event.priority !== filters.priority) return false;
      if (filters.source !== 'all' && event.source !== filters.source) return false;
      return true;
    });
  }, [events, filters, metadata?.users]);

  const eventIndex = useMemo(() => indexEvents(filteredEvents), [filteredEvents]);
  const selectedEvents = eventIndex.get(selectedDate) || [];
  const canCreate = Boolean(metadata?.permissions?.can_create);

  const selectDate = useCallback((date) => {
    setSelectedDate(date);
    const parsed = new Date(`${date}T00:00:00`);
    setMonth(parsed.getMonth());
    setSelectedEvent(null);
    setDetailOpen(true);
  }, []);

  const openEvent = useCallback((event) => {
    setSelectedDate(event.date);
    setSelectedEvent(event);
    setDetailOpen(true);
  }, []);

  const openBook = useCallback((date = selectedDate) => {
    if (!canCreate) return;
    setBookDate(date);
    setEditingEvent(null);
    setBookOpen(true);
  }, [canCreate, selectedDate]);

  const changeYear = (nextYear) => {
    if (nextYear < 2000 || nextYear > 2100) return;
    setYear(nextYear);
  };

  const goToday = () => {
    setYear(todayYear);
    setMonth(todayMonth);
    setSelectedDate(today);
    setSelectedEvent(null);
    setDetailOpen(true);
  };

  const openMonth = (nextMonth) => {
    setMonth(nextMonth);
    setSelectedDate(`${year}-${String(nextMonth + 1).padStart(2, '0')}-01`);
    setView('month');
  };

  const editEvent = (event) => {
    setDetailOpen(false);
    setEditingEvent(event);
    setBookDate(event.date);
    setBookOpen(true);
  };

  const handleKeyboard = (keyboardEvent) => {
    if (keyboardEvent.target instanceof HTMLInputElement
      || keyboardEvent.target instanceof HTMLSelectElement
      || keyboardEvent.target instanceof HTMLTextAreaElement) return;
    const moves = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
    if (moves[keyboardEvent.key]) {
      keyboardEvent.preventDefault();
      const next = toISO(addDays(selectedDate, moves[keyboardEvent.key]));
      const nextYear = Number(next.slice(0, 4));
      if (nextYear !== year) setYear(nextYear);
      setSelectedDate(next);
      setMonth(Number(next.slice(5, 7)) - 1);
    } else if (keyboardEvent.key === 'Enter') {
      setSelectedEvent(null);
      setDetailOpen(true);
    } else if (keyboardEvent.key === 'Escape') {
      setDetailOpen(false);
      setDrawerOpen(false);
      setBookOpen(false);
    }
  };

  const commonViewProps = {
    year,
    month,
    eventIndex,
    selectedDate,
    onSelectDate: selectDate,
    onOpenDate: selectDate,
    onOpenEvent: openEvent,
    onOpenMonth: openMonth,
    onBookDate: openBook,
  };

  const renderView = () => {
    if (view === 'month') return <MonthView {...commonViewProps} />;
    if (view === 'week') return <WeekView selectedDate={selectedDate} eventIndex={eventIndex} onOpenEvent={openEvent} onSelectDate={selectDate} />;
    if (view === 'day') return <DayView selectedDate={selectedDate} events={selectedEvents} onOpenEvent={openEvent} />;
    if (view === 'agenda') return <AgendaView events={filteredEvents} onOpenEvent={openEvent} />;
    return <YearView {...commonViewProps} />;
  };

  const totals = {
    all: filteredEvents.length,
    shifts: filteredEvents.filter((item) => item.type === 'shift' || item.type === 'overtime').length,
    holidays: filteredEvents.filter((item) => item.type === 'holiday').length,
    attention: filteredEvents.filter((item) => item.status === 'overdue' || item.priority === 'critical').length,
  };

  return (
    <div className="calendar-react-root cal:min-h-full cal:text-tracs-primary" tabIndex={-1} onKeyDown={handleKeyboard}>
      <CalendarHeader
        year={year}
        view={view}
        onView={setView}
        onYear={changeYear}
        onToday={goToday}
        onBook={() => openBook(selectedDate)}
        canCreate={canCreate}
      />

      <div className="cal:flex cal:flex-col cal:gap-4 cal:pb-6 cal:pt-4">
        <div className="cal:grid cal:grid-cols-2 cal:gap-2 cal:lg:grid-cols-4">
          <SummaryCard icon={Layers3} label="Visible Items" value={totals.all} tone="blue" />
          <SummaryCard icon={CalendarRange} label="Shift / Overtime" value={totals.shifts} tone="green" />
          <SummaryCard icon={CalendarCheck2} label="Public Holidays" value={totals.holidays} tone="purple" />
          <SummaryCard icon={AlertTriangle} label="Need Attention" value={totals.attention} tone="red" />
        </div>

        <CalendarToolbar
          filters={filters}
          setFilter={setFilter}
          metadata={metadata}
          resultCount={filteredEvents.length}
          onOpenDrawer={() => setDrawerOpen(true)}
          onReset={() => setFilters(defaultFilters(year))}
        />

        <div className="cal:flex cal:items-center cal:justify-between cal:gap-3">
          <div>
            <h2 className="cal:text-sm cal:font-semibold cal:text-tracs-primary">
              {view === 'year' ? `${year} overview` : `${view[0].toUpperCase()}${view.slice(1)} view`}
            </h2>
            <p className="cal:font-mono cal:text-[9px] cal:text-tracs-muted">Selected date: {formatDate(selectedDate)} · Asia/Jakarta</p>
          </div>
        </div>

        {loading ? <CalendarSkeleton /> : error ? <CalendarErrorState message={error} onRetry={retry} /> : renderView()}
      </div>

      <CalendarFilterDrawer
        open={drawerOpen}
        onClose={() => setDrawerOpen(false)}
        filters={filters}
        setFilter={setFilter}
        metadata={metadata}
        onReset={() => setFilters(defaultFilters(year))}
      />
      <EventDetailPanel
        open={detailOpen}
        date={selectedDate}
        events={selectedEvents}
        event={selectedEvent}
        onClose={() => setDetailOpen(false)}
        onOpenEvent={openEvent}
        onEdit={editEvent}
        onRefresh={refresh}
      />
      <BookScheduleModal
        open={bookOpen}
        date={bookDate}
        event={editingEvent}
        metadata={metadata}
        onClose={() => setBookOpen(false)}
        onSaved={refresh}
      />
    </div>
  );
}

function SummaryCard({ icon: Icon, label, value, tone }) {
  const toneClass = {
    blue: 'cal:bg-tracs-accent-soft cal:text-tracs-accent',
    green: 'cal:bg-tracs-success-soft cal:text-tracs-success',
    purple: 'cal:bg-tracs-purple-soft cal:text-tracs-purple',
    red: 'cal:bg-tracs-danger-soft cal:text-tracs-danger',
  }[tone];
  return (
    <TracsCard className="cal:flex cal:items-center cal:gap-3 cal:p-3">
      <span className={`cal:flex cal:size-8 cal:items-center cal:justify-center cal:rounded-tracs ${toneClass}`}><Icon className="cal:size-4" /></span>
      <div>
        <strong className="cal:block cal:text-base cal:font-semibold cal:text-tracs-primary">{value}</strong>
        <span className="cal:font-mono cal:text-[8.5px] cal:uppercase cal:tracking-[.08em] cal:text-tracs-muted">{label}</span>
      </div>
    </TracsCard>
  );
}
