---
name: project-structure
description: Adresářová struktura projektu, naming konvence, modul struktura. Referencuje se při vytváření souborů a modulů.
triggers:
  - model
---

# Skill: Project Structure

> Struktura projektu — adresáře, soubory, naming konvence.
> Viz docs/02-architecture.md pro detailní popis.

## Adresářová struktura

```
wp-monitor/
├── backend/                         # PHP backend
│   ├── bin/                         # CLI skripty (migrate, seed, cron)
│   │   ├── migrate
│   │   ├── seed
│   │   └── cron
│   ├── config/                      # Konfigurace
│   │   ├── modules.php              # Registr modulů
│   │   ├── routes.php               # Globální routes
│   │   ├── dependencies.php         # DI container definice
│   │   └── middleware.php           # Middleware pipeline
│   ├── migrations/                  # Doctrine migrations
│   ├── public/                      # Document root (Apache/Nginx)
│   │   └── index.php                # Slim entry point
│   ├── src/
│   │   ├── Core/                    # Jádro aplikace
│   │   │   ├── ModuleInterface.php
│   │   │   ├── ModuleRegistry.php
│   │   │   ├── Config.php
│   │   │   └── Container.php
│   │   ├── Security/               # Security vrstva
│   │   │   ├── CryptoService.php    # AES-256-GCM šifrování
│   │   │   ├── KeyDerivation.php    # Argon2id
│   │   │   ├── JwtService.php       # JWT generování/validace
│   │   │   ├── CsrfService.php      # CSRF tokeny
│   │   │   └── RateLimiter.php      # Rate limiting
│   │   ├── Auth/                    # Auth modul
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Repositories/
│   │   │   └── manifest.json
│   │   ├── Sites/                   # Sites modul
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   ├── Repositories/
│   │   │   └── manifest.json
│   │   ├── Dashboard/               # Dashboard modul
│   │   ├── Updates/                 # Updates modul
│   │   ├── Backups/                 # Backups modul
│   │   ├── Security/                # Security & Monitoring modul
│   │   ├── Seo/                     # SEO & Performance modul
│   │   └── Shared/                  # Sdílené třídy
│   │       ├── HttpClient/          # Guzzle wrapper
│   │       ├── Logger/              # Monolog setup
│   │       └── Exceptions/
│   ├── storage/                     # Runtime data (gitignored)
│   │   ├── logs/
│   │   ├── cache/
│   │   ├── backups/
│   │   └── coverage/
│   ├── tests/
│   │   ├── Unit/
│   │   └── Integration/
│   ├── .env                          # Environment (gitignored)
│   ├── .env.example                  # Template
│   ├── .php-cs-fixer.php
│   ├── phpstan.neon
│   ├── phpunit.xml
│   ├── rector.php
│   └── composer.json
│
├── frontend/                        # React frontend
│   ├── public/
│   ├── src/
│   │   ├── main.tsx                 # Entry point
│   │   ├── App.tsx                  # Router + layout
│   │   ├── modules/                 # Feature-based moduly
│   │   │   ├── auth/
│   │   │   ├── sites/
│   │   │   ├── dashboard/
│   │   │   ├── updates/
│   │   │   ├── backups/
│   │   │   ├── security/
│   │   │   └── seo/
│   │   ├── components/
│   │   │   ├── ui/                  # shadcn/ui base komponenty
│   │   │   ├── blocks/              # shadcnblocks.com bloky
│   │   │   │   ├── dashboard/
│   │   │   │   ├── app-shell/
│   │   │   │   ├── data-table/
│   │   │   │   ├── chart-group/
│   │   │   │   ├── bento/
│   │   │   │   └── feature/
│   │   │   └── common/              # WP Monitor custom komponenty
│   │   ├── hooks/                   # Globální custom hooks
│   │   ├── lib/                     # Utility funkce
│   │   │   ├── api.ts               # API client (axios/fetch)
│   │   │   ├── crypto.ts            # Client-side crypto (volitelně)
│   │   │   └── utils.ts             # Obecné utility
│   │   ├── store/                   # Zustand stores (globální)
│   │   ├── types/                   # Globální TypeScript typy
│   │   └── __tests__/               # Testy
│   ├── .eslintrc.cjs
│   ├── .prettierrc
│   ├── tsconfig.json
│   ├── vitest.config.ts
│   ├── vite.config.ts
│   └── package.json
│
├── docs/                            # Dokumentace (01-15)
├── .github/                         # GitHub Actions, templates
│   ├── workflows/
│   │   ├── ci.yml
│   │   ├── branch-rules.yml
│   │   ├── release.yml
│   │   └── code-quality.yml
│   ├── CODEOWNERS
│   ├── pull_request_template.md
│   ├── branch-protection.md
│   └── labels.json
├── .windsurf/                       # Windsurf konfigurace
│   ├── rules/
│   │   ├── wp-monitor.md            # Hlavní pravidla
│   │   ├── security-coding.md       # Security skill
│   │   ├── performance-coding.md    # Performance skill
│   │   ├── modularity-coding.md     # Modularity skill
│   │   └── project-structure.md     # Tento soubor
│   └── workflows/
│       ├── new-module.md
│       ├── build-deploy.md
│       ├── code-review.md
│       ├── db-migration.md
│       └── new-api-endpoint.md
├── .gitattributes
├── .gitignore
├── .commitlintrc.cjs
├── CONTRIBUTING.md
├── README.md
└── wp-monitor.code-workspace
```

## Naming konvence

### PHP soubory

| Typ | Konvence | Příklad |
|-----|----------|---------|
| Třída | PascalCase | `CryptoService.php` |
| Interfejs | PascalCase + Interface | `ModuleInterface.php` |
| Trait | PascalCase + Trait | `EncryptsDataTrait.php` |
| Enum | PascalCase | `SiteStatus.php` |
| DTO | PascalCase + DTO | `UpdateOptionsDTO.php` |
| Config | kebab-case | `modules.php` |
| Migrace | `Version{YYYYMMDD}_{NN}_{desc}.php` | `Version20260807_001_create_users.php` |

### TypeScript soubory

| Typ | Konvence | Příklad |
|-----|----------|---------|
| Komponenta | PascalCase.tsx | `SitesListPage.tsx` |
| Page komponenta | PascalCase + Page.tsx | `DashboardPage.tsx` |
| Hook | camelCase + use prefix | `useSites.ts` |
| Utility | camelCase.ts | `formatDate.ts` |
| Typy | camelCase.ts | `site.ts` |
| Store | camelCase + Store | `authStore.ts` |
| API | camelCase.ts | `api.ts` |
| Test | PascalCase + .test | `CryptoService.test.ts` |

### CSS / Tailwind

| Typ | Konvence | Příklad |
|-----|----------|---------|
| CSS soubor | kebab-case | `globals.css` |
| CSS class | kebab-case | `.site-card-header` |
| Tailwind | utility classes | `className="flex gap-4 p-6"` |

## Modul struktura (backend)

Každý modul v `backend/src/{ModuleName}/`:

```
Updates/
├── Controllers/
│   └── UpdatesController.php
├── Services/
│   ├── UpdatesService.php
│   └── BatchUpdateService.php
├── Repositories/
│   └── UpdateHistoryRepository.php
├── Events/
│   ├── UpdateCompletedEvent.php
│   └── BatchUpdateStartedEvent.php
├── Listeners/
│   └── BackupEventListener.php
├── DTO/
│   └── UpdateOptions.php
├── manifest.json              # Modul metadata
└── routes.php                 # Modul routes
```

### manifest.json

```json
{
    "name": "updates",
    "version": "1.0.0",
    "description": "Správa aktualizací WordPress webů",
    "dependencies": ["auth", "sites", "backups"],
    "routes": "routes.php",
    "events": {
        "listens": ["backup.completed"],
        "dispatches": ["update.completed", "batch.update.started"]
    }
}
```

## Modul struktura (frontend)

Každý modul v `frontend/src/modules/{moduleName}/`:

```
updates/
├── pages/
│   ├── UpdatesOverviewPage.tsx
│   ├── UpdateHistoryPage.tsx
│   └── SiteUpdatesTab.tsx
├── components/
│   ├── BatchUpdateDialog.tsx
│   ├── BatchProgressBar.tsx
│   └── AutoUpdateRulesForm.tsx
├── hooks/
│   ├── useUpdates.ts
│   └── useBatchUpdate.ts
├── api.ts                    # API volání pro tento modul
├── types.ts                  # TypeScript typy pro tento modul
└── store.ts                  # Zustand store (pokud potřebuje)
```

## Pravidla pro umísťování souborů

1. **Modulový kód do modulu** — `Updates/Controller` do `backend/src/Updates/Controllers/`
2. **Sdílený kód do Shared** — `HttpClient` do `backend/src/Shared/HttpClient/`
3. **Globální hooks do `hooks/`** — `useAuth` do `frontend/src/hooks/`
4. **Modulové hooks do modulu** — `useUpdates` do `frontend/src/modules/updates/hooks/`
5. **shadcn/ui do `components/ui/`** — instalace přes `npx shadcn@latest add`
6. **shadcnblocks do `components/blocks/`** — instalace přes `npx shadcn@latest add <url>`
7. **Custom komponenty do `components/common/`** — WP Monitor specifické
8. **Testy zrcadlí strukturu** — `src/Updates/Service.php` → `tests/Unit/Updates/ServiceTest.php`

## Testovací checklist — Project Structure

### Struktura — statická kontrola

- [ ] **Backend moduly** — každý modul má `Controllers/`, `Services/`, `Repositories/`, `manifest.json`, `routes.php`
- [ ] **Backend moduly** — žádný modul neimportuje přímo z jiného modulu (jen z `Shared/`)
- [ ] **Frontend moduly** — každý modul má `pages/`, `components/`, `hooks/`, `api.ts`, `types.ts`
- [ ] **Frontend moduly** — žádný modul neimportuje z `modules/{jiny_modul}/`
- [ ] **shadcn/ui** — base komponenty v `frontend/src/components/ui/`
- [ ] **shadcnblocks** — bloky v `frontend/src/components/blocks/` (dashboard, app-shell, data-table, chart-group, bento, feature)
- [ ] **Custom komponenty** — v `frontend/src/components/common/`
- [ ] **Storage adresáře** — `backend/storage/` je v `.gitignore`
- [ ] **.env** — `backend/.env` a `frontend/.env` jsou v `.gitignore`
- [ ] **.env.example** — existuje pro backend i frontend (template)

### Naming konvence — kontrola

- [ ] **PHP třídy** — PascalCase (`CryptoService.php`, ne `cryptoService.php`)
- [ ] **PHP interfejsy** — PascalCase + Interface (`ModuleInterface.php`)
- [ ] **PHP traity** — PascalCase + Trait (`EncryptsDataTrait.php`)
- [ ] **TS komponenty** — PascalCase.tsx (`SitesListPage.tsx`)
- [ ] **TS hooks** — camelCase s use prefix (`useSites.ts`)
- [ ] **TS utility** — camelCase.ts (`formatDate.ts`)
- [ ] **CSS soubory** — kebab-case (`globals.css`)
- [ ] **DB migrace** — `Version{YYYYMMDD}_{NN}_{description}.php`
- [ ] **Branch názvy** — `{type}/{kebab-case-description}` (viz docs/08-versioning-strategy.md)

### Test struktura — zrcadlení

- [ ] **Backend unit testy** — `tests/Unit/{Module}/` zrcadlí `src/{Module}/`
- [ ] **Backend integration testy** — `tests/Integration/{Module}/` pro každý modul
- [ ] **Frontend testy** — `__tests__/{Module}.test.tsx` vedle nebo v modulu
- [ ] **Test pokrytí** — každá Service má test, každý Controller má test
- [ ] **Test pokrytí** — každý custom hook má test
- [ ] **Test pokrytí** — každý API endpoint má integration test

### Konfigurační soubory — přítomnost

- [ ] **backend/.php-cs-fixer.php** — PSR-12 + custom pravidla
- [ ] **backend/phpstan.neon** — level 8 + strict rules
- [ ] **backend/rector.php** — auto-upgrade + refactoring pravidla
- [ ] **backend/phpunit.xml** — PHPUnit konfigurace s test DB
- [ ] **frontend/.eslintrc.cjs** — ESLint + TypeScript strict
- [ ] **frontend/.prettierrc** — Prettier konfigurace
- [ ] **frontend/tsconfig.json** — strict mode
- [ ] **frontend/vitest.config.ts** — Vitest konfigurace
- [ ] **frontend/vite.config.ts** — Vite + proxy + manualChunks
- [ ] **.commitlintrc.cjs** — Conventional Commits konfigurace
- [ ] **.gitattributes** — line endings (eol=lf)
- [ ] **.gitignore** — storage, .env, node_modules, vendor, coverage

### CI/CD — GitHub Actions

- [ ] **ci.yml** — 14+ jobů (backend lint/static/test, frontend lint/typecheck/test/build, security, commitlint)
- [ ] **branch-rules.yml** — branch naming, PR target, PR title, auto-label
- [ ] **release.yml** — build + GitHub Release na tag push
- [ ] **code-quality.yml** — týdenní Rector, bundle analysis, circular deps, dependency review
- [ ] **pull_request_template.md** — security + quality checklist
- [ ] **CODEOWNERS** — code ownership pro review
- [ ] **labels.json** — 20 issue labels

### Dokumentace — přítomnost

- [ ] **docs/01-15** — všech 15 dokumentů existuje
- [ ] **README.md** — odkazy na všechny docs
- [ ] **CONTRIBUTING.md** — návod pro přispěvatele
- [ ] **.windsurf/rules/** — 5 rules souborů (wp-monitor + 4 skills)
- [ ] **.windsurf/workflows/** — 5 workflow souborů
