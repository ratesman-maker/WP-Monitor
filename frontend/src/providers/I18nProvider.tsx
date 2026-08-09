import type { ReactNode } from 'react';

// Side-effect import: initializes i18next synchronously on module load.
// This must be imported before any component that uses useTranslation().
import '@/i18n/config';

interface I18nProviderProps {
  children: ReactNode;
}

/**
 * Initializes i18next (synchronous, no Suspense needed).
 * Wraps children so that useTranslation() is available in the subtree.
 *
 * The actual i18next instance is initialized via the side-effect import above.
 * react-i18next's useTranslation() hook reads from the global i18n instance,
 * so no React context provider is strictly required — this component exists
 * to guarantee the import order and make the intent explicit.
 */
export function I18nProvider({ children }: I18nProviderProps) {
  return <>{children}</>;
}
