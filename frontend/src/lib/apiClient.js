const DEFAULT_CSRF_META_NAME = 'csrf-token';

export class ApiError extends Error {
  constructor(message, {
    status = 0,
    data = null,
    errors = [],
    meta = {},
    response = null,
  } = {}) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.data = data;
    this.errors = errors;
    this.meta = meta;
    this.response = response;
  }
}

function readCsrfToken(metaName) {
  if (typeof document === 'undefined') {
    return '';
  }

  return document.querySelector(`meta[name="${metaName}"]`)?.content ?? '';
}

async function readJson(response) {
  const contentType = response.headers.get('content-type') ?? '';
  if (!contentType.includes('application/json')) {
    throw new ApiError('The server returned an unexpected response.', {
      status: response.status,
      response,
    });
  }

  return response.json();
}

function normalizeErrors(errors) {
  if (Array.isArray(errors)) {
    return errors;
  }
  if (errors && typeof errors === 'object') {
    return errors;
  }
  return [];
}

export function createApiClient({
  baseUrl = '',
  csrfMetaName = DEFAULT_CSRF_META_NAME,
  csrfHeaderName = 'X-CSRF-Token',
  fetchImpl = globalThis.fetch,
  onSessionExpired,
  onPermissionDenied,
} = {}) {
  if (typeof fetchImpl !== 'function') {
    throw new TypeError('A fetch implementation is required.');
  }

  async function request(path, options = {}) {
    const {
      csrfToken: requestCsrfToken,
      csrfHeaderName: requestCsrfHeaderName,
      ...fetchOptions
    } = options;
    const method = (fetchOptions.method ?? 'GET').toUpperCase();
    const headers = new Headers(options.headers);
    const isFormData =
      typeof FormData !== 'undefined' && options.body instanceof FormData;
    const shouldSerializeBody =
      options.body &&
      !isFormData &&
      typeof options.body === 'object' &&
      !(options.body instanceof URLSearchParams);
    const body = shouldSerializeBody ? JSON.stringify(options.body) : options.body;

    headers.set('Accept', 'application/json');

    if (body && !isFormData && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
      const csrfToken = requestCsrfToken ?? readCsrfToken(csrfMetaName);
      if (csrfToken) {
        headers.set(requestCsrfHeaderName ?? csrfHeaderName, csrfToken);
      }
    }

    const response = await fetchImpl(`${baseUrl}${path}`, {
      ...fetchOptions,
      method,
      headers,
      body,
      credentials: 'same-origin',
    });
    const payload = await readJson(response);

    if (response.status === 401) {
      onSessionExpired?.(payload);
    }
    if (response.status === 403) {
      onPermissionDenied?.(payload);
    }

    if (!response.ok || payload.success !== true) {
      throw new ApiError(payload.message || 'The request could not be completed.', {
        status: response.status,
        data: payload.data ?? null,
        errors: normalizeErrors(payload.errors),
        meta: payload.meta ?? {},
        response,
      });
    }

    return {
      success: true,
      message: payload.message ?? '',
      data: payload.data ?? null,
      errors: normalizeErrors(payload.errors),
      meta: payload.meta ?? {},
    };
  }

  return { request };
}
