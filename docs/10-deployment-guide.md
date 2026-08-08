# WP Monitor — Produkční deployment

## 1. Server požadavky

### 1.1 Minimální konfigurace

| Komponenta | Minimální | Doporučené |
|------------|-----------|------------|
| **PHP** | 8.3 | 8.3+ |
| **MySQL** | 8.0 | 8.4+ |
| **MariaDB** | 10.6 | 11.4+ |
| **RAM** | 1 GB | 2 GB+ |
| **Disk** | 10 GB | 50 GB+ (zálohy) |
| **PHP memory_limit** | 256M | 512M |
| **PHP max_execution_time** | 120 | 300 |
| **PHP upload_max_filesize** | 50M | 100M |
| **PHP post_max_size** | 50M | 100M |

### 1.2 Požadovaná PHP rozšíření

```bash
php -m | grep -E 'openssl|sodium|mbstring|intl|curl|pdo_mysql|json|zip|gd|xml'
```

Požadováno: `openssl`, `sodium`, `mbstring`, `intl`, `curl`, `pdo_mysql`, `json`, `zip`
Volitelně: `gd` (náhledy), `xml` (sitemap parsing), `opcache` (performance)

### 1.3 OpCache nastavení (doporučené)

```ini
; php.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

## 2. Apache + PHP-FPM konfigurace

### 2.1 VirtualHost (HTTPS)

```apache
<VirtualHost *:443>
    ServerName wp-monitor.example.com
    DocumentRoot /var/www/wp-monitor/backend/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/wp-monitor.crt
    SSLCertificateKeyFile /etc/ssl/private/wp-monitor.key
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384

    # Security headers
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    Header always set Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

    # Frontend (statické soubory)
    Alias /assets /var/www/wp-monitor/frontend/dist/assets
    <Directory /var/www/wp-monitor/frontend/dist/assets>
        Require all granted
        ExpiresActive On
        ExpiresByType text/css "access plus 1 year"
        ExpiresByType application/javascript "access plus 1 year"
        ExpiresByType image/svg+xml "access plus 1 year"
    </Directory>

    # Backend
    <Directory /var/www/wp-monitor/backend/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # Zakázat přístup k citlivým souborům
    <FilesMatch "\.(env|sql|md|neon|xml\.dist)$">
        Require all denied
    </FilesMatch>

    # PHP-FPM
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/run/php/php8.3-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # Proxy API požadavky na backend
    ProxyPreserveHost On
    ProxyPass /api/ http://127.0.0.1:8080/api/
    ProxyPassReverse /api/ http://127.0.0.1:8080/api/
</VirtualHost>

# HTTP → HTTPS redirect
<VirtualHost *:80>
    ServerName wp-monitor.example.com
    Redirect permanent / https://wp-monitor.example.com/
</VirtualHost>
```

### 2.2 Nginx alternativa

```nginx
server {
    listen 443 ssl http2;
    server_name wp-monitor.example.com;

    ssl_certificate /etc/ssl/certs/wp-monitor.crt;
    ssl_certificate_key /etc/ssl/private/wp-monitor.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;

    root /var/www/wp-monitor/backend/public;
    index index.php;

    # Security headers
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'" always;

    # Frontend static
    location /assets/ {
        alias /var/www/wp-monitor/frontend/dist/assets/;
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Backend API
    location /api/ {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    # Frontend SPA (fallback)
    location / {
        root /var/www/wp-monitor/frontend/dist;
        try_files $uri $uri/ /index.html;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Zakázat citlivé soubory
    location ~ /\.(env|git|htaccess) {
        deny all;
    }
    location ~ \.(sql|md|neon)$ {
        deny all;
    }
}

server {
    listen 80;
    server_name wp-monitor.example.com;
    return 301 https://$server_name$request_uri;
}
```

## 3. Instalační postup

### 3.1 Příprava serveru

```bash
# Vytvoření uživatele a adresáře
sudo mkdir -p /var/www/wp-monitor
sudo chown -R www-data:www-data /var/www/wp-monitor

# Vytvoření storage adresářů
sudo mkdir -p /var/www/wp-monitor/backend/storage/{logs,cache,backups,coverage,phpstan}
sudo chown -R www-data:www-data /var/www/wp-monitor/backend/storage
sudo chmod -R 755 /var/www/wp-monitor/backend/storage
```

### 3.2 Nasazení backendu

```bash
cd /var/www/wp-monitor/backend

# Instalace závislostí (bez dev)
composer install --no-dev --optimize-autoloader --no-interaction

# Konfigurace
cp .env.example .env
# Upravit .env — viz docs/12-environment-variables.md

# Databáze
php bin/migrate
php bin/seed   # pouze první instalace

# Oprávnění
chown -R www-data:www-data storage
chmod -R 755 storage
```

### 3.3 Nasazení frontendu

```bash
# Build na vývojovém stroji (ne na serveru — server nemusí mít Node.js)
cd frontend
npm ci
npm run build

# Kopírování na server
scp -r dist/* user@server:/var/www/wp-monitor/frontend/dist/
```

### 3.4 Cron jobs

```bash
# /etc/cron.d/wp-monitor

# Uptime monitoring (každou minutu)
* * * * * www-data php /var/www/wp-monitor/backend/bin/cron monitoring:check-uptime

# SSL check (denně 3:00)
0 3 * * * www-data php /var/www/wp-monitor/backend/bin/cron monitoring:check-ssl

# Scheduled backups (dle konfigurace per-site)
0 * * * * www-data php /var/www/wp-monitor/backend/bin/cron backups:run-scheduled

# Auto-update (dle cron expression per-site)
0 3 * * * www-data php /var/www/wp-monitor/backend/bin/cron updates:run-auto

# Security scan (týdně)
0 4 * * 0 www-data php /var/www/wp-monitor/backend/bin/cron security:scan-all

# Retention cleanup (denně 5:00)
0 5 * * * www-data php /var/www/wp-monitor/backend/bin/cron backups:cleanup

# Session cleanup (denně 4:00)
0 4 * * * www-data php /var/www/wp-monitor/backend/bin/cron auth:cleanup-sessions
```

## 4. Post-deploy kontrola

### 4.1 Kontrolní seznam

```bash
# 1. PHP verze a rozšíření
php -v
php -m | grep -E 'openssl|sodium|mbstring|intl|curl|pdo_mysql'

# 2. Databázové připojení
php bin/console db:check

# 3. Migrace aktuální
php bin/migrate:status

# 4. .env soubor existuje a má správná oprávnění
ls -la .env  # -rw------- www-data www-data

# 5. Storage adresáře zapisovatelné
php bin/console storage:check

# 6. SSL certifikát platný
openssl s_client -connect wp-monitor.example.com:443 -servername wp-monitor.example.com < /dev/null 2>/dev/null | openssl x509 -noout -dates

# 7. Security headers přítomny
curl -sI https://wp-monitor.example.com | grep -E 'X-Content-Type-Options|X-Frame-Options|Strict-Transport-Security'

# 8. API odpovídá
curl -s https://wp-monitor.example.com/api/health | jq .

# 9. Frontend načítá
curl -sI https://wp-monitor.example.com/ | grep "200 OK"

# 10. Cron jobs nastaveny
crontab -l -u www-data | grep wp-monitor
```

### 4.2 Health check endpoint

```
GET /api/health

200 OK:
{
    "status": "healthy",
    "version": "1.0.0",
    "php": "8.3.0",
    "db": "connected",
    "storage": "writable",
    "modules": ["auth", "sites", "dashboard", "updates", "backups", "security", "seo"]
}
```

## 5. Aktualizace (update proces)

```bash
# 1. Záloha před aktualizací
php bin/console backup:before-deploy

# 2. Stáhnutí nové verze
git fetch --tags
git checkout v1.1.0

# 3. Backend
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php bin/migrate

# 4. Frontend (build lokálně, kopírovat)
# ... viz sekce 3.3

# 5. Clear cache
php bin/console cache:clear

# 6. Post-deploy kontrola
# ... viz sekce 4.1

# 7. Restart PHP-FPM (volitelně)
sudo systemctl reload php8.3-fpm
```

## 6. Rollback

```bash
# 1. Návrat na předchozí verzi
git checkout v1.0.0

# 2. Backend
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
php bin/migrate:rollback  # pokud je potřeba

# 3. Frontend
# Kopírovat předchozí build

# 4. Clear cache
php bin/console cache:clear

# 5. Kontrola
curl -s https://wp-monitor.example.com/api/health | jq .
```

## 7. SSL certifikát

### 7.1 Let's Encrypt (doporučeno)

```bash
# Instalace certbot
sudo apt install certbot python3-certbot-apache  # Apache
sudo apt install certbot python3-certbot-nginx   # Nginx

# Získání certifikátu
sudo certbot --apache -d wp-monitor.example.com  # Apache
sudo certbot --nginx -d wp-monitor.example.com   # Nginx

# Auto-obnova (certbot přidá cron sám)
sudo certbot renew --dry-run
```

### 7.2 Self-signed (pouze pro vývoj/testing)

```bash
openssl req -x509 -newkey rsa:4096 -keyout wp-monitor.key -out wp-monitor.crt \
    -days 365 -nodes -subj "/CN=wp-monitor.local"
```

## 8. Firewall

```bash
# Povolit pouze HTTP(S) a SSH
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp      # SSH
sudo ufw allow 80/tcp      # HTTP (redirect)
sudo ufw allow 443/tcp     # HTTPS
sudo ufw enable
```
