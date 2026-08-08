# Implementační plán — Kompletní projektový scaffolding

## Metadata

| Pole | Hodnota |
|------|---------|
| Název | Kompletní projektový scaffolding (backend + frontend + Docker + GitHub) |
| Typ | modul |
| Priorita | P0 |
| Status | draft (revize 1) |
| Vytvořeno | 08.08.2026 |
| Revize | 08.08.2026 |
| Autor | Devin (AI agent) |
| Související skill | `.devin/skills/build-deploy/SKILL.md`, `.devin/skills/project-structure/SKILL.md` |
| Související dokumentace | `docs/02-architecture.md`, `docs/07-development-guide.md`, `docs/05-database-schema.md`, `docs/14-design-system.md`, `docs/09-github-workflow.md`, `docs/12-environment-variables.md` |

## 1. Cíl

Vytvořit kompletní projektový scaffolding pro WP Monitor — funkční vývojové prostředí v Dockeru s backendem (Slim 4), frontendem (React 18 + Vite), databází (MariaDB), a GitHub infrastrukturou (Actions, PR template, labels, CODEOWNERS). Po dokončení bude možné spustit `docker compose up -d` a mít plně funkční dev prostředí s přístupem na `http://localhost:5173` (frontend) a `http://localhost:8080/api` (backend).

## 2. Reálný stav (před implementací)

### 2.1 Backend

Neexistuje vůbec. Žádné soubory v `backend/` — adresář nebyl vytvořen.

### 2.2 Frontend

Neexistuje vůbec. Žádné soubory v `frontend/` — adresář nebyl vytvořen.

### 2.3 Databáze

Databáze `wp_monitor` existuje na `127.0.0.1:3307` (host MariaDB 11.8), ale je **prázdná** — žádné tabulky. Ověřeno přes `mcp_server_mysql` MCP (`mysql_query` s `SHOW TABLES` → prázdný výsledek).

### 2.4 Rozdíl oproti dokumentaci

Dokumentace (02-architecture.md, 07-development-guide.md) popisuje kompletní strukturu, ale nic z toho v kódu neexistuje. Dokumentace referencuje PHP 8.3, ale host má PHP 8.5 — v Dockeru použijeme PHP 8.3-FPM per dokumentaci. Dokumentace referencuje MariaDB 11.4, host má 11.8 — v Dockeru použijeme `mariadb:11.4` per dokumentaci. Host MariaDB na portu 3307 bude zastavena a Docker DB poběží na 3307 — MCP server tak bude vidět Docker DB tabulky bez změny konfigurace.

## 3. Priority (bez výjimek)

### 3.1 Bezpečnost

- **`.env` soubory** v `.gitignore` — žádné secrets v repozitáři
- **`.env.example`** pouze s placeholder hodnotami (`CHANGE_ME`, `REPLACE_WITH_GENERATED_KEY`)
- **APP_KEY** generován přes `random_bytes(32)` — nikdy ne hardcode
- **MASTER_PASSWORD_SALT** generován přes `random_bytes(16)`
- **Docker kontejnery** běží jako non-root user (php-fpm)
- **Nginx** blokuje přístup k `.env`, `.git`, `storage/`, `.sql`, `.md` souborům
- **CORS** omezeno na specifikované origins (ne `*` v produkci)
- **DB heslo** v docker-compose.yml přes env vars, ne hardcode

### 3.2 Rychlost

- **Vite HMR** pro rychlý frontend development
- **OpCache** zapnutý i v dev (s `validate_timestamps=1`)
- **Composer autoload** optimalizován
- **Docker volumes** pro vendor/ a node_modules — rychlý restart
- **Healthcheck** pro DB — kontejnery čekají na DB dostupnost

### 3.3 Modularita

- **Backend** struktura podle `docs/02-architecture.md` — Core/, Http/, Security/, Services/, Storage/, Modules/, Domain/
- **Frontend** feature-based struktura — modules/ (auth, sites, dashboard, atd.)
- **Docker** oddělené služby (db, app, web, frontend) — každá má vlastní kontejner
- **Konfigurace** v `config/` adresáři (settings.php, container.php, routes.php, modules.php)
- **ModuleInterface** kontrakt pro všechny moduly

### 3.4 UI komponenty (striktní pravidla)

- **STRIKTNÍ ZÁKAZ vytváření vlastních komponent** — nepoužívat custom CSS ani custom React komponenty pro UI
- **Používat pouze shadcn/ui komponenty** — instalované přes `shadcn` MCP server nebo CLI
- **Používat pouze shadcnblocks.com bloky** pro kompozice (Dashboard, Data Table, Chart, Layout)
- **CSS** — pouze Tailwind utility classes a shadcn CSS proměnné v `globals.css`, žádné vlastní CSS soubory
- **Logické komponenty** (bez UI, např. `ProtectedRoute`) mohou být v `frontend/src/components/`, ale nesmí obsahovat custom styling — pouze skládají shadcn komponenty

## 4. Architektura

### 4.1 Backend

```
backend/
├── bin/
│   └── migrate                    # CLI skript pro migrace
├── config/
│   ├── settings.php               # Env-based globální nastavení
│   ├── container.php              # DI container definice
│   ├── routes.php                 # Core routes
│   ├── middleware.php             # Middleware pipeline konfigurace
│   └── modules.php                # Seznam aktivních modulů
├── migrations/
│   └── Version20260808_01_create_core_tables.php
├── public/
│   └── index.php                  # Slim front controller
├── src/
│   ├── Core/
│   │   ├── ModuleInterface.php    # Kontrakt pro moduly
│   │   ├── ModuleRegistry.php     # Registrace modulů
│   │   └── ModuleManifest.php     # DTO pro manifest
│   ├── Http/
│   │   ├── Middleware/
│   │   │   └── JsonBodyParserMiddleware.php
│   │   └── ErrorResponse.php      # RFC 7807 error formát
│   ├── Shared/
│   │   ├── HttpClient/            # Guzzle wrapper (budoucí)
│   │   ├── Logger/                # Monolog setup (budoucí)
│   │   └── Exceptions/            # Sdílené výjimky (budoucí)
│   └── Storage/
│       └── Connection.php         # DBAL connection factory
├── storage/
│   ├── logs/
│   ├── cache/
│   └── backups/
├── tests/
│   └── Unit/
├── .env.example
├── .php-cs-fixer.php
├── phpstan.neon
├── phpunit.xml
├── rector.php
└── composer.json
```

### 4.2 Frontend

```
frontend/
├── public/
├── src/
│   ├── main.tsx                   # Entry point
│   ├── App.tsx                    # Root component + router
│   ├── modules/
│   │   ├── auth/
│   │   ├── sites/
│   │   └── dashboard/
│   ├── components/
│   │   ├── ui/                    # shadcn/ui base
│   │   └── blocks/                # shadcnblocks.com
│   ├── lib/
│   │   ├── api.ts                 # API client
│   │   ├── queryClient.ts         # TanStack Query config
│   │   └── utils.ts               # Utility (cn, formátování)
│   ├── stores/
│   │   └── authStore.ts           # Zustand auth state
│   ├── types/
│   │   └── api.ts                 # API typy
│   └── styles/
│       └── globals.css            # Tailwind + CSS proměnné
├── .env.example
├── .eslintrc.cjs
├── .prettierrc
├── .stylelintrc.json              # Stylelint — zákaz px a %
├── components.json                # shadcn/ui config s Shadcnblocks registry
├── index.html
├── package.json
├── postcss.config.js              # Tailwind 3 + Autoprefixer
├── tailwind.config.ts
├── tsconfig.json
├── vite.config.ts
└── vitest.config.ts
```

### 4.3 Databáze

První migrace vytvoří 12 core tabulek podle `docs/05-database-schema.md`:

1. `users` — uživatelé (admin, manager, viewer)
2. `user_sessions` — session s encryption key
3. `sites` — spravované WordPress weby
4. `site_credentials` — šifrované credentials (AES-256-GCM)
5. `site_groups` + `site_group_map` — skupiny webů
6. `site_tags` + `site_tag_map` — tagy webů
7. `site_meta` — metadata webů
8. `user_site_permissions` — per-user per-site oprávnění
9. `audit_log` — append-only audit log (s trigger proti UPDATE/DELETE)
10. `settings` — globální nastavení
11. `modules` — registrované moduly
12. `module_configs` — per-site modul konfigurace

## 5. API endpointy

| Metoda | Cesta | Popis | Role | Request | Response |
|--------|-------|-------|------|---------|----------|
| GET | /api/health | Health check | veřejný | — | `{"status":"ok","version":"1.0.0"}` |

Pouze health check endpoint pro scaffolding — ostatní endpointy se přidají v dalších plánech (auth, sites, atd.).

## 6. Kroky implementace

### Fáze 0: Načtení stylu kódování (před jakýmkoliv kódem)

Není relevantní — žádný kód zatím neexistuje. Konvence budou definovány v konfiguračních souborech (`.php-cs-fixer.php`, `phpstan.neon`, `.eslintrc.cjs`, `tsconfig.json`).

- [ ] **Dostupné MCP servery** — využij při implementaci:
  - `mcp_server_mysql` — DB dotazy (`mysql_query` s `SHOW TABLES`, `DESCRIBE`, `SELECT`, atd.)
  - `playwright` — vizuální kontrola frontendu (`browser_navigate`, `browser_take_screenshot`, `browser_console_messages`)
  - `context7` — aktuální dokumentace knihoven (`resolve-library-id` → `query-docs`) pro React, Slim 4, Doctrine, Vite, Tailwind
  - `shadcn` — vyhledávání a instalace shadcn/ui komponent a shadcnblocks.com premium bloků (`search_items_in_registries`, `get_add_command_for_items`)
  - `sequential-thinking` — komplexní architektonická a bezpečnostní rozhodnutí (`sequentialthinking`)
  - `github` — issues, PRs, repozitáře

### Fáze 1: Docker infrastruktura

- [ ] Vytvořit `docker-compose.yml` — 4 služby (db, app, web, frontend) s volumes, healthcheck, networks. DB na host portu 3307 (host MariaDB bude zastavena).
- [ ] Zastavit host MariaDB službu (`sudo systemctl stop mariadb`) — uvolní port 3307 pro Docker DB
- [ ] Vytvořit `docker/php/Dockerfile` — PHP 8.3-FPM Alpine s rozšířeními (pdo_mysql, sodium, intl, mbstring, zip, gd, opcache, curl, xml)
- [ ] Vytvořit `docker/php/php.ini` — dev nastavení (memory_limit 512M, OpCache, error_reporting)
- [ ] Vytvořit `docker/php/entrypoint.sh` — wait for DB + composer install + migrace
- [ ] Vytvořit `docker/nginx/default.conf` — server block pro API + SPA + PHP-FPM
- [ ] Vytvořit `docker/frontend/Dockerfile` — Node 22 Alpine + Vite dev server
- [ ] Vytvořit `.dockerignore` — ignorovat .git, docs, .devin, vendor, node_modules, .env
- [ ] Vytvořit `Makefile` — shortcut příkazy (up, down, restart, logs, shell, migrate, test, atd.)

### Fáze 2: Backend scaffolding

- [ ] Vytvořit `backend/composer.json` — Slim 4, PHP-DI, Doctrine DBAL, Guzzle 7, Monolog, Symfony EventDispatcher/Validator/Serializer, ramsey/uuid
- [ ] Vytvořit `backend/.env.example` — všechny env proměnné z docs/12-environment-variables.md s placeholder hodnotami
- [ ] Vytvořit `backend/public/index.php` — Slim 4 bootstrap (AppFactory, container, routing, error middleware)
- [ ] Vytvořit `backend/config/settings.php` — načtení env proměnných do pole
- [ ] Vytvořit `backend/config/container.php` — PHP-DI definice (Connection, Logger, atd.)
- [ ] Vytvořit `backend/config/routes.php` — core routes (health check)
- [ ] Vytvořit `backend/config/middleware.php` — middleware pipeline konfigurace
- [ ] Vytvořit `backend/config/modules.php` — seznam aktivních modulů (prázdný pro scaffolding)
- [ ] Vytvořit `backend/src/Core/ModuleInterface.php` — kontrakt pro moduly
- [ ] Vytvořit `backend/src/Core/ModuleRegistry.php` — registrace modulů
- [ ] Vytvořit `backend/src/Core/ModuleManifest.php` — DTO pro manifest
- [ ] Vytvořit `backend/src/Storage/Connection.php` — DBAL connection factory
- [ ] Vytvořit `backend/src/Http/Middleware/JsonBodyParserMiddleware.php` — JSON body parser
- [ ] Vytvořit `backend/src/Http/ErrorResponse.php` — RFC 7807 error response
- [ ] Vytvořit `backend/src/Shared/` adresář strukturu (HttpClient/, Logger/, Exceptions/) s `.gitkeep`
- [ ] Vytvořit `backend/storage/` adresáře (logs, cache, backups) s `.gitkeep`
- [ ] Vytvořit `backend/bin/migrate` — CLI skript pro spuštění migrací
- [ ] Vytvořit `backend/.php-cs-fixer.php` — PSR-12 pravidla
- [ ] Vytvořit `backend/phpstan.neon` — PHPStan level 8 + strict rules
- [ ] Vytvořit `backend/phpunit.xml` — PHPUnit konfigurace
- [ ] Vytvořit `backend/rector.php` — Rector konfigurace
- [ ] Definovat **composer scripts** v `composer.json`: `test` (phpunit), `analyse` (phpstan), `cs-check` (php-cs-fixer), `cs-fix` (php-cs-fixer fix), `audit` (composer audit)
- [ ] Spustit `composer install` v Docker kontejneru

### Fáze 3: DB migrace

- [ ] Vytvořit `backend/migrations/Version20260808_01_create_core_tables.php` — 12 core tabulek s indexy, foreign keys, ENGINE=InnoDB, CHARSET=utf8mb4
- [ ] Implementovat `up()` — vytvoření všech tabulek + audit_log append-only trigger
- [ ] Implementovat `down()` — odstranění triggerů + tabulek v reverzním pořadí
- [ ] Spustit migraci v Dockeru (`docker compose exec app php bin/migrate`)
- [ ] Ověřit přes `mcp_server_mysql` MCP (`mysql_query` s `SHOW TABLES`, `DESCRIBE`, `SHOW CREATE TABLE`)

### Fáze 4: Frontend scaffolding

- [ ] Vytvořit `frontend/package.json` — React 18, TypeScript 5, Vite 5, Tailwind 3, TanStack Query 5, Zustand, React Router 6, Lucide React, React Hook Form, Zod, Recharts + dev deps (ESLint, Prettier, Stylelint, Vitest, @types)
- [ ] Definovat **npm scripts** v `package.json`: `dev` (vite), `build` (tsc + vite build), `lint` (eslint), `typecheck` (tsc --noEmit), `test` (vitest), `preview` (vite preview), `stylelint` (stylelint "src/**/*.css"`)
- [ ] Vytvořit `frontend/index.html` — HTML entry point s `window.__ENV__` script pro runtime env vars
- [ ] Vytvořit `frontend/vite.config.ts` — React plugin + proxy `/api` na `http://localhost:8080` + manualChunks
- [ ] Vytvořit `frontend/postcss.config.js` — Tailwind CSS 3 + Autoprefixer PostCSS konfigurace (vyžadováno pro Tailwind 3 s Vite)
- [ ] Vytvořit `frontend/tsconfig.json` — strict mode, noUncheckedIndexedAccess, exactOptionalPropertyTypes, path aliases (`@/*` → `./src/*`)
- [ ] Vytvořit `frontend/tailwind.config.ts` — container queries plugin, custom spacing/containers/fontSize/fontFamily/borderRadius, darkMode: 'class'
- [ ] Vytvořit `frontend/.stylelintrc.json` — pravidla zakazující `px` a `%` v CSS, výjimky pro shadcn proměnné
- [ ] Vytvořit `frontend/src/styles/globals.css` — Tailwind directives + CSS proměnné (dark + light) + container contexts (#root, .module-container, .card-container, .sidebar-container)
- [ ] Vytvořit `frontend/src/main.tsx` — React entry point
- [ ] Vytvořit `frontend/src/App.tsx` — Router + layout shell
- [ ] Vytvořit `frontend/src/lib/utils.ts` — cn() utility + formátování
- [ ] Vytvořit `frontend/src/lib/api.ts` — API client (fetch wrapper s auth header)
- [ ] Vytvořit `frontend/src/lib/queryClient.ts` — TanStack Query konfigurace
- [ ] Vytvořit `frontend/src/stores/authStore.ts` — Zustand auth state
- [ ] Vytvořit `frontend/src/types/api.ts` — API typy (HealthResponse, atd.)
- [ ] Vytvořit `frontend/src/modules/` adresáře (auth/, sites/, dashboard/) s `.gitkeep` v každém
- [ ] Vytvořit `frontend/.env.example` — VITE_API_URL, VITE_APP_NAME, VITE_APP_VERSION, VITE_ENABLE_CLIENT_CRYPTO
- [ ] Vytvořit `frontend/.eslintrc.cjs` — @typescript-eslint/strict + airbnb + import/order
- [ ] Vytvořit `frontend/.prettierrc` — formátování
- [ ] Vytvořit `frontend/vitest.config.ts` — Vitest konfigurace
- [ ] Vytvořit `frontend/components.json` — shadcn/ui config s Shadcnblocks registry. API key přes `${SHADCNBLOCKS_API_KEY}` env var interpolaci (ne hardcode). Pokud interpolace nefunguje, `components.json` musí být v `.gitignore` a vytvořit `components.json.example` s placeholderem.
- [ ] Spustit `npm install` v Docker kontejneru
- [ ] Inicializovat shadcn/ui (`npx shadcn@latest init`)
- [ ] Přidat base komponenty (button, card, dialog, input, label, table, tabs, badge, alert, dropdown-menu, form, separator, skeleton, scroll-area, sheet, popover, tooltip, command, avatar, breadcrumb, pagination)

### Fáze 5: GitHub infrastruktura

- [ ] Vytvořit `.github/workflows/ci.yml` — 14 kontrol (backend lint/static/tests, frontend lint/typecheck/tests/build, security audit + CodeQL, commitlint)
- [ ] Vytvořit `.github/workflows/branch-rules.yml` — branch naming, PR target, PR title, auto-label
- [ ] Vytvořit `.github/workflows/release.yml` — tag-triggered build + GitHub Release
- [ ] Vytvořit `.github/workflows/code-quality.yml` — cron (Monday 2:00 UTC), Rector dry-run, bundle analysis, circular deps
- [ ] Vytvořit `.github/CODEOWNERS` — ownership pro security soubory
- [ ] Vytvořit `.github/pull_request_template.md` — PR checklist
- [ ] Vytvořit `.github/labels.json` — 20 labels s barvami
- [ ] Vytvořit `.commitlintrc.cjs` — Conventional Commits validace
- [ ] Vytvořit `.gitattributes` — line endings, binary files

### Fáze 6: Root soubory

- [ ] Vytvořit `README.md` — projekt popis, instalace, Docker příkazy, struktura
- [ ] Vytvořit `CONTRIBUTING.md` — jak přispívat, branch naming, commit formát, PR proces
- [ ] Aktualizovat `.gitignore` — přidat `CHANGELOG_CS.md` (už je), ověřit všechny cesty

### Fáze 7: Verifikace

- [ ] Spustit `docker compose up -d --build` — všechny 4 služby běží
- [ ] Ověřit `http://localhost:8080/api/health` — vrátí `{"status":"ok"}`
- [ ] Ověřit `http://localhost:5173` — frontend načte (prázdná stránka s layoutem)
- [ ] Ověřit DB přes `mcp_server_mysql` MCP — 12 tabulek existuje
- [ ] Vizuální kontrola přes `playwright` MCP — screenshot frontendu
- [ ] Spustit `composer analyse` v Dockeru — PHPStan level 8 bez chyb
- [ ] Spustit `composer cs-check` v Dockeru — PSR-12 bez chyb
- [ ] Spustit `npm run lint` v Dockeru — ESLint bez chyb
- [ ] Spustit `npm run typecheck` v Dockeru — TypeScript bez chyb
- [ ] Spustit `npm run build` v Dockeru — Vite build úspěšný

## 7. Testy

### 7.1 Backend

- Unit test pro `Connection.php` — DBAL connection se správně vytvoří s env proměnnými
- Unit test pro `JsonBodyParserMiddleware.php` — parsuje JSON body, odmítne neplatný JSON
- Integration test pro `/api/health` — vrátí 200 + JSON se status

### 7.2 Frontend

- Test pro `api.ts` — API client odesílá správné headery
- Test pro `authStore.ts` — Zustand store inicializace
- Test pro `App.tsx` — router vykreslí správné routy

## 8. Validace

- [ ] `docker compose up -d --build` — všechny služby startují bez chyb
- [ ] `docker compose ps` — 4 služby running + healthy
- [ ] `curl http://localhost:8080/api/health` — 200 OK + JSON response
- [ ] `http://localhost:5173` — frontend načte v prohlížeči
- [ ] DB verifikace přes `mcp_server_mysql` MCP (`mysql_query` s `SHOW TABLES`, `DESCRIBE` pro každou tabulku, `SHOW INDEX` pro indexy, `SHOW CREATE TABLE` pro ENGINE/CHARSET)
- [ ] Vizuální kontrola přes `playwright` MCP (`browser_navigate` na `http://localhost:5173`, `browser_take_screenshot`, `browser_console_messages` s `level: "error"`)
- [ ] `docker compose exec app composer analyse` — PHPStan level 8 bez chyb
- [ ] `docker compose exec app composer cs-check` — PSR-12 bez chyb
- [ ] `docker compose exec frontend npm run lint` — ESLint bez chyb
- [ ] `docker compose exec frontend npm run typecheck` — TypeScript bez chyb
- [ ] `docker compose exec frontend npm run build` — Vite build úspěšný
- [ ] `composer audit` — bez známých vulnerabilit
- [ ] `npm audit` — bez známých vulnerabilit

## 8b. Ladění chyb (striktní pravidla)

Při ladění jakékoliv chyby (lint, typecheck, build, test, runtime) platí:

1. **Žádné odhadování** — nezkoušet naslepo dvacet možností
2. **Nejprve dokumentace** — před pokusem o opravu najít oficiální dokumentaci:
   - `webfetch` / `web_search` pro oficiální docs knihovny/nástroje
   - `context7` MCP pro aktuální dokumentaci knihoven
   - GitHub Issues pro známé bugy a řešení
   - Teprve s pochopením root cause navrhnout řešení
3. **Logování do souboru** — pokud chyba není zřejmá z výstupu:
   - Vytvoř `.debug.log` soubor v `storage/logs/` (backend) nebo `frontend/.debug.log`
   - Zapisuj logy příkazů, výstupy, chybové hlášky
   - Soubor musí být přístupný pro čtení přes `read` nástroj
   - Po vyřešení smazat `.debug.log` soubor
4. **Systematický přístup**:
   - Reprodukovat chybu
   - Přečíst chybovou zprávu pozorně (celou, ne jen první řádek)
   - Najít dokumentaci pro daný nástroj/knihovnu
   - Identifikovat root cause
   - Aplikovat jedno řešení (ne zkoušet více najednou)
   - Ověřit, že řešení funguje
5. **Zakázáno**:
   - `--force`, `--no-verify`, obcházení kontrol
   - Naslepo měnit konfiguraci
   - Zkoušet řešení bez pochopení příčiny

## 9. Rizika a mitigace

| Riziko | Pravděpodobnost | Dopad | Mitigace |
|--------|----------------|-------|----------|
| Docker build selže na chybějících PHP rozšířeních | střední | střední | Ověřit seznam rozšíření v Dockerfile proti composer.json requirements |
| MariaDB 11.4 vs 11.8 inkompatibilita | nízká | nízký | Docker použije mariadb:11.4 — izolováno od host MariaDB 11.8 |
| shadcn/ui init selže bez components.json | střední | střední | Vytvořit components.json před `npx shadcn@latest init` |
| Vite proxy nefunguje (CORS) | střední | střední | Nastavit CORS_ALLOWED_ORIGINS v backend .env + Vite proxy config |
| Port konflikt (3306, 8080, 5173) | nízká | střední | Ověřit že porty nejsou obsazené před `docker compose up` |
| Composer dependencies konflikt | nízká | střední | Pinovat verze v composer.json, použít `composer audit` |

## 10. Rollback strategie

- **Kód**: `git revert` commitu s scaffoldingem, nebo `git reset --hard` na předchozí commit (vše je v jednom commitu, rollback je čistý)
- **DB migrace**: `php bin/migrate down` — odstraní všechny 12 tabulek + audit_log trigger
- **DB záloha**: Před migrací vytvořit zálohu `mysqldump wp_monitor > backup.sql`
- **Dependencies**: `docker compose down -v` smaže volumes (vendor, node_modules, db-data) — čistý stav
- **Docker**: `docker compose down` zastaví a odstraní kontejnery

## 11. Cross-module impact

Žádný — toto je greenfield scaffolding, žádné moduly zatím neexistují. Všechny moduly (auth, sites, dashboard, updates, backups, security, seo) budou přidány v dalších implementačních plánech.

| Modul | Dopad | Míra | Poznámka |
|-------|-------|------|----------|
| — | — | — | Greenfield, žádné existující moduly |

- **EventDispatcher eventy**: Žádné — EventDispatcher bude konfigurován ale žádné eventy se zatím neemitují
- **Sdílené služby**: `Connection.php` (DBAL) — budou použity všemi moduly
- **DB tabulky**: 12 core tabulek — budou referencovány všemi moduly

## 12. Poznámky

- **PHP verze**: Docker použije PHP 8.3-FPM Alpine (per dokumentaci), i když host má PHP 8.5. To zajišťuje konzistenci s produkcí.
- **MariaDB verze**: Docker použije `mariadb:11.4` (per dokumentaci). Host MariaDB 11.8 bude zastavena (`sudo systemctl stop mariadb`) — port 3307 uvolněn pro Docker DB. MCP server nadále používá port 3307, ale nyní se připojuje k Docker DB.
- **Port mapping**: Docker DB na host portu 3307 (stejný jako původně host MariaDB). Žádný port konflikt. Backend, app, web kontejnery komunikují s DB přes interní síť na portu 3306 (Docker interní).
- **shadcnblocks API key**: `components.json` obsahuje API key. Pokud shadcn CLI podporuje `${SHADCNBLOCKS_API_KEY}` env var interpolaci, použít ji. Pokud ne, `components.json` musí být v `.gitignore` a v repozitáři pouze `components.json.example` s placeholderem. Klíč nikdy ne hardcode v commitovaném souboru.
- **Frontend v Dockeru**: Vite dev server běží v kontejneru na portu 5173 s HMR. Pro produkci se buildí do `backend/public/assets/`.
- **Makefile**: Poskytuje zkratky pro časté Docker příkazy — `make up`, `make down`, `make logs`, `make shell`, `make migrate`, `make test`, atd.
- **Composer scripts**: `composer.json` musí obsahovat `scripts` sekci s `test`, `analyse`, `cs-check`, `cs-fix`, `audit` — jinak příkazy v plánu (`composer test`, atd.) nefungují.
- **npm scripts**: `package.json` musí obsahovat `scripts` sekci s `dev`, `build`, `lint`, `typecheck`, `test`, `preview`, `stylelint` — jinak příkazy v plánu nefungují.
- **PostCSS**: Tailwind CSS 3 vyžaduje `postcss.config.js` s `tailwindcss` a `autoprefixer` pluginy. Bez něj Vite Tailwind nezpracuje a styly nefungují.
- **Stylelint**: Design system (docs/14) striktně zakazuje `px` a `%` jednotky. `.stylelintrc.json` zajistí enforcement tohoto pravidla.

## 13. Post-implementační kontrola

- [ ] **Code review** — projdi implementaci podle skillu `.devin/skills/code-review/SKILL.md` (bezpečnost, rychlost, modularita)
- [ ] **DB verifikace** — ověř přes `mcp_server_mysql` MCP (`mysql_query` s `SHOW TABLES`, `DESCRIBE`, `SHOW INDEX`, `SHOW CREATE TABLE`) — 12 tabulek, správné indexy, ENGINE=InnoDB, CHARSET=utf8mb4
- [ ] **Vizuální kontrola** — ověř přes `playwright` MCP (`browser_navigate` na `http://localhost:5173`, `browser_take_screenshot`, `browser_console_messages`)
- [ ] **Cross-module impact** — N/A (greenfield)
- [ ] **Environment variables** — `backend/.env.example` a `frontend/.env.example` obsahují všechny potřebné proměnné s placeholder hodnotami
- [ ] **Dependency audit**:
  - Backend: `composer audit` — bez známých vulnerabilit
  - Frontend: `npm audit` — bez známých vulnerabilit
  - Verze musí být publikována alespoň 7 dní
- [ ] **Dokumentace** — README.md a CONTRIBUTING.md vytvořeny
- [ ] **Plán dokončen** — všechny checkboxy odkrokovány, status `completed`

## 14. GitHub a changelog

- [ ] **Secrets scanning** — `git diff --cached` neobsahuje žádné API klíče, hesla, tokens, `.env` soubory
- [ ] **Commit** — `feat(core): complete project scaffolding with Docker, backend, frontend, and GitHub infrastructure`
- [ ] **Push** — pushni na `origin/main`
- [ ] **Changelog** — podle skillu `.devin/skills/changelog/SKILL.md`:
  - Aktualizuj `CHANGELOG.md` (anglicky, commitnuto do gitu)
  - Aktualizuj `CHANGELOG_CS.md` (česky, gitignored, lokální)
  - Verze: 0.1.0 (pre-release scaffolding)
  - Datum: DD.MM.YYYY
- [ ] **Commit changelog** — `docs: update changelog for version 0.1.0`
- [ ] **Push** — pushni changelog na `origin/main`

---

## Changelog plánu

| Datum | Změna | Autor |
|-------|-------|-------|
| 08.08.2026 | Vytvoření plánu | Devin |
| 08.08.2026 | Revize: DB port 3307 (zastavit host MariaDB), přidán postcss.config.js, .stylelintrc.json, config/middleware.php, src/Shared/, VITE_ENABLE_CLIENT_CRYPTO, composer/npm scripts, components.json clarifikace, .gitkeep pro moduly | Devin |
