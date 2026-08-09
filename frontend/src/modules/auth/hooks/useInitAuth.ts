import { useEffect, useState } from 'react';

import { refreshAccessToken } from '../lib/refresh';

import { useAuthStore } from '@/stores/authStore';

interface InitAuthState {
  isInitializing: boolean;
  isAuthenticated: boolean;
}

/**
 * Silent session restoration on app start.
 *
 * After a page reload, the persisted auth store contains `{user, csrfToken,
 * sessionId}` (from sessionStorage) but the access token is null (it was
 * in memory only). This hook detects that state and attempts a single
 * silent refresh via the httpOnly `wpm_refresh` cookie. If the refresh
 * succeeds, the access token is restored and the user stays logged in.
 * If it fails, the store is cleared and the user is redirected to /login.
 *
 * Runs once on mount. Reads from `useAuthStore.getState()` inside the
 * effect to avoid re-running when the store changes.
 */
export function useInitAuth(): InitAuthState {
  // Lazy initial state — synchronously check if we need to restore the session
  // BEFORE the first render, so ProtectedRoute sees isInitializing=true on
  // the very first render and shows the loading skeleton instead of redirecting.
  const [isInitializing, setIsInitializing] = useState<boolean>(() => {
    const { user, token } = useAuthStore.getState();
    return !!user && !token;
  });

  useEffect(() => {
    if (!isInitializing) {
      return;
    }
    refreshAccessToken().finally(() => setIsInitializing(false));
  }, [isInitializing]);

  const token = useAuthStore((s) => s.token);
  const user = useAuthStore((s) => s.user);

  return {
    isInitializing,
    isAuthenticated: !!token && !!user,
  };
}
