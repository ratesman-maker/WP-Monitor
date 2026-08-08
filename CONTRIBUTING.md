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
```

## PR proces

1. Vytvoř branch z `develop` (nebo `main` pro hotfix)
2. Implementuj změnu s testy
3. Spusť všechny kontroly lokálně:
   ```bash
   docker compose exec app composer analyse
   docker compose exec app composer cs-check
   docker compose exec app composer test
   docker compose exec frontend npm run lint
   docker compose exec frontend npm run typecheck
   docker compose exec frontend npm run test
   docker compose exec frontend npm run build
   ```
4. Vytvoř PR s popisem (šablona v `.github/pull_request_template.md`)
5. CI musí projít všechny 14 kontrol
6. Code review od maintainera
7. Squash merge do cílové branch

## Code style

### Backend (PHP)

- PSR-12 (vynuceno přes `php-cs-fixer`)
- PHPStan level 8 + strict rules
- `declare(strict_types=1);` v každém souboru
- Typové hinty na všech parametrech a return typech
- Final třídy kde možné
- Readonly properties pro immutability

### Frontend (TypeScript/React)

- Strict mode + `noUncheckedIndexedAccess` + `exactOptionalPropertyTypes`
- ESLint (`@typescript-eslint/strict` + `react-hooks` + `react-refresh`)
- Funkční komponenty s hooks
- shadcn/ui komponenty — žádné custom CSS
- Tailwind utility classes — žádné `px` ani `%` (vynuceno přes Stylelint)

## Architektonická pravidla

1. **Bezpečnost** — žádné plaintext credentials, šifrování CryptoService, CSRF, input validace
2. **Rychlost** — indexy na kritických sloupcích, lazy loading, cache, minimální payload
3. **Modularita** — EventDispatcher komunikace, tenké controllery, generické služby v Core/

## Testování

- Backend: PHPUnit (unit + integration)
- Frontend: Vitest (unit + component)
- E2E: Playwright (vizuální kontrola)
- Každý PR musí obsahovat testy pro novou funkcionalitu
