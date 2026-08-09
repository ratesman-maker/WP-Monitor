import { waitFor } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import { useInitAuth } from '@/modules/auth/hooks/useInitAuth';
import { useAuthStore } from '@/stores/authStore';
import { renderHookWithProviders } from '@/test/testUtils';

// Mock the refresh module so we don't hit the network
const mockRefreshAccessToken = vi.fn();
vi.mock('@/modules/auth/lib/refresh', () => ({
  refreshAccessToken: () => mockRefreshAccessToken(),
  isRefreshing: () => false,
}));

describe('useInitAuth', () => {
  beforeEach(() => {
    sessionStorage.removeItem('wpm-auth');
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

  it('does not attempt refresh when no user is present', () => {
    useAuthStore.setState({ user: null, token: null });
    const { result } = renderHookWithProviders(() => useInitAuth());
    expect(mockRefreshAccessToken).not.toHaveBeenCalled();
    expect(result.current.isInitializing).toBe(false);
  });

  it('does not attempt refresh when a token is already present', () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: 'existing-token',
      isAuthenticated: true,
    });
    const { result } = renderHookWithProviders(() => useInitAuth());
    expect(mockRefreshAccessToken).not.toHaveBeenCalled();
    expect(result.current.isInitializing).toBe(false);
  });

  it('synchronously sets isInitializing=true when user present but token missing', () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: null,
      isAuthenticated: false,
    });
    // Hold the refresh promise so we can observe isInitializing=true
    let resolveRefresh!: (token: string | null) => void;
    mockRefreshAccessToken.mockReturnValue(
      new Promise<string | null>((resolve) => {
        resolveRefresh = resolve;
      }),
    );

    const { result } = renderHookWithProviders(() => useInitAuth());
    // Must be true on the very first render (synchronous) — before any effect runs
    expect(result.current.isInitializing).toBe(true);
    expect(mockRefreshAccessToken).toHaveBeenCalledTimes(1);

    resolveRefresh('new-token');
  });

  it('sets isInitializing to false after refresh completes', async () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: null,
      isAuthenticated: false,
    });
    mockRefreshAccessToken.mockResolvedValue('new-token');

    const { result } = renderHookWithProviders(() => useInitAuth());
    expect(result.current.isInitializing).toBe(true);

    await waitFor(() => {
      expect(result.current.isInitializing).toBe(false);
    });
  });

  it('sets isInitializing to false even when refresh fails', async () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: null,
      isAuthenticated: false,
    });
    mockRefreshAccessToken.mockResolvedValue(null);

    const { result } = renderHookWithProviders(() => useInitAuth());
    expect(result.current.isInitializing).toBe(true);

    await waitFor(() => {
      expect(result.current.isInitializing).toBe(false);
    });
  });

  it('reports isAuthenticated=false when token is missing', () => {
    useAuthStore.setState({
      user: { id: 1, username: 'admin', role: 'admin' },
      token: null,
      isAuthenticated: false,
    });
    mockRefreshAccessToken.mockResolvedValue('new-token');
    const { result } = renderHookWithProviders(() => useInitAuth());
    expect(result.current.isAuthenticated).toBe(false);
  });
});
