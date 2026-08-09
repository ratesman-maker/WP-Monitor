import { screen } from '@testing-library/react';
import { describe, expect, it, vi, beforeEach } from 'vitest';

import LoginPage from '@/modules/auth/pages/LoginPage';
import { useAuthStore } from '@/stores/authStore';
import { renderWithProviders } from '@/test/testUtils';

// Mock the useLogin hook so we don't need a real API
vi.mock('@/modules/auth/hooks/useLogin', () => ({
  useLogin: () => ({
    mutateAsync: vi.fn().mockResolvedValue({}),
    isPending: false,
  }),
}));

function renderLoginPage() {
  return renderWithProviders(<LoginPage />);
}

describe('LoginPage', () => {
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

  it('renders WP Monitor title', () => {
    renderLoginPage();
    expect(screen.getByText('WP Monitor')).toBeInTheDocument();
  });

  it('renders username and password inputs', () => {
    renderLoginPage();
    expect(screen.getByLabelText(/username/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/master password/i)).toBeInTheDocument();
  });

  it('renders sign in button', () => {
    renderLoginPage();
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument();
  });

  it('renders SettingsToggles (accessible before auth)', () => {
    renderLoginPage();
    // SettingsToggles renders 2 buttons (language + theme)
    // Plus the "Sign in" submit button = 3 total
    const buttons = screen.getAllByRole('button');
    expect(buttons.length).toBeGreaterThanOrEqual(3);
  });

  it('shows error when submitting empty fields', async () => {
    const { user } = renderLoginPage();

    await user.click(screen.getByRole('button', { name: /sign in/i }));

    expect(screen.getByText(/please enter both username and password/i)).toBeInTheDocument();
  });

  it('allows typing into username and password fields', async () => {
    const { user } = renderLoginPage();

    const usernameInput = screen.getByLabelText(/username/i);
    const passwordInput = screen.getByLabelText(/master password/i);

    await user.type(usernameInput, 'admin');
    await user.type(passwordInput, 'password123');

    expect(usernameInput).toHaveValue('admin');
    expect(passwordInput).toHaveValue('password123');
  });

  it('renders translated title in CS locale', () => {
    renderWithProviders(<LoginPage />, { locale: 'cs' });
    expect(screen.getByText('Přihlaste se ke svému účtu pro pokračování.')).toBeInTheDocument();
  });
});
