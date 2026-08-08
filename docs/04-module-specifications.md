# WP Monitor — Specifikace modulů

## Obsah
1. [Core moduly](#1-core-moduly)
   - 1.1 [Auth](#11-auth)
   - 1.2 [Sites](#12-sites)
   - 1.3 [Dashboard](#13-dashboard)
   - 1.4 [Activity Log](#14-activity-log)
   - 1.5 [Settings](#15-settings)
2. [Funkční moduly](#2-funkční-moduly)
   - 2.1 [Updates](#21-updates)
   - 2.2 [Backups](#22-backups)
   - 2.3 [Security & Monitoring](#23-security--monitoring)
   - 2.4 [SEO & Performance](#24-seo--performance)
3. [Budoucí moduly](#3-budoucí-moduly)

---

## 1. Core moduly

### 1.1 Auth

#### 1.1.1 Přehled

Modul Auth zodpovídá za autentizaci uživatele, správu session, CSRF ochranu a rate limiting. Je povinný a nelze ho deaktivovat.

#### 1.1.2 Funkční požadavky

| ID | Požadavek | Priorita |
|----|-----------|----------|
| AUTH-01 | Uživatel se přihlásí pomocí master hesla | P0 |
| AUTH-02 | Systém derivuje encryption key z master hesla (Argon2id) | P0 |
| AUTH-03 | Systém ověří heslo dešifrováním verification tokenu | P0 |
| AUTH-04 | Po úspěšném loginu se vydá JWT token + CSRF token | P0 |
| AUTH-05 | Session timeout po nečinnosti (default 15 min, konfigurovatelné) | P0 |
| AUTH-06 | Sliding session — při aktivitě se timeout prodlužuje | P1 |
| AUTH-07 | Rate limiting na login (5 pokusů / 15 min) | P0 |
| AUTH-08 | Account lockout po 10 neúspěšných pokusech (30 min) | P0 |
| AUTH-09 | Změna master hesla (rotace všech credentials) | P0 |
| AUTH-10 | Logout — zničení session + revokace JWT | P0 |
| AUTH-11 | Multi-user podpora (admin, manager, viewer) | P1 |
| AUTH-12 | Per-site oprávnění (can_view, can_manage) | P1 |

#### 1.1.3 API endpointy

| Metoda | Cesta | Popis | Role |
|--------|-------|-------|------|
| POST | `/api/auth/login` | Přihlášení (master heslo) | veřejný |
| POST | `/api/auth/logout` | Odhlášení | auth |
| POST | `/api/auth/verify` | Ověření JWT platnosti | auth |
| POST | `/api/auth/password/change` | Změna master hesla | auth |
| GET | `/api/auth/session` | Info o aktuální session | auth |
| GET | `/api/users` | Seznam uživatelů | admin |
| POST | `/api/users` | Vytvoření uživatele | admin |
| PUT | `/api/users/{id}` | Úprava uživatele | admin |
| DELETE | `/api/users/{id}` | Smazání uživatele | admin |
| PUT | `/api/users/{id}/permissions` | Nastavení per-site oprávnění | admin |

#### 1.1.4 Login request/response

```json
// POST /api/auth/login
// Request
{
    "username": "admin",
    "password": "my-master-passphrase"
}

// Response 200
{
    "token": "eyJhbGci...",
    "csrfToken": "a1b2c3d4...",
    "expiresIn": 3600,
    "user": {
        "id": 1,
        "username": "admin",
        "role": "admin"
    }
}

// Response 401
{
    "error": {
        "code": "AUTH_INVALID_CREDENTIALS",
        "message": "Neplatné přihlašovací údaje"
    }
}

// Response 429
{
    "error": {
        "code": "RATE_LIMIT_EXCEEDED",
        "message": "Příliš mnoho pokusů. Zkuste to za 14 minut.",
        "retryAfter": 840
    }
}
```

#### 1.1.5 Frontend komponenty

- **LoginPage** — formulář pro přihlášení (username + master heslo)
- **PasswordChangeDialog** — dialog pro změnu master hesla (staré + nové + potvrzení)
- **UserManagementPage** — seznam uživatelů, přidávání, úprava rolí
- **PermissionMatrix** — matice per-site oprávnění pro uživatele

---

### 1.2 Sites

#### 1.2.1 Přehled

Modul Sites zodpovídá za správu WordPress webů — přidávání, úpravy, mazání, testování připojení, seskupování a tagování.

#### 1.2.2 Funkční požadavky

| ID | Požadavek | Priorita |
|----|-----------|----------|
| SITE-01 | Přidání webu (URL, název, WP uživatel, application password) | P0 |
| SITE-02 | Testování připojení k webu před uložením (GET /wp-json/wp/v2/users/me) | P0 |
| SITE-03 | HTTPS enforcement — HTTP URL odmítnuta | P0 |
| SITE-04 | Credentials šifrovány AES-256-GCM před uložením | P0 |
| SITE-05 | Seznam webů s pagination, filtrováním, řazením | P0 |
| SITE-06 | Detail webu — základní info, zdraví, moduly | P0 |
| SITE-07 | Úprava webu (název, credentials, nastavení) | P0 |
| SITE-08 | Smazání webu (s potvrzením, včetně credentials a logů) | P0 |
| SITE-09 | Seskupování webů do skupin (groups) | P1 |
| SITE-10 | Tagování webů (volné tagy) | P1 |
| SITE-11 | Hromadný import webů (CSV import) | P2 |
| SITE-12 | Detekce WP verze, pluginů a šablon při přidání | P1 |
| SITE-13 | Status indicator (online/offline/degraded) | P0 |
| SITE-14 | Poslední kontrola stavu (timestamp + výsledek) | P0 |

#### 1.2.3 Site entity

```php
class Site
{
    public int $id;
    public string $name;              // Uživatelský název (např. "Klient - Firma s.r.o.")
    public string $url;               // https://example.com
    public string $wpUsername;        // WP uživatel pro REST API
    public ?string $wpVersion;        // Detekovaná verze WP
    public ?string $phpVersion;       // Detekovaná verze PHP (přes MU-plugin)
    public bool $muPluginInstalled;   // Je MU-plugin nainstalován?
    public string $status;            // online | offline | degraded | unknown
    public ?int $httpStatus;          // Poslední HTTP status
    public ?string $lastCheckedAt;    // ISO timestamp poslední kontroly
    public ?string $lastError;        // Poslední chyba (pokud nějaká)
    public array $groups;             // Skupiny, do kterých web patří
    public array $tags;               // Tagy
    public array $metadata;           // Dodatečná metadata (JSON)
    public string $createdAt;
    public string $updatedAt;
}
```

#### 1.2.4 API endpointy

| Metoda | Cesta | Popis | Role |
|--------|-------|-------|------|
| GET | `/api/sites` | Seznam webů (pagination, filter, sort) | auth |
| POST | `/api/sites` | Přidání webu | manager+ |
| GET | `/api/sites/{id}` | Detail webu | auth |
| PUT | `/api/sites/{id}` | Úprava webu | manager+ |
| DELETE | `/api/sites/{id}` | Smazání webu | manager+ |
| POST | `/api/sites/{id}/test` | Test připojení | auth |
| POST | `/api/sites/{id}/refresh` | Refresh informací (WP verze, pluginy) | auth |
| GET | `/api/sites/groups` | Seznam skupin | auth |
| POST | `/api/sites/groups` | Vytvoření skupiny | manager+ |
| PUT | `/api/sites/groups/{id}` | Úprava skupiny | manager+ |
| DELETE | `/api/sites/groups/{id}` | Smazání skupiny | manager+ |
| POST | `/api/sites/import` | CSV import webů | admin |
| GET | `/api/sites/{id}/credentials` | Zjištění stavu credentials (ne hodnotu!) | admin |

#### 1.2.5 Frontend komponenty

- **SitesListPage** — tabulkový přehled webů s filtrováním a hromadným výběrem
- **AddSiteDialog** — formulář pro přidání webu (URL, název, credentials, test připojení)
- **SiteDetailPage** — detail webu s taby (Overview, Updates, Backups, Security, SEO, Logs)
- **SiteEditDialog** — úprava webu
- **SiteGroupManager** — správa skupin a tagů
- **SiteImportDialog** — CSV import s preview
- **SiteStatusBadge** — barevný badge (zelený = online, červený = offline, žlutý = degraded)

---

### 1.3 Dashboard

#### 1.3.1 Přehled

Hlavní dashboard s agregovanými metrikami napříč všemi spravovanými weby. Zobrazuje klíčové informace a upozornění.

#### 1.3.2 Funkční požadavky

| ID | Požadavek | Priorita |
|----|-----------|----------|
| DASH-01 | Přehledná karta s celkovým počtem webů a statusy | P0 |
| DASH-02 | Karta s čekajícími aktualizacemi (počet webů s dostupnými updaty) | P0 |
| DASH-03 | Karta s bezpečnostními upozorněními (počet webů s problémy) | P0 |
| DASH-04 | Karta s uptime statistikou (průměrný uptime za 24h) | P1 |
| DASH-05 | Graf aktivit za posledních 7 dní (updates, backups, scans) | P1 |
| DASH-06 | Seznam nedávných akcí (posledních 10) | P0 |
| DASH-07 | Seznam webů vyžadujících pozornost (offline, security issues) | P0 |
| DASH-08 | Dynamické widgety z aktivních modulů | P1 |
| DASH-09 | Filtrování dashboardu podle skupiny/tagu | P2 |
| DASH-10 | Rychlé akce z dashboardu (spustit update na vybraných webech) | P1 |

#### 1.3.3 Dashboard widgety

Moduly mohou registrovat vlastní widgety:

```php
// Modul registrace widgetu
$registry->registerDashboardWidget('updates', [
    'id' => 'pending-updates',
    'title' => 'Čekající aktualizace',
    'size' => 'medium',        // small | medium | large | full
    'position' => 2,           // pořadí v layoutu
    'component' => 'PendingUpdatesWidget',  // frontend komponenta
    'refreshInterval' => 60,   // sekundy (0 = no auto-refresh)
    'permissions' => ['sites:read'],
]);
```

#### 1.3.4 API endpointy

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/api/dashboard/overview` | Agregované metriky |
| GET | `/api/dashboard/recent-activity` | Poslední akce |
| GET | `/api/dashboard/attention` | Weby vyžadující pozornost |
| GET | `/api/dashboard/widgets` | Seznam aktivních widgetů |

#### 1.3.5 Frontend komponenty

- **DashboardPage** — hlavní layout (shadcnblocks: Dashboard + Bento bloky)
- **StatCard** — karta s jednou metrikou (shadcnblocks: Dashboard stat card bloky)
- **ActivityFeed** — seznam nedávných akcí (shadcnblocks: Data Table / List Panel bloky)
- **AttentionList** — weby vyžadující pozornost (shadcnblocks: Data Table bloky)
- **ActivityChart** — graf aktivit (shadcnblocks: Chart Group bloky + Recharts)

---

### 1.4 Activity Log

#### 1.4.1 Přehled

Audit log všech akcí provedených v systému. Append-only, nelze mazat ani upravovat.

#### 1.4.2 Funkční požadavky

| ID | Požadavek | Priorita |
|----|-----------|----------|
| LOG-01 | Zobrazení logů s pagination | P0 |
| LOG-02 | Filtrování podle uživatele, webu, akce, modulu, data | P0 |
| LOG-03 | Full-text vyhledávání v logech | P1 |
| LOG-04 | Export logů (CSV, JSON) | P1 |
| LOG-05 | Detail log záznamu (všechny informace včetně details JSON) | P0 |
| LOG-06 | Retence logů (konfigurovatelné, default 365 dní) | P1 |
| LOG-07 | Logování všech akcí dle specifikace v security-model.md | P0 |
| LOG-08 | Append-only — žádné UPDATE/DELETE z aplikace | P0 |

#### 1.4.3 API endpointy

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/api/audit-log` | Seznam logů (pagination, filter) |
| GET | `/api/audit-log/{id}` | Detail log záznamu |
| GET | `/api/audit-log/export` | Export (CSV, JSON) |

#### 1.4.4 Frontend komponenty

- **ActivityLogPage** — tabulkový přehled s filtry (shadcnblocks: Data Table bloky)
- **LogDetailDialog** — detail záznamu (shadcn/ui: Dialog + Tabs)
- **LogFilters** — panel filtrů (shadcnblocks: Data Table filter bloky)

---

### 1.5 Settings

#### 1.5.1 Přehled

Globální nastavení aplikace, konfigurace modulů, správa klíčů.

#### 1.5.2 Funkční požadavky

| ID | Požadavek | Priorita |
|----|-----------|----------|
| SET-01 | Zobrazení a úprava globálního nastavení | P0 |
| SET-02 | Aktivace/deaktivace modulů | P0 |
| SET-03 | Konfigurace modulů (per-module nastavení) | P0 |
| SET-04 | Nastavení session timeout | P1 |
| SET-05 | Nastavení rate limit parametrů | P1 |
| SET-06 | Nastavení WP client parametrů (timeout, concurrency, retry) | P1 |
| SET-07 | Nastavení zálohování (retence, storage backend) | P1 |
| SET-08 | Test konfigurace (např. test S3 připojení) | P2 |
| SET-09 | Zobrazení systémových informací (PHP verze, DB verze, moduly) | P1 |
| SET-10 | Backup a restore konfigurace | P2 |

#### 1.5.3 API endpointy

| Metoda | Cesta | Popis | Role |
|--------|-------|-------|------|
| GET | `/api/settings` | Globální nastavení | admin |
| PUT | `/api/settings` | Úprava nastavení | admin |
| GET | `/api/modules` | Seznam modulů + stav | admin |
| PUT | `/api/modules/{id}/enable` | Aktivace modulu | admin |
| PUT | `/api/modules/{id}/disable` | Deaktivace modulu | admin |
| GET | `/api/modules/{id}/config` | Konfigurace modulu | admin |
| PUT | `/api/modules/{id}/config` | Úprava konfigurace modulu | admin |
| GET | `/api/system/info` | Systémové informace | admin |

#### 1.5.4 Frontend komponenty

- **SettingsPage** — tabbed layout (shadcnblocks: Application Shell + Tabs bloky)
- **ModuleList** — seznam modulů s přepínači (shadcnblocks: Feature bloky + shadcn/ui Switch)
- **ModuleConfigForm** — dynamický formulář (shadcn/ui Form + React Hook Form + Zod)
- **SystemInfoPanel** — systémové informace (shadcnblocks: Bento bloky)

---

## 2. Funkční moduly

### 2.1 Updates

#### 2.1.1 Přehled

Modul Updates zodpovídá za správu aktualizací WordPress jádra, pluginů a šablon na spravovaných webech. Podporuje jednotlivé i hromadné aktualizace, rollback a auto-update pravidla.

#### 2.1.2 Funkční požadavky

| ID | Požadavek | Priorita |
|----|-----------|----------|
| UPD-01 | Zobrazení dostupných aktualizací pro každý web (core, plugins, themes) | P0 |
| UPD-02 | Aktualizace jednoho pluginu na jednom webu | P0 |
| UPD-03 | Aktualizace všech pluginů na jednom webu | P0 |
| UPD-04 | Aktualizace WordPress jádra na jednom webu | P0 |
| UPD-05 | Aktualizace šablony na jednom webu | P0 |
| UPD-06 | Hromadná aktualizace — výběr N webů + typ (core/plugins/themes/all) | P0 |
| UPD-07 | Hromadná aktualizace — paralelní zpracování s progress barem | P0 |
| UPD-08 | Rollback pluginu na předchozí verzi | P1 |
| UPD-09 | Rollback WordPress jádra na předchozí verzi | P1 |
| UPD-10 | Auto-update pravidla (per-site nebo globální) | P1 |
| UPD-11 | Vyloučení pluginů z aktualizací (exclusion list) | P1 |
| UPD-12 | Historie aktualizací (kdy, co, kdo, výsledek) | P0 |
| UPD-13 | Notifikace o dostupných aktualizacích (e-mail, dashboard) | P2 |
| UPD-14 | Scheduled updates — plánované aktualizace (např. každou neděli 3:00) | P2 |
| UPD-15 | Pre-update backup — vytvoření zálohy před aktualizací | P1 |
| UPD-16 | Dry run — zobrazení co by se aktualizovalo bez provedení | P1 |
| UPD-17 | Bulk select — výběr konkrétních pluginů napříč weby pro hromadný update | P1 |

#### 2.1.3 Update typy

| Typ | WP REST endpoint | Poznámka |
|-----|------------------|----------|
| Core | `GET /wp-json/wp/v2/` → verze; update přes MU-plugin nebo WP admin API | Core update není přímo v REST API — vyžaduje MU-plugin nebo workaround |
| Plugin | `GET /wp-json/wp/v2/plugins`, `PUT /wp-json/wp/v2/plugins/{slug}` | Dostupné od WP 5.5+ |
| Theme | `GET /wp-json/wp/v2/themes`, `PUT /wp-json/wp/v2/themes/{stylesheet}` | Dostupné od WP 5.5+ |

**Poznámka k core update:** WordPress REST API nativně nepodporuje core update. Možnosti:
1. **MU-plugin** — vlastní endpoint `/wp-json/wp-monitor/v1/core/update` (doporučeno)
2. **WP-CLI přes SSH** — pokud je SSH přístup dostupný (mimo rozsah REST API)
3. **Simulace přes admin** — trigger `wp_update_core()` přes custom endpoint

#### 2.1.4 Hromadná aktualizace — flow

```
1. Uživatel vybere weby (nebo skupinu) + typ aktualizace
2. Frontend zobrazí dry-run preview (co se aktualizuje na kterém webu)
3. Uživatel potvrdí
4. Backend spustí BatchProcessor:
   a. Pro každý web paralelně (max 10 concurrent):
      i.   (Volitelně) Vytvoření pre-update backup
      ii.  Získání seznamu dostupných aktualizací
      iii. Provedení aktualizací (core → plugins → themes)
      iv.  Ověření úspěchu (GET /wp-json/wp/v2/ → nová verze)
      v.   Logování výsledku
   b. Progress report přes WebSocket nebo polling
5. Zobrazení výsledku (success/partial/failed per site)
6. Audit log záznam
```

#### 2.1.5 Auto-update pravidla

```php
// Konfigurace auto-update (per-site nebo globální)
[
    'core' => 'minor',          // off | minor | major
    'plugins' => 'all',         // off | all | selected
    'themes' => 'off',          // off | all | selected
    'selectedPlugins' => ['yoast-seo', 'wordfence'],
    'excludedPlugins' => ['custom-plugin'],
    'schedule' => '0 3 * * 0',  // cron expression (každá neděle 3:00)
    'preUpdateBackup' => true,
]
```

#### 2.1.6 API endpointy

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/api/updates/{siteId}` | Dostupné aktualizace pro web |
| GET | `/api/updates` | Agregované aktualizace napříč weby |
| POST | `/api/updates/{siteId}/execute` | Provedení aktualizace na jednom webu |
| POST | `/api/updates/batch` | Hromadná aktualizace |
| POST | `/api/updates/batch/preview` | Dry-run preview hromadné aktualizace |
| GET | `/api/updates/{siteId}/history` | Historie aktualizací webu |
| GET | `/api/updates/history` | Globální historie aktualizací |
| POST | `/api/updates/{siteId}/rollback/{type}/{slug}` | Rollback na předchozí verzi |
| GET | `/api/updates/rules` | Auto-update pravidla |
| PUT | `/api/updates/rules/{siteId}` | Úprava pravidel pro web |
| PUT | `/api/updates/rules/global` | Úprava globálních pravidel |

#### 2.1.7 Batch update request

```json
// POST /api/updates/batch
{
    "siteIds": [1, 2, 3, 5, 8],
    "type": "all",              // core | plugins | themes | all
    "plugins": null,            // null = všechny; ["yoast-seo","wordfence"] = konkrétní
    "preBackup": true,          // vytvořit zálohu před aktualizací
    "dryRun": false             // true = pouze preview
}

// Response 202 (async — vrací batch ID)
{
    "batchId": "batch_abc123",
    "status": "processing",
    "totalSites": 5,
    "progressUrl": "/api/updates/batch/batch_abc123/status"
}

// GET /api/updates/batch/batch_abc123/status
{
    "batchId": "batch_abc123",
    "status": "completed",       // processing | completed | failed
    "progress": {
        "total": 5,
        "completed": 5,
        "success": 4,
        "failed": 1,
        "pending": 0
    },
    "results": [
        {
            "siteId": 1,
            "siteName": "Klient A",
            "status": "success",
            "updates": [
                { "type": "plugin", "slug": "yoast-seo", "from": "20.1", "to": "20.3" },
                { "type": "core", "slug": "wordpress", "from": "6.4.2", "to": "6.4.3" }
            ]
        },
        {
            "siteId": 5,
            "siteName": "Klient D",
            "status": "failed",
            "error": "Connection timeout",
            "updates": []
        }
    ]
}
```

#### 2.1.8 Frontend komponenty

- **UpdatesOverviewPage** — agregovaný přehled (shadcnblocks: Dashboard + Data Table bloky)
- **SiteUpdatesTab** — detail aktualizací pro web (shadcnblocks: Data Table + Tabs bloky)
- **BatchUpdateDialog** — průvodce hromadnou aktualizací (shadcn/ui: Dialog + Stepper + Checkbox)
- **BatchProgressBar** — real-time progress (shadcn/ui: Progress + custom polling hook)
- **BatchResultsReport** — výsledky hromadné operace (shadcnblocks: Data Table + Status badges)
- **UpdateHistoryPage** — historie s filtry (shadcnblocks: Data Table bloky)
- **AutoUpdateRulesForm** — formulář pravidel (shadcn/ui: Form + React Hook Form + Zod)
- **RollbackDialog** — dialog rollback (shadcn/ui: Dialog + Select)

---

### 2.2 Backups

#### 2.2.1 Přehled

Modul Backups zodpovídá za zálohování databází a souborů spravovaných WordPress webů. Podporuje plánované i manuální zálohy, více úložišť a obnovu.

#### 2.2.2 Funkční požadavky

| ID | Požadavek | Priorita |
|----|-----------|----------|
| BAK-01 | Manuální záloha DB jednoho webu | P0 |
| BAK-02 | Manuální záloha souborů jednoho webu | P0 |
| BAK-03 | Manuální kompletní záloha (DB + soubory) | P0 |
| BAK-04 | Hromadná záloha (výběr N webů) | P0 |
| BAK-05 | Plánované zálohy (cron expression) | P1 |
| BAK-06 | Konfigurace per-site (co zálohovat, jak často, retence) | P1 |
| BAK-07 | Seznam záloh s metadata (velikost, datum, typ, stav) | P0 |
| BAK-08 | Stažení zálohy | P0 |
| BAK-09 | Obnova ze zálohy (DB a/nebo soubory) | P0 |
| BAK-10 | Smazání zálohy (s potvrzením) | P0 |
| BAK-11 | Retence policy (auto-delete starých záloh) | P1 |
| BAK-12 | Šifrování záloh (AES-256-GCM) | P0 |
| BAK-13 | Storage backends: local, S3, SFTP | P1 |
| BAK-14 | Komprese záloh (gzip, zstd) | P1 |
| BAK-15 | Inkrementální zálohy (pouze změny) | P2 |
| BAK-16 | Notifikace o výsledku zálohy (e-mail) | P2 |
| BAK-17 | Velikost zálohy v reportu | P1 |
| BAK-18 | Disk space monitoring pro lokální úložiště | P1 |
| BAK-19 | Excluded files (např. cache, tmp) | P1 |
| BAK-20 | Pre-update backup integrace s Updates modulem | P1 |

#### 2.2.3 Zálohovací proces

```
1. Iniciace (manuální nebo scheduled)
2. WP Monitor → MU-plugin: POST /wp-json/wp-monitor/v1/backup/db
   a. MU-plugin: mysqldump nebo PHP-based dump
   b. Komprese (gzip/zstd)
   c. Šifrování (AES-256-GCM s backup key)
   d. Upload na WP Monitor storage (nebo přímo na S3/SFTP)
3. WP Monitor → MU-plugin: POST /wp-json/wp-monitor/v1/backup/files
   a. MU-plugin: archivace wp-content (zip)
   b. Excluded: cache/*, uploads/tmp/*, etc.
   c. Komprese + šifrování
   d. Upload na storage
4. Metadata uložena v DB (backup záznam)
5. Retence check — smazání starých záloh
6. Audit log
```

**Bez MU-pluginu:** Pokud MU-plugin není nainstalován, zálohy jsou omezené:
- DB dump: nelze (vyžaduje přístup k MySQL)
- Soubory: nelze (vyžaduje filesystem přístup)
- Alternativa: instrukce pro uživatele + ruční upload zálohy

#### 2.2.4 Storage konfigurace

```php
// Local storage
[
    'backend' => 'local',
    'path' => '/var/www/wp-monitor/storage/backups',
    'maxDiskUsage' => '50GB',     // alert při překročení
]

// S3-compatible storage
[
    'backend' => 's3',
    'endpoint' => 'https://s3.amazonaws.com',
    'bucket' => 'wp-monitor-backups',
    'region' => 'eu-central-1',
    'accessKey' => 'encrypted:...',  // šifrované
    'secretKey' => 'encrypted:...',  // šifrované
    'path' => 'backups/',
    'encryption' => 'sse-aes256',    // server-side encryption navíc
]

// SFTP storage
[
    'backend' => 'sftp',
    'host' => 'backup.example.com',
    'port' => 22,
    'username' => 'backup',
    'password' => 'encrypted:...',   // nebo privateKey
    'privateKey' => 'encrypted:...',
    'path' => '/home/backup/wp-monitor/',
]
```

#### 2.2.5 Retence policy

```php
[
    'keepDaily' => 7,       // 7 denních záloh
    'keepWeekly' => 4,      // 4 týdenní zálohy
    'keepMonthly' => 12,    // 12 měsíčních záloh
    'maxAge' => 365,        // max 365 dní
    'maxSize' => '100GB',   // max celková velikost
]
```

#### 2.2.6 API endpointy

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/api/backups` | Seznam záloh (filter by site, date, type) |
| GET | `/api/backups/{siteId}` | Zálohy pro konkrétní web |
| POST | `/api/backups/{siteId}/create` | Vytvoření zálohy |
| POST | `/api/backups/batch` | Hromadná záloha |
| GET | `/api/backups/{id}` | Detail zálohy |
| GET | `/api/backups/{id}/download` | Stažení zálohy |
| POST | `/api/backups/{id}/restore` | Obnova ze zálohy |
| DELETE | `/api/backups/{id}` | Smazání zálohy |
| GET | `/api/backups/schedules` | Seznam plánovaných záloh |
| POST | `/api/backups/schedules` | Vytvoření plánu |
| PUT | `/api/backups/schedules/{id}` | Úprava plánu |
| DELETE | `/api/backups/schedules/{id}` | Smazání plánu |
| GET | `/api/backups/storage/info` | Info o úložišti (volné místo, počet záloh) |
| POST | `/api/backups/storage/test` | Test připojení k úložišti |

#### 2.2.7 Frontend komponenty

- **BackupsOverviewPage** — přehled záloh (shadcnblocks: Data Table + Dashboard bloky)
- **SiteBackupsTab** — zálohy pro web (shadcnblocks: Data Table bloky)
- **CreateBackupDialog** — výběr typu zálohy (shadcn/ui: Dialog + RadioGroup + Select)
- **BatchBackupDialog** — hromadná záloha (shadcn/ui: Dialog + SiteSelector custom)
- **BackupList** — tabulka záloh s akcemi (shadcnblocks: Data Table + Dropdown Menu)
- **RestoreDialog** — průvodce obnovou (shadcn/ui: Dialog + Alert + Checkbox potvrzení)
- **BackupScheduleForm** — plánování (shadcn/ui: Form + React Hook Form + Zod)
- **StorageInfoCard** — info o úložišti (shadcnblocks: Bento + Chart Group bloky)

---

### 2.3 Security & Monitoring

#### 2.3.1 Přehled

Modul Security & Monitoring zodpovídá za průběžný monitoring zdraví spravovaných webů, security scanning, kontrolu SSL certifikátů, detekci zranitelností a audit bezpečnostní konfigurace.

#### 2.3.2 Funkční požadavky

**Monitoring:**

| ID | Požadavek | Priorita |
|----|-----------|----------|
| MON-01 | Uptime monitoring (HTTP check v konfigurovatelném intervalu) | P0 |
| MON-02 | Default interval: 5 minut (konfigurovatelné per-site) | P0 |
| MON-03 | Status: up / down / degraded (deggraded = pomalá odpověď > 5s) | P0 |
| MON-04 | Uptime statistika (24h, 7d, 30d, 90d) | P0 |
| MON-05 | Response time monitoring (průměr, p95, p99) | P1 |
| MON-06 | Downtime historie s incidenty | P0 |
| MON-07 | Notifikace při downtime (e-mail, webhook) | P1 |
| MON-08 | SSL certifikát monitoring (expiry check) | P0 |
| MON-09 | Notifikace před expirací SSL (30, 14, 7, 1 den) | P1 |
| MON-10 | DNS monitoring (A record, MX, TXT) | P2 |
| MON-11 | Blacklist check (Google Safe Browsing) | P2 |

**Security scanning:**

| ID | Požadavek | Priorita |
|----|-----------|----------|
| SEC-01 | Malware scan ( kontrola souborů oproti signaturám) | P1 |
| SEC-02 | File integrity monitoring (hash souborů, detekce změn) | P1 |
| SEC-03 | Vulnerability check — WordPress core (CVE databáze) | P0 |
| SEC-04 | Vulnerability check — pluginy (WPScan / Patchstack API) | P0 |
| SEC-05 | Vulnerability check — šablony | P1 |
| SEC-06 | Audit WP konfigurace (debug mode, file editing, etc.) | P1 |
| SEC-07 | Kontrola uživatelských účtů (zbyteční admin účty, slabá hesla) | P1 |
| SEC-08 | 2FA audit (kteří admin účty nemají 2FA) | P2 |
| SEC-09 | Kontrola WordPress verze (zastaralá verze = riziko) | P0 |
| SEC-10 | Security score per web (0-100) | P1 |
| SEC-11 | Aggregated security score napříč weby | P1 |
| SEC-12 | Doporučení pro nápravu (actionable recommendations) | P1 |
| SEC-13 | Scheduled security scans (cron) | P2 |
| SEC-14 | Security report (PDF export) | P2 |

**Firewall & hardening:**

| ID | Požadavek | Priorita |
|----|-----------|----------|
| FW-01 | Přehled aktivních security pluginů na webech | P1 |
| FW-02 | Kontrola wp-config.php (zda jsou bezpečnostní klíves nastaveny) | P2 |
| FW-03 | Kontrola .htaccess / nginx konfigurace | P2 |
| FW-04 | Kontrola file permissions (wp-config.php by měl být 600/640) | P2 |
| FW-05 | Kontrola directory listing (zda je vypnutý) | P2 |
| FW-06 | Kontrola XML-RPC status | P1 |
| FW-07 | Kontrola REST API přístupnosti | P1 |
| FW-08 | Kontrola wp-cron.php status | P2 |

#### 2.3.3 Uptime check implementace

```php
// UptimeChecker — běží jako cron job (každých 5 minut)
class UptimeChecker
{
    public function check(Site $site): UptimeResult
    {
        $startTime = microtime(true);

        try {
            $response = $this->httpClient->get($site->url, [
                'timeout' => 10,
                'verify' => true,          // SSL verification
                'allow_redirects' => true,
                'http_errors' => false,    // nevyhazovat výjimku na 4xx/5xx
            ]);

            $responseTime = (microtime(true) - $startTime) * 1000; // ms
            $statusCode = $response->getStatusCode();

            $status = match(true) {
                $statusCode >= 200 && $statusCode < 300 => 'up',
                $responseTime > 5000 => 'degraded',
                default => 'down',
            };

            return new UptimeResult($site->id, $status, $statusCode, $responseTime);
        } catch (RequestException $e) {
            return new UptimeResult($site->id, 'down', 0, 0, $e->getMessage());
        }
    }
}
```

#### 2.3.4 SSL check implementace

```php
class SslChecker
{
    public function check(Site $site): SslResult
    {
        $parsed = parse_url($site->url);
        $host = $parsed['host'];
        $port = 443;

        $client = stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno, $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['capture_peer_cert' => true]])
        );

        $params = stream_context_get_params($client);
        $cert = openssl_x509_parse($params['options']['ssl']['peer_certificate']);

        return new SslResult(
            issuer: $cert['issuer']['O'] ?? 'Unknown',
            validFrom: date('Y-m-d', $cert['validFrom_time_t']),
            validTo: date('Y-m-d', $cert['validTo_time_t']),
            daysUntilExpiry: (int)(($cert['validTo_time_t'] - time()) / 86400),
            selfSigned: $cert['issuer']['CN'] === $cert['subject']['CN'],
        );
    }
}
```

#### 2.3.5 Vulnerability check

```php
class VulnerabilityChecker
{
    // Zdroje:
    // 1. WPScan API (https://wpscan.com/api) — komerční, API key potřeba
    // 2. Patchstack API (https://patchstack.com/database/) — komerční
    // 3. Wordfence Intelligence — volně dostupné
    // 4. NVD (National Vulnerability Database) — volně dostupné

    public function checkSite(Site $site): VulnerabilityReport
    {
        $wpVersion = $site->wpVersion;
        $plugins = $this->wpClient->getPlugins($site);
        $themes = $this->wpClient->getThemes($site);

        $vulnerabilities = [];

        // Core vulnerabilities
        $coreVulns = $this->checkCoreVersion($wpVersion);
        $vulnerabilities = array_merge($vulnerabilities, $coreVulns);

        // Plugin vulnerabilities
        foreach ($plugins as $plugin) {
            $pluginVulns = $this->checkPlugin($plugin['slug'], $plugin['version']);
            $vulnerabilities = array_merge($vulnerabilities, $pluginVulns);
        }

        // Theme vulnerabilities
        foreach ($themes as $theme) {
            $themeVulns = $this->checkTheme($theme['stylesheet'], $theme['version']);
            $vulnerabilities = array_merge($vulnerabilities, $themeVulns);
        }

        return new VulnerabilityReport($site->id, $vulnerabilities);
    }
}
```

#### 2.3.6 Security score kalkulace

```
Security Score (0-100) =
    (100
    - (core_outdated ? 20 : 0)
    - (core_vulnerabilities * 15)
    - (plugin_vulnerabilities * 5)
    - (theme_vulnerabilities * 3)
    - (no_ssl ? 25 : 0)
    - (ssl_expiring_soon ? 5 : 0)
    - (debug_mode ? 10 : 0)
    - (file_editing_enabled ? 5 : 0)
    - (xmlrpc_enabled ? 3 : 0)
    - (excess_admin_accounts * 5)
    - (no_2fa_admin * 2)
    )
    clamped to [0, 100]
```

#### 2.3.7 API endpointy

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/api/monitoring/uptime` | Uptime statistiky (všechny weby) |
| GET | `/api/monitoring/uptime/{siteId}` | Uptime statistika pro web |
| GET | `/api/monitoring/uptime/{siteId}/history` | Downtime historie |
| GET | `/api/monitoring/ssl` | SSL status napříč weby |
| GET | `/api/monitoring/ssl/{siteId}` | SSL detail pro web |
| GET | `/api/monitoring/response-times` | Response time statistiky |
| POST | `/api/monitoring/check/{siteId}` | Manuální uptime check |
| GET | `/api/security/scan/{siteId}` | Security scan výsledky |
| POST | `/api/security/scan/{siteId}` | Spuštění security scanu |
| POST | `/api/security/scan/batch` | Hromadný security scan |
| GET | `/api/security/vulnerabilities` | Agregované zranitelnosti |
| GET | `/api/security/score` | Security score napříč weby |
| GET | `/api/security/score/{siteId}` | Security score pro web |
| GET | `/api/security/recommendations/{siteId}` | Doporučení pro nápravu |
| GET | `/api/security/report` | Security report (PDF) |

#### 2.3.8 Frontend komponenty

- **MonitoringOverviewPage** — mapa/seznam webů s uptime statusy (shadcnblocks: Dashboard + Data Table bloky)
- **UptimeChart** — graf uptime (shadcnblocks: Chart Group bloky + Recharts)
- **SslStatusTable** — tabulka SSL certifikátů (shadcnblocks: Data Table + Status badges)
- **ResponseTimeChart** — graf response time (shadcnblocks: Chart Group bloky + Recharts)
- **IncidentTimeline** — timeline downtime (shadcnblocks: Feature bloky + custom timeline)
- **SecurityScanPage** — výsledky security scanu (shadcnblocks: Dashboard + Bento bloky)
- **VulnerabilityList** — seznam zranitelností (shadcnblocks: Data Table + Badge severity)
- **SecurityScoreGauge** — gauge chart (shadcnblocks: Chart Group + custom RadialBar)
- **SecurityRecommendations** — doporučení (shadcnblocks: Feature bloky + Alert)
- **BatchSecurityScanDialog** — hromadný scan (shadcn/ui: Dialog + SiteSelector custom)

---

### 2.4 SEO & Performance

#### 2.4.1 Přehled

Modul SEO & Performance zodpovídá za audit SEO metadat, structured data, sitemap, Core Web Vitals a performance optimalizaci napříč spravovanými weby.

#### 2.4.2 Funkční požadavky

**SEO audit:**

| ID | Požadavek | Priorita |
|----|-----------|----------|
| SEO-01 | Kontrola title tag (délka, duplicity) | P0 |
| SEO-02 | Kontrola meta description (délka, chybějící) | P0 |
| SEO-03 | Kontrola Open Graph tags (og:title, og:description, og:image) | P1 |
| SEO-04 | Kontrola Twitter Card tags | P2 |
| SEO-05 | Kontrola canonical URLs | P1 |
| SEO-06 | Kontrola robots.txt | P1 |
| SEO-07 | Kontrola sitemap.xml (dostupnost, struktura) | P1 |
| SEO-08 | Kontrola structured data (JSON-LD, Schema.org) | P2 |
| SEO-09 | Kontrola heading struktury (H1-H6 hierarchie) | P1 |
| SEO-10 | Kontrola broken links (interní + externí) | P2 |
| SEO-11 | Kontrola alt textů obrázků | P1 |
| SEO-12 | Kontrola permalink struktury | P2 |
| SEO-13 | SEO score per web (0-100) | P1 |
| SEO-14 | Doporučení pro zlepšení SEO | P1 |

**Performance audit:**

| ID | Požadavek | Priorita |
|----|-----------|----------|
| PERF-01 | Core Web Vitals (LCP, FID/INP, CLS) | P0 |
| PERF-02 | Page load time (full, DOMContentLoaded) | P0 |
| PERF-03 | Page size (total, HTML, CSS, JS, images) | P0 |
| PERF-04 | Number of requests (total, by type) | P1 |
| PERF-05 | Cache status (browser cache, server cache) | P1 |
| PERF-06 | Image optimization check (formáty, lazy loading) | P1 |
| PERF-07 | JS/CSS minification check | P2 |
| PERF-08 | Gzip/Brotli compression check | P1 |
| PERF-09 | CDN detection | P2 |
| PERF-10 | Performance score per web (0-100) | P1 |
| PERF-11 | Doporučení pro zlepšení performance | P1 |
| PERF-12 | Historical performance trend (graf) | P2 |

**Analytics integrace:**

| ID | Požadavek | Priorita |
|----|-----------|----------|
| ANA-01 | Detekce Google Analytics / GA4 | P2 |
| ANA-02 | Detekce Google Search Console verifikace | P2 |
| ANA-03 | Detekce Facebook Pixel | P2 |
| ANA-04 | Detekce Hotjar / Microsoft Clarity | P2 |
| ANA-05 | Zobrazení základních metrik (pokud API k dispozici) | P2 |

#### 2.4.3 SEO audit implementace

```php
class SeoAuditor
{
    public function audit(Site $site): SeoAuditResult
    {
        // 1. Fetch homepage HTML
        $html = $this->httpClient->get($site->url)->getBody();
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        // 2. Title tag
        $titleNodes = $xpath->query('//title');
        $title = $titleNodes->length > 0 ? $titleNodes->item(0)->textContent : null;

        // 3. Meta description
        $metaDesc = $this->getMetaContent($xpath, 'name', 'description');

        // 4. Open Graph
        $ogTitle = $this->getMetaContent($xpath, 'property', 'og:title');
        $ogDesc = $this->getMetaContent($xpath, 'property', 'og:description');
        $ogImage = $this->getMetaContent($xpath, 'property', 'og:image');

        // 5. Canonical
        $canonical = $this->getLinkHref($xpath, 'canonical');

        // 6. Headings
        $headings = $this->analyzeHeadings($xpath);

        // 7. Robots.txt
        $robots = $this->fetchRobotsTxt($site->url);

        // 8. Sitemap
        $sitemap = $this->fetchSitemap($site->url, $robots);

        // 9. Structured data
        $structuredData = $this->extractStructuredData($xpath);

        // 10. Images alt texts
        $images = $this->analyzeImages($xpath);

        return new SeoAuditResult(...);
    }
}
```

#### 2.4.4 Performance audit implementace

```php
class PerformanceAuditor
{
    // Core Web Vitals — získány z:
    // 1. Google PageSpeed Insights API (volné, rate-limited)
    // 2. CrUX (Chrome UX Report) API
    // 3. Vlastní měření přes Puppeteer/Playwright (pokud dostupné)

    public function audit(Site $site): PerformanceAuditResult
    {
        // PageSpeed Insights API
        $psi = $this->callPageSpeedInsights($site->url);

        return new PerformanceAuditResult(
            lcp: $psi['lighthouseResult']['audits']['largest-contentful-paint']['numericValue'] ?? null,
            cls: $psi['lighthouseResult']['audits']['cumulative-layout-shift']['numericValue'] ?? null,
            inp: $psi['lighthouseResult']['audits']['interaction-to-next-paint']['numericValue'] ?? null,
            pageSize: $this->calculatePageSize($site->url),
            requestCount: $this->countRequests($site->url),
            performanceScore: $psi['lighthouseResult']['categories']['performance']['score'] * 100,
        );
    }

    private function callPageSpeedInsights(string $url): array
    {
        // GET https://www.googleapis.com/pagespeedonline/v5/runPagespeed
        // ?url={url}&strategy=mobile&category=performance
        // API key volitelný (zvyšuje rate limit)
    }
}
```

#### 2.4.5 SEO score kalkulace

```
SEO Score (0-100) =
    (100
    - (missing_title ? 15 : 0)
    - (title_too_long ? 5 : 0)
    - (title_too_short ? 5 : 0)
    - (missing_meta_desc ? 15 : 0)
    - (meta_desc_too_long ? 3 : 0)
    - (missing_og_tags ? 5 : 0)
    - (missing_canonical ? 5 : 0)
    - (no_sitemap ? 10 : 0)
    - (no_robots_txt ? 5 : 0)
    - (bad_heading_structure ? 5 : 0)
    - (missing_alt_texts * 1)
    - (broken_links * 2)
    - (no_structured_data ? 3 : 0)
    )
    clamped to [0, 100]
```

#### 2.4.6 API endpointy

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/api/seo/audit/{siteId}` | SEO audit výsledky |
| POST | `/api/seo/audit/{siteId}` | Spuštění SEO auditu |
| POST | `/api/seo/audit/batch` | Hromadný SEO audit |
| GET | `/api/seo/score` | SEO score napříč weby |
| GET | `/api/seo/score/{siteId}` | SEO score pro web |
| GET | `/api/seo/recommendations/{siteId}` | SEO doporučení |
| GET | `/api/performance/audit/{siteId}` | Performance audit výsledky |
| POST | `/api/performance/audit/{siteId}` | Spuštění performance auditu |
| POST | `/api/performance/audit/batch` | Hromadný performance audit |
| GET | `/api/performance/score` | Performance score napříč weby |
| GET | `/api/performance/score/{siteId}` | Performance score pro web |
| GET | `/api/performance/history/{siteId}` | Historie performance metrik |
| GET | `/api/performance/recommendations/{siteId}` | Performance doporučení |
| GET | `/api/analytics/detection/{siteId}` | Detekce analytics nástrojů |

#### 2.4.7 Frontend komponenty

- **SeoAuditPage** — výsledky SEO auditu (shadcnblocks: Dashboard + Bento bloky)
- **SeoScoreGauge** — gauge chart SEO score (shadcnblocks: Chart Group + custom RadialBar)
- **MetaTagsTable** — tabulka meta tagů (shadcnblocks: Data Table bloky)
- **HeadingStructureTree** — vizualizace H1-H6 (shadcn/ui: Collapsible + custom tree)
- **SitemapViewer** — zobrazení sitemap (shadcnblocks: Feature bloky + custom tree)
- **BrokenLinksList** — broken links (shadcnblocks: Data Table + Badge)
- **PerformanceAuditPage** — výsledky performance (shadcnblocks: Dashboard + Bento bloky)
- **CoreWebVitalsCards** — karty LCP, CLS, INP (shadcnblocks: Dashboard stat card bloky)
- **PerformanceScoreGauge** — gauge chart (shadcnblocks: Chart Group + custom RadialBar)
- **PageSizeBreakdown** — koláčový graf (shadcnblocks: Chart Group + Recharts Pie)
- **RequestWaterfall** — vodopád požadavků (custom + shadcn/ui ScrollArea)
- **PerformanceTrendChart** — historický trend (shadcnblocks: Chart Group + Recharts Line)
- **AnalyticsDetectionCard** — detekované nástroje (shadcnblocks: Bento + Badge)
- **BatchAuditDialog** — hromadný audit (shadcn/ui: Dialog + SiteSelector custom)

---

## 3. Budoucí moduly

### 3.1 Content Manager

- Hromadná správa příspěvků a stránek napříč weby
- Publikování, úprava, mazání přes WP REST API
- Template system pro hromadné vytváření obsahu
- Content calendar napříč weby

### 3.2 User Manager

- Správa WP uživatelů a rolí napříč weby
- Hromadné přidávání/odebírání uživatelů
- Audit admin účtů
- Password policy enforcement

### 3.3 Deploy Manager

- Deploy pluginů a šablon ze Git repozitářů
- Version control integrace (GitHub, GitLab, Bitbucket)
- Rollback na předchozí verzi
- Deploy schedule a staging environment support

### 3.4 White Label

- Branding pro agentury (logo, barvy, custom domain)
- Custom login page
- Client portal (omezený přístup pro klienty)
- Per-client reporting

### 3.5 Client Reports

- Automatizované reporty pro klienty (PDF, e-mail)
- Customizable šablony
- Scheduled reports (týdenní, měsíční)
- Obsahuje: uptime, updates, security, performance, SEO

### 3.6 AI Assistant

- AI-powered analýza zdraví webů
- Automatická doporučení pro optimalizaci
- Prediktivní alerting (predikce downtime na základě trendů)
- Automatická oprava běžných problémů
- Natural language query ("Které weby potřebují aktualizaci?")

---

## 4. Mezimodulová komunikace

### 4.1 Event-driven architektura

Moduly komunikují přes Symfony EventDispatcher — nikoliv přímými voláními.

```php
// Events
SiteAddedEvent           — emitováno při přidání webu
SiteRemovedEvent         — emitováno při smazání webu
SiteStatusChangedEvent   — emitováno při změně statusu (up→down)
UpdateCompletedEvent     — emitováno po dokončení aktualizace
BackupCompletedEvent     — emitováno po dokončení zálohy
SecurityScanCompletedEvent — emitováno po security scanu
VulnerabilityDetectedEvent — emitováno při detekci zranitelnosti
SslExpiringEvent         — emitováno při blížící se expiraci SSL
```

### 4.2 Event subscribers

| Event | Subscriber | Akce |
|-------|-----------|------|
| `SiteAddedEvent` | UpdatesModule | Získání aktuálních verzí pluginů/šablon |
| `SiteAddedEvent` | SecurityScanModule | Iniciální security scan |
| `SiteAddedEvent` | SeoPerformanceModule | Iniciální SEO/performance audit |
| `SiteAddedEvent` | MonitoringModule | Zahájení uptime monitoring |
| `SiteRemovedEvent` | All modules | Cleanup dat spojených s webem |
| `UpdateCompletedEvent` | SecurityScanModule | Re-scan po aktualizaci |
| `UpdateCompletedEvent` | BackupsModule | Pre-update backup (před eventem) |
| `BackupCompletedEvent` | AuditLogger | Logování |
| `VulnerabilityDetectedEvent` | NotificationService | Odeslání notifikace |
| `SslExpiringEvent` | NotificationService | Odeslání upozornění |

### 4.3 Notification service

Centrální služba pro odesílání notifikací (e-mail, webhook, Slack, Discord):

```php
class NotificationService
{
    public function notify(int $userId, string $channel, Notification $notification): void
    {
        // channel: email | webhook | slack | discord
        // Notification: title, message, severity, link, metadata
    }
}
```

Konfigurace notifikací per-user:

```php
[
    'email' => [
        'enabled' => true,
        'address' => 'admin@example.com',
        'events' => ['site_down', 'vulnerability_detected', 'ssl_expiring', 'backup_failed'],
    ],
    'webhook' => [
        'enabled' => true,
        'url' => 'https://hooks.slack.com/...',
        'events' => ['site_down', 'update_completed'],
    ],
]
```
