import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import commonEn from './locales/en/common.json';
import authEn from './locales/en/auth.json';
import commonCs from './locales/cs/common.json';
import authCs from './locales/cs/auth.json';

export const SUPPORTED_LANGUAGES = ['en', 'cs'] as const;
export type SupportedLanguage = (typeof SUPPORTED_LANGUAGES)[number];
export const DEFAULT_LANGUAGE: SupportedLanguage = 'en';
export const LANGUAGE_STORAGE_KEY = 'wpm-lang';

/**
 * Detect the user's preferred language:
 * 1. Explicit choice stored in localStorage (highest priority)
 * 2. navigator.language (browser preference)
 * 3. Fallback to DEFAULT_LANGUAGE ('en')
 */
function detectLanguage(): SupportedLanguage {
  // 1. localStorage — explicit user choice
  const stored = localStorage.getItem(LANGUAGE_STORAGE_KEY);
  if (stored !== null && SUPPORTED_LANGUAGES.includes(stored as SupportedLanguage)) {
    return stored as SupportedLanguage;
  }

  // 2. navigator.language — browser preference (e.g. 'cs-CZ', 'en-US')
  const browserLang = navigator.language?.toLowerCase() ?? '';
  if (browserLang.startsWith('cs')) {
    return 'cs';
  }

  // 3. Fallback
  return DEFAULT_LANGUAGE;
}

void i18n.use(initReactI18next).init({
  resources: {
    en: { common: commonEn, auth: authEn },
    cs: { common: commonCs, auth: authCs },
  },
  lng: detectLanguage(),
  fallbackLng: DEFAULT_LANGUAGE,
  defaultNS: 'common',
  ns: ['common', 'auth'],
  interpolation: {
    escapeValue: false, // React escapes by default
  },
  react: {
    useSuspense: false, // synchronous init, no Suspense boundary needed
  },
});

// Keep <html lang> in sync with the active language (WCAG 3.1.1 — screen readers)
i18n.on('languageChanged', (lng: string) => {
  document.documentElement.lang = lng;
});

// Set initial <html lang> (in case no languageChanged fires on init)
document.documentElement.lang = i18n.language;

export default i18n;
