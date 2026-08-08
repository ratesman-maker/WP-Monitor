# WP Monitor — Vývojový guide

## 1. Lokální vývojové prostředí

### 1.1 Požadavky

- **Docker Engine 24.0+** s **Docker Compose v2** (plugin `docker compose` nebo samostatný `docker-compose`)
- **Git**
- **Node.js 22+** a **npm** — pro frontend tooling a Vite dev server (lze alternativně spustit v kontejneru, viz 1.11)
- **Make** (volitelně) — pro zkratkové příkazy přes `Makefile`

> Na hostu není potřeba instalovat PHP, Composer ani MariaDB — vše běží v Docker kontejnerech. Node.js na hostu je doporučené pro nejlepší Vite HMR experience, ale není povinné (viz 1.11 Hybridní režim).

### 1.2 Architektura Docker prostředí

Vývojové prostředí běží výhradně v Dockeru. Čtyři služby:

| Služba | Image / zdroj | Účel | Port (host) |
|--------|---------------|------|-------------|
| **db** | `mariadb:11.4` | MariaDB databáze | `3306` |
| **app** | `docker/php/Dockerfile` (PHP 8.3-FPM) | PHP-FPM backend, Composer, PHPStan, PHPUnit | — |
| **web** | `nginx:1.27-alpine` | Nginx reverse proxy, servuje backend + statické soubory | `8080` |
| **frontend** | `docker/frontend/Dockerfile` (Node 22) | Vite dev server s HMR | `5173` |

**Přehled komunikace:**

```
Browser
  │
  ├── http://localhost:5173  →  frontend (Vite dev server)
  │                                    │
  │                                    └── proxy /api → web:80
  │
  └── http://localhost:8080  →  web (Nginx)
                                     │
                                     ├── /api/  →  app:9000 (PHP-FPM)
                                     └── /      →  frontend/dist (produkční build)

Databáze: localhost:3306 (přímý přístup z nástrojů — DBeaver, DataGrip, CLI)
```

V dev režimu se přistupuje k frontendu výhradně přes Vite dev server (`:5173`), který proxyuje API požadavky na Nginx (`:8080`). Nginx pak předává PHP požadavky na PHP-FPM (`app`). Databáze je dostupná na `localhost:3306` pro přímý přístup z externích nástrojů.

### 1.3 Adresářová struktura Docker souborů

```
wp-monitor/
├── docker/
│   ├── php/
│   │   ├── Dockerfile          # PHP 8.3-FPM s rozšířeními a Composerem
│   │   ├── php.ini             # Dev konfigurace PHP
│   │   └── entrypoint.sh       # Spuštění PHP-FPM + čekání na DB
│   ├── nginx/
│   │   └── default.conf        # Nginx server blok
│   └── frontend/
│       └── Dockerfile          # Node 22 + Vite dev server
├── docker-compose.yml          # Definice služeb
├── .dockerignore               # Ignorované soubory pro build context
└── Makefile                    # Zkratkové příkazy (volitelné)
```

### 1.4 docker-compose.yml

```yaml
services:
  db:
    image: mariadb:11.4
    container_name: wp-monitor-db
    restart: unless-stopped
    environment:
      MARIADB_ROOT_PASSWORD: ${DB_ROOT_PASSWORD:-root}
      MARIADB_DATABASE: ${DB_NAME:-wp_monitor}
      MARIADB_USER: ${DB_USER:-wp_monitor}
      MARIADB_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - db-data:/var/lib/mysql
    ports:
      - "3306:3306"
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10

  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    container_name: wp-monitor-app
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy
    environment:
      APP_ENV: development
      APP_DEBUG: "true"
      DB_HOST: db
      DB_PORT: "3306"
      DB_NAME: ${DB_NAME:-wp_monitor}
      DB_USER: ${DB_USER:-wp_monitor}
      DB_PASSWORD: ${DB_PASSWORD:-secret}
    volumes:
      - ./backend:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/wp-monitor.ini:ro
    networks:
      - default

  web:
    image: nginx:1.27-alpine
    container_name: wp-monitor-web
    restart: unless-stopped
    depends_on:
      - app
    ports:
      - "8080:80"
    volumes:
      - ./backend:/var/www/html:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    networks:
      - default

  frontend:
    build:
      context: .
      dockerfile: docker/frontend/Dockerfile
    container_name: wp-monitor-frontend
    restart: unless-stopped
    depends_on:
      - web
    environment:
      VITE_API_URL: http://localhost:5173/api
    ports:
      - "5173:5173"
    volumes:
      - ./frontend:/app
      - frontend-node-modules:/app/node_modules
    networks:
      - default

volumes:
  db-data:
  frontend-node-modules:

networks:
  default:
    driver: bridge
```

### 1.5 Dockerfile pro PHP-FPM

`docker/php/Dockerfile`:

```dockerfile
FROM php:8.3-fpm-alpine

# Systémové závislosti pro PHP rozšíření
RUN apk add --no-cache \
    linux-headers \
    libxml2-dev \
    curl-dev \
    openssl-dev \
    libsodium-dev \
    oniguruma-dev \
    icu-dev \
    libzip-dev \
    gd-dev \
    netcat-openbsd \
    && docker-php-ext-install \
        pdo_mysql \
        mysqli \
        mbstring \
        intl \
        curl \
        xml \
        zip \
        gd \
        sodium \
        opcache

# Composer
COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Entrypoint — čeká na DB a spustí PHP-FPM
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
```

`docker/php/php.ini` (dev konfigurace):

```ini
; Dev nastavení
memory_limit = 512M
max_execution_time = 120
display_errors = On
error_reporting = E_ALL

; OpCache (zapnuto i v dev, s validací timestamp pro hot reload)
opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 1
opcache.revalidate_freq = 0

; Xdebug (volitelně — odkomentovat pro debugging)
; zend_extension=xdebug
; xdebug.mode=debug
; xdebug.start_with_request=yes
; xdebug.client_host=host.docker.internal
; xdebug.client_port=9003
```

`docker/php/entrypoint.sh`:

```bash
#!/bin/sh
set -e

# Čekání na dostupnost databáze
echo "Čekání na databázi..."
until nc -z db 3306 2>/dev/null; do
  sleep 1
done
echo "Databáze je dostupná."

# Instalace Composer závislostí (pokud chybí)
if [ ! -d "vendor" ]; then
  echo "Instalace Composer závislostí..."
  composer install --no-interaction
fi

# Migrace (pokud je bin/migrate k dispozici)
if [ -f "bin/migrate" ]; then
  echo "Spuštění migrací..."
  php bin/migrate || true
fi

exec "$@"
```

### 1.6 Nginx konfigurace

`docker/nginx/default.conf`:

```nginx
server {
    listen 80;
    server_name localhost;
    root /var/www/html/public;
    index index.php;

    # Backend API
    location /api/ {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # Frontend SPA (produkční build — v dev se používá Vite :5173)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Zakázat přístup k citlivým souborům
    location ~ /\.(env|git) {
        deny all;
    }
    location ~ \.(sql|md|neon)$ {
        deny all;
    }
}
```

### 1.7 Dockerfile pro frontend

`docker/frontend/Dockerfile`:

```dockerfile
FROM node:22-alpine

WORKDIR /app

# Kopírování manifestů a instalace závislostí
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm install

# Vite dev server na 0.0.0.0 pro přístup z hostu
EXPOSE 5173
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
```

### 1.8 .dockerignore

```
.git
.gitignore
docs/
.devin/
.windsurf/
*.md
.env
.env.*
vendor/
node_modules/
backend/storage/logs/*
backend/storage/cache/*
backend/storage/backups/*
frontend/dist/
```

### 1.9 Inicializace projektu

```bash
# 1. Klonování repozitáře
git clone <repo-url> wp-monitor
cd wp-monitor

# 2. Konfigurace prostředí
cp backend/.env.example backend/.env
# Upravit backend/.env — APP_KEY, MASTER_PASSWORD_SALT, BACKUP_ENCRYPTION_KEY
# DB připojení je nastaveno v docker-compose.yml (DB_HOST=db)

cp frontend/.env.example frontend/.env
# Upravit frontend/.env — VITE_API_URL

# 3. Spuštění Docker kontejnerů
docker compose up -d --build

# 4. Kontrola stavu
docker compose ps
docker compose logs -f app       # logy z PHP-FPM

# 5. Seed databáze (první spuštění)
docker compose exec app php bin/seed

# 6. Přístup
# Frontend (Vite dev):  http://localhost:5173
# Backend API:          http://localhost:8080/api
# Databáze:             localhost:3306 (wp_monitor / secret)
```

### 1.10 Běžné operace

```bash
# Start / stop
docker compose up -d
docker compose stop

# Restart konkrétní služby
docker compose restart app
docker compose restart web

# Logy
docker compose logs -f app
docker compose logs -f frontend
docker compose logs -f db

# Vstup do kontejneru
docker compose exec app sh
docker compose exec db mariadb -u wp_monitor -psecret wp_monitor

# Composer (v kontejneru)
docker compose exec app composer install
docker compose exec app composer test
docker compose exec app composer analyse

# Migrace
docker compose exec app php bin/migrate
docker compose exec app php bin/migrate:status

# Frontend (v kontejneru)
docker compose exec frontend npm run build
docker compose exec frontend npm run lint
docker compose exec frontend npm run typecheck

# Čistý reset (pozor — smaže DB data!)
docker compose down -v
docker compose up -d --build
```

### 1.11 Hybridní režim — Vite na hostu (volitelné)

Pro nejlepší HMR experience lze Vite dev server spustit přímo na hostu místo v kontejneru. Backend (PHP-FPM + Nginx + MariaDB) zůstává v Dockeru.

```bash
# 1. Spustit jen backend služby
docker compose up -d db app web

# 2. Frontend na hostu
cd frontend
npm install
npm run dev
# Vite proxy /api → http://localhost:8080 (Nginx)
```

V `frontend/vite.config.ts` nastavit proxy:

```typescript
export default defineConfig({
  server: {
    host: '0.0.0.0',
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
});
```

### 1.12 Makefile (volitelné zkratky)

`Makefile`:

```makefile
.PHONY: up down restart logs ps shell migrate seed test analyse lint typecheck build clean

up:
	docker compose up -d --build

down:
	docker compose down

restart:
	docker compose restart

logs:
	docker compose logs -f

ps:
	docker compose ps

shell:
	docker compose exec app sh

db-shell:
	docker compose exec db mariadb -u wp_monitor -psecret wp_monitor

migrate:
	docker compose exec app php bin/migrate

seed:
	docker compose exec app php bin/seed

test:
	docker compose exec app composer test
	cd frontend && npm run test

analyse:
	docker compose exec app composer analyse

lint:
	cd frontend && npm run lint

typecheck:
	cd frontend && npm run typecheck

build:
	cd frontend && npm run build

clean:
	docker compose down -v
	docker compose up -d --build
```

Použití: `make up`, `make logs`, `make test`, `make migrate`, atd.

### 1.13 shadcn/ui + shadcnblocks.com setup

Projekt používá **shadcn/ui** pro base komponenty a **shadcnblocks.com** (premium licence) pro hotové bloky.

#### Instalace shadcn/ui

```bash
# Inicializace shadcn/ui ve frontend projektu
npx shadcn@latest init

# Přidání base komponent
npx shadcn@latest add button dialog input label select
npx shadcn@latest add table tabs card badge alert dropdown-menu
npx shadcn@latest add form checkbox radio-group switch separator
npx shadcn@latest add progress skeleton scroll-area sheet popover tooltip
npx shadcn@latest add command avatar breadcrumb pagination
```

#### Instalace shadcnblocks.com bloků

Bloky se instalují přes **Shadcn CLI** nebo **IDE Extension**:

```bash
# Přihlášení s premium účtem
npx shadcn@latest login

# Instalace konkrétního bloku (např. dashboard stat cards)
npx shadcn@latest add https://www.shadcnblocks.com/blocks/dashboard

# Instalace Application Shell bloků (sidebar, topbar, layout)
npx shadcn@latest add https://www.shadcnblocks.com/blocks/application-shell

# Instalace Data Table bloků
npx shadcn@latest add https://www.shadcnblocks.com/blocks/data-table

# Instalace Chart Group bloků
npx shadcn@latest add https://www.shadcnblocks.com/blocks/chart-group

# Instalace Bento grid bloků (dashboard widgety)
npx shadcn@latest add https://www.shadcnblocks.com/blocks/bento
```

Bloky se kopírují do `frontend/src/components/blocks/` — je to náš kód, můžeme je libovolně upravovat.

#### Mapování bloků na WP Monitor moduly

| shadcnblocks kategorie | WP Monitor použití |
|------------------------|-------------------|
| **Dashboard** (18 bloků) | Hlavní dashboard — stat cards, overview layouts |
| **Application Shell** (14 bloků) | App layout — sidebar, topbar, navigation |
| **Data Table** (32 bloků) | Seznamy webů, záloh, logů, zranitelností, aktualizací |
| **Chart Group** (15 bloků) | Uptime grafy, performance trendy, activity charts |
| **Bento** (53 bloků) | Dashboard widgety, security score, storage info |
| **Feature** (311 bloků) | Seznamy, přehledy, doporučení |

#### Pravidla pro práci s bloky

1. **Bloky upravuj minimálně** — zachovat strukturu, měnit pouze data binding a styly
2. **Custom logika do separátních komponent** — blok je presentational, data fetching do hooku
3. **Neduplikovat bloky** — pokud potřebuješ variantu, rozšíř existující blok
4. **Theme konzistence** — používáme jednu z 14 dostupných themes (viz shadcnblocks.com)
5. **Při aktualizaci bloku** — diff a review změn, nepřepsat naše customizace

### 2.0 Dodržované standardy

#### PHP standardy

| Standard | Popis | Nástroj |
|----------|-------|---------|
| **PSR-1** | Základní coding standard (názvy tříd, metod, souborů) | PHP CS Fixer |
| **PSR-12** | Rozšířený coding standard (formátování, závorky, odsazení) | PHP CS Fixer (`backend/.php-cs-fixer.php`) |
| **PSR-3** | Logger interface | Monolog |
| **PSR-4** | Autoloading (namespace → adresář) | Composer |
| **PSR-7** | HTTP message interface | Slim 4 |
| **PSR-11** | Container interface | PHP-DI |
| **PSR-15** | HTTP middleware | Slim 4 middleware pipeline |
| **PSR-17** | HTTP factories | Slim 4 |
| **PHPStan level 8** | Nejpřísnější statická analýza | `backend/phpstan.neon` |
| **PHPStan strict rules** | Extra striktní pravidla nad level 8 | phpstan-strict-rules |
| **Rector** | Automatické upgrady a refaktoring | `backend/rector.php` |

#### TypeScript / JavaScript standardy

| Standard | Popis | Nástroj |
|----------|-------|---------|
| **TypeScript strict mode** | `strict: true`, `noUncheckedIndexedAccess`, `exactOptionalPropertyTypes` | `frontend/tsconfig.json` |
| **ESLint** | Linting s `@typescript-eslint/strict` a `airbnb` preset | `frontend/.eslintrc.cjs` |
| **Prettier** | Konzistentní formátování | `frontend/.prettierrc` |
| **React 18 best practices** | Funkční komponenty, hooks, jsx-runtime | ESLint react/recommended |
| **WCAG 2.1 AA** | Přístupnost | shadcn/ui (Radix UI základ) |

**Klíčná ESLint pravidla (vynucená):**
- `@typescript-eslint/no-explicit-any` — error
- `@typescript-eslint/no-floating-promises` — error
- `@typescript-eslint/strict-boolean-expressions` — warn
- `@typescript-eslint/switch-exhaustiveness-check` — error
- `react-hooks/rules-of-hooks` — error
- `react-hooks/exhaustive-deps` — error
- `import/no-cycle` — error (maxDepth: 3)
- `import/no-default-export` — error (kromě page komponent)
- `max-depth` — warn (3)
- `max-params` — warn (4)
- `complexity` — warn (10)
- `max-lines-per-function` — warn (50, skipComments)

#### Bezpečnostní standardy

| Standard | Popis | Aplikace |
|----------|-------|----------|
| **OWASP Top 10** | Top 10 webových zranitelností | Pokryto v `docs/03-security-model.md` |
| **OWASP ASVS v4.0 Level 2** | Application Security Verification Standard | Checklist pro security audit |
| **OWASP Cryptographic Storage** | Šifrování dat v klidu | AES-256-GCM + Argon2id |
| **NIST SP 800-63B** | Autentizace — key derivation parametry | Argon2id s INTERACTIVE/SENSITIVE limity |
| **RFC 7519** | JWT spec | JWT implementace v JwtService |
| **RFC 8446** | TLS 1.3 | HTTPS vynuceno pro veškerou komunikaci |

#### API standardy

| Standard | Popis | Aplikace |
|----------|-------|----------|
| **REST** | Resource-based URL, HTTP metody, status kódy | Všechny endpointy |
| **OpenAPI 3.1** | Strojově čitelná API dokumentace | Generováno z kódu (budoucí fáze) |
| **RFC 7807** | Problem Details for HTTP APIs | Náš error formát je kompatibilní |

#### Git standardy

| Standard | Popis |
|----------|-------|
| **Conventional Commits 1.0** | `feat(scope): description`, `fix(scope):`, `security(scope):`, etc. |
| **Git Flow (zjednodušený)** | `main` → `develop` → `feature/*`, `fix/*`, `security/*`, `refactor/*`, etc. |
| **SemVer 2.0** | `MAJOR.MINOR.PATCH` — tag formát `v{X.Y.Z}` |
| **Branch naming** | `{type}/{kebab-case-description}` — viz `docs/08-versioning-strategy.md` |
| **Signed commits (volitelně)** | GPG/SSH pro security projekt |

Kompletní verzovací strategie v **`docs/08-versioning-strategy.md`** — branch model, workflow podle typu změny, merge pravidla, release a hotfix proces.

#### Konfigurační soubory v projektu

| Soubor | Účel |
|--------|------|
| `backend/.php-cs-fixer.php` | PSR-12 + vlastní pravidla formátování |
| `backend/phpstan.neon` | PHPStan level 8 + strict rules |
| `backend/rector.php` | Automatické upgrady a refaktoring |
| `backend/phpunit.xml` | PHPUnit konfigurace s test DB |
| `frontend/.eslintrc.cjs` | ESLint + TypeScript strict + airbnb |
| `frontend/.prettierrc` | Prettier formátování |
| `frontend/tsconfig.json` | TypeScript strict mode |
| `frontend/vitest.config.ts` | Vitest konfigurace s coverage thresholds |
| `.gitignore` | Ignorované soubory (vendor, node_modules, .env, storage) |

### 2.1 PHP

- **PSR-12** coding standard (vynuceno přes `.php-cs-fixer.php`)
- **PHPStan level 8** + strict rules pro statickou analýzu (`phpstan.neon`)
- **PHP CS Fixer** pro formátování (`.php-cs-fixer.php`)
- **Rector** pro automatické upgrady (`rector.php`)
- Všechny třídy mají strict types: `declare(strict_types=1);`
- Type hints na všech parametrech a return typech
- readonly properties kde vhodné (PHP 8.2+)

**Příklad:**

```php
<?php

declare(strict_types=1);

namespace WPMonitor\Security;

readonly class CryptoService
{
    public function __construct(
        private string $key  // 32-byte encryption key
    ) {}

    public function encrypt(string $plaintext, string $aad = ''): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
            $plaintext,
            $aad,
            $nonce,
            $this->key
        );
        return base64_encode($nonce . $ciphertext);
    }
}
```

### 2.2 TypeScript / React

- **ESLint** + **Prettier**
- Funkční komponenty + hooks (žádné class komponenty)
- Všechny props typovány přes interface
- `import type` pro type-only importy
- Named exports (ne default), kromě page komponent

**Příklad:**

```tsx
import type { Site } from '@/types/domain';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

interface SitesListProps {
  groupId?: number;
  onSiteSelect?: (site: Site) => void;
}

export function SitesList({ groupId, onSiteSelect }: SitesListProps): JSX.Element {
  const { data, isLoading } = useQuery({
    queryKey: ['sites', { groupId }],
    queryFn: () => api.getSites({ groupId }),
  });

  if (isLoading) return <LoadingSpinner />;

  return (
    <div className="space-y-2">
      {data?.items.map((site) => (
        <SiteRow key={site.id} site={site} onClick={onSiteSelect} />
      ))}
    </div>
  );
}
```

### 2.3 Limity kódu a prevence špaget kódu

Nejde o dogmatické počty řádků, ale o udržení čitelnosti, testovatelnosti a jedné zodpovědnosti. Limity jsou orientační — varovný signál, ne automatický důvod k refaktoru.

**Doporučené limity (orientační):**

| Vrstva | Doporučený strop | Kdy překročit je OK |
|--------|-----------------|---------------------|
| **Controller (PHP)** | ~150 řádků | Validace + response formátování zabere místo |
| **Service (PHP)** | ~400 řádků | Komplexní business logika s edge cases |
| **Repository (PHP)** | ~300 řádků | Hodně query metod |
| **Domain Entity (PHP)** | ~100 řádků | Výjimečně — většinou je to DTO |
| **Middleware (PHP)** | ~100 řádků | — |
| **Security třídy (PHP)** | ~200 řádků | CryptoService, AuthMiddleware — musí být krátké a jasné |
| **Page komponenta (React)** | ~250 řádků | Layout + orchestrace komponent |
| **Běžná komponenta (React)** | ~200 řádků | Komplexní UI (tabulky, formuláře) |
| **Custom hook** | ~80 řádků | Někdy je hook logicky nedělitelný |
| **API modul (api.ts)** | ~200 řádků | Hodně endpointů = hodně funkcí |
| **Zustand store** | ~150 řádků | Více akcí + selectors |

**Metody a funkce:**

| Typ | Doporučený strop | Poznámka |
|-----|-----------------|----------|
| **PHP metoda** | ~40 řádků | Pokud přesahuje, zvážit extrakci privátní metody |
| **TS funkce** | ~30 řádků | Čisté funkce by měly být krátké |
| **React komponenta (render)** | ~50 řádků JSX | Více = rozdělit na sub-komponenty |

**Varovné signály (ne tvrdá čísla — refaktorovat když):**

| Signál | Co znamená | Akce |
|--------|-----------|------|
| Třída má 5+ různých "témat" v metodách | Porušena single responsibility | Rozdělit na 2+ tříd |
| Metoda má 4+ úrovně if/else nesting | Špatná čitelnost | Extrahovat metodu nebo použít early return |
| Komponenta má 8+ `useState` | Stavová složitost | Přesunout do custom hooku nebo Zustand |
| Service injectuje 6+ závislostí | Pravděpodobně dělá moc věcí | Rozdělit na subservisy |
| Stejná logika ve 3+ souborech | Duplikace | Extrahovat do sdílené utility/service |
| Soubor přes 3 obrazovky scrollování | Čitelnost klesá | Zvážit rozdělení |
| Funkce volá 5+ dalších funkcí v řadě | Špageta flow | Zjednodušit pipeline |

**Tvrdá pravidla (non-negotiable):**

1. **Jedna třída = jedna zodpovědnost** — pokud popíšeš co třída dělá a potřebuješ "a", rozděl
2. **Max 4 parametry v metodě/funkci** — více = použít DTO/options objekt
3. **Max 3 úrovně nesting** v metodách — hlubší = early return nebo extrakce
4. **Žádné God objekty** — Service nedělá DB + HTTP + šifrování najednou (může orchestrovat, ne implementovat vše)
5. **Dependency depth ≤ 3** — A→B→C→D je limit

**Příklad:**

```php
// ❌ Špatně — God service, míchá zodpovědnosti
class UpdatesService
{
    public function executeUpdate(Site $site, array $options): array
    {
        // 80 řádků: šifrování credentials, HTTP call, DB log, backup, rollback...
    }
}

// ✅ Správně — orchestruje, neprovádí vše sama
class UpdatesService
{
    public function __construct(
        private readonly WordPressClientFactory $clientFactory,
        private readonly BackupService $backupService,
        private readonly UpdateHistoryRepository $historyRepo,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function executeUpdate(Site $site, UpdateOptions $options): UpdateResult
    {
        $client = $this->clientFactory->create($site);

        if ($options->preBackup) {
            $this->backupService->createQuickBackup($site);
        }

        $result = $this->applyUpdates($client, $options);

        $this->historyRepo->log($site, $result);
        $this->auditLogger->log('update.execute', $site, $result);

        return $result;
    }

    private function applyUpdates(WordPressClient $client, UpdateOptions $options): UpdateResult
    {
        // ~20-30 řádků, jasný flow
    }
}
```

### 2.4 Souborové konvence

| Typ | Konvence | Příklad |
|-----|----------|---------|
| PHP třídy | PascalCase | `CryptoService.php` |
| PHP interfejsy | PascalCase + Interface | `ModuleInterface.php` |
| PHP traity | PascalCase + Trait | `EncryptsDataTrait.php` |
| TS/TSX soubory | PascalCase pro komponenty | `SitesListPage.tsx` |
| TS utility | camelCase | `formatDate.ts` |
| CSS/Tailwind | kebab-case | `globals.css` |
| DB migrace | `Version{YYYYMMDD}_{NN}_{description}.php` | `Version20260807_001_create_users.php` |

## 3. Git workflow

Kompletní verzovací strategie v **`docs/08-versioning-strategy.md`** — sémantické verzování, branch model, workflow podle typu změny, merge pravidla, release a hotfix proces.

### 3.1 Branch model (zjednodušený Git Flow)

```
main (produkční, vždy deployable)
├── v1.0.0 (tag)
├── v1.0.1 (tag — hotfix)
│
develop (vývojová integrace)
├── feature/updates-batch-ui      → PR do develop
├── fix/ssl-check-timeout          → PR do develop
├── security/xss-site-detail       → PR do develop
├── refactor/module-registry       → PR do develop
├── release/v1.1.0                 → PR do main
│
hotfix/v1.0.1                      → PR do main (z main)
```

**Branch routing (vynuceno GitHub Actions):**

| Branch prefix | Target | Merge metoda |
|---------------|--------|--------------|
| `feature/*`, `fix/*`, `security/*`, `refactor/*`, `perf/*`, `docs/*`, `test/*`, `chore/*` | `develop` | Squash merge |
| `release/*` | `main` | Merge commit |
| `hotfix/*` | `main` | Merge commit |

### 3.2 Commit konvence

Conventional Commits (vynuceno přes `.commitlintrc.cjs` a GitHub Actions):

```
<type>(<scope>): <description>

type:    feat | fix | security | refactor | perf | docs | test | chore | ci | build | style | revert
scope:   auth | sites | updates | backups | security | seo | core | api | ui | docs | config | db | deps | ci
max:     72 znaků pro subject
```

**Příklady:**

```
feat(updates): pridat hromadny update s progress barem
fix(security): opravit race condition v rate limiteru
security(auth): zpevnit key derivation — Argon2id sensitive params
docs(api): doplnit priklady pro backups endpointy
test(sites): pridat testy pro CSV import
chore(deps): aktualizovat Slim 4 na 4.14
```

### 3.3 Pull request proces

1. Vytvořit branch z `develop` (nebo `main` pro hotfix/release) podle naming konvence
2. Implementovat + testy
3. `composer test` + `npm run test` musí projít
4. `composer analyse` (PHPStan) bez chyb
5. `npm run lint` bez chyb
6. PR s popisem změn + link na issue (PR template automaticky)
7. Code review (CODEOWNERS vynuceno pro security soubory)
8. CI pipeline musí projít (10+ jobů)
9. Squash merge do `develop` (nebo merge commit pro release/hotfix do `main`)
10. Release: vytvoř `release/v{X.Y.Z}` → merge do `main` → tag `v{X.Y.Z}` → spustí Release workflow

## 4. Testování

### 4.1 Backend testy

- **PHPUnit** pro unit + integration testy
- **Mockery** pro mocking závislostí
- Test DB: samostatná databáze `wp_monitor_test` (v Dockeru — `docker compose exec app php bin/migrate --env=testing`)
- Feature testy: testují celý request lifecycle přes Slim test client

**Struktura:**

```
backend/tests/
├── Unit/
│   ├── Security/
│   │   ├── CryptoServiceTest.php
│   │   ├── KeyDerivationTest.php
│   │   └── JwtServiceTest.php
│   ├── Services/
│   │   ├── WordPressClientTest.php
│   │   └── BatchProcessorTest.php
│   └── Modules/
│       └── Updates/
│           └── UpdatesServiceTest.php
├── Integration/
│   ├── Auth/
│   │   └── LoginFlowTest.php
│   ├── Sites/
│   │   └── SiteCrudTest.php
│   └── Updates/
│       └── BatchUpdateTest.php
└── TestCase.php
```

**Příklad testu:**

```php
class CryptoServiceTest extends TestCase
{
    public function testEncryptDecryptRoundtrip(): void
    {
        $key = random_bytes(32);
        $crypto = new CryptoService($key);

        $plaintext = 'my-secret-password';
        $aad = pack('NN', 1, 1); // site_id=1, user_id=1

        $encrypted = $crypto->encrypt($plaintext, $aad);
        $decrypted = $crypto->decrypt($encrypted, $aad);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testDecryptFailsWithWrongAad(): void
    {
        $key = random_bytes(32);
        $crypto = new CryptoService($key);

        $encrypted = $crypto->encrypt('secret', pack('NN', 1, 1));

        $this->expectException(DecryptionException::class);
        $crypto->decrypt($encrypted, pack('NN', 2, 1)); // different site_id
    }
}
```

### 4.2 Frontend testy

- **Vitest** pro unit testy
- **React Testing Library** pro komponent testy
- **MSW (Mock Service Worker)** pro API mocking

**Struktura:**

```
frontend/src/
├── __tests__/
│   ├── components/
│   │   ├── SitesList.test.tsx
│   │   └── BatchUpdateDialog.test.tsx
│   ├── lib/
│   │   ├── api.test.ts
│   │   └── crypto.test.ts
│   └── stores/
│       └── authStore.test.ts
```

### 4.3 E2E testy

- **Playwright** pro end-to-end testy
- Testuje se celý flow: login → add site → run updates → verify

```
e2e/
├── auth.spec.ts
├── sites.spec.ts
├── updates.spec.ts
└── backups.spec.ts
```

### 4.4 Test příkazy

```bash
# Backend
composer test                    # PHPUnit
composer test:coverage           # s coverage reportem
composer analyse                 # PHPStan

# Frontend
npm run test                     # Vitest
npm run test:ui                  # Vitest UI
npm run lint                     # ESLint
npm run typecheck                # tsc --noEmit

# E2E
npx playwright test
npx playwright test --headed
```

## 5. Vytvoření nového modulu

### 5.1 Backend

1. **Vytvoř adresář:** `backend/src/Modules/{ModuleName}/`

2. **Vytvoř manifest.json:**

```json
{
    "id": "mymodule",
    "name": "My Module",
    "version": "1.0.0",
    "description": "Popis modulu",
    "author": "WP Monitor",
    "minCoreVersion": "1.0.0",
    "dependencies": [],
    "permissions": ["sites:read"],
    "configSchema": {},
    "frontend": {
        "entry": "index.tsx",
        "routes": [
            { "path": "/mymodule", "component": "MyModulePage" }
        ]
    }
}
```

3. **Implementuj ModuleInterface:**

```php
namespace WPMonitor\Modules\MyModule;

use WPMonitor\Core\{ModuleInterface, ModuleRegistry, ModuleManifest};

class MyModule implements ModuleInterface
{
    public function register(ModuleRegistry $registry): void
    {
        $registry->registerRoutes($this, function ($r) {
            $r->get('/mymodule/{siteId}', [MyController::class, 'getData']);
        });

        $registry->registerServices($this, [
            MyService::class => \DI\autowire(),
        ]);
    }

    public function getManifest(): ModuleManifest
    {
        return ModuleManifest::fromFile(__DIR__ . '/manifest.json');
    }

    public function boot(): void {}
}
```

4. **Zaregistruj modul** v `config/modules.php`:

```php
return [
    'mymodule' => [
        'enabled' => true,
        'class' => \WPMonitor\Modules\MyModule\MyModule::class,
    ],
];
```

5. **Vytvoř DB migraci** (pokud modul vyžaduje tabulky)

### 5.2 Frontend

1. **Vytvoř adresář:** `frontend/src/modules/mymodule/`

2. **Vytvoř page komponentu:**

```tsx
import { useQuery } from '@tanstack/react-query';
import { api } from '@/lib/api';

export function MyModulePage() {
  const { data } = useQuery({
    queryKey: ['mymodule'],
    queryFn: () => api.get('/mymodule'),
  });

  return <div>{/* ... */}</div>;
}
```

3. **Modul se automaticky objeví v navigaci** na základě manifest.json routes

## 6. Šifrování a bezpečnost — vývojářské poznámky

### 6.1 Pravidla pro práci s credentials

- **Nikdy** nelogovat plaintext credentials (ani do debug logu)
- **Nikdy** nevracet credentials v API response
- **Vždy** použít `sodium_memzero()` pro wipe dešifrovaných dat z paměti po použití
- **Vždy** použít AAD při šifrování (site_id + user_id)
- **Nikdy** neukládat encryption key do perzistentního úložiště (pouze session)

### 6.2 Pravidla pro WordPress client

- **Vždy** HTTPS (`verify: true` v Guzzle)
- **Vždy** timeout na požadavky (default 30s)
- **Vždy** retry s exponential backoff
- **Nikdy** neposílat credentials v URL (pouze v Authorization header)
- **Vždy** zachytit výjimky a logovat s maskovanými credentials

## 7. Build a deploy

### 7.1 Produkční build

```bash
# Frontend
cd frontend
npm run build              # Vite build → frontend/dist/

# Kopírování do public
cp -r dist/* ../backend/public/assets/

# Backend
cd ../backend
composer install --no-dev --optimize-autoloader
php bin/migrate
php bin/cache:clear
```

### 7.2 CI/CD pipeline (GitHub Actions)

```yaml
name: CI
on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [develop]

jobs:
  backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: openssl, sodium, mbstring, intl, curl, pdo_mysql
      - run: composer install --no-interaction
      - run: composer analyse
      - run: composer test

  frontend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '22'
      - run: cd frontend && npm ci
      - run: cd frontend && npm run lint
      - run: cd frontend && npm run typecheck
      - run: cd frontend && npm run test
      - run: cd frontend && npm run build
```

## 8. Debugging

### 8.1 Backend debug

- **Monolog** s `debug` level v dev prostředí (`APP_DEBUG=true`)
- Logy: `storage/logs/app.log` (přes volume mount přístupné i na hostu v `backend/storage/logs/`)
- PHP error log: `storage/logs/php-error.log`
- Slim error handler s detailním výpisem v dev režimu
- **Docker logy:** `docker compose logs -f app` (PHP-FPM stderr), `docker compose logs -f web` (Nginx access/error log)

### 8.2 Frontend debug

- **React DevTools**
- **TanStack Query DevTools** (povoleno v dev)
- Console logging přes `import.meta.env.DEV` guard

### 8.3 API debugging

```bash
# Test endpointu s curl (dev — Nginx na portu 8080)
curl -X GET http://localhost:8080/api/sites \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" | jq

# Test login
curl -X POST http://localhost:8080/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"test-passphrase"}' | jq

# Nebo přes Vite proxy (port 5173)
curl -X GET http://localhost:5173/api/sites \
  -H "Authorization: Bearer <token>" | jq
```

## 9. Checklist před release

- [ ] Všechny testy prošly (`composer test` + `npm run test`)
- [ ] PHPStan bez chyb (`composer analyse`)
- [ ] ESLint bez chyb (`npm run lint`)
- [ ] TypeScript bez chyb (`npm run typecheck`)
- [ ] Žádné TODO/FIXME v kódu
- [ ] `composer audit` bez zranitelností
- [ ] `npm audit` bez zranitelností
- [ ] DB migrace otestovány na čisté DB
- [ ] Security headers testovány (curl -I)
- [ ] HTTPS vynuceno
- [ ] .env není v git
- [ ] Production .env nastaven
- [ ] APP_KEY je silný (32+ bytes random)
- [ ] Debug mode vypnut (`APP_DEBUG=false`)
- [ ] OPcache povolen
- [ ] Logy mají správná oprávnění
- [ ] Storage adresář má správná oprávnění
