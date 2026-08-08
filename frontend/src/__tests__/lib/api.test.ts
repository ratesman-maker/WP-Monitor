import { afterEach, describe, expect, it, vi } from 'vitest';

import { api } from '@/lib/api';

// Mock global fetch
const mockFetch = vi.fn();
vi.stubGlobal('fetch', mockFetch);

function jsonResponse(data: unknown, status = 200): Response {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: () => Promise.resolve(data),
    headers: new Headers(),
  } as Response;
}

describe('api client', () => {
  afterEach(() => {
    mockFetch.mockReset();
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
      mockFetch.mockResolvedValue({
        ok: false,
        status: 500,
        json: () => Promise.resolve({ message: 'Server error' }),
        headers: new Headers(),
      } as Response);
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
      mockFetch.mockResolvedValue(jsonResponse(null, 204));
      await api.delete('/sites/1');
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8080/api/sites/1',
        expect.objectContaining({ method: 'DELETE' }),
      );
    });

    it('returns undefined for 204 No Content', async () => {
      mockFetch.mockResolvedValue(jsonResponse(null, 204));
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
});
