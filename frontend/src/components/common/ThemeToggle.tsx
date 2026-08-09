import { Moon, Sun } from 'lucide-react';
import { useTheme } from 'next-themes';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';

/**
 * Single-button theme toggle (Light ↔ Dark).
 *
 * Shows the TARGET state (what will happen on click), consistent with LanguageToggle:
 * - Dark mode active → shows Sun icon (click = switch to light)
 * - Light mode active → shows Moon icon (click = switch to dark)
 *
 * aria-pressed reflects the CURRENT state (true = dark active) for screen readers.
 */
export function ThemeToggle() {
  const { theme, setTheme } = useTheme();
  const { t } = useTranslation();

  const isDark = theme === 'dark';
  const targetKey = isDark ? 'light' : 'dark';
  const targetLabel = t(`theme.${targetKey}`);

  return (
    <Button
      variant="ghost"
      size="icon"
      onClick={() => setTheme(isDark ? 'light' : 'dark')}
      aria-pressed={isDark}
      aria-label={t('theme.toggleAria', { target: targetLabel })}
      title={t('theme.toggleTitle', { target: targetLabel })}
    >
      {isDark ? <Sun /> : <Moon />}
    </Button>
  );
}
