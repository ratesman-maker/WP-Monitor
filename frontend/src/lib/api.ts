import i18n from '@/i18n';
import { refreshAccessToken } from '@/modules/auth/lib/refresh';

const API_URL = (window.__ENV__?.VITE_API_URL ?? import.meta.env.VITE_API_URL) + '';

interface ApiRequestOptions extends RequestInit {
  token?: string;
  csrfToken?: string;
}

/**
 * Endpoints that must NOT trigger the 401 refresh interceptor.
 * - `/auth/refresh` — calling refresh from within refresh would loop forever
 * - `/auth/login`    — 401 here means bad credentials, not an expired token
 * - `/auth/setup`    — first-time setup, no session involved
 */
function shouldSkipRefresh(endpoint: string): boolean {
  return (
    endpoint.includes('/auth/refresh') ||
    endpoint.includes('/auth/login') ||
    endpoint.includes('/auth/setup')
  );
}

async function apiRequest<T>(
  endpoint: string,
  options: ApiRequestOptions = {}
): Promise<T> {
  const { token, csrfToken, ...fetchOptions } = options;
  const url = endpoint.startsWith('http') ? endpoint : `${API_URL}${endpoint}`;

  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(options.headers as Record<string, string>),
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  // CSRF token for state-changing requests (double-submit cookie pattern)
  const method = (fetchOptions.method ?? 'GET').toUpperCase();
  if (csrfToken && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
    headers['X-CSRF-Token'] = csrfToken;
  }

  const response = await fetch(url, {
    ...fetchOptions,
    headers,
    credentials: 'include', // send cookies (wpm_csrf, wpm_refresh) for CSRF + refresh
  });

  // 401 interceptor — attempt a single token refresh, then retry once.
  // Skipped for auth endpoints that don't use an access token.
  if (response.status === 401 && !shouldSkipRefresh(endpoint)) {
    const newToken = await refreshAccessToken();
    if (newToken) {
      const retryHeaders = { ...headers, Authorization: `Bearer ${newToken}` };
      const retryResponse = await fetch(url, {
        ...fetchOptions,
        headers: retryHeaders,
        credentials: 'include',
      });
      return parseResponse<T>(retryResponse, endpoint);
    }
    // Refresh failed — refreshAccessToken already cleared the store.
    throw new Error(i18n.t('error.sessionExpired'));
  }

  return parseResponse<T>(response, endpoint);
}

async function parseResponse<T>(response: Response, endpoint: string): Promise<T> {
  if (!response.ok) {
    const error = await response.json().catch(() => ({ message: 'Request failed' }));
    throw new Error((error as { message?: string }).message ?? `HTTP ${response.status}`);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  // Endpoints that return no body (e.g. logout 204) are handled above.
  // For empty 200 responses, tolerate missing body.
  const text = await response.text();
  if (text === '') {
    return undefined as T;
  }
  try {
    return JSON.parse(text) as T;
  } catch {
    throw new Error(`Invalid JSON response from ${endpoint}`);
  }
}

export const api = {
  get: <T>(endpoint: string, options?: ApiRequestOptions) =>
    apiRequest<T>(endpoint, { ...options, method: 'GET' }),

  post: <T>(endpoint: string, body?: unknown, options?: ApiRequestOptions) =>
    apiRequest<T>(endpoint, {
      ...options,
      method: 'POST',
      body: body ? JSON.stringify(body) : null,
    }),

  put: <T>(endpoint: string, body?: unknown, options?: ApiRequestOptions) =>
    apiRequest<T>(endpoint, {
      ...options,
      method: 'PUT',
      body: body ? JSON.stringify(body) : null,
    }),

  delete: <T>(endpoint: string, options?: ApiRequestOptions) =>
    apiRequest<T>(endpoint, { ...options, method: 'DELETE' }),
};
