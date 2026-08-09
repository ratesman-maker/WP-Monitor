import { LanguageToggle } from './LanguageToggle';
import { ThemeToggle } from './ThemeToggle';

import { Separator } from '@/components/ui/separator';

/**
 * Horizontal layout combining LanguageToggle + Separator + ThemeToggle.
 * Reusable — rendered in the Sidebar (authenticated pages) and on the LoginPage.
 */
export function SettingsToggles() {
  return (
    <div className="flex items-center gap-2">
      <LanguageToggle />
      <Separator orientation="vertical" className="h-6" />
      <ThemeToggle />
    </div>
  );
}
