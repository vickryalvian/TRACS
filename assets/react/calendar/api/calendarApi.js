const BASE = '/api/calendar';

async function request(path, options = {}) {
  const url = path.startsWith('/') ? path : `${BASE}/${path}`;
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
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
  markDone(event) {
    const path = event.source === 'reminders' ? '/api/reminder-toggle.php' : '/api/task-toggle.php';
    return request(path, {
      method: 'POST',
      body: JSON.stringify({ id: event.source_id, is_completed: true }),
    });
  },
};
