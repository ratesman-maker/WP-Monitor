import 'i18next';

import type commonEn from './locales/en/common.json';
import type authEn from './locales/en/auth.json';

declare module 'i18next' {
  // Merge custom types into i18next's CustomTypeOptions so that
  // t('nonexistent.key') fails at compile time instead of runtime.
  interface CustomTypeOptions {
    defaultNS: 'common';
    resources: {
      common: typeof commonEn;
      auth: typeof authEn;
    };
  }
}
