import { useAuthStore } from '@/stores/authStore';

/**
 * Single-flight refresh — if multiple requests get 401 simultaneously,
 * only one /auth/refresh call is made; the others await the same promise.
 *
 * The refresh token is sent automatically by the browser via the
 * `wpm_refresh` httpOnly cookie (credentials: 'include').
 *
 * This module calls fetch() directly (instead of going through @/lib/api)
 * to avoid a circular dependency: lib/api.ts → auth/lib/refresh.ts →
 * auth/api.ts → lib/api.ts. The relative URL `/api/auth/refresh` is
 * resolved by the Vite dev proxy in development and same-origin in prod.
 */
let refreshPromise: Promise<string | null> | null = null;

/**
 * Attempt to refresh the access token.
 *
 * @returns the new access token, or `null` if the refresh failed
 *          (in which case the auth store is cleared → user is logged out).
 */
export function refreshAccessToken(): Promise<string | null> {
  if (refreshPromise) {
    return refreshPromise;
  }

  refreshPromise = fetch('/api/auth/refresh', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'include', // send the wpm_refresh httpOnly cookie
  })
    .then(async (response) => {
      if (!response.ok) {
        throw new Error(`Refresh failed: HTTP ${response.status}`);
      }
      const data = (await response.json()) as { token: string; refreshToken: string };
      const { setAccessToken, logout } = useAuthStore.getState();
      if (!data?.token) {
        logout();
        return null;
      }
      setAccessToken(data.token);
      return data.token;
    })
    .catch(() => {
      // Refresh failed (invalid/expired refresh token, network error, etc.)
      // → clear the session and force re-login.
      useAuthStore.getState().logout();
      return null;
    })
    .finally(() => {
      refreshPromise = null;
    });

  return refreshPromise;
}

/**
 * Whether a refresh is currently in flight.
 */
export function isRefreshing(): boolean {
  return refreshPromise !== null;
}
