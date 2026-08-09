# AGENTS.md — WP Monitor

## Kritická pravidla

### ZÁKAZ subagentů
**Nikdy nepoužívej `run_subagent` tool.** Subagenti shodili vývojové prostředí a přerušili proces. Veškerou práci dělej přímo v hlavním agentovi — používání paralelních subagentů je v tomto projektu zakázáno.

## Vývojové prostředí

- **OS:** Linux (Ubuntu 26.04)
- **PHP:** 8.5.4 (host), 8.5-FPM (Docker)
- **MariaDB:** 11.8.6 (host, port 3307), 11.4 (Docker, port 3306)
- **Node.js:** 26.7.0 (Docker)
- **Docker:** Engine 29.7.2 + Compose v5.4.0
- **Composer:** 2.10

## MCP servery (6)

| Server | Účel |
|--------|------|
| `mcp_server_mysql` | DB dotazy na host MariaDB (port 3307, db `wp_monitor`, user `wp_monitor`) |
| `github` | GitHub issues, PRs, repozitáře |
| `gh` CLI | GitHub Actions runs, PR creation, repo management (`~/.local/bin/gh`, auth via git token) |
| `playwright` | Vizuální kontrola frontendu (screenshoty, console errors, E2E) |
| `context7` | Aktuální dokumentace knihoven (React, Slim 4, Doctrine, Vite, Tailwind) |
| `shadcn` | shadcn/ui komponenty + shadcnblocks.com premium bloky |
| `sequential-thinking` | Komplexní architektonická rozhodnutí |

## Příkazy

- **Docker:** `docker compose up -d --build` (start), `docker compose down` (stop)
- **Backend testy:** `docker compose exec app composer test`
- **PHPStan:** `docker compose exec app composer analyse`
- **Frontend testy:** `docker compose exec frontend npm run test`
- **ESLint:** `docker compose exec frontend npm run lint`
- **TypeScript:** `docker compose exec frontend npm run typecheck`
- **Migrace:** `docker compose exec app php bin/migrate`

## Git

- **Repozitář:** https://github.com/ratesman-maker/WP-Monitor.git
- **Branch:** `main` (produkce), `develop` (integrace)
- **Commits:** Conventional Commits (`feat:`, `fix:`, `security:`, `refactor:`, `docs:`, `chore:`)
- **Git user:** Miroslav Bartík <ratesman-maker@users.noreply.github.com>
- **Push:** `GIT_ASKPASS=/bin/true git push origin main`

## Priority (bez kompromisů)

1. **Bezpečnost** — žádné plaintext credentials, šifrování CryptoService, CSRF, input validace
2. **Rychlost** — indexy na kritických sloupcích, lazy loading, cache, minimální payload
3. **Modularita** — EventDispatcher komunikace, tenké controllery, generické služby v Core/
4. **Překladatelnost** — každý user-facing string přes `t()` / `i18n.t()`, žádné hardcoded texty, EN+CS překlady vždy paralelně

## Skills

Implementační plány se vytvářejí přes skill `implementation-plan` před každou implementací. Všechny skills jsou v `.devin/skills/`.

Každá priorita má vlastní coding skill, který se aplikuje při každém psaní nebo úpravě kódu:
- `security-coding` — bezpečnost (#1)
- `performance-coding` — rychlost (#2)
- `modularity-coding` — modularita (#3)
- `i18n-coding` — překladatelnost (#4)
