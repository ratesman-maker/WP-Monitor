# WP Monitor — Projektové zadání

## 1. Přehled projektu

**WP Monitor** je profesionální nástroj pro hromadnou administraci WordPress webů — lokální/hostingová alternativa ke službám ManageWP, MainWP nebo InfiniteWP. Nástroj umožňuje spravovat desítky až stovky WordPress instalací z jednoho místa s důrazem na bezpečnost, rychlost a modularitu.

### 1.1 Cíle projektu

| Priorita | Cíl | Popis |
|----------|-----|-------|
| **P0 — Kritické** | 100% zabezpečení | Veškeré přihlašovací údaje ke spravovaným webům jsou šifrovány end-to-end. Master heslo nikdy neopustí klienta. Žádné plaintext credentials v DB ani v paměti serveru déle než nutno. |
| **P0 — Kritické** | Rychlost | Paralelní komunikace se všemi weby. UI responzivní < 100ms. Hromadné operace na 50+ webech < 30s. |
| **P0 — Kritické** | Modularita | Každý modul je nezávislý balíček s vlastním manifestem. Moduly lze přidávat/odebírat bez zásahu do core. Plugin systém na úrovni backendu i frontendu. |
| **P1 — Vysoká** | Spolehlivost | Graceful degradation — pokud jeden web neodpovídá, ostatní operace pokračují. Retry mechanismus s exponential backoff. |
| **P1 — Vysoká** | Auditovatelnost | Každá akce je logována s timestampem, uživatelem, cílovým webem a výsledkem. |
| **P2 — Střední** | Rozšiřitelnost | API pro vlastní moduly třetích stran. Webhook system pro integrace. |

### 1.2 Cíloví uživatelé

- **Freelance WordPress vývojáři** — spravují 5-50 webů klientů
- **Agentury** — spravují 20-200+ webů
- **In-house týmy** — správa interní WordPress infrastruktury

### 1.3 Případy užití (high-level)

1. **Přidání nového webu** — uživatel zadá URL, přihlašovací údaje (application password), web se přidá do dashboardu
2. **Hromadná aktualizace** — výběr N webů → aktualizace jádra/pluginů/šabon jedním klikem
3. **Monitoring** — průběžná kontrola uptime, SSL certifikátů, dostupnosti aktualizací
4. **Zálohování** — plánované zálohy DB + souborů s možností obnovy
5. **Security audit** — skenování zranitelností, malware detekce, kontrola konfigurace
6. **SEO & Performance** — audit metadat, rychlosti načítání, optimalizace

## 2. Technické požadavky

### 2.1 Runtime požadavky

| Komponenta | Minimální | Doporučené |
|------------|-----------|------------|
| PHP | 8.3+ | 8.4+ |
| MySQL/MariaDB | 8.0+ / 10.6+ | 8.4+ / 11.4+ |
| PHP extensions | openssl, sodium, mbstring, intl, curl, json, pdo_mysql | + opcache, apcu |
| Memory limit | 256M | 512M+ |
| Node.js (vývoj) | 20 LTS | 22 LTS |

### 2.2 Komunikační protokol se spravovanými weby

Primární komunikace probíhá přes **WordPress REST API** (`/wp-json/wp/v2/` a vlastní endpointy).

**Autentizace:**
- **Application Passwords** (WP 5.6+) — primární metoda, per-site scoped tokeny
- **Volitelný MU-plugin** — pro rozšířené funkce (file management, DB operations, server stats) instalovaný na spravovaných webech

**Požadavky na spravované weby:**
- WordPress 5.6+ (pro Application Passwords)
- Povolené REST API
- HTTPS (vynuceno)
- Povolené Application Passwords (lze vynutit přes konstantu `WP_APP_PASSWORD_ALLOW_FOR_LOCAL_SSL`)

### 2.3 Bezpečnostní požadavky (non-negotiable)

1. **Master heslo** — uživatel zadá při spuštění, z něj se derivuje encryption key (Argon2id)
2. **Šifrování credentials** — AES-256-GCM s klíčem derivovaným z master hesla
3. **Žádné plaintext údaje** — v DB pouze šifrované bloby
4. **HTTPS only** — komunikace se spravovanými weby výhradně přes HTTPS
5. **CSRF ochrana** — všechny POST/PUT/DELETE požadavky vyžadují CSRF token
6. **Rate limiting** — na login endpointech a API obecně
7. **Audit log** — každá akce logována, nelze smazat (append-only)
8. **Session timeout** — automatické odhlášení po nečinnosti (konfigurovatelné, default 15 min)
9. **Zero-knowledge architektura** — server nikdy nezná master heslo ani decryption key v perzistentní formě

## 3. Moduly — přehled

### 3.1 Core moduly (povinné)

| Modul | Popis |
|-------|-------|
| **Auth** | Master password login, session management, CSRF, rate limiting |
| **Sites** | CRUD správa webů, testování připojení, grouping/tagging |
| **Dashboard** | Přehledný dashboard s klíčovými metrikami napříč weby |
| **Activity Log** | Audit log všech akcí, filtrování, export |
| **Settings** | Globální nastavení, konfigurace modulů, správa uživatelů (multi-user) |

### 3.2 Funkční moduly

| Modul | Popis |
|-------|-------|
| **Updates** | Správa aktualizací (jádro, pluginy, šablony), hromadné updaty, rollback, auto-update pravidla |
| **Backups** | Zálohování DB + souborů, plánování, obnova, ukládání lokálně/S3/SFTP, retence |
| **Security & Monitoring** | Uptime monitoring, SSL check, malware scan, vulnerability check, firewall pravidla, 2FA audit |
| **SEO & Performance** | SEO audit (meta tags, structured data, sitemap), performance audit (Core Web Vitals), analytics integrace |

### 3.3 Plánované moduly (budoucí fáze)

| Modul | Popis |
|-------|-------|
| **Content Manager** | Hromadná správa příspěvků/stránek napříč weby |
| **User Manager** | Správa uživatelů a rolí napříč weby |
| **Deploy Manager** | Deploy pluginů/šabon ze Git repozitářů |
| **White Label** | Branding pro agentury (logo, barvy, custom domain) |
| **Client Reports** | Automatizované reporty pro klienty (PDF, e-mail) |
| **AI Assistant** | AI-powered analýza, doporučení, automatická oprava |

## 4. Multi-user podpora

- **Role:** Admin, Manager, Viewer
- **Admin** — plný přístup, správa uživatelů, nastavení
- **Manager** — správa webů, spouštění operací, bez správy uživatelů
- **Viewer** — pouze prohlížení dashboardu a logů
- Každý uživatel má vlastní master heslo (nebo sdílené — konfigurovatelné)
- Per-site oprávnění (které weby může který uživatel spravovat)

## 5. Nasazení

### 5.1 Fáze 1 — Lokální vývoj
- Docker (Docker Engine + Docker Compose) — MariaDB, PHP-FPM, Nginx, Vite dev server v kontejnerech
- Vite dev server s proxy na Nginx backend (`/api` → `:8080`)
- Hot reload pro frontend (HMR)
- Reprodukovatelné prostředí shodné s produkčním Linux stackem

### 5.2 Fáze 2 — Produkční nasazení
- Klasický PHP hosting (Apache/Nginx + PHP-FPM + MySQL)
- Frontend buildován přes Vite → statické soubory v `public/assets/`
- Composer install pro backend dependencies
- Single-tenant (jedna instalace = jeden uživatel/tým)

### 5.3 Fáze 3 — SaaS (volitelné)
- Multi-tenant architektura
- Každý tenant má izolovaná data (separate DB nebo row-level isolation)
- Billing integrace

## 6. Omezení a hranice

- Nástroj **není** WordPress plugin — je to samostatná aplikace
- Nástroj **nenahrazuje** WP-CLI — doplňuje ho pro hromadné operace přes REST API
- Nástroj **nevyžaduje** instalaci pluginu na spravované weby (MU-plugin je volitelný pro rozšířené funkce)
- Nástroj **neprovádí** code-level úpravy na spravovaných webech (pouze přes REST API a WP admin akce)

## 7. Terminologie

| Termín | Definice |
|--------|----------|
| **Master heslo** | Heslo uživatele WP Monitor, z kterého se derivuje encryption key |
| **Spravovaný web** | WordPress instalace přidaná do WP Monitor |
| **Application Password** | WordPress token pro REST API autentizaci (per-site) |
| **MU-plugin** | Must-Use plugin instalovaný na spravovaném webu pro rozšířené funkce |
| **Modul** | Samostatný balíček s funkcionalitou (Updates, Backups, etc.) |
| **Akce** | Operace provedená na spravovaném webu (update, backup, scan, etc.) |
| **Batch** | Hromadná operace na více webech najednou |
