import { render, renderHook, type RenderOptions } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement, ReactNode } from 'react';
import { MemoryRouter, type MemoryRouterProps } from 'react-router-dom';

import { I18nProvider } from '@/providers/I18nProvider';
import { ThemeProvider } from '@/providers/ThemeProvider';
import { queryClient } from '@/lib/queryClient';
import i18n, { LANGUAGE_STORAGE_KEY } from '@/i18n';

interface RenderWithProvidersOptions extends Omit<RenderOptions, 'wrapper'> {
  /** MemoryRouter initialEntries (defaults to ['/']) */
  routerProps?: MemoryRouterProps;
  /** Theme to force for the test (defaults to 'dark') */
  theme?: 'dark' | 'light';
  /** Locale to force for the test (defaults to 'en') */
  locale?: 'en' | 'cs';
}

/**
 * Render a component wrapped in all required providers:
 * I18nProvider + ThemeProvider + QueryClientProvider + MemoryRouter.
 *
 * Use this instead of raw render() for any test that renders a component
 * using useTranslation(), useTheme(), useNavigate(), or useAuthStore().
 *
 * Defaults: locale='en', theme='dark', router initialEntries=['/'].
 * These defaults keep existing tests (which assert on EN strings) working.
 */
export function renderWithProviders(
  ui: ReactElement,
  options: RenderWithProvidersOptions = {},
) {
  const {
    routerProps = { initialEntries: ['/'] },
    locale = 'en',
    theme: _theme = 'dark',
  } = options;

  // Force the locale for this test (resets i18n + localStorage)
  if (i18n.language !== locale) {
    void i18n.changeLanguage(locale);
  }
  localStorage.setItem(LANGUAGE_STORAGE_KEY, locale);

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <I18nProvider>
        <ThemeProvider>
          <QueryClientProviderWithClient client={queryClient}>
            <MemoryRouter {...routerProps}>{children}</MemoryRouter>
          </QueryClientProviderWithClient>
        </ThemeProvider>
      </I18nProvider>
    );
  }

  return {
    user: userEvent.setup(),
    ...render(ui, { wrapper: Wrapper, ...options }),
  };
}

/**
 * Render a hook wrapped in all required providers (for testing hooks that
 * use useTranslation(), useTheme(), useNavigate(), etc.).
 */
export function renderHookWithProviders<TResult>(
  hook: () => TResult,
  options: Omit<RenderWithProvidersOptions, 'wrapper'> = {},
) {
  const {
    routerProps = { initialEntries: ['/'] },
    locale = 'en',
    theme: _theme = 'dark',
  } = options;

  if (i18n.language !== locale) {
    void i18n.changeLanguage(locale);
  }
  localStorage.setItem(LANGUAGE_STORAGE_KEY, locale);

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <I18nProvider>
        <ThemeProvider>
          <QueryClientProviderWithClient client={queryClient}>
            <MemoryRouter {...routerProps}>{children}</MemoryRouter>
          </QueryClientProviderWithClient>
        </ThemeProvider>
      </I18nProvider>
    );
  }

  return renderHook(hook, { wrapper: Wrapper });
}

// Lazy import to avoid circular deps in test setup
import { QueryClientProvider } from '@tanstack/react-query';
function QueryClientProviderWithClient({
  client,
  children,
}: {
  client: typeof queryClient;
  children: ReactNode;
}) {
  return <QueryClientProvider client={client}>{children}</QueryClientProvider>;
}
