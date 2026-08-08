# WP Monitor — Bezpečnostní model

## 1. Bezpečnostní principy

### 1.1 Non-negotiable pravidla

1. **Zero-knowledge** — Server nikdy nezná master heslo ani decryption key v perzistentní formě
2. **Defense in depth** — Více vrstev ochrany, žádná single point of failure
3. **Least privilege** — Každá komponenta má minimální potřebná oprávnění
4. **Fail secure** — Při chybě se vždy zvolí bezpečnější varianta (zamknout, ne otevřít)
5. **Audit everything** — Každá akce je logována, logy jsou append-only
6. **No plaintext in transit** — Veškerá komunikace přes HTTPS
7. **No plaintext at rest** — Credentials šifrovány v DB, encryption key nikdy neuložen
8. **No plaintext in memory longer than necessary** — Dešifrované credentials ihned po použití wiped z paměti

### 1.2 Threat model

| Hrozba | Vektor | Mitigace |
|--------|--------|----------|
| **DB compromise** | Útočník získá DB dump | Credentials jsou šifrovány AES-256-GCM, bez master hesla nelze dešifrovat |
| **Server compromise** | Útočník získá přístup na server | Master heslo není na serveru uloženo; encryption key je v paměti pouze během aktivní session |
| **MITM attack** | Odposlech komunikace | HTTPS + HSTS; volitelně certificate pinning pro komunikaci se spravovanými weby |
| **Brute force login** | Opakované pokusy na master heslo | Rate limiting (5 pokusů / 15 min), exponential backoff, account lockout |
| **CSRF** | Falešný požadavek od uživatele | CSRF token na všech POST/PUT/DELETE; SameSite=Strict cookies |
| **XSS** | Injection škodlivého JS | React (default escaping), CSP headers, no dangerouslySetInnerHTML |
| **Session hijacking** | Krádež session cookie | HttpOnly + Secure + SameSite=Strict; session binding na IP + User-Agent |
| **Credential leakage** | Únik application password | Šifrováno v DB; v logu pouze maskované (****); v API response nikdy nevraceno |
| **Privilege escalation** | User získá admin práva | Role-based access control; per-site oprávnění; princip nejnižšího oprávnění |

## 2. Master heslo a key derivation

### 2.1 Flow

```
Uživatel zadá master heslo
         │
         ▼
┌─────────────────────┐
│  KeyDerivation       │
│  Argon2id            │
│  - password: input   │
│  - salt: DB-stored   │  ← per-user salt (generován při first setup)
│  - time_cost: 4      │
│  - memory_cost: 64MB │
│  - threads: 4        │
│  - output: 32 bytes  │
└─────────┬───────────┘
          │
          ▼ 32-byte encryption key
┌─────────────────────┐
│  CryptoService       │
│  AES-256-GCM         │
│  - key: derived      │
│  - encrypt/decrypt   │
└─────────────────────┘
```

### 2.2 Key derivation parametry

```php
// Argon2id parametry (dle OWASP doporučení 2024+)
$options = [
    'memory_cost' => 65536,    // 64 MB
    'time_cost'   => 4,        // 4 iterace
    'threads'     => 4,        // 4 vlákna
];
$key = sodium_crypto_pwhash(
    32,                        // 32 bytes = 256 bits
    $masterPassword,
    $salt,                     // 16 bytes, per-user, uložen v DB
    SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
    SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
    SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
);
```

**Poznámka:** Pro interaktivní login se používají `INTERACTIVE` limity (rychlejší). Pro perzistentní šifrování (first setup) se používají `SENSITIVE` limity (pomalější, bezpečnější).

### 2.3 Ukládání klíče v paměti

- Encryption key je uložen **pouze v session** (server-side, šifrovaná session)
- Session je šifrována pomocí `APP_KEY` (environment variable)
- Po `SESSION_TIMEOUT` (default 15 min) nečinnosti je session zničena
- V frontendu je key v Zustand store (in-memory only, bez persist)
- Po reload stránky → uživatel musí znovu zadat master heslo

### 2.4 First setup (inicializace)

1. Uživatel spustí WP Monitor poprvé
2. Zadá master heslo (min 12 znaků, doporučeno passphrase)
3. Systém vygeneruje 16-byte salt (`random_bytes(16)`)
4. Derivuje encryption key pomocí Argon2id
5. Uloží salt do `users` tabulky
6. Vytvoří šifrovaný verification token (známý plaintext → šifrovaný blob) pro ověření hesla při dalších loginech
7. Master heslo **není** nikde uloženo (ani hashovaná verze — používá se verification token)

### 2.5 Login flow

```
1. Uživatel zadá master heslo
2. Systém načte salt z DB pro daného uživatele
3. Derivuje encryption key (Argon2id)
4. Pokusí se dešifrovat verification token
5. Pokud dešifrování uspěje → heslo je správné
6. Encryption key se uloží do šifrované session
7. Vrátí JWT token (pro API autentizaci) + CSRF token
```

**Výhoda tohoto přístupu:** Nepoužíváme hash porovnání (password_verify), ale přímo dešifrování. Tím se ověří nejen heslo, ale i funkčnost klíče pro následné dešifrování credentials.

## 3. Šifrování credentials

### 3.1 Šifrovací schéma

```
AES-256-GCM
├── Key: 32 bytes (derivovaný z master hesla)
├── Nonce: 12 bytes (random_bytes, unikátní pro každý šifrování)
├── Plaintext: application password pro WP REST API
├── AAD (Additional Authenticated Data): site_id + user_id
└── Output: nonce + ciphertext + tag
```

```php
// CryptoService::encrypt()
public function encrypt(string $plaintext, string $aad = ''): string
{
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES); // 12 bytes
    $ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
        $plaintext,
        $aad,                    // Additional Authenticated Data
        $nonce,
        $this->key               // 32-byte key z KeyDerivation
    );
    return base64_encode($nonce . $ciphertext);
}

// CryptoService::decrypt()
public function decrypt(string $encrypted, string $aad = ''): string
{
    $decoded = base64_decode($encrypted);
    $nonce = substr($decoded, 0, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);
    $ciphertext = substr($decoded, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES);

    $plaintext = sodium_crypto_aead_aes256gcm_decrypt(
        $ciphertext,
        $aad,
        $nonce,
        $this->key
    );

    if ($plaintext === false) {
        throw new DecryptionException('Dešifrování selhalo — pravděpodobně špatný klíč nebo manipulace s daty');
    }

    return $plaintext;
}
```

### 3.2 AAD (Additional Authenticated Data)

AAD zaručuje, že šifrovaný blob nelze "přesunout" na jiný web nebo uživatele:

```
AAD = pack('NN', $siteId, $userId)
```

Při dešifrování se AAD ověří — pokud se neshoduje, dešifrování selže.

### 3.3 Lifecycle credentials

```
1. Uživatel zadá application password pro web
2. CryptoService::encrypt(password, AAD=siteId+userId)
3. Šifrovaný blob uložen do site_credentials tabulky
4. Při komunikaci s webem:
   a. CryptoService::decrypt(blob, AAD=siteId+userId)
   b. WordPressClient vytvoří request s dešifrovaným password
   c. Po odeslání requestu se password ihned wipe z paměti (sodium_memzero)
5. V API response se application password NIKDY nevrací
6. V logu se password loguje jako **** (masked)
```

### 3.4 Rotation master hesla

Při změně master hesla:

1. Uživatel zadá staré + nové master heslo
2. Ze starého hesla se derivuje old_key
3. Z nového hesla se derivuje new_key
4. Nový salt se vygeneruje
5. Všechny credentials se dešifrují s old_key a znovu zašifrují s new_key
6. Verification token se znovu zašifruje s new_key
7. Salt se aktualizuje v DB
8. Old_key se wipe z paměti

**Transakce:** Celá operace probíhá v DB transakci — pokud cokoliv selže, vše se rollbackuje.

## 4. Autentizace a autorizace

### 4.1 JWT token

Po úspěšném loginu se vydá JWT token:

```json
{
    "header": {
        "alg": "HS256",
        "typ": "JWT"
    },
    "payload": {
        "sub": 1,                    // user_id
        "role": "admin",             // admin | manager | viewer
        "iat": 1723000000,           // issued at
        "exp": 1723003600,           // expiration (1 hodina)
        "jti": "unique_token_id",    // pro revocation
        "ip": "192.168.1.1",         // IP binding
        "ua": "sha256_hash"          // User-Agent binding
    }
}
```

- **Signing key:** `APP_KEY` (environment variable, base64-encoded 32 bytes)
- **Algorithm:** HS256 (HMAC-SHA256)
- **Expiration:** 1 hodina (konfigurovatelné)
- **IP + UA binding:** Token je vázán na IP a User-Agent z doby vydání — při změně je token invalidován
- **Revocation:** `jti` je uložen v Redis/APCu blacklist (pro logout / force logout)

### 4.2 Session management

| Vlastnost | Hodnota | Důvod |
|-----------|---------|-------|
| Cookie name | `wpm_session` | — |
| HttpOnly | true | Zabrání XSS přístupu |
| Secure | true | Pouze přes HTTPS |
| SameSite | Strict | Zabrání CSRF |
| Lifetime | 900s (15 min) | Konfigurovatelné |
| Sliding expiration | Ano | Při aktivitě se prodlužuje |
| Storage | Server-side (APCu / DB) | Session data na serveru, cookie obsahuje pouze ID |

### 4.3 Role-Based Access Control (RBAC)

```
┌─────────────────────────────────────────────────────────┐
│                        Admin                              │
│  - Všechny oprávnění                                      │
│  - Správa uživatelů                                       │
│  - Globální nastavení                                     │
│  - Správa modulů (enable/disable)                         │
├─────────────────────────────────────────────────────────┤
│                       Manager                              │
│  - Správa webů (CRUD)                                     │
│  - Spouštění operací (updates, backups, scans)           │
│  - Zobrazení logů                                          │
│  - Nelze spravovat uživatele a globální nastavení         │
├─────────────────────────────────────────────────────────┤
│                       Viewer                               │
│  - Pouze prohlížení dashboardu                             │
│  - Pouze prohlížení logů                                   │
│  - Žádné modifikační akce                                 │
└─────────────────────────────────────────────────────────┘
```

### 4.4 Per-site oprávnění

```sql
-- Tabulka user_site_permissions
user_id    | site_id | can_view | can_manage
-----------+---------+----------+-----------
1          | 1       | true     | true
1          | 2       | true     | false
2          | 1       | true     | false
```

Uživatel vidí pouze weby, ke kterým má `can_view = true`. Operace vyžadují `can_manage = true`.

## 5. CSRF ochrana

### 5.1 Token flow

```
1. Po loginu → server vydá CSRF token (random_bytes(32))
2. Token uložen v session (server-side)
3. Token vracen v login response (v JSON, ne v cookie)
4. Frontend ukládá token v Zustand store (in-memory)
5. Každý POST/PUT/DELETE požadavek musí obsahovat header:
   X-CSRF-Token: <token>
6. CsrfMiddleware ověří token proti session
7. Pokud nesouhlasí → 403 Forbidden
```

### 5.2 Double submit pattern

Pro extra ochranu je token také v cookie (`wpm_csrf`, SameSite=Strict, HttpOnly=false):

```
X-CSRF-Token (header) === wpm_csrf (cookie) === session token
```

## 6. Rate limiting

| Endpoint | Limit | Window | Blokace |
|----------|-------|--------|---------|
| `POST /auth/login` | 5 pokusů | 15 min | IP + username |
| `POST /auth/verify` | 10 pokusů | 15 min | IP |
| `GET /api/*` (obecné) | 100 req | 1 min | IP + user |
| `POST /api/*` (obecné) | 30 req | 1 min | IP + user |
| `POST /api/sites/batch` | 5 req | 1 min | IP + user |

**Implementace:** APCu (single-server) nebo Redis (multi-server). Sliding window algoritmus.

Při překročení limitu → `429 Too Many Requests` s `Retry-After` header.

## 7. HTTP security headers

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'
Referrer-Policy: no-referrer
Permissions-Policy: geolocation=(), microphone=(), camera=()
```

**CSP:** Žádné `unsafe-eval`. Inline styly povoleny (TailwindCSS potřebuje), ale inline scripty zakázány.

## 8. Audit log

### 8.1 Struktura

```sql
CREATE TABLE audit_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    action      VARCHAR(100) NOT NULL,      -- 'site.add', 'update.execute', etc.
    site_id     INT UNSIGNED NULL,          -- NULL pro globální akce
    module      VARCHAR(50) NULL,           -- 'updates', 'backups', etc.
    status      ENUM('success','failed','partial') NOT NULL,
    details     JSON NULL,                  -- dodatečné informace
    ip_address  VARCHAR(45) NOT NULL,
    user_agent  VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_site (site_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;
```

### 8.2 Append-only

- Audit log tabulka nemá DELETE ani UPDATE operace (v aplikačním kódu)
- Na DB úrovni lze nastavit trigger, který blokuje UPDATE/DELETE
- Logy mají retenci (default 365 dní) — starší záznamy archivovány

### 8.3 Logované akce

| Akce | Trigger | Detail |
|------|---------|--------|
| `auth.login` | Login | success/failed, IP |
| `auth.logout` | Logout | — |
| `auth.password.change` | Změna master hesla | — |
| `site.add` | Přidání webu | URL, site name |
| `site.remove` | Smazání webu | site_id, URL |
| `site.credential.update` | Změna credentials | site_id (password ****) |
| `update.execute` | Aktualizace | site_id, type (core/plugin/theme), slug, version |
| `update.batch` | Hromadná aktualizace | site_ids[], count, results summary |
| `backup.create` | Vytvoření zálohy | site_id, type (db/files), size |
| `backup.restore` | Obnova ze zálohy | site_id, backup_id |
| `security.scan` | Security scan | site_id, findings count |
| `user.create` | Vytvoření uživatele | username, role |
| `user.role.change` | Změna role | user_id, old_role, new_role |
| `module.enable` | Aktivace modulu | module_id |
| `module.disable` | Deaktivace modulu | module_id |
| `settings.update` | Změna nastavení | key, old_value, new_value |

## 9. Komunikace se spravovanými weby — bezpečnost

### 9.1 Application Passwords

- WordPress Application Passwords (WP 5.6+) generované per-site
- Každá application password je 24-znakový token s právy daného uživatele
- WP Monitor ukládá tento token zašifrovaný (AES-256-GCM)
- Při požadavku se posílá jako Basic Auth header: `Authorization: Basic base64(user:app_password)`
- **Nevýhoda:** Application Password má stejná práva jako uživatel — nelze omezit na konkrétní akce
- **Mitigace:** Doporučuje se vytvořit dedikovaného WP uživatele s omezenými právy (Editor nebo vlastní role) pro WP Monitor

### 9.2 MU-plugin signed requests

Pro rozšířené funkce (file management, DB operations) se používá MU-plugin s HMAC autentizací:

```
1. Při instalaci MU-pluginu se vygeneruje sdílený klíč (32 bytes)
2. Klíč se uloží zašifrovaný v WP Monitor (jako credential)
3. MU-plugin si klíč ukládá do wp_options (zašifrovaný AES-256, klíč derivovaný z konstanty v wp-config.php)
4. Každý požadavek na MU-plugin endpointy obsahuje:
   - X-WPM-Signature: HMAC-SHA256(path + body + timestamp, shared_key)
   - X-WPM-Timestamp: unix_timestamp
   - X-WPM-Nonce: unique_nonce (prevence replay attack)
5. MU-plugin ověří:
   - Signature (HMAC)
   - Timestamp (max 5 minut starý)
   - Nonce (není v cache — prevence replay)
```

### 9.3 HTTPS enforcement

- Všechny komunikace se spravovanými weby **musí** být přes HTTPS
- HTTP URL jsou automaticky odmítnuta při přidávání webu
- Guzzle client je konfigurován s `verify: true` (SSL verification povinná)
- Volitelný certificate pinning (uložení fingerprintu při prvním připojení, ověření při dalších)

## 10. Zálohování a obnova — bezpečnost

- Zálohy obsahují citlivá data (DB dump) → musí být šifrovány
- Šifrování záloh: AES-256-GCM s klíčem derivovaným z master hesla (nebo samostatného backup key)
- Zálohy ukládány s náhodným názvem (ne predikovatelným)
- Přístup k zálohám vyžaduje auth + master heslo (dešifrování)
- Retence: automatický delete po N dnech (konfigurovatelné)
- Při obnově: audit log + potvrzení uživatele

## 11. Šifrování v frontendu (volitelné)

Pro maximální security může frontend provádět šifrování/dešifrování credentials přímo v prohlížeči pomocí Web Crypto API:

```
1. Master heslo → PBKDF2 (100k iterací) → encryption key (browser-side)
2. Credentials šifrovány v browseru před odesláním na server
3. Server ukládá pouze šifrovaný blob (nikdy nevidí plaintext)
4. Při čtení: server pošle šifrovaný blob → browser dešifruje
```

**Výhoda:** Server nikdy nevidí plaintext credentials ani encryption key.
**Nevýhoda:** Složitější implementace, browser musí podporovat Web Crypto API.

**Rozhodnutí:** Implementováno jako volitelné (config: `client_side_encryption = true/false`). Default: false (server-side šifrování pro jednoduchost, lze přepnout na client-side pro maximální security).

## 12. Vulnerability management

- **Dependency scanning:** `composer audit` + `npm audit` v CI/CD
- **Security headers:** Automaticky testováno při deploy
- **WordPress core vulnerabilities:** Security modul kontroluje verzi WordPress oproti CVE databázi
- **Plugin vulnerabilities:** Security modul kontroluje oproti WPScan / Patchstack API
- **Penetration testing:** Doporučeno před produkčním nasazením

## 13. Incident response

| Scénář | Akce |
|--------|------|
| **Podezření na kompromitaci DB** | Změnit master heslo (rotace všech credentials), audit log analýza |
| **Podezření na kompromitaci serveru** | Změnit master heslo, rotace APP_KEY, revokace všech JWT, audit log analýza |
| **Podezření na kompromitaci master hesla** | Změna master hesla (rotace credentials), audit log analýza |
| **Narušení spravovaného webu** | Revokace application password pro daný web, generování nového, audit log |
