import { ThemeProvider as NextThemesProvider } from 'next-themes';
import type { ReactNode } from 'react';

interface ThemeProviderProps {
  children: ReactNode;
}

/**
 * Wraps the app with next-themes.
 *
 * - attribute="class": adds class to <html> (e.g. class="light")
 * - defaultTheme="dark": dark is the default (matches globals.css :root)
 * - enableSystem={false}: binary toggle (Light ↔ Dark), no "system" option
 * - storageKey="wpm-theme": persisted in localStorage
 *
 * globals.css uses .light class for the light palette; :root is dark by default.
 * next-themes adds class="light" for light theme, adds nothing for dark (default).
 */
export function ThemeProvider({ children }: ThemeProviderProps) {
  return (
    <NextThemesProvider
      attribute="class"
      defaultTheme="dark"
      enableSystem={false}
      storageKey="wpm-theme"
    >
      {children}
    </NextThemesProvider>
  );
}
