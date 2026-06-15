import { createApiClient } from '../../lib/apiClient';

const apiClient = createApiClient();

function queryString(filters) {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (value !== '' && value !== null && value !== undefined) {
      params.set(key, String(value));
    }
  });

  return params.toString();
}

export async function loadGlobalContext() {
  return apiClient.request('/api/v1/context.php');
}

export async function loadShiftContext() {
  return apiClient.request('/api/v1/shift-assignment/context.php');
}

export async function loadShiftAssignments(filters, options = {}) {
  const query = queryString(filters);
  return apiClient.request(
    `/api/v1/shift-assignment/assignments.php${query ? `?${query}` : ''}`,
    options,
  );
}

export async function createShiftAssignment(payload, csrf = {}) {
  return apiClient.request('/api/v1/shift-assignment/assignments.php', {
    method: 'POST',
    body: payload,
    csrfToken: csrf.token ?? '',
    csrfHeaderName: csrf.header ?? 'X-CSRF-Token',
  });
}

export async function updateShiftAssignment(assignmentId, payload, csrf = {}) {
  return apiClient.request(
    `/api/v1/shift-assignment/assignment.php?id=${encodeURIComponent(assignmentId)}`,
    {
      method: 'PATCH',
      body: payload,
      csrfToken: csrf.token ?? '',
      csrfHeaderName: csrf.header ?? 'X-CSRF-Token',
    },
  );
}

export async function deleteShiftAssignment(assignmentId, csrf = {}) {
  return apiClient.request(
    `/api/v1/shift-assignment/assignment.php?id=${encodeURIComponent(assignmentId)}`,
    {
      method: 'DELETE',
      csrfToken: csrf.token ?? '',
      csrfHeaderName: csrf.header ?? 'X-CSRF-Token',
    },
  );
}

export async function previewShiftTemplate(payload, csrf = {}) {
  return apiClient.request('/api/v1/shift-assignment/templates/preview.php', {
    method: 'POST',
    body: payload,
    csrfToken: csrf.token ?? '',
    csrfHeaderName: csrf.header ?? 'X-CSRF-Token',
  });
}
