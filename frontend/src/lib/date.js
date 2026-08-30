const ISO_DATE_PATTERN = /^(\d{4})-(\d{2})-(\d{2})$/;

export function formatDisplayDate(isoDate) {
  const match = ISO_DATE_PATTERN.exec(isoDate ?? '');
  if (!match) {
    return '';
  }

  const [, year, month, day] = match;
  return `${day}-${month}-${year}`;
}

export function formatIsoDate(displayDate) {
  const match = /^(\d{2})-(\d{2})-(\d{4})$/.exec(displayDate ?? '');
  if (!match) {
    return '';
  }

  const [, day, month, year] = match;
  return `${year}-${month}-${day}`;
}
