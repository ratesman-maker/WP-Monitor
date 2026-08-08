# Implementační plán — Auth modul

## Metadata

| Pole | Hodnota |
|------|---------|
| Název | Auth modul |
| Typ | modul |
| Priorita | P0 |
| Status | completed |
| Vytvořeno | 07.08.2026 |
| Autor | — |
| Související workflow | new-module.md |
| Související dokumentace | docs/04-module-specifications.md (1.1 Auth), docs/03-security-model.md, docs/06-api-specification.md (2. Auth API) |

## 1. Cíl

Implementovat Auth modul pro WP Monitor — autentizaci uživatele pomocí master hesla, správu session, CSRF ochranu a rate limiting. Modul je povinný (core) a nelze ho deaktivovat.

**P0 funkce:**
- AUTH-01: Přihlášení pomocí master hesla
- AUTH-02: Derivace encryption key z master hesla (Argon2id)
- AUTH-03: Ověření hesla dešifrováním verification tokenu
- AUTH-04: Vydání JWT token + CSRF token po loginu
- AUTH-05: Session timeout (15 min, konfigurovatelné)
- AUTH-07: Rate limiting na login (5 pokusů / 15 min)
- AUTH-08: Account lockout po 10 neúspěšných pokusech (30 min)
- AUTH-10: Logout — zničení session + revokace JWT

**First setup:**
- Endpoint POST /api/auth/setup — vytvoření admin uživatele při prvním spuštění

## 2. Reálný stav (před implementací)

### 2.1 Backend

**Existuje:**
- `src/Core/Kernel.php` — Slim 4 bootstrap, registrace middleware, routes, modulů
- `src/Core/ModuleInterface.php` — interface (register, getManifest, boot)
- `src/Core/ModuleManifest.php` — readonly DTO pro manifest
- `src/Core/ModuleRegistry.php` — registrace modulů
- `src/Http/Controllers/HealthController.php` — health endpoint
- `src/Http/ErrorHandler.php` — error handler (mapuje výjimky na error kódy)
- `src/Http/ErrorResponse.php` — JSON error response formát
- `src/Http/Middleware/JsonBodyParserMiddleware.php` — JSON body parser
- `src/Storage/ConnectionFactory.php` — Doctrine DBAL connection factory (singleton)
- `config/container.php` — PHP-DI container (Logger, HealthController, ErrorHandler)
- `config/modules.php` — prázdný seznam modulů (Auth zakomentovaný)
- `config/routes.php` — pouze /api/health route
- `config/settings.php` — settings config

**Chybí:**
- CryptoService, KeyDerivationService (Core)
- AuthModule, AuthController, JwtService, SessionService, AuthService, RateLimiter
- UserRepository, SessionRepository
- AuthMiddleware, CsrfMiddleware
- DB connection v container.php (ConnectionFactory existuje ale není registrován)

### 2.2 Frontend

**Existuje:**
- `src/App.tsx` — landing page (HomePage)
- `src/main.tsx` — React bootstrap (QueryClient, BrowserRouter)
- `src/lib/api.ts` — API client (fetch wrapper, ApiException)
- `src/lib/utils.ts` — cn() utility
- `src/styles/globals.css` — TailwindCSS
- `src/__tests__/` — test setup + Counter test

**Chybí:**
- Auth store (Zustand)
- Login page, Setup page
- ProtectedRoute komponenta
- Auth typy a API funkce

### 2.3 Databáze

Ověřeno přes `mariadb` MCP. Tabulky existují:

**users** (14 sloupců, 5 indexů):
- `id` (PK, auto_increment), `username` (UNI), `email` (UNI, nullable)
- `role` (MUL, default 'viewer'), `password_salt` (varbinary 16)
- `verification_token` (varbinary 255), `verification_aad` (varbinary 16)
- `failed_login_count` (default 0), `locked_until` (nullable)
- `last_login_at`, `last_login_ip`, `is_active` (MUL, default 1)
- `created_at`, `updated_at`
- **0 záznamů**

**user_sessions** (10 sloupců, 4 indexy):
- `id` (PK, varchar 128), `user_id` (MUL)
- `ip_address`, `user_agent_hash`, `encryption_key` (varbinary 255)
- `jwt_jti` (MUL, nullable), `csrf_token`
- `expires_at` (MUL), `created_at`, `last_activity`

**Chybí:**
- `rate_limit_attempts` tabulka pro DB-based rate limiting

### 2.4 Rozdíl oproti dokumentaci

- Dokumentace popisuje APCu/Redis pro rate limiting — my implementujeme DB-based (APCu nemusí být dostupné, Redis není v composer.json)
- Dokumentace zmiňuje multi-user podporu (AUTH-11, AUTH-12) — to je P1, implementujeme později
- Dokumentace zmiňuje password change (AUTH-09) — to je P0 ale implementujeme v další fázi (rotace credentials vyžaduje Sites modul)
- `user_sessions.encryption_key` je v DB — dokumentace říká "encryption key v paměti pouze" — řešíme šifrováním encryption_key pomocí APP_KEY v DB

## 3. Priority (bez výjimek)

### 3.1 Bezpečnost

- **Zero-knowledge**: Master heslo se nikde neukládá (ani hashovaná verze) — používá se verification token
- **Argon2id key derivation**: sodium_crypto_pwhash s INTERACTIVE limity pro login, SENSITIVE pro setup
- **AES-256-GCM šifrování**: sodium_crypto_aead_aes256gcm pro verification token i credentials
- **Encryption key v session**: Zašifrovaný pomocí APP_KEY (AES-256-GCM) v user_sessions.encryption_key
- **JWT s IP+UA binding**: Token vázán na IP a User-Agent — při změně invalidován
- **JWT s jti**: Pro revokaci při logout (jti uložen v session, při logout se session smaže)
- **CSRF ochrana**: Token v session + X-CSRF-Token header na POST/PUT/DELETE
- **Rate limiting**: 5 pokusů / 15 min na login (IP + username), DB-based
- **Account lockout**: 10 neúspěšných pokusů → 30 min lockout
- **HTTPS enforcement**: V produkci pouze přes HTTPS (Secure cookies, HSTS)
- **Input validace**: Username (max 50), password (min 12 znaků)
- **Fail secure**: Při chybě se vždy zvolí bezpečnější varianta

### 3.2 Rychlost

- **Argon2id INTERACTIVE limity** pro login (rychlejší než SENSITIVE)
- **Index na user_sessions.jwt_jti** — rychlá revokace
- **Index na user_sessions.expires_at** — rychlé cleanup expirovaných session
- **Index na users.username** — rychlý lookup při loginu
- **Minimální JWT payload** — jen sub, role, iat, exp, jti, ip, ua (ne email)
- **Session cleanup** — expirované session mazány při loginu (lazy cleanup)

### 3.3 Modularita

- **CryptoService v Core/** — generická, používá ji Auth i Sites modul
- **KeyDerivationService v Core/** — generická, používá ji Auth modul
- **AuthModule implementuje ModuleInterface** — konzistentní s ostatními moduly
- **Tenké controllery** — logika v AuthService, controller jen volá service
- **Repository pattern** — UserRepository a SessionRepository pro DB přístup
- **EventDispatcher** — emituje auth.login, auth.logout, auth.setup eventy (pro audit log)
- **manifest.json** — modul se registruje přes manifest

## 4. Architektura

### 4.1 Backend

```
src/
├── Core/
│   ├── CryptoService.php          # AES-256-GCM encrypt/decrypt
│   ├── KeyDerivationService.php   # Argon2id key derivation
│   ├── Kernel.php                 # (existuje)
│   ├── ModuleInterface.php        # (existuje)
│   ├── ModuleManifest.php         # (existuje)
│   └── ModuleRegistry.php         # (existuje)
├── Modules/
│   └── Auth/
│       ├── AuthModule.php         # ModuleInterface implementace
│       ├── manifest.json          # modul manifest
│       ├── Controllers/
│       │   └── AuthController.php # login, logout, verify, session, setup
│       ├── Services/
│       │   ├── JwtService.php     # JWT generování a verifikace
│       │   ├── SessionService.php # session CRUD, sliding expiration
│       │   ├── AuthService.php    # login/logout/verify/setup logika
│       │   └── RateLimiter.php    # DB-based rate limiting
│       └── Repositories/
│           ├── UserRepository.php    # users CRUD
│           └── SessionRepository.php # user_sessions CRUD
├── Http/
│   ├── Middleware/
│   │   ├── AuthMiddleware.php       # JWT verifikace, IP+UA binding
│   │   ├── CsrfMiddleware.php       # CSRF token validace
│   │   └── JsonBodyParserMiddleware.php  # (existuje)
│   ├── Controllers/
│   │   └── HealthController.php     # (existuje)
│   ├── ErrorHandler.php             # (existuje)
│   └── ErrorResponse.php            # (existuje)
└── Storage/
    └── ConnectionFactory.php        # (existuje)
```

### 4.2 Frontend

```
src/
├── modules/
│   └── auth/
│       ├── types.ts              # User, LoginResponse, SessionInfo
│       ├── api.ts                # login, logout, verify, setup API funkce
│       └── pages/
│           ├── LoginPage.tsx     # přihlašovací formulář
│           └── SetupPage.tsx     # first-setup formulář
├── stores/
│   └── auth-store.ts             # Zustand store (token, csrfToken, user)
├── components/
│   └── ProtectedRoute.tsx        # ochrana rout pro nepřihlášené
├── lib/
│   ├── api.ts                    # (existuje) — rozšířit o auth header injection
│   └── utils.ts                  # (existuje)
└── App.tsx                       # (existuje) — rozšířit o routes
```

### 4.3 Databáze

**Nová tabulka:**

```sql
CREATE TABLE rate_limit_attempts (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier  VARCHAR(100) NOT NULL,    -- IP nebo IP+username
    endpoint    VARCHAR(50) NOT NULL,     -- 'login', 'setup', 'verify'
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_endpoint (endpoint),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Existující tabulky beze změny:** users, user_sessions

## 5. API endpointy

| Metoda | Cesta | Popis | Role | Request | Response |
|--------|-------|-------|------|---------|----------|
| POST | `/api/auth/setup` | First setup (pouze pokud users prázdná) | veřejný | `{username, password}` | `{token, csrfToken, expiresIn, user}` |
| POST | `/api/auth/login` | Přihlášení | veřejný | `{username, password}` | `{token, csrfToken, expiresIn, user}` |
| POST | `/api/auth/logout` | Odhlášení | auth | — | 204 |
| POST | `/api/auth/verify` | Ověření JWT | auth | — | `{valid, user, expiresAt}` |
| GET | `/api/auth/session` | Info o session | auth | — | `{userId, username, role, createdAt, lastActivity, expiresAt, ipAddress}` |

## 6. Kroky implementace

### Fáze 1: Core služby + DB migrace
- [ ] Vytvořit `CryptoService` (AES-256-GCM encrypt/decrypt přes libsodium)
- [ ] Vytvořit `KeyDerivationService` (Argon2id přes sodium_crypto_pwhash)
- [ ] Vytvořit migraci `Version20260807_02_create_rate_limit_attempts`
- [ ] Spustit migraci

### Fáze 2: Auth backend (services + repositories)
- [ ] Vytvořit `UserRepository` (findByUsername, updateFailedLogin, lockAccount, create)
- [ ] Vytvořit `SessionRepository` (create, findById, findByJti, updateActivity, delete, deleteExpired)
- [ ] Vytvořit `JwtService` (generate, verify — firebase/php-jwt, HS256, IP+UA binding)
- [ ] Vytvořit `SessionService` (createSession, getSession, refreshSession, destroySession)
- [ ] Vytvořit `RateLimiter` (checkLimit, recordAttempt, DB-based)
- [ ] Vytvořit `AuthService` (login, logout, verify, setup — orchestruje ostatní služby)

### Fáze 3: Auth backend (controller + middleware + module)
- [ ] Vytvořit `AuthController` (setup, login, logout, verify, session)
- [ ] Vytvořit `AuthMiddleware` (JWT verifikace, načtení session, IP+UA binding)
- [ ] Vytvořit `CsrfMiddleware` (CSRF token validace na POST/PUT/DELETE)
- [ ] Vytvořit `manifest.json` pro Auth modul
- [ ] Vytvořit `AuthModule` (implementuje ModuleInterface, registruje routes)
- [ ] Registrovat AuthModule v `config/modules.php`
- [ ] Registrovat služby v `config/container.php` (Connection, CryptoService, KeyDerivationService, repositories, services)
- [ ] Zaregistrovat AuthMiddleware a CsrfMiddleware v Kernel.php

### Fáze 4: Frontend
- [ ] Vytvořit `src/modules/auth/types.ts` (User, LoginResponse, SessionInfo)
- [ ] Vytvořit `src/modules/auth/api.ts` (login, logout, verify, setup funkce)
- [ ] Vytvořit `src/stores/auth-store.ts` (Zustand — token, csrfToken, user, in-memory only)
- [ ] Rozšířit `src/lib/api.ts` o auth header injection (Authorization, X-CSRF-Token)
- [ ] Vytvořit `src/modules/auth/pages/LoginPage.tsx`
- [ ] Vytvořit `src/modules/auth/pages/SetupPage.tsx`
- [ ] Vytvořit `src/components/ProtectedRoute.tsx`
- [ ] Aktualizovat `src/App.tsx` o auth routes

### Fáze 5: Testy
- [ ] Backend: CryptoService test (encrypt/decrypt roundtrip, AAD, wrong key)
- [ ] Backend: KeyDerivationService test (deterministic, different salt)
- [ ] Backend: JwtService test (generate/verify, expired, invalid signature)
- [ ] Backend: AuthService test (login success, login wrong password, setup, rate limit)
- [ ] Backend: AuthController integration test (login endpoint, setup endpoint)
- [ ] Frontend: auth-store test (login, logout state changes)
- [ ] Frontend: LoginPage test (form submission, error handling)

## 7. Testy

### 7.1 Backend

**Unit testy:**
- `CryptoServiceTest` — encrypt/decrypt roundtrip, AAD verification, wrong key failure, wrong AAD failure
- `KeyDerivationServiceTest` — deterministic output se stejným salt, different output s different salt, 32-byte output
- `JwtServiceTest` — generate/verify roundtrip, expired token, invalid signature, IP/UA mismatch
- `RateLimiterTest` — under limit, at limit, over limit, window expiry

**Integration testy:**
- `AuthControllerTest` — login success (200), login wrong password (401), login rate limited (429), setup success (200), setup when users exist (409), logout (204), verify (200), session (200)

### 7.2 Frontend

**Komponent testy:**
- `auth-store.test.ts` — login sets token/user, logout clears state
- `LoginPage.test.tsx` — renders form, submits with credentials, shows error on failure

## 8. Validace

- [ ] `composer test` — bez chyb
- [ ] `composer analyse` — PHPStan bez chyb
- [ ] `composer cs-check` — PSR-12 bez chyb
- [ ] `npm run test` — bez chyb
- [ ] `npm run lint` — bez chyb
- [ ] `npm run typecheck` — bez chyb
- [ ] DB verifikace přes `mariadb` MCP (`list_tables`, `describe_table` na rate_limit_attempts)
- [ ] Vizuální kontrola přes `mcp-playwright` (login page, setup page)

## 9. Rizika a mitigace

| Riziko | Pravděpodobnost | Dopad | Mitigace |
|--------|----------------|-------|----------|
| ext-sodium není dostupné | nízká | vysoký | composer.json vyžaduje ext-sodium; přidat runtime check v CryptoService |
| Argon2id je pomalé na slabém hardwaru | střední | střední | INTERACTIVE limity pro login; konfigurovatelné přes .env |
| APP_KEY není nastavena | střední | vysoký | Runtime check v Kernel.php — fail fast pokud APP_KEY chybí |
| Rate limit tabulka roste nekonečně | střední | nízký | Lazy cleanup expirovaných záznamů při každém check |
| JWT secret leak | nízký | vysoký | APP_KEY z environment variable, ne v kódu ani DB |
| Session fixation | nízká | střední | Generovat nové session ID při každém loginu |

## 10. Rollback strategie

- **Kód**: Vše v jednom commitu — `git revert` pro vrácení. Nebo feature branch smazání.
- **DB migrace**: `php bin/migrate down` pro rollback rate_limit_attempts tabulky. Existující tabulky (users, user_sessions) se nemění.
- **DB záloha**: Před migrací vytvořit zálohu wp_monitor databáze (`mysqldump wp_monitor > backup.sql`).
- **Dependencies**: Žádné nové balíčky — firebase/php-jwt už je v composer.json.

## 11. Cross-module impact

| Modul | Dopad | Míra | Poznámka |
|-------|-------|------|----------|
| Sites | žádná | žádná | Sites modul ještě neexistuje; bude používat CryptoService z Core/ |
| Dashboard | žádná | žádná | Dashboard bude používat AuthMiddleware pro auth |
| Všechny moduly | AuthMiddleware | střední | AuthMiddleware bude globální middleware — všechny /api/* endpointy (kromě /auth/login a /auth/setup) vyžadují auth |

- **EventDispatcher eventy**: auth.login (success/failed), auth.logout, auth.setup — pro audit log (Activity Log modul)
- **Sdílené služby**: CryptoService a KeyDerivationService v Core/ — budou používány Sites modulem pro šifrování credentials
- **DB tabulky**: Nová rate_limit_attempts — používá pouze Auth modul

## 12. Poznámky

- JWT používá firebase/php-jwt (už v composer.json ^6.11) s HS256 algoritmem
- Key derivation používá ext-sodium (sodium_crypto_pwhash) — Argon2id
- Šifrování používá ext-sodium (sodium_crypto_aead_aes256gcm) — AES-256-GCM
- Session ID generováno jako UUID (ramsey/uuid už v composer.json)
- .env už má všechny potřebné proměnné (APP_KEY, SESSION_TIMEOUT, JWT_TTL, JWT_ISSUER, RATE_LIMIT_LOGIN)
- Frontend používá Zustand pro auth store (in-memory only, bez persist — po reload musí uživatel znovu zadat heslo)

## 13. Post-implementační kontrola

- [ ] **Code review** — projdi implementaci podle workflow `.windsurf/workflows/code-review.md` (bezpečnost, rychlost, modularita)
- [ ] **DB verifikace** — ověř přes `mariadb` MCP (`list_tables`, `describe_table` na rate_limit_attempts)
- [ ] **Vizuální kontrola** — ověř přes `mcp-playwright` (login page, setup page)
- [ ] **Cross-module impact** — ověř že AuthMiddleware neblokuje /api/health
- [ ] **Environment variables** — žádné nové .env proměnné (všechny už existují v .env.example)
- [ ] **Dependency audit** — žádné nové balíčky (všechny už v composer.json/package.json)
- [ ] **Dokumentace** — aktualizuj `docs/04-module-specifications.md` pokud je potřeba
- [ ] **Plán dokončen** — všechny checkboxy odkrokovány, status `completed`

## 14. GitHub a changelog

- [ ] **Secrets scanning** — zkontroluj že v git staged souborech nejsou API klíče, hesla, .env soubory
- [ ] **Commit** — vytvoř commit s konvenčním formátem (`feat(auth): add auth module with login, setup, JWT, CSRF, rate limiting`)
- [ ] **Push** — pushni na `origin/main`
- [ ] **Changelog** — podle workflow `.windsurf/workflows/changelog.md`:
  - Aktualizuj `CHANGELOG.md` (anglicky, commitnuto do gitu)
  - Aktualizuj `CHANGELOG_CS.md` (česky, gitignored, lokální)
  - Verze podle Semantic Versioning (MINOR — nová funkce)
  - Datum v evropském formátu DD.MM.YYYY
- [ ] **Commit changelog** — commitni pouze `CHANGELOG.md` (`docs: update changelog for version X.Y.Z`)
- [ ] **Push** — pushni changelog na `origin/main`

---

## Changelog plánu

| Datum | Změna | Autor |
|-------|-------|-------|
| 07.08.2026 | Vytvoření plánu | — |
