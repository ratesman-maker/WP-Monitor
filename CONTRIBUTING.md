# Contributing to WP Monitor

Děkujeme za zájem o přispívání do WP Monitor. Tento dokument popisuje proces vývoje, konvence a workflow.

## Branch naming

| Typ | Pattern | Příklad |
|------|---------|---------|
| Feature | `feat/<krátký-popis>` | `feat/auth-module` |
| Bug fix | `fix/<krátký-popis>` | `fix/health-endpoint-cors` |
| Security | `security/<krátký-popis>` | `security/credential-encryption` |
| Refactor | `refactor/<krátký-popis>` | `refactor/storage-layer` |
| Perf | `perf/<krátký-popis>` | `perf/query-optimization` |
| Docs | `docs/<krátký-popis>` | `docs/api-reference` |
| Test | `test/<krátký-popis>` | `test/auth-integration` |
| Chore | `chore/<krátký-popis>` | `chore/update-deps` |
| Release | `release/<krátký-popis>` | `release/v0.2.0` |
| Hotfix | `hotfix/<krátký-popis>` | `hotfix/cors-header` |

> **Poznámka:** Používáme zkrácené typy (`feat/`, ne `feature/`) v souladu s Conventional Commits. Branch naming validuje `branch-rules.yml` workflow.

## Commit formát

Používáme [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <subject>

<volitelné body>

<volitelný footer>
```

### Povolené typy

`feat`, `fix`, `security`, `refactor`, `perf`, `docs`, `test`, `chore`, `release`, `hotfix`

### Příklady

```
feat(auth): add JWT login endpoint
fix(api): handle missing Content-Type header
security(credentials): rotate encryption key on update
docs(api): document /api/health endpoint
chore(deps): bump slim/slim to 4.15
test(storage): add Connection unit tests
```

## PR proces

1. Vytvoř branch z `develop` (nebo `main` pro hotfix)
2. Implementuj změnu s testy
3. Spusť všechny kontroly lokálně:
   ```bash
   # Backend
   docker compose exec app composer analyse       # PHPStan level 8
   docker compose exec app composer cs-check      # PHP-CS-Fixer (PSR-12)
   docker compose exec app composer test          # PHPUnit (17 tests)
   docker compose exec app composer audit         # Composer vulnerabilities

   # Frontend
   docker compose exec frontend npm run lint       # ESLint
   docker compose exec frontend npm run typecheck  # TypeScript
   docker compose exec frontend npm run test       # Vitest (21 tests)
   docker compose exec frontend npm run build      # Vite production build
   ```
4. Vytvoř PR s popisem (šablona v `.github/pull_request_template.md`)
5. CI musí projít všechny kontroly:
   - **Backend:** PHPStan, cs-check, PHPUnit, composer audit
   - **Frontend:** ESLint, typecheck, Vitest, build, npm audit
   - **Commitlint:** Conventional Commits formát (pouze na PR)
6. Code review od maintainera (CODEOWNERS pro security soubory povinné)
7. Squash merge do cílové branch

> **CI failure notify:** Když CI failne, automaticky se vytvoří GitHub issue s `P0-critical` štítkem. Issue se zavře automaticky když CI projde.

## Code style

### Backend (PHP)

- PSR-12 (vynuceno přes `php-cs-fixer`)
- PHPStan level 8 + strict rules (kromě `disallowedShortTernary` a `missingType.iterableValue`)
- `declare(strict_types=1);` v každém souboru
- Typové hinty na všech parametrech a return typech
- Final třídy kde možné
- Readonly properties pro immutability
- Short ternary `?:` povolen (idiomatické PHP)

### Frontend (TypeScript/React)

- Strict mode + `noUncheckedIndexedAccess`
- ESLint (`@typescript-eslint` + `react-hooks` + `react-refresh`)
- Funkční komponenty s hooks
- shadcn/ui komponenty — žádné custom CSS
- Tailwind utility classes — žádné `px` ani `%` (vynuceno přes Stylelint)

## Architektonická pravidla

1. **Bezpečnost** — žádné plaintext credentials, šifrování CryptoService, CSRF, input validace
2. **Rychlost** — indexy na kritických sloupcích, lazy loading, cache, minimální payload
3. **Modularita** — EventDispatcher komunikace, tenké controllery, generické služby v Core/

## Testování

### Backend

- **PHPUnit 11** pro unit + integration testy
- **Mockery** pro mocking závislostí
- `TestCase.php` base class s `MockeryPHPUnitIntegration` trait
- `composer test` — `--fail-on-empty-test-suite --fail-on-risky` (CI failne při 0 testů nebo risky)
- `composer test:coverage` — coverage report (HTML + text)

**Struktura:**
```
backend/tests/
├── TestCase.php                              # Base class (Mockery integration)
├── Unit/
│   ├── Storage/
│   │   └── ConnectionTest.php                # 8 tests
│   └── Http/Middleware/
│       └── JsonBodyParserMiddlewareTest.php  # 5 tests
└── Integration/
    └── HealthEndpointTest.php                # 4 tests
```

### Frontend

- **Vitest 4** pro unit testy
- **React Testing Library** pro komponent testy
- **MSW** (Mock Service Worker) pro API mocking
- **jsdom** pro DOM simulaci
- `npm run test` — Vitest run
- `npm run test:watch` — watch mode
- `npm run test:ui` — Vitest UI
- `npm run test:coverage` — coverage report

**Struktura:**
```
frontend/src/__tests__/
├── setup.ts                          # window.__ENV__, jest-dom, RTL cleanup
├── components/
│   └── App.test.tsx                  # 7 tests (navigation + routes)
├── lib/
│   └── api.test.ts                   # 10 tests (get/post/put/delete, auth, errors)
└── stores/
    └── authStore.test.ts             # 4 tests (login, logout, roles)
```

### E2E (plánováno)

- **Playwright** pro end-to-end testy
- Testuje se celý flow: login → add site → run updates → verify
- Zatím neimplementováno — plánováno pro další fázi

### Pravidla

- Každý PR musí obsahovat testy pro novou funkcionalitu
- CI failne když 0 testů (`--fail-on-empty-test-suite`)
- CI failne při risky testech (`--fail-on-risky`)
- 0 vulnerabilities vyžadováno (`npm audit` + `composer audit`)

## Dependabot

Automatické aktualizace závislostí (týdenně, pondělí 3:00 Prague):

| Ecosystem | Adresář | Co updatuje |
|-----------|---------|-------------|
| npm | `/frontend` | React, Radix, ESLint, Vite, testing deps |
| composer | `/backend` | Doctrine, Symfony, Slim, PHPStan |
| docker | `/docker/php`, `/docker/frontend` | Base images |
| github-actions | `/` | Workflow action versions |

Dependabot vytváří PR s labely `maintenance` + `dependencies`. Související deps jsou grupovány do jednoho PR.
