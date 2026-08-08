# WP Monitor — Environment Variables

## 1. Backend (`backend/.env`)

### 1.1 Aplikace

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `APP_ENV` | ✅ | `production` | `production` / `development` / `testing` |
| `APP_DEBUG` | ✅ | `false` | Zapne detailní chybové zprávy (jen dev!) |
| `APP_KEY` | ✅ | — | 32-byte klíč pro šifrování (base64 encoded) |
| `APP_URL` | ✅ | — | URL aplikace (např. `https://wp-monitor.example.com`) |
| `APP_VERSION` | — | `1.0.0` | Verze aplikace |

### 1.2 Databáze

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `DB_HOST` | ✅ | `127.0.0.1` | DB host |
| `DB_PORT` | — | `3306` | DB port |
| `DB_NAME` | ✅ | `wp_monitor` | Název databáze |
| `DB_USER` | ✅ | — | DB uživatel |
| `DB_PASSWORD` | ✅ | — | DB heslo |
| `DB_CHARSET` | — | `utf8mb4` | Charset |

### 1.3 Bezpečnost — šifrování

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `MASTER_PASSWORD_SALT` | ✅ | — | 16-byte salt pro Argon2id key derivation |
| `ARGON2_MEMORY_COST` | — | `65536` | Argon2id memory cost (KiB) — INTERACTIVE |
| `ARGON2_TIME_COST` | — | `3` | Argon2id time cost (iterace) |
| `ARGON2_THREADS` | — | `4` | Argon2id paralelní vlákna |
| `BACKUP_ENCRYPTION_KEY` | ✅ | — | 32-byte klíč pro šifrování záloh (base64) |

### 1.4 Bezpečnost — session & auth

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `SESSION_TIMEOUT` | — | `900` | Session timeout v sekundách (15 min) |
| `JWT_TTL` | — | `3600` | JWT token TTL v sekundách (1 hodina) |
| `JWT_REFRESH_TTL` | — | `604800` | Refresh token TTL (7 dní) |
| `JWT_ISSUER` | — | `wp-monitor` | JWT issuer claim |
| `RATE_LIMIT_LOGIN` | — | `5` | Max login pokusů za 15 min |
| `RATE_LIMIT_API` | — | `60` | Max API požadavků za minutu |

### 1.5 WordPress client

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `WP_CLIENT_TIMEOUT` | — | `30` | HTTP timeout pro WP požadavky (sekundy) |
| `WP_CLIENT_CONNECT_TIMEOUT` | — | `10` | Connect timeout (sekundy) |
| `WP_CLIENT_CONCURRENCY` | — | `10` | Max paralelní požadavky (Guzzle pool) |
| `WP_CLIENT_RETRY` | — | `2` | Počet retry pokusů |
| `WP_CLIENT_VERIFY_SSL` | — | `true` | Ověřovat SSL certifikáty |
| `WP_CLIENT_USER_AGENT` | — | `WP-Monitor/1.0` | User-Agent header |

### 1.6 Logging

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `LOG_LEVEL` | — | `warning` | `debug` / `info` / `warning` / `error` |
| `LOG_PATH` | — | `storage/logs` | Cesta k log adresáři |
| `LOG_MAX_FILES` | — | `30` | Max počet log souborů (rotace) |

### 1.7 Storage — zálohy

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `BACKUP_STORAGE_BACKEND` | — | `local` | `local` / `s3` / `sftp` |
| `BACKUP_LOCAL_PATH` | — | `storage/backups` | Cesta pro lokální zálohy |
| `BACKUP_MAX_DISK_USAGE` | — | `50GB` | Alert při překročení |
| `BACKUP_DEFAULT_COMPRESSION` | — | `gzip` | `gzip` / `zstd` / `none` |
| `BACKUP_DEFAULT_RETENTION_DAILY` | — | `7` | Počet denních záloh |
| `BACKUP_DEFAULT_RETENTION_WEEKLY` | — | `4` | Počet týdenních záloh |
| `BACKUP_DEFAULT_RETENTION_MONTHLY` | — | `12` | Počet měsíčních záloh |

### 1.8 Storage — S3 (volitelné)

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `S3_ENDPOINT` | — | — | S3 endpoint (AWS nebo S3-compatible) |
| `S3_BUCKET` | — | — | Název bucketu |
| `S3_REGION` | — | — | Region |
| `S3_ACCESS_KEY` | — | — | Access key (šifrovaný v DB, ne v .env!) |
| `S3_SECRET_KEY` | — | — | Secret key (šifrovaný v DB, ne v .env!) |
| `S3_PATH` | — | `backups/` | Prefix v bucketu |

### 1.9 Storage — SFTP (volitelné)

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `SFTP_HOST` | — | — | SFTP host |
| `SFTP_PORT` | — | `22` | SFTP port |
| `SFTP_USERNAME` | — | — | Uživatelské jméno |
| `SFTP_PASSWORD` | — | — | Heslo (šifrované v DB) |
| `SFTP_PRIVATE_KEY` | — | — | Cesta k SSH klíči |
| `SFTP_PATH` | — | `/home/backup/` | Cesta na SFTP serveru |

### 1.10 Monitoring

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `MONITORING_INTERVAL` | — | `60` | Uptime check interval (sekundy) |
| `MONITORING_TIMEOUT` | — | `10` | Uptime check timeout (sekundy) |
| `MONITORING_SSL_WARN_DAYS` | — | `30` | SSL warning (dny do expirace) |
| `MONITORING_SSL_CRITICAL_DAYS` | — | `7` | SSL critical (dny do expirace) |
| `MONITORING_RESPONSE_TIME_WARN` | — | `3000` | Response time warning (ms) |

### 1.11 CORS

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `CORS_ALLOWED_ORIGINS` | — | `*` | Povolené origins (comma-separated) |
| `CORS_ALLOWED_METHODS` | — | `GET,POST,PUT,DELETE,OPTIONS` | Povolené HTTP metody |
| `CORS_ALLOWED_HEADERS` | — | `Content-Type,Authorization,X-CSRF-Token` | Povolené headers |

### 1.12 Email (volitelné)

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `MAIL_DRIVER` | — | `smtp` | `smtp` / `sendmail` / `disabled` |
| `MAIL_HOST` | — | — | SMTP host |
| `MAIL_PORT` | — | `587` | SMTP port |
| `MAIL_USERNAME` | — | — | SMTP uživatel |
| `MAIL_PASSWORD` | — | — | SMTP heslo |
| `MAIL_FROM` | — | `noreply@wp-monitor.example.com` | Odesílatel |
| `MAIL_ENCRYPTION` | — | `tls` | `tls` / `ssl` / `none` |

## 2. Frontend (`frontend/.env`)

### 2.1 Build-time

| Proměnná | Povinná | Default | Popis |
|----------|---------|---------|-------|
| `VITE_API_URL` | ✅ | — | URL backend API (např. `https://wp-monitor.example.com/api`) |
| `VITE_APP_NAME` | — | `WP Monitor` | Název aplikace |
| `VITE_APP_VERSION` | — | `1.0.0` | Verze |
| `VITE_ENABLE_CLIENT_CRYPTO` | — | `false` | Zapne client-side šifrování (Web Crypto API) |

### 2.2 Runtime (window.__ENV__)

Proměnné s prefixem `VITE_` jsou nahrazeny při buildu. Pro runtime konfiguraci (bez rebuildu):

```html
<!-- index.html -->
<script>
  window.__ENV__ = {
    VITE_API_URL: "https://wp-monitor.example.com/api",
    VITE_APP_NAME: "WP Monitor"
  };
</script>
```

## 3. Ukázkový `.env.example`

### 3.1 Backend

```env
# Aplikace
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:REPLACE_WITH_GENERATED_KEY
APP_URL=https://wp-monitor.example.com
APP_VERSION=1.0.0

# Databáze
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=wp_monitor
DB_USER=wp_monitor
DB_PASSWORD=CHANGE_ME
DB_CHARSET=utf8mb4

# Šifrování
MASTER_PASSWORD_SALT=CHANGE_ME_16_BYTES
ARGON2_MEMORY_COST=65536
ARGON2_TIME_COST=3
ARGON2_THREADS=4
BACKUP_ENCRYPTION_KEY=base64:REPLACE_WITH_GENERATED_KEY

# Session & Auth
SESSION_TIMEOUT=900
JWT_TTL=3600
JWT_REFRESH_TTL=604800
JWT_ISSUER=wp-monitor
RATE_LIMIT_LOGIN=5
RATE_LIMIT_API=60

# WordPress client
WP_CLIENT_TIMEOUT=30
WP_CLIENT_CONNECT_TIMEOUT=10
WP_CLIENT_CONCURRENCY=10
WP_CLIENT_RETRY=2
WP_CLIENT_VERIFY_SSL=true
WP_CLIENT_USER_AGENT=WP-Monitor/1.0

# Logging
LOG_LEVEL=warning
LOG_PATH=storage/logs
LOG_MAX_FILES=30

# Backup storage
BACKUP_STORAGE_BACKEND=local
BACKUP_LOCAL_PATH=storage/backups
BACKUP_MAX_DISK_USAGE=50GB
BACKUP_DEFAULT_COMPRESSION=gzip
BACKUP_DEFAULT_RETENTION_DAILY=7
BACKUP_DEFAULT_RETENTION_WEEKLY=4
BACKUP_DEFAULT_RETENTION_MONTHLY=12

# Monitoring
MONITORING_INTERVAL=60
MONITORING_TIMEOUT=10
MONITORING_SSL_WARN_DAYS=30
MONITORING_SSL_CRITICAL_DAYS=7
MONITORING_RESPONSE_TIME_WARN=3000

# CORS
CORS_ALLOWED_ORIGINS=https://wp-monitor.example.com
CORS_ALLOWED_METHODS=GET,POST,PUT,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-CSRF-Token

# Email (volitelné)
MAIL_DRIVER=disabled
```

### 3.2 Frontend

```env
VITE_API_URL=https://wp-monitor.example.com/api
VITE_APP_NAME=WP Monitor
VITE_APP_VERSION=1.0.0
VITE_ENABLE_CLIENT_CRYPTO=false
```

## 4. Generování klíčů

```bash
# APP_KEY (32 bytes, base64)
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"

# MASTER_PASSWORD_SALT (16 bytes)
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"

# BACKUP_ENCRYPTION_KEY (32 bytes, base64)
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

## 5. Bezpečnostní pravidla pro .env

1. **Nikdy ne commitovat `.env`** — je v `.gitignore`
2. **`.env` má oprávnění `600`** — pouze vlastník může číst
3. **S3/SFTP credentials nejsou v `.env`** — jsou šifrované v DB (CryptoService)
4. **`APP_DEBUG=false` na produkci** — jinak únik citlivých informací
5. **`APP_KEY` je kritický** — bez něj nelze dešifrovat credentials
6. **Pravidelná rotace klíčů** — `APP_KEY` a `BACKUP_ENCRYPTION_KEY` doporučeno rotovat ročně (vyžaduje re-šifrování dat)
