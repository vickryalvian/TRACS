import React, { useMemo, useState } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { MonthMiniCalendar } from '../../../../assets/react/calendar/components/MonthMiniCalendar';
import { MonthView } from '../../../../assets/react/calendar/components/MonthView';
import { EventDetailPanel } from '../../../../assets/react/calendar/components/EventDetailPanel';
import { TracsModal } from '../../../../assets/react/calendar/components/TracsModal';
import { addDays, indexEvents, jakartaToday, MONTHS, toISO } from '../../../../assets/react/calendar/utils/date';

export function ClientCalendar({ calendar, year, setYear, clientIds, onChanged }) {
  const today = jakartaToday();
  const [month, setMonth] = useState(Number(today.slice(5, 7)) - 1);
  const [selectedDate, setSelectedDate] = useState(today);
  const [full, setFull] = useState(false);
  const [period, setPeriod] = useState('month');
  const [type, setType] = useState('all');
  const [status, setStatus] = useState('pending');
  const [detail, setDetail] = useState(null);
  const events = useMemo(() => calendar.events.filter(e => e.source === 'clients' && clientIds.has(String(e.client_id))), [calendar.events, clientIds]);
  const filtered = events.filter(e => (type === 'all' || e.meta.activity_type === type) && (status === 'all' || (status === 'done' ? e.status === 'done' : e.status !== 'done')));
  const eventIndex = indexEvents(filtered);
  const types = [...new Set(events.map(e => e.meta.activity_type))];
  const monthEnd = toISO(new Date(year, month + 1, 0));
  const weekEnd = toISO(addDays(today, 7 - (new Date(`${today}T12:00:00`).getDay() || 7)));
  const monthStart = `${year}-${String(month + 1).padStart(2, '0')}-01`;
  const weekStart = toISO(addDays(today, 1 - (new Date(`${today}T12:00:00`).getDay() || 7)));
  const upcoming = filtered.filter(e => e.date >= (period === 'week' ? weekStart : monthStart) && e.date <= (period === 'week' ? weekEnd : monthEnd));
  function navigate(delta) {
    const next = new Date(year, month + delta, 1);
    setYear(next.getFullYear()); setMonth(next.getMonth()); setSelectedDate(toISO(next));
  }
  function openDate(date) { setSelectedDate(date); setDetail({ date, event: null }); }
  function openEvent(event) { setSelectedDate(event.date); setDetail({ date: event.date, event }); }
  const viewProps = { year, month, selectedDate, eventIndex, onSelectDate: openDate, onOpenDate: openDate, onOpenMonth: () => setFull(true), onOpenEvent: openEvent };
  const controls = <div className="clients-calendar-controls">
    <div><button className="btn btn-ghost btn-sm" aria-label="Previous month" onClick={() => navigate(-1)}><ChevronLeft size={14} /></button><strong>{MONTHS[month]} {year}</strong><button className="btn btn-ghost btn-sm" aria-label="Next month" onClick={() => navigate(1)}><ChevronRight size={14} /></button><button className="btn btn-ghost btn-sm" onClick={() => { setYear(Number(today.slice(0, 4))); setMonth(Number(today.slice(5, 7)) - 1); setSelectedDate(today); }}>Today</button></div>
    <div><select className="form-input" aria-label="Activity type" value={type} onChange={e => setType(e.target.value)}><option value="all">All activities</option>{types.map(t => <option key={t} value={t}>{t.replaceAll('_', ' ')}</option>)}</select><select className="form-input" aria-label="Reminder status" value={status} onChange={e => setStatus(e.target.value)}><option value="pending">Pending</option><option value="done">Done</option><option value="all">All statuses</option></select></div>
  </div>;
  const error = calendar.error;
  return <section className="panel clients-calendar calendar-react-root">
    <div className="panel-head"><h2 className="panel-title">Client Calendar</h2><button className="btn btn-ghost btn-sm" onClick={() => setFull(true)}>View More</button></div>
    {controls}
    {error ? <p className="clients-empty" role="alert">{error} <button className="btn btn-ghost btn-sm" onClick={calendar.refresh}>Retry</button></p> : <div className="clients-calendar-summary" aria-busy={calendar.loading}>
      <MonthMiniCalendar {...viewProps} />
      <div className="clients-upcoming"><div className="clients-upcoming-head"><h3>Upcoming</h3><select className="form-input" aria-label="Upcoming period" value={period} onChange={e => setPeriod(e.target.value)}><option value="month">{MONTHS[month]} {year}</option><option value="week">This week</option></select></div>
        {calendar.loading ? <p className="clients-empty">Loading reminders…</p> : upcoming.length ? <ul>{upcoming.map(event => <li key={event.id}><button onClick={() => openEvent(event)}><span className={event.status === 'overdue' ? 'clients-overdue' : ''}>{event.date === today ? 'Today' : event.date === toISO(addDays(today, 1)) ? 'Tomorrow' : event.date.slice(8) + ' ' + MONTHS[Number(event.date.slice(5, 7)) - 1].slice(0, 3)}{event.status === 'overdue' ? ' · Overdue' : ''}</span><strong>{event.meta.client_name}</strong><span>{event.meta.reminder_title}</span></button></li>)}</ul> : <p className="clients-empty">No client reminders in this period.</p>}
      </div>
    </div>}
    {full && <TracsModal title="Client Calendar" wide onClose={() => setFull(false)}><div className="calendar-react-root">{controls}{error ? <p role="alert">{error}</p> : calendar.loading ? <p>Loading reminders…</p> : <MonthView {...viewProps} />}
      {detail && <EventDetailPanel open date={detail.date} events={eventIndex.get(detail.date) || []} event={events.find(e => e.id === detail.event?.id) || detail.event} onClose={() => setDetail(null)} onOpenEvent={openEvent} onRefresh={onChanged} />}
    </div></TracsModal>}
    {!full && detail && <EventDetailPanel open date={detail.date} events={eventIndex.get(detail.date) || []} event={events.find(e => e.id === detail.event?.id) || detail.event} onClose={() => setDetail(null)} onOpenEvent={openEvent} onRefresh={onChanged} />}
  </section>;
}
