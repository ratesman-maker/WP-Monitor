---
name: security-coding
description: Bezpečnostní pravidla pro psaní kódu — šifrování, auth, XSS, CSRF, audit log. Aplikuje se při každém psaní nebo úpravě kódu.
triggers:
  - model
---

# Skill: Security Coding

> Tato pravidla se aplikují při každém psaní nebo úpravě kódu.
> Bezpečnost je NON-NEGOTIABLE — žádné kompromisy, žádné výjimky.

## PHP Backend

### Šifrování a credentials

```php
// ✅ VŽDY: Šifrovat credentials přes CryptoService s AAD
$encrypted = $this->crypto->encrypt($plaintext, [
    'site_id' => $site->id,
    'user_id' => $userId,
]);
// AAD = Additional Authenticated Data — váže šifrovaná data na kontext

// ✅ VŽDY: Wipe dešifrovaná data z paměti
$decrypted = $this->crypto->decrypt($encrypted, $aad);
// ... použít $decrypted ...
sodium_memzero($decrypted);  // nikdy nezapomenout!

// ❌ NIKDY: Nešifrovat bez AAD
$encrypted = $this->crypto->encrypt($plaintext);  // chybí AAD!

// ❌ NIKDY: Nelogovat plaintext credentials
$this->logger->info("Connecting with password: " . $password);  // ZÁKAZ!

// ❌ NIKDY: Nevracet credentials v API response
return $site->wpPassword;  // ZÁKAZ! Vracet jen metadata
```

### Input validace

```php
// ✅ VŽDY: Validovat input na API endpointu
$route->post('/api/sites', function (Request $req, Response $res) {
    $data = $this->validate($req, [
        'url' => 'required|url|max:255',
        'name' => 'required|string|max:100',
        'wpUsername' => 'required|string|max:60',
        'wpPassword' => 'required|string|min:8',
    ]);
    // ...
});

// ✅ VŽDY: Sanitizovat string input
$name = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

// ✅ VŽDY: Použít prepared statements (Doctrine DBAL)
$qb = $this->conn->createQueryBuilder();
$qb->select('*')->from('sites')->where('id = :id')->setParameter('id', $id);

// ❌ NIKDY: SQL injection
$conn->query("SELECT * FROM sites WHERE id = " . $id);  // ZÁKAZ!
```

### Zakázané funkce

```php
// ❌ NIKDY tyto funkce v kódu:
eval()           // RCE zranitelnost
exec()           // RCE zranitelnost
shell_exec()     // RCE zranitelnost
system()         // RCE zranitelnost
passthru()       // RCE zranitelnost
proc_open()      // RCE zranitelnost
file_get_contents() s user input  // Path traversal
include/require s user input      // LFI/RFI
unserialize() s user input        // Object injection
```

### Session a autentizace

```php
// ✅ VŽDY: Argon2id pro password hashing
$hash = sodium_crypto_pwhash_str(
    $password,
    SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
    SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE
);

// ✅ VŽDY: JWT s krátkým TTL (15 min) + refresh token (7 dní)
// ✅ VŽDY: Rate limiting na login (5 pokusů / 15 min)
// ✅ VŽDY: Session timeout (15 min neaktivita)
// ✅ VŽDY: Audit log na každý auth event (login, logout, failed, refresh)

// ❌ NIKDY: Ukládat JWT v localStorage (pouze v memory)
// ❌ NIKDY: Ukládat master heslo (jen hash + salt pro key derivation)
```

### CSRF ochrana

```php
// ✅ VŽDY: CSRF token na POST/PUT/DELETE
$csrfToken = $this->csrf->generate();
// Frontend odesílá v hlavičce: X-CSRF-Token: {token}
// Backend validuje před zpracováním
```

### Audit log

```php
// ✅ VŽDY: Logovat security-relevant akce
$this->auditLogger->log([
    'action' => 'site.credentials.updated',
    'user_id' => $userId,
    'site_id' => $siteId,
    'ip' => $req->getServerParam('REMOTE_ADDR'),
    'user_agent' => $req->getServerParam('HTTP_USER_AGENT'),
    'timestamp' => time(),
]);

// Audit log je APPEND-ONLY — nikdy nemazat ani upravovat záznamy
```

## TypeScript Frontend

### XSS ochrana

```tsx
// ❌ NIKDY: dangerouslySetInnerHTML s user input
<div dangerouslySetInnerHTML={{ __html: userContent }} />  // ZÁKAZ!

// ✅ VŽDY: React automaticky escapuje obsah
<div>{userContent}</div>  // bezpečné

// ✅ Pokud potřebuješ HTML, sanitizuj přes DOMPurify
import DOMPurify from 'dompurify';
<div dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(userContent) }} />
```

### Token storage

```tsx
// ❌ NIKDY: JWT v localStorage
localStorage.setItem('token', jwt);  // ZÁKAZ! XSS únik

// ✅ VŽDY: JWT v memory (React state/context)
const [accessToken, setAccessToken] = useState<string | null>(null);

// ✅ Refresh token v httpOnly cookie (nastavuje backend)
```

### API volání

```tsx
// ✅ VŽDY: Validovat API response přes Zod
const SiteSchema = z.object({
    id: z.number(),
    url: z.string().url(),
    name: z.string(),
});
const sites = SiteSchema.array().parse(await response.json());

// ✅ VŽDY: HTTPS pro API volání (Vite proxy v dev, Apache/Nginx v prod)
// ❌ NIKDY: Neodesílat credentials v URL parametrech
fetch(`/api/sites?password=${password}`)  // ZÁKAZ!
```

## Checklist před každým commitem

- [ ] Žádné plaintext credentials v kódu, logu nebo API response
- [ ] Všechny credentials šifrovány přes CryptoService s AAD
- [ ] `sodium_memzero()` po dešifrování
- [ ] Input validace na všech API endpointech
- [ ] CSRF token na POST/PUT/DELETE
- [ ] Žádné `eval()`, `exec()`, `shell_exec()`, `system()`
- [ ] Žádné `dangerouslySetInnerHTML` bez sanitizace
- [ ] JWT v memory, ne v localStorage
- [ ] Audit log na security akce
- [ ] HTTPS vynuceno pro WP client

## Testovací checklist — Security

### Unit testy (PHPUnit + Vitest)

- [ ] **CryptoService** — test encrypt → decrypt round-trip
- [ ] **CryptoService** — test decrypt s neplatným AAD vyhodí výjimku
- [ ] **CryptoService** — test decrypt s poškozeným ciphertext vyhodí výjimku
- [ ] **KeyDerivation** — test Argon2id produkuje stejný klíč pro stejné heslo + salt
- [ ] **KeyDerivation** — test různé heslo → různý klíč
- [ ] **JwtService** — test generování a validace platného tokenu
- [ ] **JwtService** — test expirovaný token je odmítnut
- [ ] **JwtService** — test neplatný podpis je odmítnut
- [ ] **CsrfService** — test token generování a validace
- [ ] **CsrfService** — test neplatný token je odmítnut
- [ ] **RateLimiter** — test limit je dodržen (5 pokusů)
- [ ] **RateLimiter** — test reset po time window
- [ ] **AuditLogger** — test záznam je append-only (nelze upravit)
- [ ] **Input validace** — test každý endpoint odmítne neplatný input (400)
- [ ] **Input validace** — test SQL injection pokus je odmítnut
- [ ] **Input validace** — test XSS pokus je odmítnut/sanitizován

### Integration testy

- [ ] **Login flow** — test správné heslo → JWT + refresh token
- [ ] **Login flow** — test špatné heslo → 401 + rate limit counter++
- [ ] **Login flow** — test 5x špatné heslo → 429 rate limited
- [ ] **CSRF** — test POST bez CSRF tokenu → 403
- [ ] **CSRF** — test POST s platným CSRF → 200/201
- [ ] **API response** — test žádný endpoint nevrací šifrovaná data v plaintext
- [ ] **API response** — test `/api/sites` nevrací `wp_password` field
- [ ] **Audit log** — test login/logout/failed/credentials.update jsou logovány
- [ ] **Session** — test session timeout po 15 min neaktivity
- [ ] **Refresh token** — test rotation (starý refresh je zneplatněn po použití)

### Frontend testy (Vitest + React Testing Library)

- [ ] **XSS** — test `dangerouslySetInnerHTML` není použito bez DOMPurify
- [ ] **Token storage** — test JWT není uložen v localStorage
- [ ] **API validace** — test Zod schema odmítne neplatnou API response
- [ ] **Auth guard** — test neautentizovaný uživatel je přesměrován na login
- [ ] **Auth guard** — test expirovaný token → refresh → retry nebo logout

### Manuální / penetrační testy

- [ ] **OWASP Top 10** — projít všech 10 kategorií
- [ ] **Burp Suite / OWASP ZAP** — scan API endpointů
- [ ] **SQL injection** — sqlmap na všechny GET parametry
- [ ] **XSS** — test `<script>` v input polích
- [ ] **CSRF** — test cross-origin POST požadavek je odmítnut
- [ ] **Rate limiting** — test 100 rychlých požadavků → 429
- [ ] **SSL/TLS** — testssl.sh nebo ssllabs.com (min. grade A)
- [ ] **Security headers** — curl -sI kontrola (HSTS, CSP, X-Frame-Options, X-Content-Type-Options)
- [ ] **Dependency scan** — `composer audit` + `npm audit --audit-level=high`
- [ ] **CodeQL** — GitHub Actions prošel bez alertů
