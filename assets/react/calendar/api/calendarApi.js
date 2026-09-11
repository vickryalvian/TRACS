const BASE = '/api/calendar';

async function request(path, options = {}) {
  const url = path.startsWith('/') ? path : `${BASE}/${path}`;
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
    ...options,
  });
  const payload = await response.json().catch(() => ({
    success: false,
    message: 'The server returned an invalid response.',
  }));
  if (!response.ok || !payload.success) {
    const error = new Error(payload.message || 'Calendar request failed.');
    error.status = response.status;
    error.errors = payload.errors || {};
    throw error;
  }
  if (options.method && options.method !== 'GET') {
    try { localStorage.setItem('tracs-calendar-updated', String(Date.now())); } catch { /* Storage may be disabled. */ }
    window.dispatchEvent(new CustomEvent('tracs-calendar-updated'));
  }
  return payload.data;
}

export const calendarApi = {
  events(start, end) {
    const params = new URLSearchParams({ start, end });
    return request(`events.php?${params}`);
  },
  metadata() {
    return request('metadata.php');
  },
  create(data) {
    return request('create.php', { method: 'POST', body: JSON.stringify(data) });
  },
  update(data) {
    return request('update.php', { method: 'POST', body: JSON.stringify(data) });
  },
  remove(id) {
    return request('delete.php', { method: 'POST', body: JSON.stringify({ id }) });
  },
  updateClientReminder(event, data) {
    return request('/api/v1/client-portfolio/actions.php', {
      method: 'POST', body: JSON.stringify({ action: 'update_followup', followup_id: event.meta.followup_id, ...data }),
    });
  },
  markDone(event) {
    if (event.source === 'clients') return this.updateClientReminder(event, { status: 'completed' });
    const path = event.source === 'reminders' ? '/api/reminder-toggle.php' : '/api/task-toggle.php';
    return request(path, {
      method: 'POST',
      body: JSON.stringify({ id: event.source_id, is_completed: true }),
    });
  },
};
