---
name: wp-monitor-project
description: Hlavní pravidla projektu WP Monitor — tech stack, bezpečnost, konvence, architektura. Referencuje se při práci na projektu.
triggers:
  - model
---

# WP Monitor — Devin pravidla

## Skills (detailní pravidla pro psaní kódu)
- `.devin/skills/security-coding/SKILL.md` — bezpečnostní pravidla (šifrování, auth, XSS, CSRF, audit)
- `.devin/skills/performance-coding/SKILL.md` — performance pravidla (DB, HTTP, React, bundle)
- `.devin/skills/modularity-coding/SKILL.md` — modularita a architektura (vrstvy, eventy, DTO, limity)
- `.devin/skills/project-structure/SKILL.md` — adresářová struktura, naming konvence, moduly

## Projekt
WP Monitor je profesionální nástroj pro hromadnou administraci WordPress webů.
Prioritní zásady: **100% zabezpečení, rychlost, modularita** — bez kompromisů.

## Technologie
- **Backend:** PHP 8.5+, Slim 4, PHP-DI, Doctrine DBAL, Guzzle 7, Symfony EventDispatcher
- **Frontend:** React 18, TypeScript, Vite, TailwindCSS, shadcn/ui, TanStack Query, Zustand
- **DB:** MySQL 8.0+ / MariaDB 10.6+, utf8mb4
- **Šifrování:** AES-256-GCM (libsodium), Argon2id key derivation

## Bezpečnostní pravidla (NON-NEGOTIABLE)
1. Nikdy nelogovat plaintext credentials
2. Nikdy nevracet credentials v API response
3. Vždy šifrovat credentials přes CryptoService s AAD (site_id + user_id)
4. Vždy wipe dešifrovaná data z paměti (sodium_memzero)
5. Vždy HTTPS pro komunikaci se spravovanými weby
6. Vždy CSRF token na POST/PUT/DELETE
7. Vždy input validace na API endpointech
8. Žádné hardcode hesla nebo API klíče
9. Master heslo nikdy není uloženo
10. Audit log je append-only

## Coding konvence
- PHP: PSR-12, strict_types, type hints, readonly properties
- TypeScript: striktní typy, import type, named exports
- Commits: Conventional Commits (feat/fix/security/docs/test/chore)
- DB migrace: Doctrine Migrations, vždy up() + down()

## Standardy (vynuceno konfiguračními soubory)
- PHP: PSR-1/12, PSR-3/4/7/11/15/17, PHPStan level 8 + strict, Rector
- TS: strict mode, ESLint (@typescript-eslint/strict + airbnb), Prettier
- Bezpečnost: OWASP Top 10, OWASP ASVS v4.0 Level 2, NIST SP 800-63B, RFC 7519/8446
- API: REST, OpenAPI 3.1 (budoucí), RFC 7807 (error formát)
- Git: Conventional Commits 1.0, Git Flow (zjednodušený)
- Konfig soubory: backend/.php-cs-fixer.php, phpstan.neon, rector.php, phpunit.xml
- Konfig soubory: frontend/.eslintrc.cjs, .prettierrc, tsconfig.json, vitest.config.ts

## Architektura
- Moduly komunikují přes EventDispatcher — ne přímými voláními
- Kontrolery jsou tenké — logika v Service vrstvě
- Repository pattern pro DB přístup
- DI container pro všechny závislosti
- Každý modul má manifest.json a implementuje ModuleInterface

## Prevence špaget kódu (viz docs/07-development-guide.md sekce 2.3)
- Jedna třída = jedna zodpovědnost
- Max 4 parametry v metodě/funkci (více = DTO/options objekt)
- Max 3 úrovně nesting v metodách (hlubší = early return nebo extrakce)
- Dependency depth ≤ 3 (A→B→C→D je limit)
- Service orchestruje, neprovádí vše sama (DB + HTTP + šifrování = 3 různé třídy)
- Orientační stropy: Service ~400, Controller ~150, Security třídy ~200, Page komponenta ~250 řádků
- Varovné signály: 5+ témat ve třídě, 8+ useState, 6+ injektovaných závislostí, duplikace ve 3+ souborech

## Frontend — shadcnblocks.com (premium licence)
- UI base: shadcn/ui (button, dialog, table, etc.) — instalace přes `npx shadcn@latest add`
- UI bloky: shadcnblocks.com (premium) — 1816+ bloků, instalace přes `npx shadcn@latest add <url>`
- Bloky v `frontend/src/components/blocks/` (dashboard, app-shell, data-table, chart-group, bento, feature)
- Custom komponenty v `frontend/src/components/common/` (WP Monitor specifické)
- Pravidla: bloky upravuj minimálně, custom logiku do separátních komponent, neduplikovat bloky
- Mapování: Dashboard→stat cards, App Shell→layout, Data Table→seznamy, Chart Group→grafy, Bento→widgety

## Frontend — Container Queries & Fluid Typography (viz docs/14-design-system.md)
- **ZÁKAZ `px` a `%`** — používat `rem`, `em`, `cqw`, `cqi`, `cqh`, `ch`, `vw`, `vh`, `fr`
- **Container queries** — komponenty reagují na velikost rodiče (`@container`, `@md:`, `@lg:`, atd.)
- **Fluid typography** — `clamp(min, preferred, max)` s `cqi` jednotkami (text roste s kontejnerem)
- **Media queries** — pouze pro page-level layout (sidebar show/hide), ne pro komponenty
- **shadcnblocks** — obalovat v `@container`, override `px` → `rem`, `%` → `cqw`/`cqi`/`fr`
- **Container context** — `#root`, `.module-container`, `.card-container`, `.sidebar-container`
- **Tailwind plugin** — `@tailwindcss/container-queries` povinný
- **Grid systém** — 12-sloupcová mřížka (`grid-cols-12` + `col-span-*`), bento grid, dashboard grid — vše `fr`/`rem`/`cqi`, nikdy `px`/`%`
- **Stylelint** — pravidlo zakazující `px` a `%` v CSS
- **Theme systém** — dark (výchozí) + light (samostatně navržený, ne inverze!), `darkMode: 'class'`, `dark:` prefix, elevation dark=barva/light=stín, no-flash script, `prefers-color-scheme` respekt

## Dokumentace
- `docs/01-project-brief.md` — projektové zadání
- `docs/02-architecture.md` — architektura
- `docs/03-security-model.md` — bezpečnostní model
- `docs/04-module-specifications.md` — specifikace modulů
- `docs/05-database-schema.md` — DB schéma
- `docs/06-api-specification.md` — API specifikace
- `docs/07-development-guide.md` — vývojový guide
- `docs/08-versioning-strategy.md` — verzovací strategie (Git Flow, SemVer, branch naming)
- `docs/09-github-workflow.md` — GitHub workflow průvodce (větve, PR, CI/CD, release)
- `docs/10-deployment-guide.md` — produkční deployment (server, Apache/Nginx, SSL, cron)
- `docs/11-mu-plugin-specification.md` — WordPress MU-plugin (REST API, HMAC auth)
- `docs/12-environment-variables.md` — reference .env proměnných
- `docs/13-architecture-decision-records.md` — ADR (architektonická rozhodnutí)
- `docs/14-design-system.md` — design system (theme, typografie, komponenty)
- `docs/15-operational-runbook.md` — operační runbook (incidenty, údržba, recovery)

## Verzování a větvení (viz docs/08-versioning-strategy.md)
- SemVer 2.0 — tag formát `v{MAJOR}.{MINOR}.{PATCH}`
- Git Flow zjednodušený: `main` (produkce) ← `develop` (integrace) ← feature/fix/security/refactor/perf/docs/test/chore
- Branch naming: `{type}/{kebab-case-description}` (max 50 znaků, bez diakritiky)
- Branch routing (vynuceno GitHub Actions):
  - feature/fix/security/refactor/perf/docs/test/chore → develop (squash merge)
  - release/* → main (merge commit)
  - hotfix/* → main (merge commit)
- Conventional Commits vynuceno přes .commitlintrc.cjs
- GitHub Actions: branch-rules.yml kontroluje naming, target branch, PR title, auto-label

## Příkazy
- **Příkazy spouštějte přes bash shell (Linux/Docker prostředí)**
- Backend testy: `composer test`
- Frontend testy: `npm run test`
- PHPStan: `composer analyse`
- ESLint: `npm run lint`
