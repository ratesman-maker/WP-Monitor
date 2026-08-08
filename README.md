# WP Monitor

WordPress site management dashboard — monitor, manage, and maintain multiple WordPress installations from a single interface.

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.3, Slim 4, Doctrine DBAL, PHP-DI, Guzzle 7 |
| Frontend | React 18, TypeScript 5, Vite 5, Tailwind CSS 3, shadcn/ui |
| Database | MariaDB 11.4 |
| Dev | Docker, Docker Compose, Nginx, PHPStan, PHPUnit, Vitest |

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
├── frontend/         # React 18 + Vite SPA
├── docker/           # Docker configuration
├── docs/             # Project documentation
├── .github/          # GitHub Actions, templates, labels
├── docker-compose.yml
├── Makefile
└── .devin/           # Devin CLI skills & config
```

## Development Commands

```bash
make up          # Start all containers
make down        # Stop all containers
make logs        # View logs (all services)
make shell       # Enter PHP container shell
make db-shell    # Enter MariaDB shell
make migrate     # Run database migrations
make test        # Run all tests (backend + frontend)
make analyse     # Run PHPStan
make lint        # Run ESLint
make typecheck   # Run TypeScript typecheck
make build       # Build frontend for production
make clean       # Reset everything (deletes DB data!)
```

## Documentation

- `docs/01-project-brief.md` — Project overview
- `docs/02-architecture.md` — Architecture & tech stack
- `docs/05-database-schema.md` — Database schema
- `docs/07-development-guide.md` — Development guide
- `docs/09-github-workflow.md` — Git workflow & CI/CD
- `docs/12-environment-variables.md` — Environment variables
- `docs/14-design-system.md` — Design system

## License

Proprietary — All rights reserved.
