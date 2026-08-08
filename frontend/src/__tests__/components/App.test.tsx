import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { describe, expect, it } from 'vitest';

import App from '@/App';

function renderApp(initialRoute = '/') {
  return render(
    <MemoryRouter initialEntries={[initialRoute]}>
      <App />
    </MemoryRouter>,
  );
}

describe('App', () => {
  describe('navigation', () => {
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
  });

  describe('routes', () => {
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
