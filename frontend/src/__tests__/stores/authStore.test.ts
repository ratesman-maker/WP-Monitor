import { beforeEach, describe, expect, it } from 'vitest';

import { useAuthStore } from '@/stores/authStore';

describe('authStore', () => {
  beforeEach(() => {
    // Reset store to initial state before each test
    useAuthStore.setState({ user: null, token: null, isAuthenticated: false });
  });

  describe('initial state', () => {
    it('starts unauthenticated', () => {
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(false);
      expect(state.user).toBeNull();
      expect(state.token).toBeNull();
    });
  });

  describe('login', () => {
    it('sets user, token, and isAuthenticated flag', () => {
      const user = { id: 1, username: 'admin', role: 'admin' as const };
      useAuthStore.getState().login(user, 'jwt-token-abc');
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(true);
      expect(state.user).toEqual(user);
      expect(state.token).toBe('jwt-token-abc');
    });

    it('accepts different roles', () => {
      const manager = { id: 2, username: 'manager1', role: 'manager' as const };
      useAuthStore.getState().login(manager, 'token-2');
      expect(useAuthStore.getState().user?.role).toBe('manager');
    });
  });

  describe('logout', () => {
    it('clears user, token, and isAuthenticated flag', () => {
      useAuthStore.getState().login({ id: 1, username: 'admin', role: 'admin' }, 'jwt');
      expect(useAuthStore.getState().isAuthenticated).toBe(true);

      useAuthStore.getState().logout();
      const state = useAuthStore.getState();
      expect(state.isAuthenticated).toBe(false);
      expect(state.user).toBeNull();
      expect(state.token).toBeNull();
    });
  });
});
