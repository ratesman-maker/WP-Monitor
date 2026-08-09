import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from '@/lib/api';
import { useAuthStore } from '@/stores/authStore';

// Mock global fetch
const mockFetch = vi.fn();
vi.stubGlobal('fetch', mockFetch);

// Mock the refresh module so the interceptor can be tested in isolation.
const mockRefreshAccessToken = vi.fn();
vi.mock('@/modules/auth/lib/refresh', () => ({
  refreshAccessToken: () => mockRefreshAccessToken(),
  isRefreshing: () => false,
}));

function jsonResponse(data: unknown, status = 200): Response {
  const body = typeof data === 'string' ? data : JSON.stringify(data);
  return {
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(data),
    text: () => Promise.resolve(body),
    headers: new Headers(),
  } as Response;
}

function noContentResponse(status = 204): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(null),
    text: () => Promise.resolve(''),
    headers: new Headers(),
  } as Response;
}

function errorResponse(status: number, message: string): Response {
  return {
    ok: false,
    status,
    json: () => Promise.resolve({ message }),
    text: () => Promise.resolve(JSON.stringify({ message })),
    headers: new Headers(),
  } as Response;
}

describe('api client', () => {
  afterEach(() => {
    mockFetch.mockReset();
    mockRefreshAccessToken.mockReset();
    useAuthStore.setState({
      user: null,
      token: null,
      refreshToken: null,
      csrfToken: null,
      sessionId: null,
      isAuthenticated: false,
    });
  });

  describe('get', () => {
    it('sends GET request with correct URL', async () => {
      mockFetch.mockResolvedValue(jsonResponse({ status: 'ok' }));
      await api.get('/health');
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/api/health',
        expect.objectContaining({ method: 'GET', headers: expect.objectContaining({ 'Content-Type': 'application/json' }) }),
      );
    });

    it('returns parsed JSON response', async () => {
      mockFetch.mockResolvedValue(jsonResponse({ status: 'ok', version: '1.0' }));
      const result = await api.get<{ status: string; version: string }>('/health');
      expect(result).toEqual({ status: 'ok', version: '1.0' });
    });

    it('throws on non-ok response', async () => {
      mockFetch.mockResolvedValue(errorResponse(500, 'Server error'));
      await expect(api.get('/fail')).rejects.toThrow('Server error');
    });
  });

  describe('post', () => {
    it('sends POST with JSON body', async () => {
      mockFetch.mockResolvedValue(jsonResponse({ id: 1 }));
      await api.post('/sites', { name: 'test', url: 'https://example.com' });
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/api/sites',
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify({ name: 'test', url: 'https://example.com' }),
        }),
      );
    });

    it('sends POST without body when body is undefined', async () => {
      mockFetch.mockResolvedValue(jsonResponse({ id: 1 }));
      await api.post('/sites');
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/api/sites',
        expect.objectContaining({ method: 'POST', body: null }),
      );
    });
  });

  describe('put', () => {
    it('sends PUT with JSON body', async () => {
      mockFetch.mockResolvedValue(jsonResponse({ id: 1, name: 'updated' }));
      await api.put('/sites/1', { name: 'updated' });
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/api/sites/1',
        expect.objectContaining({ method: 'PUT', body: JSON.stringify({ name: 'updated' }) }),
      );
    });
  });

  describe('delete', () => {
    it('sends DELETE request', async () => {
      mockFetch.mockResolvedValue(noContentResponse(204));
      await api.delete('/sites/1');
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/api/sites/1',
        expect.objectContaining({ method: 'DELETE' }),
      );
    });

    it('returns undefined for 204 No Content', async () => {
      mockFetch.mockResolvedValue(noContentResponse(204));
      const result = await api.delete('/sites/1');
      expect(result).toBeUndefined();
    });
  });

  describe('auth token', () => {
    it('adds Authorization header when token is provided', async () => {
      mockFetch.mockResolvedValue(jsonResponse({}));
      await api.get('/me', { token: 'jwt-token-123' });
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/api/me',
        expect.objectContaining({
          headers: expect.objectContaining({ Authorization: 'Bearer jwt-token-123' }),
        }),
      );
    });
  });

  describe('absolute URLs', () => {
    it('uses absolute URL as-is when endpoint starts with http', async () => {
      mockFetch.mockResolvedValue(jsonResponse({}));
      await api.get('https://external.com/api/data');
      expect(mockFetch).toHaveBeenCalledWith(
        'https://external.com/api/data',
        expect.objectContaining({ method: 'GET' }),
      );
    });
  });

  describe('401 refresh interceptor', () => {
    it('refreshes and retries on 401', async () => {
      // First call returns 401, second (retry) returns 200
      mockFetch
        .mockResolvedValueOnce(errorResponse(401, 'Unauthorized'))
        .mockResolvedValueOnce(jsonResponse({ data: 'success' }));
      mockRefreshAccessToken.mockResolvedValue('new-token-456');

      const result = await api.get('/sites', { token: 'expired-token' });

      expect(mockRefreshAccessToken).toHaveBeenCalledTimes(1);
      expect(mockFetch).toHaveBeenCalledTimes(2);
      // Retry must use the new token from the refresh
      expect(mockFetch).toHaveBeenNthCalledWith(
        2,
        'http://localhost:8080/api/sites',
        expect.objectContaining({
          headers: expect.objectContaining({ Authorization: 'Bearer new-token-456' }),
        }),
      );
      expect(result).toEqual({ data: 'success' });
    });

    it('throws "Session expired" when refresh fails', async () => {
      mockFetch.mockResolvedValueOnce(errorResponse(401, 'Unauthorized'));
      mockRefreshAccessToken.mockResolvedValue(null); // refresh failed

      await expect(api.get('/sites', { token: 'expired' })).rejects.toThrow('Session expired');
      expect(mockFetch).toHaveBeenCalledTimes(1); // no retry
    });

    it('does not attempt refresh for /auth/refresh endpoint', async () => {
      mockFetch.mockResolvedValueOnce(errorResponse(401, 'Unauthorized'));

      await expect(api.post('/auth/refresh')).rejects.toThrow('Unauthorized');
      expect(mockRefreshAccessToken).not.toHaveBeenCalled();
    });

    it('does not attempt refresh for /auth/login endpoint', async () => {
      mockFetch.mockResolvedValueOnce(errorResponse(401, 'Unauthorized'));

      await expect(api.post('/auth/login', { username: 'x', password: 'y' })).rejects.toThrow('Unauthorized');
      expect(mockRefreshAccessToken).not.toHaveBeenCalled();
    });

    it('does not attempt refresh for /auth/setup endpoint', async () => {
      mockFetch.mockResolvedValueOnce(errorResponse(401, 'Unauthorized'));

      await expect(api.post('/auth/setup', { username: 'x', password: 'y' })).rejects.toThrow('Unauthorized');
      expect(mockRefreshAccessToken).not.toHaveBeenCalled();
    });

    it('retries only once (no infinite loop)', async () => {
      // Both the first and retry return 401 — must not loop
      mockFetch
        .mockResolvedValueOnce(errorResponse(401, 'Unauthorized'))
        .mockResolvedValueOnce(errorResponse(401, 'Unauthorized'));
      mockRefreshAccessToken.mockResolvedValue('new-token');

      await expect(api.get('/sites', { token: 'expired' })).rejects.toThrow('Unauthorized');
      expect(mockFetch).toHaveBeenCalledTimes(2); // original + 1 retry
      expect(mockRefreshAccessToken).toHaveBeenCalledTimes(1);
    });
  });
});
