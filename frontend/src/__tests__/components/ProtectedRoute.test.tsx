import { screen } from '@testing-library/react';
import { Route, Routes } from 'react-router-dom';
import { describe, expect, it, beforeEach } from 'vitest';

import { ProtectedRoute } from '@/components/ProtectedRoute';
import { useAuthStore } from '@/stores/authStore';
import { renderWithProviders } from '@/test/testUtils';

function renderProtectedRoute(initialRoute = '/protected', isInitializing = false) {
  return renderWithProviders(
    <Routes>
      <Route path="/login" element={<div>Login Page</div>} />
      <Route
        path="/protected"
        element={
          <ProtectedRoute isInitializing={isInitializing}>
            <div>Protected Content</div>
          </ProtectedRoute>
        }
      />
    </Routes>,
    { routerProps: { initialEntries: [initialRoute] } },
  );
}

describe('ProtectedRoute', () => {
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

  it('redirects to /login when unauthenticated', () => {
    renderProtectedRoute();
    expect(screen.getByText('Login Page')).toBeInTheDocument();
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
  });

  it('renders children when authenticated', () => {
    useAuthStore.setState({
      isAuthenticated: true,
      user: { id: 1, username: 'admin', role: 'admin' },
      token: 'token',
      refreshToken: 'refresh',
      csrfToken: 'csrf',
      sessionId: 'session',
    });

    renderProtectedRoute();
    expect(screen.getByText('Protected Content')).toBeInTheDocument();
    expect(screen.queryByText('Login Page')).not.toBeInTheDocument();
  });

  it('shows loading state when initializing with a persisted user', () => {
    // Simulate page reload: user is persisted, token is null, init in progress
    useAuthStore.setState({
      isAuthenticated: false,
      user: { id: 1, username: 'admin', role: 'admin' },
      token: null,
      refreshToken: null,
      csrfToken: 'csrf',
      sessionId: 'session',
    });

    renderProtectedRoute('/protected', true);
    // Loading skeleton is shown — neither login nor protected content
    expect(screen.queryByText('Login Page')).not.toBeInTheDocument();
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
  });

  it('redirects to login when initializing without a user', () => {
    // No persisted user, init in progress — should redirect, not show loading
    renderProtectedRoute('/protected', true);
    expect(screen.getByText('Login Page')).toBeInTheDocument();
    expect(screen.queryByText('Protected Content')).not.toBeInTheDocument();
  });
});
