import React, { useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { MonthMiniCalendar } from '../../../../assets/react/calendar/components/MonthMiniCalendar';
import { MonthView } from '../../../../assets/react/calendar/components/MonthView';
import { EventDetailPanel } from '../../../../assets/react/calendar/components/EventDetailPanel';
import { TracsModal } from '../../../../assets/react/calendar/components/TracsModal';
import { addDays, indexEvents, jakartaToday, MONTHS, toISO } from '../../../../assets/react/calendar/utils/date';

function activityLabel(value) {
  return String(value || '').replaceAll('_', ' ').replace(/\b\w/g, (m) => m.toUpperCase());
}

function eventActivityKey(event) {
  return event?.source === 'clients'
    ? event.meta?.activity_type || event.type
    : event.type;
}

function matchesStatus(event, status) {
  if (status === 'all') return true;
  if (status === 'done') return event.status === 'done';
  if (status === 'pending') return !['done', 'not_applicable'].includes(event.status);
  return event.status === status;
}

export function ClientCalendar({ calendar, year, setYear, clientIds, onChanged }) {
  const today = jakartaToday();
  const [month, setMonth] = useState(Number(today.slice(5, 7)) - 1);
  const [selectedDate, setSelectedDate] = useState(today);
  const [full, setFull] = useState(false);
  const [period, setPeriod] = useState('month');
  const [type, setType] = useState('all');
  const [status, setStatus] = useState('pending');
  const [detail, setDetail] = useState(null);
  const events = calendar.events || [];
  const clientEvents = useMemo(() => events.filter(e => e.source === 'clients' && clientIds.has(String(e.client_id))), [events, clientIds]);
  const filtered = events.filter(e => (type === 'all' || eventActivityKey(e) === type) && matchesStatus(e, status));
  const clientFiltered = clientEvents.filter(e => (type === 'all' || eventActivityKey(e) === type) && matchesStatus(e, status));
  const eventIndex = indexEvents(filtered);
  const types = [...new Set(events.map(eventActivityKey).filter(Boolean))];
  const monthEnd = toISO(new Date(year, month + 1, 0));
  const weekEnd = toISO(addDays(today, 7 - (new Date(`${today}T12:00:00`).getDay() || 7)));
  const monthStart = `${year}-${String(month + 1).padStart(2, '0')}-01`;
  const weekStart = toISO(addDays(today, 1 - (new Date(`${today}T12:00:00`).getDay() || 7)));
  const upcoming = clientFiltered.filter(e => e.date >= (period === 'week' ? weekStart : monthStart) && e.date <= (period === 'week' ? weekEnd : monthEnd));
  function navigate(delta) {
    const next = new Date(year, month + delta, 1);
    setYear(next.getFullYear()); setMonth(next.getMonth()); setSelectedDate(toISO(next));
  }
  function openDate(date) { setSelectedDate(date); setDetail({ date, event: null }); }
  function openEvent(event) { setSelectedDate(event.date); setDetail({ date: event.date, event }); }
  const viewProps = { year, month, selectedDate, eventIndex, onSelectDate: openDate, onOpenDate: openDate, onOpenMonth: () => setFull(true), onOpenEvent: openEvent };
  const controls = <div className="clients-calendar-controls" data-calendar-filter data-unsaved-ignore>
    <div className="clients-calendar-nav">
      <button className="btn btn-ghost btn-sm" aria-label="Previous month" onClick={() => navigate(-1)}><ChevronLeft size={14} /></button>
      <strong>{MONTHS[month]} {year}</strong>
      <button className="btn btn-ghost btn-sm" aria-label="Next month" onClick={() => navigate(1)}><ChevronRight size={14} /></button>
      <button className="btn btn-ghost btn-sm" onClick={() => { setYear(Number(today.slice(0, 4))); setMonth(Number(today.slice(5, 7)) - 1); setSelectedDate(today); }}>Today</button>
    </div>
    <div className="clients-calendar-filter-row">
      <select className="form-input clients-calendar-select" data-filter aria-label="Activity type" value={type} onChange={e => setType(e.target.value)}><option value="all">All activities</option>{types.map(t => <option key={t} value={t}>{activityLabel(t)}</option>)}</select>
      <select className="form-input clients-calendar-select" data-filter aria-label="Reminder status" value={status} onChange={e => setStatus(e.target.value)}><option value="pending">Pending</option><option value="done">Done</option><option value="overdue">Overdue</option><option value="not_applicable">N/A</option><option value="all">All statuses</option></select>
    </div>
  </div>;
  const error = calendar.error;
  return <section className="panel clients-calendar calendar-react-root">
    {error ? <p className="clients-empty" role="alert">{error} <button className="btn btn-ghost btn-sm" onClick={calendar.refresh}>Retry</button></p> : <div className="clients-calendar-summary" aria-busy={calendar.loading}>
      <div className="clients-upcoming"><div className="clients-upcoming-head"><div><h3>Upcoming</h3><span>{period === 'week' ? 'This week' : 'This month'}</span></div><div className="clients-upcoming-actions"><select className="form-input clients-upcoming-period" data-calendar-filter data-filter data-unsaved-ignore aria-label="Upcoming period" value={period} onChange={e => setPeriod(e.target.value)}><option value="month">{MONTHS[month]} {year}</option><option value="week">This week</option></select></div></div>
        {calendar.loading ? <p className="clients-empty">Loading reminders…</p> : upcoming.length ? <ul>{upcoming.map(event => <li key={event.id}><button onClick={() => openEvent(event)}><span className={event.status === 'overdue' ? 'clients-overdue' : ''}>{event.date === today ? 'Today' : event.date === toISO(addDays(today, 1)) ? 'Tomorrow' : event.date.slice(8) + ' ' + MONTHS[Number(event.date.slice(5, 7)) - 1].slice(0, 3)}</span><strong>{event.meta.reminder_title || event.title}</strong><span>{event.meta.client_name}</span><small>{event.status === 'done' ? 'Completed' : event.status === 'overdue' ? 'Overdue' : event.status === 'not_applicable' ? 'N/A' : 'Pending'}</small></button></li>)}</ul> : <p className="clients-empty">No client reminders in this period.</p>}
      </div>
      <aside className="clients-mini-calendar">{controls}<MonthMiniCalendar {...viewProps} /></aside>
    </div>}
    {full && <TracsModal title="Client Calendar" wide onClose={() => setFull(false)}><div className="calendar-react-root">{controls}{error ? <p role="alert">{error}</p> : calendar.loading ? <p>Loading reminders…</p> : <MonthView {...viewProps} />}
      {detail && <EventDetailPanel open date={detail.date} events={eventIndex.get(detail.date) || []} event={events.find(e => e.id === detail.event?.id) || detail.event} onClose={() => setDetail(null)} onOpenEvent={openEvent} onRefresh={onChanged} />}
    </div></TracsModal>}
    {!full && detail && <EventDetailPanel open date={detail.date} events={eventIndex.get(detail.date) || []} event={events.find(e => e.id === detail.event?.id) || detail.event} onClose={() => setDetail(null)} onOpenEvent={openEvent} onRefresh={onChanged} />}
  </section>;
}
