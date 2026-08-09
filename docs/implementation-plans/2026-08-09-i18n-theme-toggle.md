# Implementační plán — i18n (EN/CS) + Theme Toggle (Light/Dark)

## Metadata

| Pole | Hodnota |
|------|---------|
| Název | i18n (EN/CS) + Theme Toggle (Light/Dark) |
| Typ | funkce |
| Priorita | P1 |
| Status | revised |
| Vytvořeno | 09.08.2026 |
| Autor | Devin (GLM-5.2 High) |
| Související skill | `.devin/skills/implementation-plan/SKILL.md`, `.devin/skills/security-coding/SKILL.md`, `.devin/skills/performance-coding/SKILL.md` |
| Související dokumentace | `docs/14-design-system.md` (theme systém), `docs/02-architecture.md` |

## 1. Cíl

Doplnit do administrace WP Monitor:

1. **i18n (EN/CS)** — kompletní překlad UI do angličtiny a češtiny s možností přepnutí. Auto-detect z `navigator.language`, volba uložena v `localStorage`.
2. **Theme toggle (light/dark)** — přepínač světlého/tmavého režimu. Dark je default (per `globals.css`), volba uložena v `localStorage`. Binární přepínač (Light↔Dark), žádná „System" volba. No-flash script (žádné blikání při načtení).
3. **Přepínače všude** — kompaktní, vždy viditelné. V Sidebaru (autentizované stránky) i na LoginPage (host). Zabaleno do jedné `SettingsToggles` komponenty (shadcn `Button` + `Separator`), použitá na obou místech.

**Zvolené knihovny:** `react-i18next` (i18n), `next-themes` (theme).

## 2. Reálný stav (před implementací)

### 2.1 Frontend

Existuje:
- `frontend/src/styles/globals.css` — **obě palety už definovány**: dark v `:root` (default), light v `.light` class. Tailwind `darkMode: 'class'` v `tailwind.config.ts`.
- `frontend/src/main.tsx` — entry point, `QueryClientProvider` + `BrowserRouter` + `App`
- `frontend/src/App.tsx` — `Sidebar` komponenta s navigačními linky (Dashboard/Sites/Settings) a Sign out button
- `frontend/src/components/ui/` — `button.tsx`, `separator.tsx`, `skeleton.tsx` (pro přepínače). `dropdown-menu.tsx` existuje ale nepoužívá se v této iteraci.
- `frontend/src/stores/authStore.ts` — Zustand store s persist middleware (vzor pro theme store)
- `frontend/package.json` — React 18.3, react-router-dom 7.18, zustand 5, @tanstack/react-query 5

Chybí:
- `react-i18next`, `i18next`, `next-themes` — neinstalovány
- Translation soubory (EN/CS JSON)
- `i18n` konfigurace
- `ThemeProvider` wrapper
- Theme toggle UI
- Language toggle UI
- žádné stringy v UI nejsou připravené pro překlad (hardcoded EN)

### 2.2 Backend

Beze změny — i18n a theme jsou čistě frontend concerns. Backend vrací API response v EN (error messages), případně lze později doplnit `Accept-Language` header (mimo tento plán).

### 2.3 Databáze

Beze změny.

### 2.4 Rozdíl oproti dokumentaci

- `docs/14-design-system.md` popisuje theme systém (dark-first + light, `darkMode: 'class'`, no-flash script) — **palety existují v CSS, ale chybí JS-side theme management a toggle UI**.
- Dokumentace zmiňuje `prefers-color-scheme` respekt — v této iteraci **neimplementováno** (binární přepínač Light↔Dark, `enableSystem={false}`). Lze doplnit v budoucí iteraci pokud bude žádoucí System volba.
- i18n není v dokumentaci vůbec zmíněno — doplníme poznámku do `docs/14-design-system.md` (P2, mimo tento plán).

## 3. Priority (bez výjimek)

### 3.1 Bezpečnost

- **localStorage pro theme + language** — neobsahuje citlivá data (jen `'dark'|'light'` a `'en'|'cs'`), bezpečné ukládat. Naopak — nutné pro persistenci uživatelské preference.
- **No-flash script** — inline `<script>` v `index.html` nastaví theme class na `<html>` **před** načtením Reactu, aby se předešlo blikání (FOUC — Flash of Unstyled Content). Skript je krátký, synchronní, neexekuje user input.
- **XSS** — i18n interpolation musí escapovat HTML. `react-i18next` ve výchozím nastavení vrací stringy (React je escapuje automaticky). `dangerouslySetInnerHTML` se nepoužije.
- **No user input v translation keys** — translation keys jsou hardcoded v kódu, nepřicházejí z user inputu (žádné injection riziko).

### 3.2 Rychlost

- **i18next lazy loading** — translation JSON soubory jsou malé (~5KB na jazyk), načítají se synchronně při startu (ne lazy — pro 2 jazyky je overhead větší než gain).
- **Bundle size** — `react-i18next` ~40KB, `i18next` ~25KB, `next-themes` ~1KB. Celkem ~66KB (gzip ~25KB). Akceptovatelné pro admin UI.
- **Theme switch** — jen class toggle na `<html>`, žádný re-render Reactu (CSS variables se přepnou). Okamžitá odezva.
- **Memoizace** — `useTranslation` hook je už optimalizovaný (rerenderuje jen komponentu při změně jazyka).

### 3.3 Modularita

- `frontend/src/i18n/` — i18n konfigurace + translation soubory (samostatný modul)
- `frontend/src/components/common/` — `ThemeToggle.tsx`, `LanguageToggle.tsx`, `SettingsToggles.tsx` (custom komponenty skládající shadcn)
- `frontend/src/providers/ThemeProvider.tsx` — wrapper kolem `next-themes`
- `frontend/src/providers/I18nProvider.tsx` — wrapper kolem `react-i18next` (init)
- `frontend/src/test/testUtils.tsx` — `renderWithProviders()` helper (I18n + Theme + QueryClient + Router) pro všechny testy — prevence duplikace provider boilerplate
- **Namespace konvence** — translation keys strukturované podle modulů: `auth.login.title`, `common.dashboard`, `sidebar.dashboard`, atd.
- **TypeScript type augmentation** — `frontend/src/i18n/i18next.d.ts` deklaruje typy pro translation keys. TypeScript chytí chybějící/nesprávné klíče při buildu (ne až v runtime). Importuje se přes `tsconfig.json` `include`.
- **`<html lang>` sync** — `i18n.on('languageChanged', (lng) => { document.documentElement.lang = lng; })` v `config.ts`. Screen readery čtou správný jazyk (WCAG 3.1.1).
- **Theme store** — `next-themes` má vlastní state management, nepotřebujeme Zustand store pro theme.

### 3.4 UI komponenty (striktní pravidla)

- **Používat shadcn/ui** — `Button` pro oba přepínače, `Separator` pro vizuální oddělení. Žádné DropdownMenu.
- **Ikony** — `lucide-react` (Sun, Moon, Globe). Už je v dependencies.
- **Žádné custom CSS** — jen Tailwind utility classes a existující CSS variables z `globals.css`.
- **Theme toggle** — Single `Button` toggle (1 klik = přepnutí Light↔Dark). Ikona Sun (dark mode → klik přepne na light) / Moon (light mode → klik přepne na dark). Binární přepínač, žádné „System" volby (next-themes `enableSystem=false`).
- **Language toggle** — Single `Button` toggle (1 klik = přepnutí EN↔CS). Zobrazí `Globe` ikonu + kód **cílového** jazyka (`CS` když jsi v EN, `EN` když jsi v CS). Konzistentní s theme toggle (ukazuje co se stane při kliku, ne aktuální stav). Binární přepínač.
- **Accessibility** — každý toggle button má:
  - `aria-label` (přeložený přes i18n) — popis akce
  - `aria-pressed` (boolean) — asistenční technologie ví, že jde o přepínač se stavem
  - `title` (přeložený přes i18n) — tooltip při hoveru
- **STRIKTNÍ: shadcn všude** — `Button` z `components/ui/`, `Separator` z `components/ui/`, ikony z `lucide-react`. Žádné DropdownMenu, žádné custom React komponenty pro UI prvky, žádné custom CSS. `ThemeToggle`/`LanguageToggle`/`SettingsToggles` jen skládají shadcn komponenty.
- **Přepínače na obou místech** — `SettingsToggles` komponenta (horizontální layout: Language | Separator | Theme) se vykreslí:
  - V Sidebaru (autentizované stránky) — pod navigačními linky, nad Sign out
  - Na LoginPage — v pravém horním rohu Card (nebo nad Card), aby byl dostupný před přihlášením

## 4. Architektura

### 4.1 Frontend

```
frontend/src/
├── i18n/                          # NOVÝ modul
│   ├── config.ts                  # i18next init (resources, detection, fallback, languageChanged → <html lang>)
│   ├── i18next.d.ts               # TypeScript type augmentation pro translation keys
│   ├── locales/
│   │   ├── en/
│   │   │   ├── common.json        # společné stringy (sidebar, buttons, errors, toggle aria/title)
│   │   │   └── auth.json          # auth modul stringy
│   │   └── cs/
│   │       ├── common.json
│   │       └── auth.json
│   └── index.ts                   # re-export z config.ts
├── providers/                     # NOVÝ adresář
│   ├── ThemeProvider.tsx          # next-themes wrapper
│   └── I18nProvider.tsx           # i18next init wrapper (side-effect import config)
├── components/common/             # NOVÝ adresář (per project-structure skill)
│   ├── ThemeToggle.tsx            # Single Button: Sun↔Moon (Light↔Dark), aria-pressed + title
│   ├── LanguageToggle.tsx         # Single Button: 'EN'↔'CS' (Globe+code, cílový jazyk), aria-pressed + title
│   └── SettingsToggles.tsx        # Horizontální layout: Language | Separator | Theme (shadcn)
├── test/                          # NOVÝ adresář
│   └── testUtils.tsx              # renderWithProviders() helper (I18n+Theme+QueryClient+Router)
├── App.tsx                        # Sidebar: přidat SettingsToggles nad Sign out
├── main.tsx                       # obalit App v ThemeProvider + I18nProvider
└── modules/auth/pages/LoginPage.tsx  # přidat SettingsToggles + překlad stringů
```

### 4.2 Theme systém

```
index.html
├── <script> no-flash: čte localStorage 'wpm-theme', nastaví class na <html>
│   - 'light' → <html class="light">
│   - 'dark' nebo null → <html> (default, no class needed — :root je dark)
│   (žádná prefers-color-scheme logika — enableSystem={false})
│
main.tsx
├── <ThemeProvider attribute="class" defaultTheme="dark" enableSystem={false} ...>
│   - next-themes spravuje class na <html>
│   - persistuje do localStorage 'wpm-theme'
│   - binární: 'dark' | 'light' (žádný 'system')
│
globals.css (EXISTUJE)
├── :root { ...dark palette... }      # default
└── .light { ...light palette... }    # next-themes přidá/odebere class
```

**Poznámka:** `globals.css` používá `.light` class (ne `:root.dark`). next-themes s `attribute="class"` přidá `class="light"` na `<html>` pro light theme, pro dark nepřidá nic (default `:root` je dark). Config: `attribute="class"`, `defaultTheme="dark"`, `enableSystem={false}`, `storageKey="wpm-theme"`.

### 4.3 i18n systém

```
i18n/config.ts
├── init({
│     resources: { en: { common, auth }, cs: { common, auth } },
│     lng: detected,           // z localStorage 'wpm-lang' nebo navigator.language
│     fallbackLng: 'en',
│     defaultNS: 'common',
│     interpolation: { escapeValue: false }, // React escapuje sám
│   })
│
LanguageToggle.tsx
├── i18n.changeLanguage('en'|'cs') + localStorage.setItem('wpm-lang', lng)
```

### 4.4 Databáze

Beze změny.

## 5. API endpointy

Beze změny — i18n a theme jsou čistě frontend.

## 6. Kroky implementace

### Fáze 0: Načtení stylu kódování
- [x] Frontend konvence ověřeny (named exports, import type, strict TS)
- [x] shadcn/ui komponenty ověřeny (`button.tsx`, `separator.tsx` existují — DropdownMenu není potřeba)
- [x] globals.css palety ověřeny (dark v :root, light v .light)
- [x] Tailwind darkMode: 'class' ověřeno

### Fáze 1: Instalace dependencies
- [ ] `docker compose exec frontend npm install i18next react-i18next next-themes`
- [ ] Ověřit verze (musí být publikovány alespoň 7 dní — ověřit přes `npm view`)
- [ ] `npm audit` — bez známých vulnerabilit

### Fáze 2: i18n konfigurace + translation soubory
- [ ] `frontend/src/i18n/locales/en/common.json` — sidebar, buttons, common errors, toggle aria-labels + titles
- [ ] `frontend/src/i18n/locales/en/auth.json` — login page stringy
- [ ] `frontend/src/i18n/locales/cs/common.json` — české překlady
- [ ] `frontend/src/i18n/locales/cs/auth.json` — české překlady
- [ ] `frontend/src/i18n/config.ts` — i18next init s `resources` (inline import), `fallbackLng: 'en'`, `defaultNS: 'common'`, detection z localStorage 'wpm-lang' nebo navigator.language. **Plus `i18n.on('languageChanged', (lng) => { document.documentElement.lang = lng; })`** pro WCAG 3.1.1.
- [ ] `frontend/src/i18n/i18next.d.ts` — TypeScript type augmentation: deklaruje `common` a `auth` namespaces jako typy, aby TypeScript chytil chybějící/nesprávné translation keys při buildu. Přidat do `tsconfig.json` `include` pokud není v default glob.
- [ ] `frontend/src/i18n/index.ts` — re-export z config.ts
- [ ] **Poznámka:** `i18next-browser-languagedetector` plugin se nepoužívá — manuální detekce z `navigator.language` + localStorage je triviální pro 2 jazyky, menší bundle, plná kontrola.

### Fáze 3: Providers (Theme + I18n)
- [ ] `frontend/src/providers/ThemeProvider.tsx` — next-themes wrapper: `attribute="class"`, `defaultTheme="dark"`, `enableSystem={false}`, `storageKey="wpm-theme"`
- [ ] `frontend/src/providers/I18nProvider.tsx` — import `./i18n/config` (side-effect init), render children (nebo Suspense boundary pokud lazy)
- [ ] `frontend/src/main.tsx` — obalit App v `<I18nProvider><ThemeProvider>...</ThemeProvider></I18nProvider>`

### Fáze 4: No-flash script v index.html
- [ ] `frontend/index.html` — inline `<script>` v `<head>` před React: čte localStorage 'wpm-theme', nastaví `<html class="light">` pokud `'light'`, jinak nechá default (dark = `:root`). Bez System volby je skript jednodušší (žádná `prefers-color-scheme` logika).
- [ ] Skript musí být synchronní (ne `async`/`defer`)

### Fáze 5: Theme + Language toggle komponenty (vše shadcn, Single Button)
- [ ] `frontend/src/components/common/ThemeToggle.tsx` — shadcn `Button` (variant ghost, size icon) s ikonou z `lucide-react`:
  - Dark mode aktivní → zobrazí `Sun` ikonu (klik = přepne na light)
  - Light mode aktivní → zobrazí `Moon` ikonu (klik = přepne na dark)
  - `useTheme()` z next-themes, `setTheme(theme === 'dark' ? 'light' : 'dark')`
  - `aria-label={t('common.theme.toggleAria')}` (přeloženo, např. „Switch to light mode")
  - `aria-pressed={theme === 'dark'}` (asistenční technologie ví, že jde o přepínač)
  - `title={t('common.theme.toggleTitle')}` (tooltip při hoveru)
- [ ] `frontend/src/components/common/LanguageToggle.tsx` — shadcn `Button` (variant ghost, size sm):
  - Zobrazí `Globe` ikonu + kód **cílového** jazyka: `i18n.language === 'en' ? 'CS' : 'EN'` (konzistentní s theme toggle — ukazuje co se stane při kliku)
  - Klik = přepne na druhý jazyk: `i18n.changeLanguage(i18n.language === 'en' ? 'cs' : 'en')` + `localStorage.setItem('wpm-lang', newLng)`
  - `aria-label={t('common.language.toggleAria')}` (např. „Switch to Čeština")
  - `aria-pressed={i18n.language === 'cs'}` (přepínač se stavem — screen readery vědí aktuální jazyk)
  - `title={t('common.language.toggleTitle')}` (tooltip)
- [ ] `frontend/src/components/common/SettingsToggles.tsx` — horizontální layout: `LanguageToggle` + shadcn `Separator` (orientace vertical) + `ThemeToggle`. Reusable na obou místech (Sidebar + LoginPage). Layout přes Tailwind `flex items-center gap-2`.
- [ ] `next-themes` config: `enableSystem={false}` (binární přepínač, žádná System volba)
- [ ] **Žádné DropdownMenu, žádné custom CSS** — jen shadcn `Button` + `Separator` + lucide ikony

### Fáze 6: Integrace do Sidebaru + LoginPage
- [ ] `frontend/src/App.tsx` — v `Sidebar` komponentě přidat `SettingsToggles` pod navigačními linky, nad Sign out (vertikální stack s gap)
- [ ] `frontend/src/modules/auth/pages/LoginPage.tsx` — přidat `SettingsToggles` do pravého horního rohu Card (absolutní pozicování přes Tailwind `absolute top-4 right-4`) nebo nad Card, aby byl dostupný před přihlášením
- [ ] Layout kompaktní (size="sm" buttony)

### Fáze 7: Překlad UI stringů
- [ ] `LoginPage.tsx` — nahradit hardcoded EN stringy za `t('auth.login.title')` atd. + `SettingsToggles` v rohu. Použít `useTranslation()` hook (komponenta).
- [ ] `App.tsx` Sidebar — `t('sidebar.dashboard')`, `t('sidebar.sites')`, `t('sidebar.settings')`, `t('sidebar.signOut')`, `t('sidebar.signingOut')`. Použít `useTranslation()` hook (komponenta).
- [ ] `ProtectedRoute.tsx` — žádné viditelné stringy (Skeleton), nic k překladu
- [ ] **Error messages v non-React modulech** — `api.ts` a `refresh.ts` NEjsou React komponenty, **nelze použít `useTranslation()` hook**. Místo toho použít globální instanci: `import i18n from '@/i18n'; i18n.t('common.error.sessionExpired')`. Toto volání se vyhodnotí v runtime při throw, takže vrátí správný překlad podle aktuálního jazyka.
- [ ] Theme/Language toggle labely — `t('common.theme.toggleAria')`, `t('common.theme.toggleTitle')`, `t('common.language.toggleAria')`, `t('common.language.toggleTitle')` (aria-label + title atributy).

### Fáze 8: Testy
- [ ] **`frontend/src/test/testUtils.tsx`** (NOVÝ) — `renderWithProviders(ui, { routerProps, theme, locale })` helper. Obalí komponentu v `I18nProvider` + `ThemeProvider` + `QueryClientProvider` + `MemoryRouter`. Default locale `'en'`, default theme `'dark'`. Všechny ostatní testy použijí tento helper místo surového `render()` — prevence duplikace provider boilerplate a rozbijení existujících testů (hardcoded EN stringy).
- [ ] `ThemeToggle.test.tsx` — render přes `renderWithProviders`, ověřit Sun ikonu v dark mode + `aria-pressed={true}`, klik → Moon ikona + `aria-pressed={false}` + `<html class="light">`, klik → Sun + dark
- [ ] `LanguageToggle.test.tsx` — render, EN mode → zobrazí 'CS' (cílový) + `aria-pressed={false}`, klik → 'EN' (cílový) + `aria-pressed={true}` + `i18n.language === 'cs'` + localStorage, klik → zpět 'CS'
- [ ] `SettingsToggles.test.tsx` — render, ověřit obsahuje oba toggles + Separator, klik na oba
- [ ] `i18n/config.test.ts` — test detection (navigator.language cs → cs, en → en), fallback pro neznámý jazyk → en, changeLanguage aktualizuje `i18n.language` + `document.documentElement.lang`
- [ ] `App.test.tsx` — aktualizovat: použít `renderWithProviders`, test sidebar obsahuje SettingsToggles, test překladu linků (cs vs en)
- [ ] `LoginPage.test.tsx` — aktualizovat: použít `renderWithProviders`, test obsahuje SettingsToggles, test překladu (cs vs en title)
- [ ] **Aktualizace existujících testů** — `App.test.tsx`, `LoginPage.test.tsx`, `ProtectedRoute.test.tsx`, `authStore.test.ts`, `api.test.ts`, `refresh.test.ts`, `useInitAuth.test.tsx` — všechny použít `renderWithProviders` místo `render()` (ty co renderují komponenty). Testy, které dělají `screen.getByText('Sign in')`, musí počítat s překladem (fixní locale `'en'` v testech přes `renderWithProviders` default).

### Fáze 9: Validace
- [ ] `docker compose exec frontend npm run typecheck` — bez chyb
- [ ] `docker compose exec frontend npm run lint` — bez chyb
- [ ] `docker compose exec frontend npm run test` — bez chyb
- [ ] `docker compose exec frontend npm run build` — bez chyb
- [ ] Playwright — login, přepnutí theme (dark↔light, ověřit změnu barev), přepnutí jazyka (EN↔CS, ověřit překlad sidebaru), reload (persist theme + jazyk)
- [ ] Playwright — na LoginPage (před přihlášením): ověřit SettingsToggles viditelné, přepnutí theme + jazyk funguje, reload persistuje

### Fáze 10: CHANGELOG + git
- [ ] `CHANGELOG.md` — `## [0.3.0] — 09.08.2026` (MINOR — nová funkce: i18n + theme toggle)
- [ ] `CHANGELOG_CS.md` — česká verze
- [ ] `git add` + commit: `feat(ui): add i18n (EN/CS) and theme toggle (light/dark)`
- [ ] `git push origin main` (po schválení)

## 7. Testy

### 7.1 Frontend
- `ThemeToggle` — render, v dark mode Sun ikona + `aria-pressed={true}`, klik → Moon + `aria-pressed={false}` + `<html class="light">`, klik → Sun + dark
- `LanguageToggle` — render, EN mode → 'CS' (cílový) + `aria-pressed={false}`, klik → 'EN' (cílový) + `aria-pressed={true}` + `i18n.language === 'cs'` + `localStorage.getItem('wpm-lang') === 'cs'`, překlad `t('sidebar.dashboard')` === 'Přehled'
- `SettingsToggles` — obsahuje oba toggles + Separator, klik na oba funguje
- `i18n/config` — detection: `navigator.language = 'cs-CZ'` → `lng === 'cs'`, `navigator.language = 'en-US'` → `lng === 'en'`, fallback pro neznámý jazyk → 'en', `languageChanged` event aktualizuje `document.documentElement.lang`
- `App` — sidebar obsahuje SettingsToggles (přes `renderWithProviders`), po změně jazyka se linky přeloží
- `LoginPage` — obsahuje SettingsToggles, title se přeloží podle aktuálního jazyka, error message se přeloží

### 7.2 Backend
Beze změny — žádné nové backend testy.

## 8. Validace

- [ ] `docker compose exec frontend npm run typecheck` — bez chyb
- [ ] `docker compose exec frontend npm run lint` — bez chyb
- [ ] `docker compose exec frontend npm run test` — bez chyb
- [ ] `docker compose exec frontend npm run build` — bez chyb
- [ ] `docker compose exec frontend npm audit --audit-level=high` — bez vulnerabilit
- [ ] Playwright — theme toggle (dark↔light), language toggle (EN↔CS), reload persistence

## 8b. Ladění chyb (striktní pravidla)

Per `implementation-plan` skill — nejdřív dokumentace (context7 pro react-i18next/next-themes API), logování do `frontend/.debug.log` pokud testy selžou.

## 9. Rizika a mitigace

| Riziko | Pravděpodobnost | Dopad | Mitigace |
|--------|----------------|-------|----------|
| next-themes `attribute="class"` s `enableSystem=false` — přidá `class="light"` na `<html>` | střední | vysoký | globals.css používá `.light` selektor (matchuje). Ověřit Playwrightem. |
| FOUC (flash of light/dark) při načtení | střední | střední | No-flash inline script v index.html (před React) |
| i18next init race (použití před init) | nízká | střední | `I18nProvider` importuje config side-effect, init je synchronní |
| `useTranslation()` v non-React modulech (api.ts, refresh.ts) — nelze, je to hook | vysoký | vysoký | Použít globální `i18n.t()` instanci místo hooku v plain modulech |
| Testy rozbity hardcoded EN stringy + chybějící providery | vysoký | střední | `renderWithProviders()` helper s fixním locale `'en'` v testech |
| `<html lang>` se neaktualizuje při změně jazyka (WCAG 3.1.1) | střední | střední | `i18n.on('languageChanged', ...)` v config.ts nastaví `document.documentElement.lang` |
| TypeScript nepřechytí chybějící translation keys | střední | nízký | `i18next.d.ts` type augmentation — build fail na neexistující klíč |
| Bundle size nárůst (~66KB) | nízká | nízký | Akceptovatelné pro admin UI; i18next lze lazy-load pokud by byl problém |
| `navigator.language` detekce nepřesná | nízká | nízký | Fallback na 'en' pro neznámé jazyky |

## 10. Rollback strategie

- **Kód:** `git revert <commit>` — vše na main branch
- **Dependencies:** `docker compose exec frontend npm uninstall i18next react-i18next next-themes`
- **localStorage:** Theme/language keys zůstanou v browseru (neškodí, nečte je nic)
- **DB:** Beze změny

## 11. Cross-module impact

| Modul | Dopad | Míra | Poznámka |
|-------|-------|------|----------|
| Auth (frontend) | LoginPage stringy → i18n | střední | Aktualizovat LoginPage + testy |
| App (Sidebar) | Navigační linky → i18n + nové toggle komponenty | střední | Aktualizovat App + testy |
| Sites (budoucí) | Žádný | žádná | Použije `t('sidebar.sites')` když se implementuje |
| Dashboard (budoucí) | Žádný | žádná | Použije `t('sidebar.dashboard')` |
| Backend | Žádný | žádná | i18n/theme jsou čistě frontend |

- **EventDispatcher eventy:** Žádné nové eventy
- **Sdílené služby:** `i18n/config.ts` a `providers/ThemeProvider` jsou nové sdílené služby, používají je všechny komponenty
- **DB tabulky:** Beze změny

## 12. Poznámky

- **Theme palety už existují** v `globals.css` — žádné CSS změny nejsou potřeba, jen JS-side management.
- **Dark je default** (per `globals.css` `:root`), light je přes `.light` class. next-themes s `attribute="class"` a `defaultTheme="dark"` to respektuje.
- **No-flash script** je kritický — bez něj uživatel s light preference uvidí na chvíli dark (nebo naopak). Skript v `index.html` běží před React hydration.
- **i18n namespaces** — `common` (sidebar, buttons, errors, toggle aria/title) + `auth` (login page). Další moduly přidají vlastní namespaces (sites, dashboard, atd.).
- **Auto-detect** — `navigator.language` vrací `'cs-CZ'`, `'en-US'`, atd. Normalizujeme na `'cs'`/`'en'`, fallback `'en'`.
- **`i18next-browser-languagedetector` se nepoužívá** — manuální detekce z `navigator.language` + localStorage je triviální pro 2 jazyky, menší bundle, plná kontrola.
- **TypeScript type augmentation** (`i18next.d.ts`) — typuje translation keys, build fail na neexistující klíč. Importuje se přes `tsconfig.json` `include`.
- **`<html lang>` sync** — `i18n.on('languageChanged', (lng) => { document.documentElement.lang = lng; })` v `config.ts`. WCAG 3.1.1 (screen readery).
- **Non-React i18n** — `api.ts` a `refresh.ts` nemohou použít `useTranslation()` hook (nejsou komponenty). Použít `import i18n from '@/i18n'; i18n.t('key')` — globální instance, runtime překlad.
- **`renderWithProviders()` test helper** — všechny testy, které renderují komponenty, musí použít tento helper (I18n + Theme + QueryClient + Router). Fixní locale `'en'` v testech, aby `screen.getByText('Sign in')` fungoval.
- **Accessibility** — toggle buttony mají `aria-label` (přeloženo), `aria-pressed` (boolean stav), `title` (tooltip). Nejen `aria-label`.
- **Konzistence toggle zobrazení** — oba toggly (theme + language) ukazují **cílový** stav, ne aktuální:
  - Theme: dark → Sun (klik = light), light → Moon (klik = dark)
  - Language: EN → 'CS' (klik = čeština), CS → 'EN' (klik = angličtina)
  - `aria-pressed` doplňuje aktuální stav pro screen readery (WCAG).
- **Verze:** 0.3.0 (MINOR — nová funkce).
