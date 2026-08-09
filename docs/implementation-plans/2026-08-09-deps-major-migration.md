# Implementation Plan: Major Dependency Migration

**Date:** 2026-08-09  
**Status:** ✅ Completed  
**Branch:** `refactor/deps-major-migration` → merged into `develop`  
**Commits:** 11

## Overview

Comprehensive major dependency upgrade for both frontend and backend,
covering TypeScript, React, Tailwind CSS, ESLint, Vite, Docker images,
backend PHP libraries, and GitHub Actions.

## Phases

### Phase 0: Backups + Context ✅
- Created backup branch `refactor/deps-major-migration`
- Backed up config files to `.migration-backup/`
- Verified baseline: 83 frontend tests, 121 backend tests, all passing

### Phase 1: TypeScript 5 → 6 ✅
- `typescript` 5.9 → 6.0.2
- `target: ES2022`, `moduleResolution: bundler`, `module: ESNext` — no deprecated options
- Typecheck, tests, build passed

### Phase 2: React 18 → 19 ✅
- `react` / `react-dom` 18.3 → 19.2.8
- `@types/react` / `@types/react-dom` → 19.2.x
- `@vitejs/plugin-react` → 5.2.0 (React 19 support)
- `@testing-library/react` → 16.3.2 (React 19 support)

### Phase 3: shadcn/ui ref-as-prop (React 19) ✅
- Converted all 19 shadcn/ui components from `React.forwardRef` to React 19
  `ref`-as-prop pattern
- Replaced `React.ElementRef` / `React.ComponentPropsWithoutRef` with
  `React.ComponentProps`
- For native HTML elements: `React.ComponentProps<"div">` instead of
  `React.HTMLAttributes<HTMLDivElement>` (includes `ref`)
- Removed `.displayName` assignments (function declarations have name)

### Phase 4: Tailwind 3 → 4 (CSS-first) ✅
- `tailwindcss` 3.4 → 4.3.3, `@tailwindcss/postcss` 4.3.3
- Replaced `@tailwind base/components/utilities` with `@import "tailwindcss"`
- Replaced `tailwind.config.ts` with `@theme` block in `globals.css`
- Added `@custom-variant dark` for class-based dark mode
- Container queries built-in (removed `@tailwindcss/container-queries`)
- **Fixed pre-existing bug:** shadcn/ui color tokens were not defined in
  config — now properly mapped in `@theme` via `--color-*` tokens
- Added missing `--popover` / `--popover-foreground` tokens

### Phase 5: ESLint 8 → 9 (flat config) ✅
- `eslint` 8.57 → 9.39.5
- Replaced `@typescript-eslint/parser` + `@typescript-eslint/eslint-plugin`
  with unified `typescript-eslint` 8.66.0
- Replaced `.eslintrc.cjs` with `eslint.config.js` (flat config)
- `eslint-plugin-react-hooks` 5 → 7.1.1
- `eslint-plugin-react-refresh` 0.4 → 0.5.3
- `eslint-import-resolver-typescript` 3.6 → 4.4.5
- Added `@eslint/js` 9.39.0
- Added browser globals explicitly (no `env: { browser: true }` in flat config)

### Phase 6: Other Frontend Deps ✅
- `vite` 7.3 → 8.0.10 (Rolldown bundler; 8.2.1 skipped — 7-day rule)
- `lucide-react` 0.454 → 1.28.0 (1.31.0 skipped — 7-day rule)
- `recharts` 2.13 → 3.10.1
- `zod` 3.23 → 4.4.3
- `tailwind-merge` 2.5 → 3.6.0
- `stylelint` 16.10 → 17.14.1
- `stylelint-config-standard` 36 → 40.0.0
- `rollup-plugin-visualizer` 5.12 → 7.0.1 (Rolldown support)

### Phase 7: Docker Images ✅
- `php:8.3-fpm-alpine` → `php:8.5-fpm-alpine`
- `node:22-alpine` → `node:26-alpine`
- `composer:2.7` → `composer:2.10`
- **PHP 8.5:** opcache is non-optional (built-in), removed from
  `docker-php-ext-install`
- **Node 26:** Native experimental `localStorage` shadows jsdom — added
  polyfill in test `setup.ts`
- Vite 8: `__dirname` → `import.meta.dirname` (vite.config.ts, vitest.config.ts)

### Phase 8: Backend Major Deps ✅
- `guzzlehttp/guzzle` 7.9 → 8.0
- `symfony/event-dispatcher` 7.1 → 8.1
- `symfony/validator` 7.1 → 8.1
- `symfony/serializer` 7.1 → 8.1
- `phpunit/phpunit` 11.5 → 13.3
- `slim/slim` 4.13 → 4.15, `slim/psr7` 1.6 → 1.8
- `php-di/php-di` 7.0 → 7.1
- `doctrine/dbal` 4.2 → 4.4, `doctrine/migrations` 3.8 → 3.9
- `monolog/monolog` 3.7 → 3.10, `ramsey/uuid` 4.7 → 4.9
- `friendsofphp/php-cs-fixer` 3.64 → 3.95
- `phpstan/phpstan` 2.0 → 2.2
- `rector/rector` 2.0 → 2.6
- PHP constraint: `>=8.3` → `>=8.4`
- Rector: `UP_TO_PHP_83` → `UP_TO_PHP_85`

### Phase 9: GitHub Actions ✅
- `actions/checkout` v4 → v5 (all workflows)
- `actions/setup-node` v4 → v5
- `actions/upload-artifact` v4 → v7
- `actions/dependency-review-action` v4 → v5
- `crazy-max/ghaction-github-labeler` v5 → v6
- PHP 8.3 → 8.5, Node 22 → 26 (ci.yml, code-quality.yml, release.yml)

### Phase 10: Final Validation ✅
- Frontend: 83 tests ✓, typecheck ✓, lint ✓, build ✓
- Backend: 121 tests ✓, PHPStan ✓, CS-Fixer ✓
- Merged into `develop`, pushed to origin

## Validation Results

| Check | Result |
|-------|--------|
| Frontend tests | 83/83 passed |
| Frontend typecheck | Clean |
| Frontend lint | Clean |
| Frontend build | 358KB JS (114KB gzip), 45KB CSS (8.4KB gzip) |
| Backend tests | 121/121 passed |
| Backend PHPStan | 0 errors |
| Backend CS-Fixer | 0 issues |

## Notes

- **7-day rule:** Vite 8.2.1 (Aug 6) and lucide-react 1.31.0 (Aug 9) were
  skipped — used 8.0.10 (Apr 23) and 1.28.0 (Jul 30) instead
- **Tailwind 4 CSS size:** 19KB → 45KB (gzip 4.7→8.4KB) — expected increase
  from proper shadcn color utility generation (was a pre-existing bug)
- **PHP CS Fixer warning:** Running on PHP 8.5 but min is 8.4 — informational
  only, not an error
