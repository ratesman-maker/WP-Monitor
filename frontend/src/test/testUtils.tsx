import { QueryClientProvider } from '@tanstack/react-query';
import { render, renderHook, type RenderOptions } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { ReactElement, ReactNode } from 'react';
import { MemoryRouter, type MemoryRouterProps } from 'react-router-dom';

import i18n, { LANGUAGE_STORAGE_KEY } from '@/i18n';
import { queryClient } from '@/lib/queryClient';
import { I18nProvider } from '@/providers/I18nProvider';
import { ThemeProvider } from '@/providers/ThemeProvider';

const THEME_STORAGE_KEY = 'wpm-theme';

interface RenderWithProvidersOptions extends Omit<RenderOptions, 'wrapper'> {
  /** MemoryRouter initialEntries (defaults to ['/']) */
  routerProps?: MemoryRouterProps;
  /** Theme to force for the test (defaults to 'dark') — persisted to localStorage so next-themes picks it up */
  theme?: 'dark' | 'light';
  /** Locale to force for the test (defaults to 'en') */
  locale?: 'en' | 'cs';
}

/**
 * Apply common provider setup (locale + theme) shared by render* helpers.
 */
function applyProviderDefaults(locale: 'en' | 'cs', theme: 'dark' | 'light') {
  // Force the locale for this test (resets i18n + localStorage)
  if (i18n.language !== locale) {
    void i18n.changeLanguage(locale);
  }
  localStorage.setItem(LANGUAGE_STORAGE_KEY, locale);
  // Force the theme — next-themes reads localStorage on mount
  localStorage.setItem(THEME_STORAGE_KEY, theme);
  if (theme === 'light') {
    document.documentElement.classList.add('light');
  } else {
    document.documentElement.classList.remove('light');
  }
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
    theme = 'dark',
  } = options;

  applyProviderDefaults(locale, theme);

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <I18nProvider>
        <ThemeProvider>
          <QueryClientProvider client={queryClient}>
            <MemoryRouter {...routerProps}>{children}</MemoryRouter>
          </QueryClientProvider>
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
    theme = 'dark',
  } = options;

  applyProviderDefaults(locale, theme);

  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <I18nProvider>
        <ThemeProvider>
          <QueryClientProvider client={queryClient}>
            <MemoryRouter {...routerProps}>{children}</MemoryRouter>
          </QueryClientProvider>
        </ThemeProvider>
      </I18nProvider>
    );
  }

  return renderHook(hook, { wrapper: Wrapper });
}
