# WP Monitor — Design System

> Vizuální jazyk a UI konvence pro WP Monitor frontend.
> Postaveno na shadcn/ui + shadcnblocks.com + TailwindCSS.
> **100% container queries + fluid typography — bez `px` a `%`.**

## 0. Principy

| Princip | Pravidlo | Proč |
|---------|----------|-----|
| **Container queries** | Komponenty reagují na velikost rodiče, ne viewportu | Komponenta v sidebaru (300cqw) a v main area (1200cqw) se chová správně |
| **Fluid typography** | `clamp()` s `cqi` jednotkami | Text se plynule přizpůsobí dostupnému prostoru |
| **Bez `px`** | Používat `rem`, `em`, `cqw`, `cqi`, `cqh`, `ch`, `vw`, `vh` | `rem` respektuje user preference (accessibility), `px` ne |
| **Bez `%`** | Používat `cqw`/`cqi` pro šířku, `cqh`/`cqb` pro výšku, `fr` pro grid | `%` je vázáno na viewport/rodiče nejednoznačně, `cq*` je explicitní |
| **shadcnblocks kompatibilita** | Bloky obalujeme v `@container`, override `px` na `rem` | shadcnblocks používají Tailwind (rem-based), custom px přepíšeme |

## 1. Tailwind konfigurace

### 1.1 Container queries plugin

```ts
// frontend/tailwind.config.ts
import containerQueries from '@tailwindcss/container-queries';
import type { Config } from 'tailwindcss';

export default {
  content: ['./src/**/*.{ts,tsx}'],
  plugins: [
    containerQueries,  // @container, @sm, @md, @lg, @xl, @2xl, @3xl, @4xl
  ],
  theme: {
    // Spacing — Tailwind default je už rem-based (0.25rem * n)
    // gap-4 = 1rem, gap-6 = 1.5rem, gap-8 = 2rem
    spacing: {
      0: '0',
      1: '0.25rem',    // 4px ekvivalent
      2: '0.5rem',     // 8px
      3: '0.75rem',    // 12px
      4: '1rem',       // 16px — výchozí
      5: '1.25rem',    // 20px
      6: '1.5rem',     // 24px
      8: '2rem',       // 32px
      10: '2.5rem',    // 40px
      12: '3rem',      // 48px
      16: '4rem',      // 64px
      20: '5rem',      // 80px
      24: '6rem',      // 96px
    },
    // Container query breakpoints (šířka kontejneru)
    containers: {
      xs: '20rem',     // 320px ekvivalent
      sm: '24rem',     // 384px
      md: '28rem',     // 448px
      lg: '32rem',     // 512px
      xl: '36rem',     // 576px
      '2xl': '42rem',  // 672px
      '3xl': '48rem',  // 768px
      '4xl': '56rem',  // 896px
      '5xl': '64rem',  // 1024px
      '6xl': '72rem',  // 1152px
      '7xl': '80rem',  // 1280px
    },
    // Fluid typography — clamp() s cqi
    fontSize: {
      '2xs': ['clamp(0.625rem, 0.6rem + 0.1cqi, 0.75rem)', { lineHeight: '1.5' }],
      'xs':  ['clamp(0.6875rem, 0.65rem + 0.15cqi, 0.8125rem)', { lineHeight: '1.5' }],
      'sm':  ['clamp(0.75rem, 0.7rem + 0.2cqi, 0.875rem)', { lineHeight: '1.5' }],
      'base':['clamp(0.875rem, 0.82rem + 0.25cqi, 1rem)', { lineHeight: '1.5' }],
      'lg':  ['clamp(1rem, 0.92rem + 0.35cqi, 1.25rem)', { lineHeight: '1.4' }],
      'xl':  ['clamp(1.125rem, 1rem + 0.5cqi, 1.5rem)', { lineHeight: '1.35' }],
      '2xl': ['clamp(1.25rem, 1.1rem + 0.7cqi, 1.75rem)', { lineHeight: '1.3' }],
      '3xl': ['clamp(1.5rem, 1.25rem + 1cqi, 2.25rem)', { lineHeight: '1.25' }],
      '4xl': ['clamp(1.75rem, 1.4rem + 1.4cqi, 3rem)', { lineHeight: '1.2' }],
      '5xl': ['clamp(2rem, 1.5rem + 2cqi, 4rem)', { lineHeight: '1.15' }],
    },
    fontFamily: {
      sans: ['Inter', 'system-ui', 'sans-serif'],
      mono: ['JetBrains Mono', 'monospace'],
    },
    // Radius — rem, ne px
    borderRadius: {
      none: '0',
      sm: '0.25rem',
      DEFAULT: '0.5rem',  // shadcn/ui default --radius
      md: '0.625rem',
      lg: '0.75rem',
      xl: '1rem',
      '2xl': '1.5rem',
      full: '9999rem',
    },
    // Breakpoints — pouze pro page-level layout (ne komponenty!)
    screens: {
      sm: '20rem',    // 320px
      md: '48rem',    // 768px
      lg: '64rem',    // 1024px
      xl: '80rem',    // 1280px
      '2xl': '96rem', // 1536px
    },
  },
} satisfies Config;
```

### 1.2 globals.css — fluid root

```css
/* globals.css */

/* Root font size — 100% respektuje user preference (ne 16px!) */
html {
  font-size: 100%;
  -webkit-text-size-adjust: 100%;
}

/* Container query context — root aplikace */
#root {
  container-type: inline-size;
  container-name: app;
}

/* Každý modul/page má vlastní container context */
.module-container {
  container-type: inline-size;
  container-name: module;
}

/* Každá karta/widget má vlastní container context */
.card-container {
  container-type: inline-size;
  container-name: card;
}

/* Sidebar container */
.sidebar-container {
  container-type: inline-size;
  container-name: sidebar;
}

/* CSS variables (HSL) — dark-first */
:root {
  --background: 222 47% 11%;
  --foreground: 210 40% 98%;
  --card: 222 47% 14%;
  --card-foreground: 210 40% 98%;
  --popover: 222 47% 14%;
  --popover-foreground: 210 40% 98%;
  --primary: 243 75% 59%;
  --primary-foreground: 0 0% 100%;
  --secondary: 215 28% 17%;
  --secondary-foreground: 210 40% 98%;
  --muted: 215 28% 17%;
  --muted-foreground: 215 20% 65%;
  --accent: 215 28% 17%;
  --accent-foreground: 210 40% 98%;
  --destructive: 0 72% 51%;
  --destructive-foreground: 0 0% 100%;
  --success: 142 71% 45%;
  --success-foreground: 0 0% 100%;
  --warning: 38 92% 50%;
  --warning-foreground: 0 0% 100%;
  --info: 199 89% 48%;
  --info-foreground: 0 0% 100%;
  --border: 215 28% 22%;
  --input: 215 28% 22%;
  --ring: 243 75% 59%;
  --radius: 0.5rem;

  /* Fluid spacing tokens (rem-based, ne px) */
  --space-xs: 0.25rem;
  --space-sm: 0.5rem;
  --space-md: 1rem;
  --space-lg: 1.5rem;
  --space-xl: 2rem;
  --space-2xl: 3rem;

  /* Fluid sidebar width — clamp s cqi (ne px, ne %) */
  --sidebar-w-min: 12rem;
  --sidebar-w-default: clamp(12rem, 15cqw, 16rem);
  --sidebar-w-collapsed: 3.5rem;
}

/* Light mode — samostatně navržená paleta (ne inverze dark mode!) */
.light {
  /* Background — teplá bílá, ne čistá #FFF (méně namáhá oči) */
  --background: 210 20% 99%;           /* #FCFCFD — warm white */
  --foreground: 222 47% 11%;           /* #0F172A — slate-900 */

  /* Surface — karty, panely */
  --card: 0 0% 100%;                   /* #FFFFFF — čistá bílá pro karty */
  --card-foreground: 222 47% 11%;

  --popover: 0 0% 100%;
  --popover-foreground: 222 47% 11%;

  /* Primary — indigo (stejný jako dark, ale foreground je bílá) */
  --primary: 243 75% 59%;              /* #4F46E5 — indigo-600 */
  --primary-foreground: 0 0% 100%;

  /* Secondary — světlejší slate */
  --secondary: 210 40% 96%;            /* #F1F5F9 — slate-100 */
  --secondary-foreground: 222 47% 11%;

  /* Muted — jemné pozadí pro hint texty */
  --muted: 210 40% 96%;
  --muted-foreground: 215 16% 47%;     /* #64748B — slate-500 */

  /* Accent — hover stavy */
  --accent: 210 40% 94%;               /* #E2E8F0 — slate-200 */
  --accent-foreground: 222 47% 11%;

  /* Status colors — upravené pro light mode (vyšší saturation pro kontrast na bílém) */
  --destructive: 0 84% 60%;            /* #E11D48 — rose-600 (více sat. než dark red) */
  --destructive-foreground: 0 0% 100%;

  --success: 142 71% 35%;              /* #16A34A — green-600 (tmavší pro kontrast na bílém) */
  --success-foreground: 0 0% 100%;

  --warning: 38 92% 45%;               /* #D97706 — amber-600 (tmavší pro kontrast) */
  --warning-foreground: 0 0% 100%;

  --info: 199 89% 40%;                 /* #0284C7 — sky-600 (tmavší pro kontrast) */
  --info-foreground: 0 0% 100%;

  /* Border & Input — světlejší border pro light mode */
  --border: 214 32% 91%;               /* #CBD5E1 — slate-300 */
  --input: 214 32% 91%;
  --ring: 243 75% 59%;                  /* indigo — focus ring */

  /* Radius — stejný */
  --radius: 0.5rem;

  /* Fluid spacing tokens — stejné (rem-based, nezávislé na theme) */
  --space-xs: 0.25rem;
  --space-sm: 0.5rem;
  --space-md: 1rem;
  --space-lg: 1.5rem;
  --space-xl: 2rem;
  --space-2xl: 3rem;

  /* Fluid sidebar width — stejné (rem-based, nezávislé na theme) */
  --sidebar-w-min: 12rem;
  --sidebar-w-default: clamp(12rem, 15cqw, 16rem);
  --sidebar-w-collapsed: 3.5rem;

  /* Shadows — light mode má výraznější stíny (dark mode je potlačuje) */
  --shadow-sm: 0 0.0625rem 0.1875rem 0 hsl(222 47% 11% / 0.05);
  --shadow-md: 0 0.25rem 0.5rem 0 hsl(222 47% 11% / 0.08);
  --shadow-lg: 0 0.5rem 1rem 0 hsl(222 47% 11% / 0.10);
  --shadow-xl: 0 0.75rem 1.5rem 0 hsl(222 47% 11% / 0.12);

  /* Elevation — light mode používá stíny pro hloubku (dark mode používá barvy) */
  --elevation-1: var(--shadow-sm);     /* karty, inputy */
  --elevation-2: var(--shadow-md);     /* hover, dropdown */
  --elevation-3: var(--shadow-lg);     /* dialog, popover */
  --elevation-4: var(--shadow-xl);     /* modal, fullscreen */
}

/* Dark mode shadows — potlačené (tmavé pozadí, stíny nejsou vidět) */
:root {
  --shadow-sm: 0 0.0625rem 0.1875rem 0 hsl(0 0% 0% / 0.20);
  --shadow-md: 0 0.25rem 0.5rem 0 hsl(0 0% 0% / 0.25);
  --shadow-lg: 0 0.5rem 1rem 0 hsl(0 0% 0% / 0.30);
  --shadow-xl: 0 0.75rem 1.5rem 0 hsl(0 0% 0% / 0.35);

  /* Elevation — dark mode používá světlejší barvy, ne stíny */
  --elevation-1: none;                 /* karty — barha pozadí stačí */
  --elevation-2: 0 0.0625rem 0.1875rem 0 hsl(0 0% 0% / 0.20);
  --elevation-3: 0 0.25rem 0.5rem 0 hsl(0 0% 0% / 0.25);
  --elevation-4: 0 0.5rem 1rem 0 hsl(0 0% 0% / 0.30);
}
```

## 2. Fluid typografie

### 2.1 Typografická škála

Všechny velikosti používají `clamp(min, preferred, max)` kde `preferred` obsahuje `cqi` (container query inline size — 1cqi = 1% šířky kontejneru).

| Třída | clamp() | Min (xs kontejner) | Max (xl kontejner) | Použití |
|-------|---------|--------------------|--------------------|---------|
| `text-2xs` | `clamp(0.625rem, 0.6rem + 0.1cqi, 0.75rem)` | 0.625rem | 0.75rem | Badge, meta |
| `text-xs` | `clamp(0.6875rem, 0.65rem + 0.15cqi, 0.8125rem)` | 0.6875rem | 0.8125rem | Caption, hint |
| `text-sm` | `clamp(0.75rem, 0.7rem + 0.2cqi, 0.875rem)` | 0.75rem | 0.875rem | Tabulky, form |
| `text-base` | `clamp(0.875rem, 0.82rem + 0.25cqi, 1rem)` | 0.875rem | 1rem | Body text |
| `text-lg` | `clamp(1rem, 0.92rem + 0.35cqi, 1.25rem)` | 1rem | 1.25rem | Card title |
| `text-xl` | `clamp(1.125rem, 1rem + 0.5cqi, 1.5rem)` | 1.125rem | 1.5rem | Section title |
| `text-2xl` | `clamp(1.25rem, 1.1rem + 0.7cqi, 1.75rem)` | 1.25rem | 1.75rem | Page title |
| `text-3xl` | `clamp(1.5rem, 1.25rem + 1cqi, 2.25rem)` | 1.5rem | 2.25rem | Hero stat |
| `text-4xl` | `clamp(1.75rem, 1.4rem + 1.4cqi, 3rem)` | 1.75rem | 3rem | Dashboard KPI |
| `text-5xl` | `clamp(2rem, 1.5rem + 2cqi, 4rem)` | 2rem | 4rem | Login heading |

### 2.2 Jak to funguje

```
Kontejner šířka:  20rem (xs)  →  40rem (md)  →  80rem (xl)
text-base:        0.875rem    →  ~0.93rem    →  1rem
text-2xl:         1.25rem     →  ~1.45rem    →  1.75rem
text-4xl:         1.75rem     →  ~2.25rem    →  3rem
```

Text roste plynule s dostupným prostorem — žádné skokové zlomy.

### 2.3 Font weights a line heights

| Element | Font | Váha | Line height |
|---------|------|------|-------------|
| H1 | Inter | 700 | 1.2 |
| H2 | Inter | 700 | 1.3 |
| H3 | Inter | 600 | 1.4 |
| H4 | Inter | 600 | 1.4 |
| Body | Inter | 400 | 1.5 |
| Small | Inter | 400 | 1.5 |
| Mono | JetBrains Mono | 400 | 1.5 |

## 3. Spacing — rem-based (bez px)

### 3.1 Spacing scale

Tailwind spacing je **už v rem** — `gap-4` = `1rem` (ne 16px). Žádné px nikde.

| Třída | rem | Ekvivalent (při 16px root) | Použití |
|-------|-----|---------------------------|---------|
| `gap-0` | 0 | 0 | Reset |
| `gap-1` | 0.25rem | 4px | Tight spacing |
| `gap-2` | 0.5rem | 8px | Icon + text |
| `gap-3` | 0.75rem | 12px | Form fields |
| `gap-4` | 1rem | 16px | **Výchozí** pro většinu UI |
| `gap-6` | 1.5rem | 24px | Sekce, karty |
| `gap-8` | 2rem | 32px | Hlavní sekce |
| `gap-12` | 3rem | 48px | Page-level spacing |
| `gap-16` | 4rem | 64px | Hero spacing |

### 3.2 Fluid spacing — clamp s cqi

Pro specifické případy kde spacing má růst s kontejnerem:

```css
/* Fluid padding — roste s kontejnerem */
.fluid-padding {
  padding: clamp(0.75rem, 2cqi, 1.5rem);
}

/* Fluid gap mezi kartami */
.fluid-gap {
  gap: clamp(0.75rem, 1.5cqi, 1.5rem);
}
```

### 3.3 Layout grid — bez px a %

```css
/* App layout — grid s fr units (ne %) */
.app-layout {
  display: grid;
  grid-template-columns: var(--sidebar-w-default) 1fr;
  min-height: 100vh;
}

/* Sidebar collapsed */
.app-layout.collapsed {
  grid-template-columns: var(--sidebar-w-collapsed) 1fr;
}

/* Card grid — auto-fill s minmax (ne %) */
.card-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr));
  gap: clamp(0.75rem, 1.5cqi, 1.5rem);
}

/* Pozor: min(100%, 20rem) je jediná povolená výjimka pro %
   — je to bezpečné (100% = plná šířka rodiče v grid kontextu) */
```

### 3.4 Container max-widths — rem, ne px

| Třída | rem | Použití |
|-------|-----|---------|
| `max-w-xs` | 20rem | Mobile full |
| `max-w-sm` | 24rem | Form |
| `max-w-md` | 28rem | Dialog |
| `max-w-lg` | 32rem | Dialog large |
| `max-w-xl` | 36rem | Article |
| `max-w-2xl` | 42rem | Table |
| `max-w-4xl` | 56rem | Wide table |
| `max-w-6xl` | 72rem | Page container |
| `max-w-7xl` | 80rem | Full width |

## 3.5 Grid systém — 12-sloupcová fluid mřížka

shadcnblocks nemá vlastní grid systém — bloky mají jen vlastní layout. WP Monitor zavádí **jednotnou 12-sloupcovou mřížku** pro konzistenci napříč všemi moduly a shadcnblocks bloky.

### Princip

- **12 sloupců** — standardní grid (jako Bootstrap, Material, ale bez px)
- **CSS Grid** — ne flexbox hacky, ne float
- **Container queries** — mřížka reaguje na šířku kontejneru, ne viewportu
- **`fr` units** — žádné % pro sloupce
- **`rem` gaps** — fluid gap s `clamp()` + `cqi`
- **Bez px, bez %** — pouze `fr`, `rem`, `cqi`, `ch`

### Tailwind konfigurace

```ts
// frontend/tailwind.config.ts — přidat do theme
gridTemplateColumns: {
  // 12-sloupcový grid
  '12': 'repeat(12, minmax(0, 1fr))',
  // Předdefinované layouty
  'sidebar-main': 'minmax(12rem, 15cqw) 1fr',
  'sidebar-collapsed-main': '3.5rem 1fr',
  'dashboard': 'repeat(auto-fit, minmax(min(100%, 20rem), 1fr))',
  'bento-3': 'repeat(3, minmax(0, 1fr))',
  'bento-4': 'repeat(4, minmax(0, 1fr))',
  'bento-6': 'repeat(6, minmax(0, 1fr))',
},
gridColumn: {
  // Span utility — col-span-1 až col-span-12 (Tailwind už má tyto)
  // Custom pro container query varianty
  'full': '1 / -1',
},
gridAutoRows: {
  'auto': 'auto',
  'min': 'min-content',
  'max': 'max-content',
  'fr': 'minmax(0, 1fr)',
},
```

### CSS — grid systém třídy

```css
/* globals.css — grid systém */

/* Základní 12-sloupcový kontejner */
.grid-12 {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: clamp(0.75rem, 1.5cqi, 1.5rem);
}

/* Container query varianty — sloupce se přidávají s šířkou kontejneru */
@container (min-width: 20rem) {
  .grid-responsive { grid-template-columns: repeat(1, minmax(0, 1fr)); }
}
@container (min-width: 28rem) {
  .grid-responsive { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@container (min-width: 42rem) {
  .grid-responsive { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@container (min-width: 56rem) {
  .grid-responsive { grid-template-columns: repeat(4, minmax(0, 1fr)); }
}
@container (min-width: 72rem) {
  .grid-responsive { grid-template-columns: repeat(6, minmax(0, 1fr)); }
}

/* Auto-fill bento grid — karty se samy rozhodují o velikosti */
.grid-bento {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 16rem), 1fr));
  grid-auto-rows: minmax(8rem, auto);
  gap: clamp(0.75rem, 1.5cqi, 1.5rem);
}

/* Dashboard grid — stat cards */
.grid-dashboard {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(min(100%, 20rem), 1fr));
  gap: clamp(0.75rem, 1.5cqi, 1.5rem);
}

/* Form grid — 2 sloupce na širokém, 1 na úzkém */
.grid-form {
  display: grid;
  grid-template-columns: repeat(1, minmax(0, 1fr));
  gap: 1rem;
}
@container (min-width: 32rem) {
  .grid-form { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

/* Sidebar + main layout */
.grid-app {
  display: grid;
  grid-template-columns: var(--sidebar-w-default) 1fr;
  min-height: 100vh;
  transition: grid-template-columns 200ms ease-in-out;
}
.grid-app.collapsed {
  grid-template-columns: var(--sidebar-w-collapsed) 1fr;
}
```

### Tailwind utility třídy pro grid

```tsx
// 12-sloupcový grid s col-span
<div className="grid grid-cols-12 gap-4 @md:gap-6">
  <div className="col-span-12 @md:col-span-6 @lg:col-span-4">
    {/* 12 → 6 → 4 sloupců podle kontejneru */}
  </div>
  <div className="col-span-12 @md:col-span-6 @lg:col-span-8">
    {/* 12 → 6 → 8 sloupců */}
  </div>
</div>

// Auto-fill responsive grid (žádné explicitní breakpointy)
<div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 @2xl:grid-cols-4 gap-4 @md:gap-6">
  {items.map(item => <Card key={item.id} />)}
</div>

// Bento grid
<div className="grid grid-cols-2 @md:grid-cols-3 @lg:grid-cols-4 @2xl:grid-cols-6 gap-3 @md:gap-4">
  <div className="col-span-2 row-span-2">{/* velký widget */}</div>
  <div className="col-span-1">{/* malý widget */}</div>
  <div className="col-span-1">{/* malý widget */}</div>
  <div className="col-span-2">{/* široký widget */}</div>
</div>
```

### Grid šablony pro WP Monitor moduly

| Layout | Grid | Container breakpointy | Použití |
|--------|------|----------------------|---------|
| **Dashboard stat cards** | `grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 @2xl:grid-cols-4` | @md, @lg, @2xl | Hlavní dashboard, přehled |
| **Bento dashboard** | `grid-cols-2 @md:grid-cols-3 @lg:grid-cols-4 @2xl:grid-cols-6` | @md, @lg, @2xl | Widgety, score cards |
| **Sites list** | `grid-cols-1 @3xl:grid-cols-2` | @3xl | Site cards vedle sebe |
| **Data table** | `grid-cols-1` (plná šířka) | — | Tabulky, detail výpisy |
| **Form 2-col** | `grid-cols-1 @lg:grid-cols-2` | @lg | Formuláře s dvojicemi polí |
| **Settings** | `grid-cols-1 @md:grid-cols-3` (labels + inputs) | @md | Settings panel |
| **Login** | `grid-cols-1` (centered, max-w-sm) | — | Login, register |
| **Sidebar + main** | `grid-template-columns: var(--sidebar-w) 1fr` | media query | App shell |

### Col-span referenční tabulka

| col-span | Šířka (z 12) | Typické použití |
|----------|-------------|-----------------|
| `col-span-12` | 100% (full) | Page header, tabulka, full-width alert |
| `col-span-8` | 2/3 | Hlavní obsah (vedle sidebaru) |
| `col-span-6` | 1/2 | Dva sloupce, dva panely |
| `col-span-4` | 1/3 | Tři sloupce, stat card |
| `col-span-3` | 1/4 | Čtyři sloupce, malý stat |
| `col-span-2` | 1/6 | Bento malý widget |
| `col-span-1` | 1/12 | Ikona, badge, mini widget |

### Integrace se shadcnblocks

shadcnblocks bloky se obalují do gridu stejně jako custom komponenty:

```tsx
// ✅ shadcnblocks Dashboard blok v grid mřížce
<div className="@container">
  <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 @2xl:grid-cols-4 gap-4 @md:gap-6">
    <BlockContainer>
      <StatCardBlock className="h-full" />
    </BlockContainer>
    <BlockContainer>
      <ChartGroupBlock className="h-full" />
    </BlockContainer>
    <BlockContainer className="col-span-1 @lg:col-span-2 @2xl:col-span-2">
      {/* Širší widget — zabere 2 sloupce na @lg+ */}
      <BentoBlock className="h-full" />
    </BlockContainer>
  </div>
</div>

// ✅ Bento layout s col-span pro různé velikosti widgetů
<div className="@container">
  <div className="grid grid-cols-2 @md:grid-cols-4 @lg:grid-cols-6 gap-3 @md:gap-4">
    <div className="col-span-2 row-span-2">  {/* velký — 2x2 */}
      <BentoBlock title="Uptime" />
    </div>
    <div className="col-span-1">              {/* malý — 1x1 */}
      <BentoBlock title="SSL" />
    </div>
    <div className="col-span-1">              {/* malý — 1x1 */}
      <BentoBlock title="Updates" />
    </div>
    <div className="col-span-2">              {/* široký — 2x1 */}
      <BentoBlock title="Backups" />
    </div>
  </div>
</div>
```

### Pravidla

1. **Vždy obalit v `@container`** — grid reaguje na šířku rodiče
2. **`grid-cols-12`** pro explicitní layout (col-span kontrola)
3. **`grid-cols-{n}` s `@md:`/`@lg:`** pro auto-responsive (jednodušší)
4. **`gap-4`** výchozí, `gap-6` pro sekce, `gap-3` pro tight bento
5. **`minmax(0, 1fr)`** vždy — zabrání overflow obsahu (min-width: 0)
6. **`h-full`** na kartách v gridu — zarovná výšky
7. **`row-span-2`** pro velké widgety v bento — využij vertikální prostor
8. **Nikdy `px` nebo `%`** v grid — pouze `fr`, `rem`, `cqi`, `ch`

## 4. Container queries — jak to funguje

### 4.1 Základní princip

```css
/* Místo media query (reaktuje na viewport): */
@media (min-width: 768px) {
  .card { grid-template-columns: 1fr 1fr; }
}

/* Container query (reaktuje na velikost rodiče): */
@container card (min-width: 28rem) {
  .card-content { grid-template-columns: 1fr 1fr; }
}
```

### 4.2 Tailwind container query třídy

```tsx
// Rodič musí mít container-type
<div className="@container">
  {/* Reaguje na šířku tohoto divu, ne viewportu */}
  <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3">
    {/* 1 sloupec když kontejner < 28rem */}
    {/* 2 sloupce když kontejner >= 28rem */}
    {/* 3 sloupce když kontejner >= 32rem */}
  </div>
</div>
```

### 4.3 Container query breakpoints

| Třída | Min šířka kontejneru | Použití |
|-------|---------------------|---------|
| `@xs` | 20rem (320px) | Phone widget |
| `@sm` | 24rem (384px) | Small card |
| `@md` | 28rem (448px) | Medium card |
| `@lg` | 32rem (512px) | Large card / sidebar |
| `@xl` | 36rem (576px) | Wide card |
| `@2xl` | 42rem (672px) | Panel |
| `@3xl` | 48rem (768px) | Main content |
| `@4xl` | 56rem (896px) | Wide main |
| `@5xl` | 64rem (1024px) | Full main |
| `@6xl` | 72rem (1152px) | Ultra wide |
| `@7xl` | 80rem (1280px) | Max width |

### 4.4 Media queries vs container queries

| Co | Použít | Proč |
|----|--------|------|
| Page layout (sidebar show/hide) | Media query (`sm:`, `md:`) | Sidebar závisí na viewportu |
| Komponenta uvnitř (grid, columns) | Container query (`@md:`, `@lg:`) | Komponenta může být v sidebaru nebo main area |
| Typografie | Container query (`cqi` v `clamp()`) | Text se přizpůsobí dostupnému prostoru |
| Dialog velikost | Container query | Dialog je kontejner sám o sobě |

## 5. shadcnblocks integrace

### 5.1 Jak obalit shadcnblocks v container context

shadcnblocks jsou postavené na TailwindCSS — většina už používá `rem`. Custom `px` hodnoty přepíšeme.

```tsx
// ✅ SPRÁVNĚ: shadcnblocks blok obalen v @container
<div className="@container">
  <DashboardBlock
    className="
      p-4 @md:p-6 @lg:p-8
      gap-4 @md:gap-6
      text-sm @md:text-base
    "
  />
</div>

// ❌ ŠPATNĚ: bez container context, px hodnoty
<div>
  <DashboardBlock className="p-4 gap-4" style={{ padding: '16px' }} />
</div>
```

### 5.2 Override px → rem v shadcnblocks

Některé shadcnblocks mají inline `px` hodnoty. Postup:

1. **Instalace:** `npx shadcn@latest add <url>` — blok se zkopíruje do `frontend/src/components/blocks/`
2. **Audit:** najít všechny `px` a `%` hodnoty v bloku
3. **Override:** nahradit `px` → `rem` (16px = 1rem), `%` → `cqw`/`cqi` nebo `fr`
4. **Container context:** přidat `@container` na rodiče

```tsx
// Před (shadcnblocks default — může obsahovat px):
<div className="p-[16px] gap-[24px] w-[50%]">

// Po (WP Monitor override — rem + container queries):
<div className="p-4 gap-6 w-full @md:w-[48rem]">
```

### 5.3 Container query wrapper pro shadcnblocks

```tsx
// Wrapper komponenta pro shadcnblocks s container context
function BlockContainer({ children, className }: Props) {
  return (
    <div className="@container">
      <div className={className}>
        {children}
      </div>
    </div>
  );
}

// Použití
<BlockContainer>
  <DataTableBlock
    className="
      grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3
      text-sm @md:text-base
      gap-4 @md:gap-6
    "
  />
</BlockContainer>
```

### 5.4 shadcnblocks mapování

| shadcnblocks kategorie | Container name | Typické @container breakpointy | Poznámka |
|------------------------|---------------|-------------------------------|----------|
| **Dashboard** (18) | `@container` | `@md`, `@lg`, `@xl` | Stat cards → grid se mění s kontejnerem |
| **Application Shell** (14) | `@container app` | `@3xl`, `@5xl` | Layout — sidebar show/hide na viewportu (media query) |
| **Data Table** (32) | `@container` | `@lg`, `@2xl`, `@4xl` | Sloupce se přidávají s šířkou kontejneru |
| **Chart Group** (15) | `@container` | `@md`, `@lg` | Chart velikost se přizpůsobí kontejneru |
| **Bento** (53) | `@container` | `@sm`, `@md`, `@lg`, `@xl` | Bento grid se přeskupuje podle kontejneru |
| **Feature** (311) | `@container` | `@md`, `@lg` | Content layout se přizpůsobí |

## 6. Komponenty — konvence

### 6.1 Button

| Varianta | Použití | Barva |
|----------|---------|-------|
| `default` | Primární akce | indigo |
| `secondary` | Sekundární akce | slate |
| `destructive` | Nebezpečná akce (smazat) | red |
| `outline` | Tlačítka v dialogu | border + transparent |
| `ghost` | Toolbar, ikon tlačítka | transparent |
| `link` | Odkaz v textu | indigo, underline |

**Velikosti (rem, ne px):**

| Velikost | Height | Padding | Font |
|----------|--------|---------|------|
| `sm` | `h-8` (2rem) | `px-3` (0.75rem) | `text-sm` |
| `default` | `h-10` (2.5rem) | `px-4` (1rem) | `text-base` |
| `lg` | `h-12` (3rem) | `px-6` (1.5rem) | `text-lg` |
| `icon` | `h-10 w-10` (2.5rem) | `0` | — |

### 6.2 Card — s container context

```tsx
// ✅ Card s @container — vnitřní layout reaguje na šířku karty
<div className="@container card-container">
  <Card className="p-4 @md:p-6 @lg:p-8">
    <CardHeader>
      <CardTitle className="text-lg @md:text-xl @lg:text-2xl">
        Titulek
      </CardTitle>
      <CardDescription className="text-sm @md:text-base">
        Popis
      </CardDescription>
    </CardHeader>
    <CardContent>
      <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 gap-4">
        {/* obsah se přeskupí podle šířky karty */}
      </div>
    </CardContent>
    <CardFooter>
      {/* akce */}
    </CardFooter>
  </Card>
</div>
```

### 6.3 Data Table

- Používat shadcnblocks Data Table bloky obalené v `@container`
- Stránkování: 25/50/100 řádků
- Sloupce: přidávají se s `@lg:`, `@2xl:`, `@4xl:` container breakpoints
- Sort: klik na header (indigo šipka)
- Filter: nad tabulkou, expandable
- Empty state: ikona + text + CTA
- Loading: skeleton rows (3-5)
- Row actions: Dropdown Menu (ne ikony v řádku)

```tsx
<div className="@container">
  <DataTableBlock
    columns={[
      { id: 'name', className: 'text-sm @md:text-base' },
      { id: 'url', className: 'hidden @lg:table-cell' },      // skryté na malém kontejneru
      { id: 'status', className: 'hidden @md:table-cell' },
      { id: 'updates', className: 'hidden @2xl:table-cell' },
      { id: 'actions' },
    ]}
  />
</div>
```

### 6.4 Status badges

| Status | Barva | Použití |
|--------|-------|---------|
| `success` | green | Online, dokončeno, healthy |
| `warning` | amber | SSL expiruje, needs attention |
| `destructive` | red | Offline, failed, critical |
| `info` | sky | In progress, pending |
| `secondary` | slate | Disabled, inactive, N/A |

### 6.5 Dialog / Modal

- Max width: `max-w-md` (default), `max-w-2xl` (formuláře), `max-w-4xl` (tabulky)
- Overlay: `bg-black/80`
- Animace: fade + scale (shadcn/ui default)
- Zavření: Esc, click outside, X button
- Potvrzovací dialogy: destructive button vpravo
- Dialog má vlastní `@container` context

### 6.6 Form

- Používat `react-hook-form` + `zod` pro validaci
- Label nad inputem (ne vedle)
- Error zpráva pod inputem (red, `text-sm`)
- Required field: `*` v labelu (red)
- Submit button: vpravo dole
- Input height: `h-10` (2.5rem), padding `px-3` (0.75rem)

## 7. Ikony

- **Knihovna:** Lucide React (`lucide-react`)
- **Velikost (rem, ne px):** `h-4 w-4` (sm, 1rem), `h-5 w-5` (default, 1.25rem), `h-6 w-6` (lg, 1.5rem)
- **Barva:** `currentColor` (dědí z text color)
- **Stroke width:** 2 (default)

```tsx
import { Shield, Database, Globe, AlertTriangle } from 'lucide-react';

<Shield className="h-5 w-5 text-primary" />
```

## 8. Animace a transitions

| Element | Transition | Duration | Easing |
|---------|-----------|----------|--------|
| Hover (button, card) | `background-color`, `opacity` | 150ms | ease-out |
| Dialog open/close | fade + scale | 200ms | ease-out |
| Sidebar collapse | width | 200ms | ease-in-out |
| Page transition | fade | 150ms | ease-out |
| Skeleton shimmer | opacity pulse | 1.5s | ease-in-out (loop) |

```css
/* Tailwind */
transition-colors duration-150 ease-out   /* default */
transition-all duration-200 ease-out      /* dialogs */
```

> **Poznámka:** `ms` pro transition duration je standardní CSS jednotka, není to `px`. Povoleno.

## 9. Responzivita — container queries + media queries

### 9.1 Kdy použít container query vs media query

| Scénář | Typ | Proč |
|--------|-----|------|
| Sidebar show/hide | Media query (`md:`) | Závisí na fyzické velikosti zařízení |
| Komponenta grid (1→2→3 sloupce) | Container query (`@md:`, `@lg:`) | Komponenta může být kdekoliv |
| Typografie velikost | Container query (`cqi` v `clamp()`) | Text se přizpůsobí prostoru |
| Dialog velikost | Container query | Dialog je izolovaný kontejner |
| Page max-width | Media query (`xl:`) | Page layout závisí na viewportu |
| Bento grid přeskupení | Container query (`@sm:`, `@md:`, `@lg:`) | Bento se přeskupí podle kontejneru |

### 9.2 Container query chování

```
Kontejner šířka:  < 24rem (@xs)  →  28rem (@md)  →  32rem (@lg)  →  42rem (@2xl)
Card grid:        1 sloupec       →  1 sloupec     →  2 sloupce    →  3 sloupce
Card padding:     p-4 (1rem)      →  p-4           →  p-6 (1.5rem) →  p-8 (2rem)
Card title:       text-lg         →  text-lg       →  text-xl      →  text-2xl
```

### 9.3 Media query — pouze pro page-level

| Breakpoint | Chování |
|------------|---------|
| `< sm` (mobile) | Sidebar skryt (hamburger), 1 sloupec |
| `sm – lg` (tablet) | Sidebar collapsible |
| `> lg` (desktop) | Plný layout, sidebar fixní |

**Mobile-first:** všechny komponenty se navrhují pro nejužší kontejner první, pak se rozšiřují.

## 10. Accessibility (a11y)

- **WCAG 2.1 Level AA** cílový standard
- **rem-based typography** — respektuje user preference (browser font size)
- **Fluid typography** — `clamp()` zaručuje čitelnost na všech velikostech
- Kontrast ratio: min. 4.5:1 pro text, 3:1 pro velký text
- Focus visible: indigo ring (`outline-2 outline-offset-2`, rem-based)
- Keyboard navigace: Tab, Enter, Esc, arrow keys
- ARIA labels na ikonových tlačítkách
- `role="status"` pro live aktualizace (progress bary)
- `aria-live="polite"` pro toast notifikace
- Screen reader: skryté dekorativní ikony `aria-hidden="true"`
- **Žádné `text-size-adjust: none`** — fluid typography potřebuje adjust

## 11. Theme systém — Dark & Light mode

WP Monitor podporuje **dva plně navržené theme** — dark (výchozí) a light. **Nejde o inverzi barev** — každý theme má vlastní paletu, stíny, elevation a kontrast poměry optimalizované pro své pozadí.

### 11.1 Rozdíly mezi dark a light (ne jen inverze)

| Aspekt | Dark mode | Light mode | Proč se liší |
|--------|-----------|------------|-------------|
| **Background** | `222 47% 11%` (slate-900) | `210 20% 99%` (warm white) | Light používá teplou bílou, ne čistou #FFF (méně namáhá oči) |
| **Card surface** | `222 47% 14%` (slate-800) | `0 0% 100%` (čistá bílá) | Karty v light musí být výrazně oddělené od pozadí |
| **Primary** | `243 75% 59%` (indigo-600) | `243 75% 59%` (indigo-600) | Stejný — brand barva | |
| **Destructive** | `0 72% 51%` (red-600) | `0 84% 60%` (rose-600) | Light potřebuje vyšší saturation pro viditelnost na bílém |
| **Success** | `142 71% 45%` (green-500) | `142 71% 35%` (green-600) | Light potřebuje tmavší zelenou pro kontrast |
| **Warning** | `38 92% 50%` (amber-500) | `38 92% 45%` (amber-600) | Light potřebuje tmavší amber |
| **Info** | `199 89% 48%` (sky-500) | `199 89% 40%` (sky-600) | Light potřebuje tmavší sky |
| **Border** | `215 28% 22%` (slate-700) | `214 32% 91%` (slate-300) | Light border je světlý, dark border je tmavý |
| **Muted text** | `215 20% 65%` (slate-400) | `215 16% 47%` (slate-500) | Light potřebuje tmavší muted text pro čitelnost |
| **Shadows** | Potlačené (černé, nízká opacity) | Výraznější (slate, vyšší opacity) | Dark pozadí nepotřebuje stíny pro hloubku |
| **Elevation** | Barvou (světlejší surface) | Stíny (shadow-sm/md/lg/xl) | Dark mode = hloubka barvou, light mode = hloubka stínem |
| **Overlay** | `bg-black/80` | `bg-black/50` | Light mode potřebuje světlejší overlay (jinak je moc kontrastní) |
| **Focus ring** | `243 75% 59%` (indigo) | `243 75% 59%` (indigo) | Stejný — brand barva |
| **Skeleton** | `bg-slate-800 animate-pulse` | `bg-slate-200 animate-pulse` | Různé base barvy |
| **Scrollbars** | `slate-700` thumb, `slate-900` track | `slate-300` thumb, `slate-100` track | Kontrast s pozadím |

### 11.2 Kontrast poměry — WCAG 2.1 AA

| Element | Dark mode kontrast | Light mode kontrast | Požadavek |
|---------|-------------------|--------------------|-----------| 
| Foreground na background | 15.3:1 ✅ | 15.1:1 ✅ | ≥ 4.5:1 |
| Card foreground na card | 14.5:1 ✅ | 15.1:1 ✅ | ≥ 4.5:1 |
| Muted foreground na background | 5.9:1 ✅ | 4.6:1 ✅ | ≥ 4.5:1 |
| Primary na primary-foreground | 5.9:1 ✅ | 5.9:1 ✅ | ≥ 4.5:1 |
| Destructive na white | 4.8:1 ✅ | 4.5:1 ✅ | ≥ 4.5:1 |
| Success na white | 3.2:1 ⚠️ (large text) | 4.8:1 ✅ | ≥ 3:1 large / 4.5:1 normal |
| Warning na white | 3.1:1 ⚠️ (large text) | 4.6:1 ✅ | ≥ 3:1 large / 4.5:1 normal |
| Info na white | 3.4:1 ⚠️ (large text) | 4.7:1 ✅ | ≥ 3:1 large / 4.5:1 normal |

> **Poznámka:** Status barvy (success/warning/info) v dark mode mají nižší kontrast na tmavém pozadí. Pro text použít `*-foreground` (bílá na barvě = 4.5:1+). Pro ikony a badge (large/bold) stačí 3:1.

### 11.3 Elevation systém

| Úroveň | Dark mode | Light mode | Použití |
|--------|-----------|------------|---------|
| **0** | Žádná | Žádná | Flat obsah, pozadí |
| **1** | Barva surface (slate-800) | `shadow-sm` | Karty, inputy, stat cards |
| **2** | `shadow-sm` (subtle) | `shadow-md` | Hover karty, dropdown trigger |
| **3** | `shadow-md` | `shadow-lg` | Dropdown menu, popover |
| **4** | `shadow-lg` | `shadow-xl` | Dialog, modal, fullscreen overlay |

```tsx
// Tailwind utility pro elevation
// Dark mode: shadow je potlačený, elevation dělá barva pozadí
// Light mode: shadow je výrazný

// Card (elevation 1)
<Card className="shadow-sm dark:shadow-none" />

// Dropdown (elevation 3)
<DropdownMenu className="shadow-lg dark:shadow-md" />

// Dialog (elevation 4)
<Dialog className="shadow-xl dark:shadow-lg" />
```

### 11.4 Theme toggle — implementace

```tsx
import { useState, useEffect } from 'react';

type Theme = 'dark' | 'light';

function useTheme() {
  const [theme, setTheme] = useState<Theme>(() => {
    // 1. Zkontrolovat localStorage
    const stored = localStorage.getItem('wp-monitor-theme');
    if (stored === 'light' || stored === 'dark') return stored;
    // 2. Respektovat system preference
    if (window.matchMedia('(prefers-color-scheme: light)').matches) return 'light';
    // 3. Default = dark (admin nástroj)
    return 'dark';
  });

  useEffect(() => {
    const root = document.documentElement;
    root.classList.remove('light', 'dark');
    root.classList.add(theme);
    root.style.colorScheme = theme;  // nativní scrollbar, form controls
    localStorage.setItem('wp-monitor-theme', theme);
  }, [theme]);

  // Sledovat změny system preference (pokud user nemá explicitní volbu)
  useEffect(() => {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    const handler = (e: MediaQueryListEvent) => {
      const stored = localStorage.getItem('wp-monitor-theme');
      if (!stored) setTheme(e.matches ? 'dark' : 'light');
    };
    media.addEventListener('change', handler);
    return () => media.removeEventListener('change', handler);
  }, []);

  const toggle = () => setTheme(t => t === 'dark' ? 'light' : 'dark');

  return { theme, setTheme, toggle };
}
```

### 11.5 Tailwind dark: variant

Tailwind `dark:` prefix funguje s class strategií (`.dark` na `<html>`):

```ts
// tailwind.config.ts
darkMode: 'class',  // ne 'media' — chceme explicitní kontrolu
```

```tsx
// Příklad — komponenta s theme-specific styly
<Card className="
  bg-white dark:bg-slate-800
  text-slate-900 dark:text-slate-50
  border-slate-200 dark:border-slate-700
  shadow-sm dark:shadow-none
  hover:shadow-md dark:hover:shadow-sm
">
  <CardContent className="
    text-slate-600 dark:text-slate-400
  ">
    {/* Obsah */}
  </CardContent>
</Card>

// Status badge — různé barvy pro dark/light
<Badge className="
  bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400
">
  Online
</Badge>

// Skeleton — různé base barvy
<div className="
  animate-pulse rounded-md
  bg-slate-200 dark:bg-slate-800
" />
```

### 11.6 CSS — scrollbar styling

```css
/* Dark mode scrollbar */
.dark {
  scrollbar-color: hsl(215 28% 22%) hsl(222 47% 11%);  /* thumb / track */
}

/* Light mode scrollbar */
.light {
  scrollbar-color: hsl(214 32% 91%) hsl(210 20% 99%);
}

/* WebKit (Chrome, Safari, Edge) */
.dark::-webkit-scrollbar { width: 0.5rem; height: 0.5rem; }
.dark::-webkit-scrollbar-track { background: hsl(222 47% 11%); }
.dark::-webkit-scrollbar-thumb {
  background: hsl(215 28% 22%);
  border-radius: 0.25rem;
}
.dark::-webkit-scrollbar-thumb:hover { background: hsl(215 28% 30%); }

.light::-webkit-scrollbar { width: 0.5rem; height: 0.5rem; }
.light::-webkit-scrollbar-track { background: hsl(210 20% 99%); }
.light::-webkit-scrollbar-thumb {
  background: hsl(214 32% 91%);
  border-radius: 0.25rem;
}
.light::-webkit-scrollbar-thumb:hover { background: hsl(214 32% 80%); }
```

### 11.7 CSS — selection color

```css
/* Text selection — různé pro dark/light */
.dark ::selection {
  background: hsl(243 75% 59% / 0.30);  /* indigo s opacity */
  color: hsl(210 40% 98%);
}

.light ::selection {
  background: hsl(243 75% 59% / 0.15);  /* indigo s nižší opacity */
  color: hsl(222 47% 11%);
}
```

### 11.8 CSS — form controls

```css
/* Native form controls (checkbox, radio, select) — color-scheme */
.dark { color-scheme: dark; }   /* prohlížeč vykreslí dark controls */
.light { color-scheme: light; } /* prohlížeč vykreslí light controls */

/* Custom checkbox — theme-aware */
input[type="checkbox"] {
  accent-color: hsl(243 75% 59%);  /* indigo — stejný pro obě theme */
}
```

### 11.9 Pravidla pro theme

1. **Ne inverze!** — každý theme má vlastní paletu optimalizovanou pro své pozadí
2. **Status barvy** — light mode používá tmavší varianty (green-600 ne green-500) pro kontrast na bílém
3. **Shadows** — dark mode potlačuje stíny, light mode používá pro elevation
4. **Elevation** — dark mode = barvou (světlejší surface), light mode = stínem
5. **Overlay** — dark mode `bg-black/80`, light mode `bg-black/50` (méně kontrastní)
6. **Skeleton** — dark mode `bg-slate-800`, light mode `bg-slate-200`
7. **Scrollbar** — theme-aware (dark thumb/track vs light thumb/track)
8. **Selection** — indigo s různou opacity (dark 30%, light 15%)
9. **color-scheme** — nastavit na `<html>` pro nativní form controls
10. **System preference** — respektovat `prefers-color-scheme` pokud user nemá explicitní volbu
11. **Transitions** — `transition-colors duration-200` pro plynulé přepnutí theme
12. **No flash** — inline script v `<head>` nastaví theme před React hydrataci

### 11.10 No-flash inline script

```html
<!-- index.html — před React hydrataci, zabrání flash of wrong theme -->
<script>
  (function() {
    var stored = localStorage.getItem('wp-monitor-theme');
    var theme = stored || (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
    document.documentElement.classList.add(theme);
    document.documentElement.style.colorScheme = theme;
  })();
</script>
```

### 11.11 Theme-aware komponenty — příklady

```tsx
// Button — primary (stejný v obou theme)
<Button variant="default" className="bg-primary text-primary-foreground">
  Akce
</Button>

// Button — ghost (různé hover)
<Button variant="ghost" className="
  hover:bg-slate-100 dark:hover:bg-slate-800
  text-slate-600 dark:text-slate-400
">
  Menu
</Button>

// Card — elevation různé
<Card className="
  bg-white dark:bg-slate-800
  border-slate-200 dark:border-slate-700
  shadow-sm dark:shadow-none
  hover:shadow-md dark:hover:shadow-sm
  transition-shadow duration-200
">
  <CardHeader>
    <CardTitle className="text-slate-900 dark:text-slate-50">
      Titulek
    </CardTitle>
    <CardDescription className="text-slate-500 dark:text-slate-400">
      Popis
    </CardDescription>
  </CardHeader>
  <CardContent className="text-slate-700 dark:text-slate-300">
    {/* obsah */}
  </CardContent>
</Card>

// Status badge — různé barvy pro dark/light
<Badge className="
  bg-green-100 text-green-700
  dark:bg-green-900/30 dark:text-green-400
  border border-green-200 dark:border-green-800
">
  Online
</Badge>

<Badge className="
  bg-red-100 text-red-700
  dark:bg-red-900/30 dark:text-red-400
  border border-red-200 dark:border-red-800
">
  Offline
</Badge>

// Input — theme-aware
<Input className="
  bg-white dark:bg-slate-900
  border-slate-300 dark:border-slate-700
  text-slate-900 dark:text-slate-50
  placeholder:text-slate-400 dark:placeholder:text-slate-500
  focus:ring-primary
" />

// Dialog overlay — různé opacity
<DialogOverlay className="bg-black/50 dark:bg-black/80" />

// Table — hover řádky
<TableRow className="
  hover:bg-slate-50 dark:hover:bg-slate-800/50
  border-slate-200 dark:border-slate-700
" />
```

## 12. Zákazy — bez px a %

### 12.1 Zakázané jednotky

| Jednotka | Zakázáno? | Proč | Použít místo |
|----------|-----------|------|---------------|
| `px` | ZÁKAZ | Ignoruje user preference | `rem` (1rem = 16px při default) |
| `%` (width) | ZÁKAZ | Nejednoznačné (rodič? viewport?) | `cqw`, `cqi`, `fr` |
| `%` (height) | ZÁKAZ | Nejednoznačné | `cqh`, `cqb`, `vh` |
| `rem` | Povoleno | Respektuje user preference | — |
| `em` | Povoleno | Relativní k rodiči font-size | — |
| `cqw` / `cqi` | Povoleno | 1% šířky kontejneru | — |
| `cqh` / `cqb` | Povoleno | 1% výšky kontejneru | — |
| `cqmin` / `cqmax` | Povoleno | Min/max z cqi a cqh | — |
| `vw` / `vh` | Povoleno | Viewport units (pouze pro page-level) | — |
| `vmin` / `vmax` | Povoleno | Min/max viewport | — |
| `ch` | Povoleno | Šířka znaku "0" | — |
| `fr` | Povoleno | Grid fraction unit | — |
| `ms` / `s` | Povoleno | Čas (animace) | — |
| `deg` | Povoleno | Úhel (transform) | — |

### 12.2 Výjimky

- `min(100%, Xrem)` v `grid-template-columns: repeat(auto-fill, minmax(...))` — bezpečné, `100%` v grid kontextu = plná šířka rodiče
- `bg-black/80` — Tailwind opacity modifier (ne `%` jednotka, ale alpha kanál)
- `100%` v `html { font-size: 100% }` — respektuje user preference

### 12.3 Stylelint pravidlo pro vynucení

```json
// .stylelintrc.json — zakázat px a % v CSS
{
  "rules": {
    "declaration-property-value-disallowed-list": {
      "/.*/": ["/\\d+px/", "/\\d+%/"]
    }
  }
}
```
