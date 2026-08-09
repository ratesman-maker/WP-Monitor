import { beforeEach, describe, expect, it } from 'vitest';

import { useAuthStore } from '@/stores/authStore';

describe('authStore', () => {
  beforeEach(() => {
    // Clear persisted state in sessionStorage before each test
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

  describe('initial state', () => {
    it('starts unauthenticated', () => {
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(false);
      expect(state.user).toBeNull();
      expect(state.token).toBeNull();
      expect(state.csrfToken).toBeNull();
      expect(state.refreshToken).toBeNull();
      expect(state.sessionId).toBeNull();
    });
  });

  describe('login', () => {
    it('sets user, tokens, and isAuthenticated flag', () => {
      const user = { id: 1, username: 'admin', role: 'admin' as const };
      useAuthStore.getState().login(user, {
        token: 'jwt-token-abc',
        refreshToken: 'refresh-abc',
        csrfToken: 'csrf-abc',
        sessionId: 'session-abc',
      });
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(true);
      expect(state.user).toEqual(user);
      expect(state.token).toBe('jwt-token-abc');
      expect(state.refreshToken).toBe('refresh-abc');
      expect(state.csrfToken).toBe('csrf-abc');
      expect(state.sessionId).toBe('session-abc');
    });

    it('accepts different roles', () => {
      const manager = { id: 2, username: 'manager1', role: 'manager' as const };
      useAuthStore.getState().login(manager, {
        token: 'token-2',
        refreshToken: 'refresh-2',
        csrfToken: 'csrf-2',
        sessionId: 'session-2',
      });
      expect(useAuthStore.getState().user?.role).toBe('manager');
    });
  });

  describe('logout', () => {
    it('clears user, tokens, and isAuthenticated flag', () => {
      useAuthStore.getState().login(
        { id: 1, username: 'admin', role: 'admin' },
        {
          token: 'jwt',
          refreshToken: 'refresh',
          csrfToken: 'csrf',
          sessionId: 'session',
        },
      );
      expect(useAuthStore.getState().isAuthenticated).toBe(true);

      useAuthStore.getState().logout();
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(false);
      expect(state.user).toBeNull();
      expect(state.token).toBeNull();
      expect(state.refreshToken).toBeNull();
      expect(state.csrfToken).toBeNull();
      expect(state.sessionId).toBeNull();
    });
  });

  describe('setAccessToken', () => {
    it('updates the access token and sets isAuthenticated when user exists', () => {
      useAuthStore.getState().login(
        { id: 1, username: 'admin', role: 'admin' },
        {
          token: 'old-token',
          refreshToken: 'refresh',
          csrfToken: 'csrf',
          sessionId: 'session',
        },
      );
      useAuthStore.getState().setAccessToken('new-token');
      const state = useAuthStore.getState();
      expect(state.token).toBe('new-token');
      expect(state.isAuthenticated).toBe(true);
    });

    it('does not authenticate when no user is present', () => {
      // Simulate the state after a page reload: user is null, token is null
      useAuthStore.getState().setAccessToken('some-token');
      const state = useAuthStore.getState();
      expect(state.token).toBe('some-token');
      expect(state.isAuthenticated).toBe(false); // no user → not authenticated
    });
  });

  describe('persistence', () => {
    it('persists user, csrfToken, sessionId to sessionStorage', () => {
      useAuthStore.getState().login(
        { id: 1, username: 'admin', role: 'admin' },
        {
          token: 'jwt',
          refreshToken: 'refresh',
          csrfToken: 'csrf',
          sessionId: 'session',
        },
      );
      const persisted = JSON.parse(sessionStorage.getItem('wpm-auth') ?? '{}') as Record<string, unknown>;
      // state field contains the partialized state
      const state = (persisted.state ?? persisted) as Record<string, unknown>;
      expect(state.user).toEqual({ id: 1, username: 'admin', role: 'admin' });
      expect(state.csrfToken).toBe('csrf');
      expect(state.sessionId).toBe('session');
    });

    it('does NOT persist the access token', () => {
      useAuthStore.getState().login(
        { id: 1, username: 'admin', role: 'admin' },
        {
          token: 'jwt-secret',
          refreshToken: 'refresh-secret',
          csrfToken: 'csrf',
          sessionId: 'session',
        },
      );
      const persisted = JSON.parse(sessionStorage.getItem('wpm-auth') ?? '{}') as Record<string, unknown>;
      const state = (persisted.state ?? persisted) as Record<string, unknown>;
      expect(state.token).toBeUndefined();
      expect(state.refreshToken).toBeUndefined();
    });

    it('clears persisted state on logout', () => {
      useAuthStore.getState().login(
        { id: 1, username: 'admin', role: 'admin' },
        {
          token: 'jwt',
          refreshToken: 'refresh',
          csrfToken: 'csrf',
          sessionId: 'session',
        },
      );
      expect(sessionStorage.getItem('wpm-auth')).not.toBeNull();
      useAuthStore.getState().logout();
      const persisted = JSON.parse(sessionStorage.getItem('wpm-auth') ?? '{}') as Record<string, unknown>;
      const state = (persisted.state ?? persisted) as Record<string, unknown>;
      expect(state.user).toBeNull();
      expect(state.csrfToken).toBeNull();
      expect(state.sessionId).toBeNull();
    });
  });
});
