import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { describe, expect, it, beforeEach } from 'vitest';

import App from '@/App';
import { useAuthStore } from '@/stores/authStore';

function renderApp(initialRoute = '/') {
  const queryClient = new QueryClient({
    defaultOptions: { queries: { retry: false }, mutations: { retry: false } },
  });

  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[initialRoute]}>
        <App />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('App', () => {
  describe('unauthenticated', () => {
    beforeEach(() => {
      useAuthStore.setState({
        user: null,
        token: null,
        refreshToken: null,
        csrfToken: null,
        sessionId: null,
        isAuthenticated: false,
      });
    });

    it('redirects to login page when not authenticated', () => {
      renderApp('/');
      expect(screen.getByText('WP Monitor')).toBeInTheDocument();
      expect(screen.getByText('Sign in to your account to continue.')).toBeInTheDocument();
    });

    it('renders login form on /login route', () => {
      renderApp('/login');
      expect(screen.getByLabelText(/username/i)).toBeInTheDocument();
      expect(screen.getByLabelText(/master password/i)).toBeInTheDocument();
    });
  });

  describe('authenticated', () => {
    beforeEach(() => {
      useAuthStore.setState({
        isAuthenticated: true,
        user: { id: 1, username: 'admin', role: 'admin' },
        token: 'test-token',
        refreshToken: 'test-refresh',
        csrfToken: 'test-csrf',
        sessionId: 'test-session',
      });
    });

    it('renders WP Monitor title in sidebar', () => {
      renderApp();
      expect(screen.getByText('WP Monitor')).toBeInTheDocument();
    });

    it('renders Dashboard link', () => {
      renderApp();
      expect(screen.getByRole('link', { name: 'Dashboard' })).toBeInTheDocument();
    });

    it('renders Sites link', () => {
      renderApp();
      expect(screen.getByRole('link', { name: 'Sites' })).toBeInTheDocument();
    });

    it('renders Settings link', () => {
      renderApp();
      expect(screen.getByRole('link', { name: 'Settings' })).toBeInTheDocument();
    });

    it('renders Dashboard content on / route', () => {
      renderApp('/');
      expect(screen.getByText('Dashboard — coming soon')).toBeInTheDocument();
    });

    it('renders Sites content on /sites route', () => {
      renderApp('/sites');
      expect(screen.getByText('Sites — coming soon')).toBeInTheDocument();
    });

    it('renders Settings content on /settings route', () => {
      renderApp('/settings');
      expect(screen.getByText('Settings — coming soon')).toBeInTheDocument();
    });
  });
});
