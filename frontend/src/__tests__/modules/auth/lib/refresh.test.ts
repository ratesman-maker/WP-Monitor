import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { refreshAccessToken, isRefreshing } from '@/modules/auth/lib/refresh';
import { useAuthStore } from '@/stores/authStore';

const mockFetch = vi.fn();
vi.stubGlobal('fetch', mockFetch);

describe('refreshAccessToken', () => {
  beforeEach(() => {
    sessionStorage.removeItem('wpm-auth');
    useAuthStore.setState({
      user: null,
      token: null,
      refreshToken: null,
      csrfToken: null,
      sessionId: null,
      isAuthenticated: false,
    });
  });

  afterEach(() => {
    mockFetch.mockReset();
  });

  it('calls /api/auth/refresh with credentials: include', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ token: 'new-token', refreshToken: 'new-refresh' }),
      text: () => Promise.resolve(JSON.stringify({ token: 'new-token', refreshToken: 'new-refresh' })),
      headers: new Headers(),
    } as Response);

    await refreshAccessToken();

    expect(mockFetch).toHaveBeenCalledWith(
      '/api/auth/refresh',
      expect.objectContaining({
        method: 'POST',
        credentials: 'include',
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
      }),
    );
  });

  it('updates the auth store with the new access token on success', async () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: null,
      isAuthenticated: false,
    });
    mockFetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ token: 'new-token', refreshToken: 'new-refresh' }),
      text: () => Promise.resolve(JSON.stringify({ token: 'new-token', refreshToken: 'new-refresh' })),
      headers: new Headers(),
    } as Response);

    const token = await refreshAccessToken();

    expect(token).toBe('new-token');
    expect(useAuthStore.getState().token).toBe('new-token');
    expect(useAuthStore.getState().isAuthenticated).toBe(true);
  });

  it('returns null and logs out on HTTP failure', async () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: 'old-token',
      isAuthenticated: true,
    });
    mockFetch.mockResolvedValue({
      ok: false,
      status: 401,
      json: () => Promise.resolve({ message: 'Invalid refresh token' }),
      text: () => Promise.resolve(JSON.stringify({ message: 'Invalid refresh token' })),
      headers: new Headers(),
    } as Response);

    const token = await refreshAccessToken();

    expect(token).toBeNull();
    expect(useAuthStore.getState().token).toBeNull();
    expect(useAuthStore.getState().user).toBeNull();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  it('returns null and logs out on network error', async () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: 'old-token',
      isAuthenticated: true,
    });
    mockFetch.mockRejectedValue(new Error('Network error'));

    const token = await refreshAccessToken();

    expect(token).toBeNull();
    expect(useAuthStore.getState().isAuthenticated).toBe(false);
  });

  it('uses single-flight — concurrent calls share one promise', async () => {
    mockFetch.mockResolvedValue({
      ok: true,
      status: 200,
      json: () => Promise.resolve({ token: 'new-token', refreshToken: 'new-refresh' }),
      text: () => Promise.resolve(JSON.stringify({ token: 'new-token', refreshToken: 'new-refresh' })),
      headers: new Headers(),
    } as Response);

    const promise1 = refreshAccessToken();
    const promise2 = refreshAccessToken();

    expect(isRefreshing()).toBe(true);
    const [token1, token2] = await Promise.all([promise1, promise2]);

    expect(token1).toBe('new-token');
    expect(token2).toBe('new-token');
    // Only one fetch call despite two concurrent refresh requests
    expect(mockFetch).toHaveBeenCalledTimes(1);
    expect(isRefreshing()).toBe(false);
  });
});
