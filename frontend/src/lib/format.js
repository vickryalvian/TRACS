export function formatNumber(value, options = {}) {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) {
    return '';
  }

  return new Intl.NumberFormat('en-US', options).format(numericValue);
}

export function fallbackText(value, fallback = '—') {
  const text = String(value ?? '').trim();
  return text || fallback;
}
