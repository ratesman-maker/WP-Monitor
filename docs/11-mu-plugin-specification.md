# WP Monitor — WordPress MU-Plugin specifikace

## 1. Přehled

MU-plugin (Must-Use plugin) je PHP soubor instalovaný na spravovaných WordPress webech. Poskytuje rozšířenou funkcionalitu, kterou standardní WP REST API nenabízí — zálohování, security scanning, správa souborů, detailed plugin info.

**Klíčové vlastnosti:**
- Nelze deaktivovat z WP admin (must-use = vždy aktivní)
- Automaticky se načítá před ostatními pluginy
- Komunikace probíhá přes REST API s **podepsanými požadavky** (HMAC-SHA256)
- Nepřidává žádné UI do WordPress admina
- Minimální footprint — nezpomaluje WordPress

## 2. Architektura

```
WP Monitor (backend)
    ↓ HTTPS požadavek s HMAC-SHA256 podpisem
WordPress MU-plugin (wp-monitor-client.php)
    ↓ REST API endpointy pod /wp-json/wp-monitor/v1/
    ↓ Vykoná akci (backup, scan, file ops)
    ↓ Vrátí JSON response
WP Monitor (backend)
```

## 3. Instalace MU-pluginu

### 3.1 Automatická instalace

WP Monitor backend se pokusí nainstalovat MU-plugin automaticky přes WP REST API + Application Password:

```
POST /wp-json/wp-monitor/v1/install
Body: { mu_plugin_content: "base64 encoded PHP" }
```

### 3.2 Manuální instalace

1. Zkopírovat `wp-monitor-client.php` do `wp-content/mu-plugins/`
2. Vytvořit `wp-content/mu-plugins/wp-monitor-client.php` (nelze do podsložky — MU-plugins nepodporují složky přímo)

### 3.3 Verzování MU-pluginu

```php
// Hlavička souboru
/**
 * Plugin Name: WP Monitor Client
 * Version:     1.0.0
 * Description: Klient pro WP Monitor — hromadná administrace WordPress webů
 * Author:      WP Monitor
 * License:     Proprietary
 */
```

WP Monitor backend kontroluje verzi MU-pluginu při každém připojení k webu. Pokud je zastaralá, nabídne aktualizaci.

## 4. Bezpečnost — podepsané požadavky

### 4.1 HMAC-SHA256 podpis

Každý požadavek od WP Monitor k MU-pluginu je podepsán:

```
Authorization: WPMonitor-HMAC-SHA256 {siteId}:{timestamp}:{nonce}:{signature}

signature = HMAC-SHA256(
    key = site_secret,  // 32-byte náhodný klíč generovaný při registraci webu
    message = HTTP_METHOD + "\n" + REQUEST_PATH + "\n" + TIMESTAMP + "\n" + NONCE + "\n" + BODY_HASH
)
```

### 4.2 Ochrany

| Ochrana | Implementace |
|---------|-------------|
| **Replay attack** | Timestamp ± 5 minut + nonce (uložen 15 minut) |
| **Man-in-the-middle** | HTTPS vynuceno + certifikát pinning (volitelně) |
| **Brute force** | Rate limiting na IP (10 req/min) |
| **Neoprávněný přístup** | HMAC-SHA256 s per-site secret |
| **CSRF** | N/A — REST API, ne cookie-based auth |

### 4.3 Site secret rotace

```php
// Rotace secret (volitelné, doporučeno každých 90 dní)
// WP Monitor → MU-plugin: POST /wp-json/wp-monitor/v1/rotate-secret
// Body: { new_secret: "encrypted:..." }
// MU-plugin uloží nový secret, starý zneplatní
```

## 5. REST API endpointy

Všechny endpointy jsou pod namespace `wp-monitor/v1`.

### 5.1 Systémové

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/wp-json/wp-monitor/v1/info` | Systémové info (WP verze, PHP verze, moduly) |
| GET | `/wp-json/wp-monitor/v1/health` | Základní health check |
| GET | `/wp-json/wp-monitor/v1/version` | Verze MU-pluginu |

### 5.2 Updates

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/wp-json/wp-monitor/v1/updates` | Dostupné aktualizace (core, plugins, themes) |
| POST | `/wp-json/wp-monitor/v1/updates/core` | Aktualizace WP core |
| POST | `/wp-json/wp-monitor/v1/updates/plugin/{slug}` | Aktualizace konkrétního pluginu |
| POST | `/wp-json/wp-monitor/v1/updates/theme/{slug}` | Aktualizace konkrétního themu |
| POST | `/wp-json/wp-monitor/v1/updates/all` | Aktualizovat vše |
| GET | `/wp-json/wp-monitor/v1/updates/history` | Historie aktualizací |

### 5.3 Backups

| Metoda | Cesta | Popis |
|--------|-------|-------|
| POST | `/wp-json/wp-monitor/v1/backup/db` | Záloha databáze |
| POST | `/wp-json/wp-monitor/v1/backup/files` | Záloha souborů |
| POST | `/wp-json/wp-monitor/v1/backup/full` | Kompletní záloha (DB + files) |
| GET | `/wp-json/wp-monitor/v1/backup/status/{jobId}` | Status běžící zálohy |
| POST | `/wp-json/wp-monitor/v1/backup/restore` | Obnova ze zálohy |

### 5.4 Security & Monitoring

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/wp-json/wp-monitor/v1/security/scan` | Security scan (file integrity, suspicious code) |
| GET | `/wp-json/wp-monitor/v1/security/users` | Seznam uživatelů s rolemi |
| GET | `/wp-json/wp-monitor/v1/security/plugins` | Detailní info o pluginech (checksum, vulnerability hints) |
| POST | `/wp-json/wp-monitor/v1/security/hardening` | Aplikovat security hardening (zakázat file editor, etc.) |

### 5.5 SEO & Performance

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/wp-json/wp-monitor/v1/seo/meta` | SEO metadata (title, description, OG, headings) |
| GET | `/wp-json/wp-monitor/v1/seo/sitemap` | Sitemap status a struktura |
| GET | `/wp-json/wp-monitor/v1/seo/robots` | robots.txt obsah |
| GET | `/wp-json/wp-monitor/v1/performance/options` | Performance relevantní WP options (cache, compression) |

### 5.6 File operations

| Metoda | Cesta | Popis |
|--------|-------|-------|
| GET | `/wp-json/wp-monitor/v1/files/list` | Seznam souborů v wp-content |
| GET | `/wp-json/wp-monitor/v1/files/read` | Čtení obsahu souboru (omezené na wp-content) |
| POST | `/wp-json/wp-monitor/v1/files/write` | Zápis souboru (omezené na wp-content) |
| DELETE | `/wp-json/wp-monitor/v1/files/delete` | Smazání souboru (omezené na wp-content) |

## 6. Implementace — struktura MU-pluginu

```php
<?php
/**
 * Plugin Name: WP Monitor Client
 * Version:     1.0.0
 * Description: Klient pro WP Monitor
 * Author:      WP Monitor
 * License:     Proprietary
 */

// Zákaz přímého přístupu
if (!defined('ABSPATH')) {
    exit;
}

// Konfigurace
define('WP_MONITOR_CLIENT_VERSION', '1.0.0');
define('WP_MONITOR_CLIENT_NAMESPACE', 'wp-monitor/v1');
define('WP_MONITOR_NONCE_TTL', 900);      // 15 minut
define('WP_MONITOR_TIMESTAMP_TOLERANCE', 300); // ± 5 minut
define('WP_MONITOR_RATE_LIMIT', 10);       // req/min per IP

// Načtení modulů
require_once __DIR__ . '/wp-monitor-client/auth.php';       // HMAC autentizace
require_once __DIR__ . '/wp-monitor-client/info.php';       // Systémové info
require_once __DIR__ . '/wp-monitor-client/updates.php';    // Aktualizace
require_once __DIR__ . '/wp-monitor-client/backups.php';    // Zálohy
require_once __DIR__ . '/wp-monitor-client/security.php';   // Security scan
require_once __DIR__ . '/wp-monitor-client/seo.php';        // SEO & Performance
require_once __DIR__ . '/wp-monitor-client/files.php';      // File operations

// Registrace REST routes
add_action('rest_api_init', 'wp_monitor_register_routes');
```

### 6.1 Autentizace (auth.php)

```php
class WPMonitorAuth
{
    private static ?string $siteSecret = null;
    private static array $usedNonces = [];

    public static function verify(WP_REST_Request $request): bool|WP_Error
    {
        // 1. Parsování Authorization header
        $auth = $request->get_header('authorization');
        if (!preg_match('/WPMonitor-HMAC-SHA256 (\d+):(\d+):([a-f0-9]+):([a-f0-9]+)/', $auth, $m)) {
            return new WP_Error('missing_auth', 'Missing or invalid auth header', ['status' => 401]);
        }

        [$siteId, $timestamp, $nonce, $signature] = array_slice($m, 1);

        // 2. Timestamp tolerance (± 5 minut)
        if (abs(time() - (int)$timestamp) > WP_MONITOR_TIMESTAMP_TOLERANCE) {
            return new WP_Error('expired', 'Request timestamp expired', ['status' => 401]);
        }

        // 3. Nonce kontrola (replay attack)
        if (isset(self::$usedNonces[$nonce])) {
            return new WP_Error('replay', 'Nonce already used', ['status' => 401]);
        }
        self::$usedNonces[$nonce] = time();

        // 4. HMAC-SHA256 verifikace
        $secret = self::getSiteSecret();
        $expectedMessage = $request->get_method() . "\n"
            . $request->get_route() . "\n"
            . $timestamp . "\n"
            . $nonce . "\n"
            . hash('sha256', $request->get_body());

        $expectedSignature = hash_hmac('sha256', $expectedMessage, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return new WP_Error('invalid_signature', 'Invalid HMAC signature', ['status' => 401]);
        }

        // 5. Rate limiting
        if (!self::checkRateLimit($_SERVER['REMOTE_ADDR'])) {
            return new WP_Error('rate_limited', 'Rate limit exceeded', ['status' => 429]);
        }

        return true;
    }

    private static function getSiteSecret(): string
    {
        if (self::$siteSecret === null) {
            // Secret uložen v WP options (šifrovaný)
            self::$siteSecret = get_option('wp_monitor_secret', '');
        }
        return self::$siteSecret;
    }

    private static function checkRateLimit(string $ip): bool
    {
        $key = 'wp_monitor_rl_' . md5($ip);
        $count = (int) get_transient($key);
        if ($count >= WP_MONITOR_RATE_LIMIT) {
            return false;
        }
        set_transient($key, $count + 1, 60);
        return true;
    }
}
```

### 6.2 Záloha databáze (backups.php)

```php
class WPMonitorBackups
{
    public function backupDb(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        $compress = $params['compress'] ?? 'gzip';  // gzip | zstd | none
        $encrypt = $params['encrypt'] ?? true;

        // 1. Generování dumpu
        $dump = $this->generateDbDump();

        // 2. Komprese
        if ($compress === 'gzip') {
            $dump = gzencode($dump);
        }

        // 3. Šifrování (AES-256-GCM s backup key)
        if ($encrypt) {
            $dump = $this->encrypt($dump, $params['backup_key']);
        }

        // 4. Vrácení base64-encoded dumpu
        return new WP_REST_Response([
            'status' => 'success',
            'size' => strlen($dump),
            'compressed' => $compress !== 'none',
            'encrypted' => $encrypt,
            'data' => base64_encode($dump),
        ], 200);
    }

    private function generateDbDump(): string
    {
        global $wpdb;
        $tables = $wpdb->get_col("SHOW TABLES");
        $dump = "";

        foreach ($tables as $table) {
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            $dump .= $create[1] . ";\n\n";

            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            foreach ($rows as $row) {
                $values = array_map(fn($v) => "'" . $wpdb->_escape($v) . "'", $row);
                $dump .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
            }
            $dump .= "\n";
        }

        return $dump;
    }
}
```

## 7. Chybové response

```json
// 401 — Neoprávněný přístup
{
    "code": "invalid_signature",
    "message": "Invalid HMAC signature",
    "data": { "status": 401 }
}

// 429 — Rate limit
{
    "code": "rate_limited",
    "message": "Rate limit exceeded",
    "data": { "status": 429, "retry_after": 60 }
}

// 500 — Chyba serveru
{
    "code": "backup_failed",
    "message": "Database dump failed: Permission denied",
    "data": { "status": 500 }
}
```

## 8. Omezení a bezpečnostní pravidla

1. **Žádné `eval()`, `exec()`, `shell_exec()`** — veškerá funkcionalita čistě přes WP API
2. **File operations omezeny na `wp-content/`** — nelze přistupovat mimo
3. **Žádné ukládání credentials** — site secret je v WP options, šifrovaný
4. **Rate limiting** — 10 požadavků/min na IP
5. **Nonce + timestamp** — ochrana proti replay attack
6. **HTTPS vynuceno** — MU-plugin odmítne ne-HTTPS požadavky (kromě localhost)
7. **Audit log** — každá akce je logována do WP options (posledních 100 akcí)
8. **Minimální footprint** — MU-plugin se načítá až po WP core, nepřidává hooks do admina

## 9. Kompatibilita

| WordPress verze | Podpora | Poznámka |
|-----------------|---------|----------|
| 6.4+ | ✅ Plná | Všechny funkce |
| 6.0–6.3 | ⚠️ Částečná | Application Passwords vyžadují 5.6+ |
| 5.6–5.9 | ⚠️ Omezená | Bez REST API rozšíření |
| < 5.6 | ❌ Nepodporováno | Bez Application Passwords |

## 10. Aktualizace MU-pluginu

WP Monitor backend automaticky detekuje zastaralou verzi MU-pluginu:

```
1. GET /wp-json/wp-monitor/v1/version → "1.0.0"
2. Porovnání s nejnovější verzí v WP Monitor
3. Pokud zastaralá → nabídnout aktualizaci v UI
4. Po schválení: POST /wp-json/wp-monitor/v1/install s novým kódem
5. MU-plugin se přepíše, verze se aktualizuje
```
