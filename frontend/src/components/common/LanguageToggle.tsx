import { Globe } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import i18n, { LANGUAGE_STORAGE_KEY, type SupportedLanguage } from '@/i18n';

/**
 * Single-button language toggle (EN ↔ CS).
 *
 * Shows the TARGET language code (what will happen on click), consistent with ThemeToggle:
 * - EN active → shows 'CS' (click = switch to Czech)
 * - CS active → shows 'EN' (click = switch to English)
 *
 * aria-pressed reflects the CURRENT state (true = CS active) for screen readers.
 */
export function LanguageToggle() {
  const { t } = useTranslation();

  const isCs = i18n.language === 'cs';
  const targetLang: SupportedLanguage = isCs ? 'en' : 'cs';
  const targetCode = targetLang.toUpperCase();
  const targetLabel = t(`language.${targetLang === 'en' ? 'english' : 'czech'}`);

  const handleToggle = () => {
    const newLang: SupportedLanguage = isCs ? 'en' : 'cs';
    void i18n.changeLanguage(newLang);
    localStorage.setItem(LANGUAGE_STORAGE_KEY, newLang);
  };

  return (
    <Button
      variant="ghost"
      size="sm"
      onClick={handleToggle}
      aria-pressed={isCs}
      aria-label={t('language.toggleAria', { target: targetLabel })}
      title={t('language.toggleTitle', { target: targetLabel })}
      className="gap-1.5 px-2"
    >
      <Globe />
      <span className="text-xs font-semibold">{targetCode}</span>
    </Button>
  );
}
