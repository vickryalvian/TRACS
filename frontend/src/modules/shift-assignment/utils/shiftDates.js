import { formatDisplayDate } from '../../../lib/date.js';

const DAY_MS = 86_400_000;

function parseIsoDate(value) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value ?? '');
  if (!match) {
    return null;
  }

  const [, year, month, day] = match;
  return new Date(Date.UTC(Number(year), Number(month) - 1, Number(day)));
}

function toIsoDate(date) {
  return date.toISOString().slice(0, 10);
}

export function todayIso() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const values = Object.fromEntries(parts.map(({ type, value }) => [type, value]));
  return `${values.year}-${values.month}-${values.day}`;
}

export function rangeForView(view, anchor = todayIso()) {
  const date = parseIsoDate(anchor) ?? parseIsoDate(todayIso());

  if (view === 'daily') {
    const iso = toIsoDate(date);
    return { start_date: iso, end_date: iso };
  }

  if (view === 'monthly') {
    const start = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth(), 1));
    const end = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0));
    return { start_date: toIsoDate(start), end_date: toIsoDate(end) };
  }

  const weekday = date.getUTCDay() || 7;
  const start = new Date(date.getTime() - (weekday - 1) * DAY_MS);
  const end = new Date(start.getTime() + 6 * DAY_MS);
  return { start_date: toIsoDate(start), end_date: toIsoDate(end) };
}

export function shiftRange(range, view, direction) {
  const start = parseIsoDate(range.start_date) ?? parseIsoDate(todayIso());
  const amount = view === 'daily' ? 1 : view === 'monthly' ? 32 : 7;
  const anchor = new Date(start.getTime() + amount * direction * DAY_MS);
  return rangeForView(view, toIsoDate(anchor));
}

export function displayRange(range) {
  const start = formatDisplayDate(range?.start_date);
  const end = formatDisplayDate(range?.end_date);
  return start === end ? start : `${start} to ${end}`;
}
