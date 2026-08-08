# WP Monitor

WordPress site management dashboard — monitor, manage, and maintain multiple WordPress installations from a single interface.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3, Slim 4, Doctrine DBAL, PHP-DI, Guzzle 7 |
| Frontend | React 18, TypeScript 5, Vite 7, Tailwind CSS 3, shadcn/ui |
| Database | MariaDB 11.4 |
| Testing | PHPUnit 11, Mockery, Vitest 4, React Testing Library, MSW |
| Dev | Docker, Docker Compose, Nginx, PHPStan 2, ESLint, Stylelint |

## Quick Start

```bash
# 1. Clone
git clone https://github.com/ratesman-maker/WP-Monitor.git wp-monitor
cd wp-monitor

# 2. Configure environment
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
# Edit .env files — generate APP_KEY and salts

# 3. Start Docker containers
make up
# or: docker compose up -d --build

# 4. Check status
make ps

# 5. Run database migrations
make migrate

# 6. Access
# Frontend: http://localhost:5173
# Backend API: http://localhost:8080/api/health
# Database: localhost:3307
```

## Project Structure

```
wp-monitor/
├── backend/          # PHP Slim 4 REST API
│   ├── src/
│   ├── tests/        # PHPUnit (Unit + Integration)
│   ├── config/       # Routes, middleware, container
│   ├── migrations/   # Doctrine migrations
│   └── bin/          # CLI scripts (migrate, seed)
├── frontend/         # React 18 + Vite SPA
│   ├── src/
│   │   ├── __tests__/  # Vitest + RTL tests
│   │   ├── components/  # shadcn/ui + custom
│   │   ├── stores/      # Zustand
│   │   └── lib/         # API client, utils
│   └── vite.config.ts
├── docker/           # Docker configuration (PHP, Nginx, frontend)
├── docs/             # Project documentation (15 docs)
├── .github/          # GitHub Actions (6 workflows), Dependabot, labels
├── docker-compose.yml
├── Makefile
└── .devin/           # Devin CLI skills & config
```

## Development Commands

```bash
# Docker
make up          # Start all containers
make down        # Stop all containers
make logs        # View logs (all services)
make shell       # Enter PHP container shell
make db-shell    # Enter MariaDB shell
make migrate     # Run database migrations
make clean       # Reset everything (deletes DB data!)

# Testing
make test        # Run all tests (backend + frontend)

# Backend
docker compose exec app composer test           # PHPUnit (17 tests)
docker compose exec app composer test:coverage  # PHPUnit + coverage report
docker compose exec app composer analyse        # PHPStan level 8
docker compose exec app composer cs-check       # PHP-CS-Fixer (PSR-12)
docker compose exec app composer cs-fix         # Auto-fix CS violations

# Frontend
docker compose exec frontend npm run test           # Vitest (21 tests)
docker compose exec frontend npm run test:watch     # Vitest watch mode
docker compose exec frontend npm run test:ui        # Vitest UI
docker compose exec frontend npm run test:coverage  # Vitest + coverage
docker compose exec frontend npm run lint           # ESLint
docker compose exec frontend npm run typecheck      # tsc --noEmit
docker compose exec frontend npm run build          # Production build
```

## Testing

| Layer | Tool | Tests | Coverage |
|-------|------|-------|----------|
| Backend unit | PHPUnit + Mockery | 13 | Connection, JsonBodyParserMiddleware |
| Backend integration | PHPUnit + Slim | 4 | /api/health endpoint |
| Frontend unit | Vitest + RTL | 21 | api client, authStore, App component |
| E2E | Playwright | — | *Plánováno* |

**CI enforcement:** `--fail-on-empty-test-suite --fail-on-risky` — CI failne když nejsou testy nebo jsou risky.

## CI/CD

6 GitHub Actions workflows + Dependabot:

| Workflow | Trigger | Purpose |
|----------|---------|---------|
| `ci.yml` | push/PR main+develop | Backend (PHPStan, cs-check, PHPUnit, audit) + Frontend (ESLint, typecheck, Vitest, build, npm audit) + commitlint |
| `codeql.yml` | push/PR + cron Mon 2:30 | CodeQL security scan (JS/TS) |
| `branch-rules.yml` | PR opened/edited/reopened/synchronize | Branch naming + PR title + PR target + auto-label |
| `code-quality.yml` | cron Mon 2:00 + manual | Rector dry-run + bundle analysis + dependency review + labels sync |
| `ci-failure-notify.yml` | workflow_run (after CI) | Creates/updates/closes GitHub issue on CI failure |
| `release.yml` | tag `v*.*.*` | Build + archive + GitHub Release |
| **Dependabot** | weekly Mon 3:00 | npm + composer + docker + github-actions updates |

**Security:** 0 npm vulnerabilities, 0 composer vulnerabilities, CodeQL scan, Dependabot, dependency review.

## Documentation

- `docs/01-project-brief.md` — Project overview
- `docs/02-architecture.md` — Architecture & tech stack
- `docs/03-security-model.md` — Security model & encryption
- `docs/04-module-specifications.md` — Module specifications
- `docs/05-database-schema.md` — Database schema (12 tables)
- `docs/06-api-specification.md` — API specification
- `docs/07-development-guide.md` — Development guide & testing
- `docs/08-versioning-strategy.md` — Semantic versioning
- `docs/09-github-workflow.md` — Git workflow & CI/CD
- `docs/10-deployment-guide.md` — Deployment guide
- `docs/11-mu-plugin-specification.md` — MU plugin specification
- `docs/12-environment-variables.md` — Environment variables
- `docs/13-architecture-decision-records.md` — ADRs
- `docs/14-design-system.md` — Design system
- `docs/15-operational-runbook.md` — Operational runbook

## License

Proprietary — All rights reserved.
