import { screen } from '@testing-library/react';
import { describe, expect, it, beforeEach } from 'vitest';

import App from '@/App';
import { useAuthStore } from '@/stores/authStore';
import { renderWithProviders } from '@/test/testUtils';

function renderApp(initialRoute = '/') {
  return renderWithProviders(<App />, { routerProps: { initialEntries: [initialRoute] } });
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

    it('renders SettingsToggles on login page (accessible before auth)', () => {
      renderApp('/login');
      // SettingsToggles renders 2 buttons (language + theme)
      const buttons = screen.getAllByRole('button');
      // "Sign in" submit button + 2 toggle buttons = 3 total
      expect(buttons.length).toBeGreaterThanOrEqual(3);
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

    it('renders SettingsToggles in sidebar when authenticated', () => {
      renderApp();
      // Sidebar contains the toggles (2 buttons) + Sign out button
      const buttons = screen.getAllByRole('button');
      expect(buttons.length).toBeGreaterThanOrEqual(3);
    });
  });

  describe('i18n — CS locale', () => {
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

    it('renders translated sidebar links in CS', () => {
      renderWithProviders(<App />, { locale: 'cs' });
      expect(screen.getByRole('link', { name: 'Přehled' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Weby' })).toBeInTheDocument();
      expect(screen.getByRole('link', { name: 'Nastavení' })).toBeInTheDocument();
    });
  });
});
