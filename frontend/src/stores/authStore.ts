import { create } from 'zustand';
import { persist, createJSONStorage } from 'zustand/middleware';

interface AuthUser {
  id: number;
  username: string;
  role: 'admin' | 'manager' | 'viewer';
  email?: string | null;
}

interface AuthTokens {
  token: string;
  refreshToken: string;
  csrfToken: string;
  sessionId: string;
}

interface AuthState {
  user: AuthUser | null;
  // Access token lives only in memory — never persisted (XSS safety).
  token: string | null;
  // Refresh token is stored in an httpOnly cookie by the backend; the frontend
  // never reads or persists it. Kept in memory only for backward-compatible
  // API calls that still send it in the body.
  refreshToken: string | null;
  csrfToken: string | null;
  sessionId: string | null;
  isAuthenticated: boolean;
  login: (user: AuthUser, tokens: AuthTokens) => void;
  logout: () => void;
  setAccessToken: (token: string) => void;
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set) => ({
      user: null,
      token: null,
      refreshToken: null,
      csrfToken: null,
      sessionId: null,
      isAuthenticated: false,
      login: (user, tokens) =>
        set({
          user,
          token: tokens.token,
          refreshToken: tokens.refreshToken,
          csrfToken: tokens.csrfToken,
          sessionId: tokens.sessionId,
          isAuthenticated: true,
        }),
      logout: () =>
        set({
          user: null,
          token: null,
          refreshToken: null,
          csrfToken: null,
          sessionId: null,
          isAuthenticated: false,
        }),
      setAccessToken: (token) =>
        set((state) => ({ token, isAuthenticated: !!token && !!state.user })),
    }),
    {
      name: 'wpm-auth',
      storage: createJSONStorage(() => sessionStorage),
      // Persist only non-sensitive data. Access + refresh tokens stay in memory
      // (refresh token is in an httpOnly cookie managed by the backend).
      partialize: (state) => ({
        user: state.user,
        csrfToken: state.csrfToken,
        sessionId: state.sessionId,
      }),
    },
  ),
);
