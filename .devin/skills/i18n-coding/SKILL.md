---
name: i18n-coding
description: Pravidla pro překladatelnost UI — i18next, translation keys, namespaces, <html lang>, accessibility. Aplikuje se při každém psaní nebo úpravě kódu.
triggers:
  - model
---

# Skill: i18n Coding (překladatelnost)

> Tato pravidla se aplikují při každém psaní nebo úpravě kódu.
> Překladatelnost je NON-NEGOTIABLE — žádné kompromisy, žádné výjimky.
> Od verze 0.3.0 je i18n nedílnou součástí projektu (EN/CS s možností rozšíření).

## Základní princip

**KAŽDÝ user-facing string musí být přeložitelný.** Žádné hardcoded texty v UI.

„User-facing" = cokoliv, co uživatel vidí nebo slyší:
- Text v komponentách (`<h1>`, `<p>`, `<button>`, `<label>`, atd.)
- Placeholder texty v inputech
- `aria-label`, `title` atributy
- Error messages zobrazené uživateli
- Toast notifikace, alert zprávy
- Tooltipy

„User-facing" NENÍ:
- Log messages (pouze pro vývojáře)
- Internal error messages (nezobrazené uživateli)
- Komentáře v kódu
- Názvy souborů, adresářů
- Console.error / console.warn output

## TypeScript Frontend

### Použití useTranslation() v komponentách

```tsx
// ✅ VŽDY: Použít useTranslation() hook v React komponentách
import { useTranslation } from 'react-i18next';

function LoginPage() {
  const { t } = useTranslation(['common', 'auth']);
  return <h1>{t('login.title', { ns: 'auth' })}</h1>;
}

// ✅ VŽDY: Default namespace je 'common' — pro auth namespace použít options
const { t } = useTranslation();
return <button>{t('sidebar.dashboard')}</button>;          // common namespace
return <button>{t('login.submit', { ns: 'auth' })}</button>; // auth namespace

// ❌ NIKDY: Hardcoded string v komponentě
return <h1>WP Monitor</h1>;                              // ZÁKAZ!
return <button>Sign in</button>;                         // ZÁKAZ!
return <input placeholder="Enter username" />;           // ZÁKAZ!
return <button aria-label="Close">✕</button>;            // ZÁKAZ!
```

### Použití i18n.t() v non-React modulech

```tsx
// ✅ VŽDY: V non-React modulech (api.ts, refresh.ts, utils.ts) použít globální instanci
import i18n from '@/i18n';

throw new Error(i18n.t('error.sessionExpired'));     // ✅ runtime překlad
const msg = i18n.t('error.networkError');             // ✅ runtime překlad

// ❌ NIKDY: useTranslation() hook mimo React komponentu
import { useTranslation } from 'react-i18next';
const { t } = useTranslation();                       // ZÁKAZ! Hook mimo komponentu
throw new Error(t('error.sessionExpired'));           // ZÁKAZ!
```

### Translation keys — konvence

```tsx
// ✅ VŽDY: Hierarchické klíče podle modulu a sekce
t('sidebar.dashboard')           // common.sidebar.dashboard
t('sidebar.signOut')             // common.sidebar.signOut
t('login.title', { ns: 'auth' }) // auth.login.title
t('login.submit', { ns: 'auth' })// auth.login.submit
t('error.sessionExpired')        // common.error.sessionExpired
t('theme.toggleAria')            // common.theme.toggleAria

// ✅ VŽDY: Descriptive klíče (ne generické)
t('login.errorEmptyFields')      // ✅ jasné
t('error1')                      // ❌ nezřetelné
t('msg')                         // ❌ nezřetelné

// ❌ NIKDY: Interpolace přes string concatenation
t('error.' + code)               // ZÁKAZ! TypeScript type augmentation neumí typovat
t(`error.${code}`)               // ZÁKAZ! TypeScript type augmentation neumí typovat

// ✅ VŽDY: Interpolace přes options
t('theme.toggleAria', { target: 'light' })  // ✅ i18next interpolation
t('error.fieldRequired', { field: 'username' }) // ✅
```

### Namespaces

```
common  — sidebar, buttons, errors, toggle labely, společné UI prvky
auth    — login, logout, registrace, password reset
sites   — (budoucí) správa WordPress webů
dashboard — (budoucí) dashboard widgets, statistiky
settings  — (budoucí) uživatelská nastavení
```

- **Default namespace:** `common`
- **Modul-specific namespace:** podle modulu (`auth`, `sites`, `dashboard`, `settings`)
- **Vždy** deklarovat namespace v `i18n/config.ts` `ns` array
- **Vždy** vytvořit JSON soubor pro každý namespace + jazyk

### <html lang> sync

```tsx
// ✅ VŽDY: i18n.on('languageChanged') aktualizuje <html lang>
// (již implementováno v src/i18n/config.ts — neduplikovat)
i18n.on('languageChanged', (lng) => {
  document.documentElement.lang = lng;
});
// WCAG 3.1.1 — screen readery čtou správný jazyk
```

### Accessibility + i18n

```tsx
// ✅ VŽDY: aria-label, aria-pressed, title přeložit
<button
  aria-label={t('theme.toggleAria', { target: targetLabel })}
  aria-pressed={isDark}
  title={t('theme.toggleTitle', { target: targetLabel })}
>
  <Sun />
</button>

// ❌ NIKDY: Hardcoded aria-label
<button aria-label="Close">✕</button>  // ZÁKAZ!
<button aria-label="Toggle theme">🌙</button>  // ZÁKAZ!
```

### Přidání nového jazyka

1. Vytvoř `frontend/src/i18n/locales/{lang}/` s JSON soubory pro každý namespace
2. Přidej jazyk do `SUPPORTED_LANGUAGES` v `config.ts`
3. Přidej resources do `i18n.init({ resources: { ... {lang}: { ... } } })`
4. Aktualizuj `LanguageToggle` pokud je binární přepínač (přejít na ToggleGroup/Select pro 3+ jazyky)
5. Ověř `<html lang>` sync (automatické přes `languageChanged` event)

## Backend (PHP)

Backend v tuto chvíli vrací API response v EN (error messages). Pokud bude potřeba lokalizovat backend:

```php
// Budoucí: Accept-Language header → lokalizované error messages
// Mimo rozsah tohoto skillu — backend i18n je P2
```

Pro teď: **frontend překládá API error messages** pokud je potřeba (mapování HTTP status → translation key).

## Checklist před každým commitem

- [ ] Žádné hardcoded user-facing stringy v komponentách
- [ ] Všechny texty používají `t()` nebo `i18n.t()`
- [ ] `aria-label`, `title`, placeholder texty přeloženy
- [ ] Nové translation keys přidány do EN i CS JSON souborů
- [ ] Hierarchické klíče (ne generické `msg1`, `error1`)
- [ ] Non-React moduly používají `i18n.t()` (ne `useTranslation()`)
- [ ] `<html lang>` sync funguje (automatické přes config.ts)
- [ ] TypeScript type augmentation (`i18next.d.ts`) chytil případné chybějící klíče

## Testovací checklist — i18n

### Unit testy (Vitest)

- [ ] **Komponenta** — render s `renderWithProviders()`, ověř přeložený text
- [ ] **Komponenta** — test v EN i CS locale (pokud relevantní)
- [ ] **Toggle** — `aria-pressed` odráží aktuální stav
- [ ] **Toggle** — `aria-label` + `title` přeloženy
- [ ] **i18n config** — detection (navigator.language → cs/en), fallback, changeLanguage
- [ ] **i18n config** — `<html lang>` sync po changeLanguage
- [ ] **Non-React modul** — `i18n.t()` vrací správný překlad

### Integration testy

- [ ] **Přepnutí jazyka** — UI se přeloží bez reloadu
- [ ] **Persistence** — jazyk uložen v localStorage, obnoven po reloadu
- [ ] **Fallback** — chybějící CS klíč → EN fallback
- [ ] **Type augmentation** — build fail na chybějící translation key

### Manuální testy

- [ ] **Playwright** — přepnutí EN↔CS, ověř překlad sidebaru/loginu
- [ ] **Playwright** — reload persistence (jazyk + theme)
- [ ] **Screen reader** — `<html lang>` správně nastaven, aria-label čte správný jazyk

## Související

- Skills: `security-coding`, `performance-coding`, `modularity-coding` — rovnocenné priority
- Implementation plan skill: `implementation-plan` — překladatelnost je povinná sekce v plánu
- i18n modul: `frontend/src/i18n/`
- Translation soubory: `frontend/src/i18n/locales/{en,cs}/{common,auth}.json`
- Type augmentation: `frontend/src/i18n/i18next.d.ts`
- Test helper: `frontend/src/test/testUtils.tsx` (`renderWithProviders`)
