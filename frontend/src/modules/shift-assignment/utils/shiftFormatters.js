import { fallbackText, formatNumber } from '../../../lib/format';

export function minutesLabel(value) {
  const minutes = Number(value);
  if (!Number.isFinite(minutes)) {
    return '0h';
  }

  const hours = Math.floor(minutes / 60);
  const remainder = minutes % 60;
  return remainder ? `${hours}h ${remainder}m` : `${hours}h`;
}

export function assignmentTypeLabel(value) {
  return fallbackText(value, 'Shift').replaceAll('_', ' ');
}

export function statusTone(status) {
  if (['completed', 'confirmed', 'active'].includes(status)) {
    return 'success';
  }
  if (['cancelled', 'no_show', 'replaced'].includes(status)) {
    return 'danger';
  }
  if (status === 'assigned') {
    return 'info';
  }
  return 'neutral';
}

export function summaryValue(value) {
  return formatNumber(value) || '0';
}
