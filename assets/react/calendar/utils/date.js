export const MONTHS = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December',
];
export const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

export function pad(value) {
  return String(value).padStart(2, '0');
}

export function toISO(date) {
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

export function jakartaToday() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
  return `${values.year}-${values.month}-${values.day}`;
}

export function fromISO(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
  return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
}

export function formatDate(value) {
  const date = typeof value === 'string' ? fromISO(value) : value;
  return date && !Number.isNaN(date.getTime())
    ? `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`
    : '—';
}

export function fullDateLabel(value) {
  const date = typeof value === 'string' ? fromISO(value) : value;
  return date && !Number.isNaN(date.getTime())
    ? new Intl.DateTimeFormat('en-US', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    }).format(date)
    : '';
}

export function parseDisplayDate(value) {
  const match = /^(\d{2})-(\d{2})-(\d{4})$/.exec((value || '').trim());
  if (!match) return null;
  const date = new Date(Number(match[3]), Number(match[2]) - 1, Number(match[1]));
  return date.getFullYear() === Number(match[3])
    && date.getMonth() === Number(match[2]) - 1
    && date.getDate() === Number(match[1])
    ? toISO(date)
    : null;
}

export function addDays(value, amount) {
  const date = typeof value === 'string' ? fromISO(value) : new Date(value);
  date.setDate(date.getDate() + amount);
  return date;
}

export function handleDateGridKey(event, iso, onSelectDate, onOpenDate) {
  const offsets = {
    ArrowLeft: -1,
    ArrowRight: 1,
    ArrowUp: -7,
    ArrowDown: 7,
  };

  if (event.key === 'Enter') {
    event.preventDefault();
    onOpenDate(iso);
    return;
  }

  if (!(event.key in offsets)) return;
  event.preventDefault();
  const nextIso = toISO(addDays(iso, offsets[event.key]));
  onSelectDate(nextIso);
  window.requestAnimationFrame(() => {
    document.querySelector(`[data-calendar-date="${nextIso}"]`)?.focus();
  });
}

export function monthCells(year, month) {
  const first = new Date(year, month, 1);
  const start = addDays(first, -first.getDay());
  return Array.from({ length: 42 }, (_, index) => {
    const date = addDays(start, index);
    return {
      date,
      iso: toISO(date),
      day: date.getDate(),
      currentMonth: date.getMonth() === month,
    };
  });
}

export function weekDates(selectedDate) {
  const selected = fromISO(selectedDate) || new Date();
  const sunday = addDays(selected, -selected.getDay());
  return Array.from({ length: 7 }, (_, index) => {
    const date = addDays(sunday, index);
    return { date, iso: toISO(date) };
  });
}

export function yearRange(year) {
  return {
    start: `${year}-01-01`,
    end: `${year}-12-31`,
  };
}

export function indexEvents(events) {
  const index = new Map();
  events.forEach((event) => {
    const dates = [event.date];
    if (event.end_date && event.end_date > event.date) {
      let cursor = addDays(event.date, 1);
      let guard = 0;
      while (toISO(cursor) <= event.end_date && guard < 400) {
        dates.push(toISO(cursor));
        cursor = addDays(cursor, 1);
        guard += 1;
      }
    }
    dates.forEach((date) => {
      const list = index.get(date) || [];
      list.push(event);
      index.set(date, list);
    });
  });
  return index;
}

export function relativeDateLabel(iso, today = toISO(new Date())) {
  if (iso === today) return 'Today';
  if (iso === toISO(addDays(today, 1))) return 'Tomorrow';
  return formatDate(iso);
}

export function eventTime(event) {
  if (!event.start_time && !event.end_time) return 'All day';
  return [event.start_time, event.end_time].filter(Boolean).join('–');
}

export function sameWeek(value, today = new Date()) {
  const dates = weekDates(toISO(today)).map((item) => item.iso);
  return dates.includes(value);
}
