# WP Monitor — Architektura

## 1. Přehled architektury

WP Monitor používá **decoupled architekturu** — PHP backend (REST API) + React frontend (SPA). Frontend je buildován přes Vite do statických souborů, které jsou servovány z PHP `public/` adresáře.

```
┌─────────────────────────────────────────────────────────┐
│                    Uživatel (Browser)                     │
│                      React SPA (Vite)                     │
└──────────────────────────┬──────────────────────────────┘
                           │ HTTPS / REST API (JSON)
                           ▼
┌─────────────────────────────────────────────────────────┐
│                    PHP Backend (Slim 4)                   │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐ │
│  │  Router   │  │   DI     │  │ Events   │  │  Auth   │ │
│  │  (FastRoute)│ │ Container│  │Dispatcher│  │Middleware│ │
│  └────┬─────┘  └────┬─────┘  └────┬─────┘  └────┬────┘ │
│       │              │              │              │      │
│  ┌────▼──────────────▼──────────────▼──────────────▼───┐ │
│  │              Module System (Plugins)                 │ │
│  │  ┌────────┐ ┌────────┐ ┌──────────┐ ┌────────────┐ │ │
│  │  │Updates │ │Backups │ │Security  │ │SEO & Perf  │ │ │
│  │  │ Module │ │ Module │ │ Module   │ │ Module     │ │ │
│  │  └────────┘ └────────┘ └──────────┘ └────────────┘ │ │
│  └─────────────────────────────────────────────────────┘ │
│  ┌─────────────────────────────────────────────────────┐ │
│  │              Core Services                           │ │
│  │  ┌─────────┐ ┌──────────┐ ┌────────┐ ┌───────────┐ │ │
│  │  │Crypto   │ │WP Client │ │DBAL    │ │Audit Log  │ │ │
│  │  │Service  │ │(Guzzle)  │ │(Doctrine)│ │(Monolog)  │ │ │
│  │  └─────────┘ └──────────┘ └────────┘ └───────────┘ │ │
│  └─────────────────────────────────────────────────────┘ │
└──────────────────────────┬──────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────┐
│              MySQL / MariaDB (Doctrine DBAL)              │
└─────────────────────────────────────────────────────────┘
                           │
                           ▼ (Guzzle HTTP, paralelně)
┌─────────────────────────────────────────────────────────┐
│           Spravované WordPress weby (N×)                 │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────┐    │
│  │  WP #1  │  │  WP #2  │  │  WP #3  │  │  WP #N  │    │
│  │ REST API│  │ REST API│  │ REST API│  │ REST API│    │
│  └─────────┘  └─────────┘  └─────────┘  └─────────┘    │
└─────────────────────────────────────────────────────────┘
```

## 2. Backend architektura

### 2.1 Technologický stack

| Vrstva | Technologie | Důvod |
|--------|-------------|-------|
| HTTP framework | **Slim 4** | Mikroframework, minimální overhead, PSR-15 compliant |
| DI Container | **PHP-DI** | Autowiring, konfigurace přes PHP array, lazy loading |
| Router | **FastRoute** (via Slim) | Rychlý, jednoduchý, PSR-15 compatible |
| HTTP client | **Guzzle 7** | Promise-based async požadavky pro paralelní komunikaci |
| DB abstrakce | **Doctrine DBAL** | Prepared statements, schema manager, migrace |
| Event system | **Symfony EventDispatcher** | Decoupled komunikace mezi moduly |
| Logger | **Monolog** | Strukturované logování, multiple handlers |
| Validation | **Symfony Validator** nebo **Respect/Validation** | Validace vstupů na API endpointech |
| Serialization | **Symfony Serializer** | DTO ↔ JSON konverze |

### 2.2 Adresářová struktura backendu

```
backend/
├── public/
│   ├── index.php              # Front controller (Slim app bootstrap)
│   ├── assets/                # Buildované frontend soubory (Vite output)
│   └── .htaccess              # Rewrite rules (Apache)
├── src/
│   ├── Core/
│   │   ├── Kernel.php         # Application kernel — bootstrap, module loading
│   │   ├── ModuleRegistry.php # Registr a discovery modulů
│   │   ├── ModuleInterface.php# Kontrakt pro každý modul
│   │   ├── ModuleManifest.php # DTO pro module manifest
│   │   └── ContainerBuilder.php# DI container konfigurace
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── AuthMiddleware.php       # JWT + session ověření
│   │   │   ├── CsrfMiddleware.php       # CSRF token validace
│   │   │   ├── RateLimitMiddleware.php  # Rate limiting
│   │   │   ├── EncryptionMiddleware.php # Dešifrování credentials on-demand
│   │   │   └── JsonBodyParserMiddleware.php
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── SiteController.php
│   │   │   ├── DashboardController.php
│   │   │   └── SettingsController.php
│   │   └── ErrorResponse.php  # Standardizovaný JSON error formát
│   ├── Security/
│   │   ├── CryptoService.php  # AES-256-GCM šifrování/dešifrování
│   │   ├── KeyDerivation.php  # Argon2id key derivation z master hesla
│   │   ├── SessionManager.php # Session management, timeout
│   │   ├── JwtService.php     # JWT generování a validace
│   │   ├── CsrfManager.php    # CSRF token generování a validace
│   │   └── RateLimiter.php    # Rate limiting (APCu nebo DB-backed)
│   ├── Services/
│   │   ├── WordPressClient.php    # Guzzle wrapper pro WP REST API
│   │   ├── WordPressClientFactory.php # Factory s credential injection
│   │   ├── SiteManager.php        # Správa webů, testování připojení
│   │   ├── BatchProcessor.php     # Paralelní zpracování operací na N webech
│   │   └── AuditLogger.php        # Audit log service
│   ├── Storage/
│   │   ├── Connection.php     # DBAL connection factory
│   │   ├── Migrations/        # Doctrine migrations
│   │   └── Repositories/
│   │       ├── SiteRepository.php
│   │       ├── UserRepository.php
│   │       ├── CredentialRepository.php
│   │       ├── AuditLogRepository.php
│   │       └── SettingsRepository.php
│   ├── Modules/
│   │   ├── Updates/
│   │   │   ├── UpdatesModule.php      # ModuleInterface implementace
│   │   │   ├── manifest.json          # Modul manifest
│   │   │   ├── Controllers/
│   │   │   ├── Services/
│   │   │   └── Events/
│   │   ├── Backups/
│   │   ├── SecurityScan/
│   │   └── SeoPerformance/
│   └── Domain/
│       ├── Site.php           # Domain entity
│       ├── User.php
│       ├── Credential.php
│       ├── AuditLogEntry.php
│       └── ModuleConfig.php
├── config/
│   ├── settings.php           # Globální nastavení (env-based)
│   ├── container.php          # DI container definitions
│   ├── routes.php             # Core routes
│   └── modules.php            # Seznam aktivních modulů
├── migrations/                # DB migrace
├── composer.json
└── .env.example
```

### 2.3 Module systém

Každý modul implementuje `ModuleInterface` a má vlastní `manifest.json`.

#### ModuleInterface

```php
namespace WPMonitor\Core;

interface ModuleInterface
{
    public function register(ModuleRegistry $registry): void;
    public function getManifest(): ModuleManifest;
    public function boot(): void;
}
```

#### ModuleManifest (manifest.json)

```json
{
    "id": "updates",
    "name": "Updates Manager",
    "version": "1.0.0",
    "description": "Správa aktualizací WordPress jádra, pluginů a šablon",
    "author": "WP Monitor",
    "minCoreVersion": "1.0.0",
    "dependencies": [],
    "permissions": ["sites:read", "sites:write"],
    "configSchema": {
        "autoUpdateCore": {
            "type": "boolean",
            "default": false,
            "label": "Automaticky aktualizovat WordPress jádro"
        },
        "autoUpdatePlugins": {
            "type": "boolean",
            "default": false,
            "label": "Automaticky aktualizovat pluginy"
        }
    },
    "frontend": {
        "entry": "index.tsx",
        "routes": [
            { "path": "/updates", "component": "UpdatesPage" },
            { "path": "/updates/history", "component": "UpdatesHistoryPage" }
        ]
    }
}
```

#### Registrace modulu

```php
namespace WPMonitor\Modules\Updates;

use WPMonitor\Core\ModuleInterface;
use WPMonitor\Core\ModuleRegistry;
use WPMonitor\Core\ModuleManifest;

class UpdatesModule implements ModuleInterface
{
    public function register(ModuleRegistry $registry): void
    {
        $registry->registerRoutes($this, function ($r) {
            $r->get('/updates/{siteId}', [UpdatesController::class, 'getUpdates']);
            $r->post('/updates/{siteId}/execute', [UpdatesController::class, 'executeUpdates']);
            $r->post('/updates/batch', [UpdatesController::class, 'batchUpdate']);
        });

        $registry->registerServices($this, [
            UpdatesService::class => \DI\autowire(),
        ]);

        $registry->registerEventSubscribers($this, [
            SiteAddedEvent::class => [UpdatesService::class, 'onSiteAdded'],
        ]);
    }

    public function getManifest(): ModuleManifest
    {
        return ModuleManifest::fromFile(__DIR__ . '/manifest.json');
    }

    public function boot(): void
    {
        // Inicializace modulu — např. registrace cron jobs
    }
}
```

### 2.4 Request lifecycle

```
1. HTTP Request → public/index.php
2. Slim App bootstrap → Kernel::handle()
3. Middleware pipeline (v pořadí):
   a. RateLimitMiddleware     — rate limiting na IP/endpoint
   b. JsonBodyParserMiddleware — parsování JSON body
   c. AuthMiddleware          — JWT/session ověření (kromě /auth endpointů)
   d. CsrfMiddleware          — CSRF token validace (POST/PUT/DELETE)
   e. EncryptionMiddleware    — on-demand dešifrování credentials
4. Router → Controller
5. Controller → Service → WordPressClient / Repository
6. Response → JSON serialization → HTTP Response
7. AuditLogger — asynchronní logování akce
```

### 2.5 Paralelní komunikace s weby

Pro hromadné operace (batch) se používá Guzzle Promise pool:

```php
// BatchProcessor — zpracování N webů paralelně
$promises = [];
foreach ($sites as $site) {
    $client = $this->clientFactory->create($site); // šifrované credentials
    $promises[$site->getId()] = $client->getAsync('/wp-json/wp/v2/updates');
}

$results = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

foreach ($results as $siteId => $result) {
    if ($result['state'] === 'fulfilled') {
        // zpracovat výsledek
    } else {
        // logovat chybu, pokračovat s dalšími
    }
}
```

**Konfigurace:**
- Max concurrency: **10** paralelních požadavků (konfigurovatelné)
- Timeout per request: **30s** (konfigurovatelné)
- Retry: **3× s exponential backoff** (1s, 2s, 4s)

## 3. Frontend architektura

### 3.1 Technologický stack

| Vrstva | Technologie | Důvod |
|--------|-------------|-------|
| UI framework | **React 18** | Industry standard, ekosystém, TypeScript podpora |
| Build tool | **Vite 5** | Extrémně rychlý HMR, ESBuild-based |
| Jazyk | **TypeScript 5** | Type safety, IDE podpora |
| Styling | **TailwindCSS 3** | Utility-first, žádné CSS-in-JS overhead |
| UI komponenty | **shadcn/ui** | Přístupné, customizovatelné, vlastní kód (ne dependency) |
| UI bloky | **shadcnblocks.com** (premium licence) | 1816+ hotových bloků — Dashboard, Application Shell, Data Table, Chart Group, Sidebar, etc. |
| State management | **Zustand** | Minimální, bez boilerplate, perfektní pro UI state |
| Data fetching | **TanStack Query 5** | Caching, retry, optimistic updates, background refetch |
| Routing | **React Router 6** | Standard, lazy loading routes |
| Icons | **Lucide React** | Lightweight, tree-shakeable |
| Forms | **React Hook Form + Zod** | Performant, schema-validace |
| Charts | **Recharts** | Dashboard grafy (integrace s shadcn Chart Group bloky) |
| Crypto | **Web Crypto API** | Dešifrování credentials v prohlížeči (volitelné) |

### 3.2 Adresářová struktura frontendu

```
frontend/
├── src/
│   ├── main.tsx               # Entry point
│   ├── App.tsx                # Root komponenta, router setup
│   ├── modules/               # Frontend moduly (mirror backend modulů)
│   │   ├── auth/
│   │   │   ├── LoginPage.tsx
│   │   │   ├── authStore.ts
│   │   │   └── api.ts
│   │   ├── sites/
│   │   │   ├── SitesListPage.tsx
│   │   │   ├── SiteDetailPage.tsx
│   │   │   ├── AddSiteDialog.tsx
│   │   │   ├── sitesStore.ts
│   │   │   └── api.ts
│   │   ├── dashboard/
│   │   ├── updates/
│   │   ├── backups/
│   │   ├── security/
│   │   └── seo/
│   ├── components/            # Sdílené UI komponenty
│   │   ├── ui/                # shadcn/ui base komponenty (button, dialog, etc.)
│   │   ├── blocks/            # shadcnblocks.com bloky (premium)
│   │   │   ├── dashboard/     # Dashboard bloky (stat cards, overview layouts)
│   │   │   ├── app-shell/     # Application Shell bloky (sidebar, topbar, layout)
│   │   │   ├── data-table/    # Data Table bloky (s filtrováním, pagination, sort)
│   │   │   ├── chart-group/   # Chart Group bloky (kombinace grafů)
│   │   │   ├── bento/         # Bento grid bloky (dashboard widgety)
│   │   │   └── feature/       # Feature bloky (seznamy, přehledy)
│   │   ├── layout/            # Naše layout komponenty (postavené na app-shell blocks)
│   │   │   ├── AppLayout.tsx
│   │   │   ├── Sidebar.tsx
│   │   │   ├── TopBar.tsx
│   │   │   └── ModuleNav.tsx  # Dynamická navigace z aktivních modulů
│   │   ├── common/            # Naše custom komponenty (specifické pro WP Monitor)
│   │   │   ├── BatchProgressBar.tsx
│   │   │   ├── SiteSelector.tsx
│   │   │   ├── ConfirmDialog.tsx
│   │   │   ├── ErrorBoundary.tsx
│   │   │   ├── SiteStatusBadge.tsx
│   │   │   └── SecurityScoreGauge.tsx
│   │   └── charts/            # Custom grafy nad Recharts (postavené na chart-group blocks)
│   ├── lib/
│   │   ├── api.ts             # API client (fetch wrapper)
│   │   ├── crypto.ts          # Web Crypto API helpers
│   │   ├── queryClient.ts     # TanStack Query konfigurace
│   │   └── utils.ts           # Utility funkce (cn, formatters)
│   ├── stores/
│   │   ├── authStore.ts       # Auth state (Zustand)
│   │   ├── uiStore.ts         # UI state (sidebar, theme, etc.)
│   │   └── moduleStore.ts     # Aktivní moduly, konfigurace
│   ├── types/
│   │   ├── api.ts             # API response/request typy
│   │   ├── domain.ts          # Domain entity typy
│   │   └── modules.ts         # Module manifest typy
│   └── styles/
│       └── globals.css        # TailwindCSS directives + custom CSS
├── package.json
├── tsconfig.json
├── tailwind.config.ts
├── vite.config.ts
└── index.html
```

### 3.3 Modulární frontend

Frontend moduly se dynamicky objevují na základě aktivovaných backend modulů:

1. Po přihlášení frontend získá seznam aktivních modulů (`GET /api/modules`)
2. Každý modul obsahuje `frontend.routes` definici z manifest.json
3. `ModuleNav` komponenta vykreslí navigaci dynamicky
4. Route komponenty se lazy-loadují: `lazy(() => import('./modules/updates/UpdatesPage'))`
5. Moduly mohou registrovat vlastní widgety na dashboard

### 3.4 State management strategie

| Typ state | Nástroj | Příklad |
|-----------|---------|---------|
| Server state (data z API) | TanStack Query | Seznam webů, updates, logy |
| UI state (sidebar, dialogy) | Zustand | Otevřený sidebar, aktivní tab |
| Auth state | Zustand (persisted) | JWT token, user info, encryption key (in-memory only) |
| Form state | React Hook Form | Add site form, settings form |
| Local component state | useState | Dropdown open/close |

**Klíčové pravidlo:** Encryption key (derivovaný z master hesla) je uložen **pouze v paměti** (Zustand store bez persist). Po reload stránky uživatel musí znovu zadat master heslo.

## 4. Komunikační vrstva

### 4.1 WP REST API endpointy (spotřebovávané WP Monitorem)

| WP REST endpoint | Účel | Modul |
|------------------|------|-------|
| `GET /wp-json/wp/v2/` | Site info, verze WordPress | Core |
| `GET /wp-json/wp/v2/plugins` | Seznam pluginů + verze | Updates |
| `PUT /wp-json/wp/v2/plugins/{slug}` | Aktualizace pluginu | Updates |
| `DELETE /wp-json/wp/v2/plugins/{slug}` | Smazání pluginu | Updates |
| `GET /wp-json/wp/v2/themes` | Seznam šablon + verze | Updates |
| `PUT /wp-json/wp/v2/themes/{slug}` | Aktualizace šablony | Updates |
| `GET /wp-json/wp/v2/users` | Seznam uživatelů | Security |
| `GET /wp-json/wp/v2/users/me` | Ověření připojení | Core |
| `GET /wp-json/wp/v2/settings` | Nastavení webu | Core, SEO |

### 4.2 Vlastní MU-plugin endpointy (volitelné)

Pokud je na spravovaném webu nainstalován WP Monitor MU-plugin, zpřístupní rozšířené endpointy:

| Endpoint | Účel | Modul |
|----------|------|-------|
| `GET /wp-json/wp-monitor/v1/server-info` | PHP verze, memory, disk usage | Security, Monitoring |
| `GET /wp-json/wp-monitor/v1/db-info` | DB velikost, tabulky, optimalizace | Backups, Performance |
| `POST /wp-json/wp-monitor/v1/backup/db` | Vytvoření DB dumpu | Backups |
| `POST /wp-json/wp-monitor/v1/backup/files` | Vytvoření file archivu | Backups |
| `GET /wp-json/wp-monitor/v1/security/scan` | Malware scan, file integrity | Security |
| `GET /wp-json/wp-monitor/v1/performance/metrics` | Core Web Vitals, load time | SEO & Performance |
| `POST /wp-json/wp-monitor/v1/restore` | Obnova ze zálohy | Backups |

MU-plugin komunikuje přes **signed requests** (HMAC-SHA256) — WP Monitor podepíše každý požadavek, MU-plugin ověří podpis proti sdílenému klíči.

### 4.3 Error handling

Standardizovaný JSON error formát:

```json
{
    "error": {
        "code": "SITE_UNREACHABLE",
        "message": "Nelze se připojit k webu example.com",
        "details": {
            "siteId": 42,
            "url": "https://example.com",
            "httpStatus": 0,
            "reason": "connection_timeout"
        },
        "timestamp": "2026-08-07T05:30:00Z",
        "requestId": "req_abc123"
    }
}
```

## 5. Deployment architektura

### 5.1 Produkční nasazení (PHP hosting)

```
server/
├── public/              ← Document Root (Apache/Nginx)
│   ├── index.php        ← Slim front controller
│   ├── assets/          ← Vite build output (JS, CSS, images)
│   └── .htaccess
├── src/                 ← PHP zdrojáky (mimo document root pokud možno)
├── config/
├── vendor/              ← Composer dependencies
├── storage/             ← Zálohy, logy, cache (mimo document root)
│   ├── backups/
│   ├── logs/
│   └── cache/
├── .env                 ← Produkční konfigurace (mimo git)
└── composer.json
```

**Nginx konfigurace (příklad):**

```nginx
server {
    listen 443 ssl http2;
    server_name wp-monitor.example.com;

    root /var/www/wp-monitor/public;
    index index.php;

    # Statické soubory (frontend assets)
    location /assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }

    # Vše ostatní → PHP front controller (SPA routing)
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Zakázat přístup k citlivým souborům
    location ~ /\.(env|git) { deny all; }
    location ~ /storage/ { deny all; }
}
```

### 5.2 Build proces

```bash
# 1. Frontend build
cd frontend && npm run build
# → output: frontend/dist/

# 2. Kopírování buildu do public/assets
cp -r frontend/dist/* backend/public/assets/

# 3. Backend dependencies
cd backend && composer install --no-dev --optimize-autoloader

# 4. DB migrace
php bin/migrate

# 5. Cache clear
php bin/cache:clear
```

## 6. Konfigurace

### 6.1 Environment variables (.env)

```ini
# App
APP_ENV=production
APP_DEBUG=false
APP_URL=https://wp-monitor.example.com
APP_KEY=base64:...              # APP encryption key (pro JWT signing)

# Database
DB_HOST=localhost
DB_NAME=wp_monitor
DB_USER=wp_monitor
DB_PASSWORD=secret
DB_CHARSET=utf8mb4

# Security
MASTER_PASSWORD_SALT=...        # Salt pro key derivation (per-instalace)
SESSION_TIMEOUT=900              # 15 minut (v sekundách)
JWT_TTL=3600                     # 1 hodina
RATE_LIMIT_LOGIN=5               # Max 5 pokusů za 15 min
ENCRYPTION_ALGORITHM=aes-256-gcm
KEY_DERIVATION=argon2id

# WordPress Client
WP_CLIENT_TIMEOUT=30             # sekundy
WP_CLIENT_MAX_CONCURRENCY=10
WP_CLIENT_RETRY_COUNT=3

# Storage
STORAGE_PATH=/var/www/wp-monitor/storage
BACKUP_RETENTION_DAYS=30

# Logging
LOG_LEVEL=info
LOG_PATH=/var/www/wp-monitor/storage/logs
```

### 6.2 Konfigurace modulů

Moduly jsou konfigurovatelné přes `config/modules.php`:

```php
return [
    'updates' => [
        'enabled' => true,
        'autoUpdateCore' => false,
        'autoUpdatePlugins' => false,
        'excludedPlugins' => ['akismet', 'hello.php'],
    ],
    'backups' => [
        'enabled' => true,
        'schedule' => 'daily',
        'retention' => 30,
        'storage' => 'local',  // local | s3 | sftp
    ],
    'security' => [
        'enabled' => true,
        'uptimeCheckInterval' => 300,  // 5 minut
        'sslCheckInterval' => 3600,    // 1 hodina
    ],
    'seo' => [
        'enabled' => true,
    ],
];
```
