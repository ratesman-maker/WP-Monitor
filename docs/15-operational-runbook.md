# WP Monitor — Operační runbook

> Co dělat když něco nefunguje. Postupy pro běžné incidenty a údržbu.

## 1. Rychlá diagnostika

### 1.1 Health check

```bash
# API health
curl -s https://wp-monitor.example.com/api/health | jq .

# Očekávaný výstup:
# {
#   "status": "healthy",
#   "version": "1.0.0",
#   "php": "8.3.0",
#   "db": "connected",
#   "storage": "writable",
#   "modules": [...]
# }

# Pokud status != "healthy":
# 1. Zkontroluj logy (sekce 1.2)
# 2. Zkontroluj DB připojení (sekce 2.1)
# 3. Zkontroluj storage (sekce 2.3)
```

### 1.2 Logy

```bash
# Backend logy (Monolog)
tail -100 /var/www/wp-monitor/backend/storage/logs/app-$(date +%Y-%m-%d).log

# PHP-FPM logy
tail -100 /var/log/php8.3-fpm.log

# Apache error log
tail -100 /var/log/apache2/wp-monitor-error.log

# Nginx error log (alternativa)
tail -100 /var/log/nginx/wp-monitor-error.log

# Systém logy
journalctl -u php8.3-fpm --since "1 hour ago"
```

### 1.3 Rychlé kontroly

```bash
# PHP alive?
php -v

# DB alive?
mysql -u wp_monitor -p -e "SELECT 1" wp_monitor

# Disk space?
df -h /var/www/wp-monitor/backend/storage

# Memory?
free -h

# PHP-FPM processes?
ps aux | grep php-fpm | wc -l

# Apache/Nginx alive?
systemctl status apache2
systemctl status nginx
```

---

## 2. Incidenty

### 2.1 Databáze nedostupná

**Příznaky:** API vrací 500, health check hlásí `db: disconnected`

```bash
# 1. Zkontroluj MariaDB/MySQL
systemctl status mariadb
# nebo
systemctl status mysql

# 2. Pokud běží, zkus připojení
mysql -u wp_monitor -p wp_monitor -e "SELECT 1"

# 3. Pokud nelze připojit, zkontroluj credentials
cat /var/www/wp-monitor/backend/.env | grep DB_

# 4. Restart DB
sudo systemctl restart mariadb

# 5. Počkej na start
mysqladmin ping -u root -p

# 6. Zkontroluj DB tabulky
php /var/www/wp-monitor/backend/bin/console db:check

# 7. Pokud je DB poškozená
mysqlcheck -u root -p --auto-repair --check wp_monitor
```

**Prevence:**
- DB monitoring přes cron (`monitoring:check-uptime`)
- Alert při DB connection error (email notifikace)

### 2.2 Disk plný (storage)

**Příznaky:** Zálohy selhávají, API vrací 500 při write operacích

```bash
# 1. Zkontroluj disk usage
df -h /var/www/wp-monitor/backend/storage

# 2. Najdi největší soubory
du -sh /var/www/wp-monitor/backend/storage/* | sort -rh

# 3. Pravděpodobně zálohy — spusť retention cleanup
php /var/www/wp-monitor/backend/bin/cron backups:cleanup

# 4. Pokud nestačí, smaž staré zálohy manuálně
ls -la /var/www/wp-monitor/backend/storage/backups/ | head -20
# Smazat zálohy starší než retention policy

# 5. Vyčisti logy starší než 30 dní
find /var/www/wp-monitor/backend/storage/logs -name "*.log" -mtime +30 -delete

# 6. Vyčisti cache
rm -rf /var/www/wp-monitor/backend/storage/cache/*
```

**Prevence:**
- Cron `backups:cleanup` denně
- Disk space monitoring (`backups:storage-info`)
- Alert při 80% disk usage

### 2.3 WP Monitor nedostupný (502/503)

**Příznaky:** Browser vrací 502 Bad Gateway nebo 503 Service Unavailable

```bash
# 1. Zkontroluj PHP-FPM
sudo systemctl status php8.3-fpm

# 2. Pokud nefunguje, restartuj
sudo systemctl restart php8.3-fpm

# 3. Zkontroluj Apache/Nginx
sudo systemctl restart apache2
# nebo
sudo systemctl restart nginx

# 4. Zkontroluj PHP-FPM socket
ls -la /run/php/php8.3-fpm.sock

# 5. Zkontroluj PHP error log
tail -50 /var/log/php8.3-fpm.log

# 6. Pokud PHP-FPM crashuje — zkontroluj memory
free -h
# Možná došla RAM — zvyš memory_limit nebo přidej RAM

# 7. Zkontroluj OPcache
php -i | grep opcache
```

### 2.4 SSL certifikát expiroval

**Příznaky:** Browser hlásí NET::ERR_CERT_DATE_INVALID

```bash
# 1. Zkontroluj certifikát
openssl s_client -connect wp-monitor.example.com:443 < /dev/null 2>/dev/null | openssl x509 -noout -dates

# 2. Obnov přes certbot
sudo certbot renew

# 3. Restart Apache/Nginx
sudo systemctl reload apache2
# nebo
sudo systemctl reload nginx

# 4. Zkontroluj auto-obnovu
sudo certbot renew --dry-run
```

**Prevence:**
- Cron `monitoring:check-ssl` denně
- Alert při SSL expiraci < 30 dní (warning), < 7 dní (critical)

### 2.5 MU-plugin nedostupný na WordPress webu

**Příznaky:** Web hlásí "offline" nebo "connection error"

```bash
# 1. Zkontroluj zda je web vůbec dostupný
curl -sI https://klient-web.cz | head -5

# 2. Zkontroluj MU-plugin
curl -s -H "Authorization: WPMonitor-HMAC-SHA256 ..." \
     https://klient-web.cz/wp-json/wp-monitor/v1/health

# 3. Pokud 404 — MU-plugin není nainstalovaný
#    → Instaluj přes WP Monitor UI (Settings → Sites → Reinstall MU-plugin)

# 4. Pokud 401 — site secret je neplatný
#    → Rotuj secret přes WP Monitor UI (Settings → Sites → Rotate Secret)

# 5. Pokud 500 — chyba na WordPress straně
#    → Zkontroluj WP debug.log na klient webu
#    → Zkontroluj PHP verze na klient webu (min. 7.4)

# 6. Pokud timeout — web je pomalý nebo nedostupný
#    → Zkontroluj uptime status v WP Monitor UI
```

### 2.6 Šifrování selhává (nelze dešifrovat credentials)

**Příznaky:** API vrací 500 při přístupu k webu, "Decryption failed" v logu

```bash
# 1. Zkontroluj APP_KEY v .env
grep APP_KEY /var/www/wp-monitor/backend/.env

# 2. Pokud APP_KEY chybí nebo se změnil → KRITICKÉ
#    Všechna šifrovaná data jsou neobnovitelná bez původního klíče!

# 3. Pokud APP_KEY existuje, zkontroluj sodium rozšíření
php -m | grep sodium

# 4. Zkontroluj log pro detail chyby
grep -i "decrypt" /var/www/wp-monitor/backend/storage/logs/app-*.log | tail -20

# 5. Pokud se APP_KEY nezměnil, možná poškozená data v DB
#    → Zkontroluj konkrétní site credentials
php /var/www/wp-monitor/backend/bin/console crypto:check --site-id=X
```

**Prevence:**
- `APP_KEY` zálohovat bezpečně (password manager, offline storage)
- Nikdy neměnit `APP_KEY` bez plánované re-šifrace všech dat
- Monitoring na decrypt errors (alert při > 0 chybách za hodinu)

---

## 3. Údržba

### 3.1 Denní

| Úkol | Nástroj | Čas |
|------|---------|-----|
| Uptime monitoring | cron `monitoring:check-uptime` | každou minutu |
| SSL check | cron `monitoring:check-ssl` | 3:00 |
| Backup cleanup | cron `backups:cleanup` | 5:00 |
| Session cleanup | cron `auth:cleanup-sessions` | 4:00 |
| Log rotation | Monolog handler | automaticky |

### 3.2 Týdenní

| Úkol | Nástroj | Čas |
|------|---------|-----|
| Security scan všech webů | cron `security:scan-all` | neděle 4:00 |
| Code quality check | GitHub Actions `code-quality.yml` | pondělí 2:00 |
| DB optimalizace | `php bin/console db:optimize` | neděle 5:00 |

### 3.3 Měsíční

| Úkol | Popis |
|------|-------|
| Audit uživatelských účtů | Zkontrolovat aktivní uživatele, deaktivovat neaktivní |
| Rotace API klíčů | Pokud se používají API keys pro integrace |
| Disk usage review | Zkontrolovat trend, případně rozšířit storage |
| Log review | Projít error logy, identifikovat opakující se problémy |
| Backup restore test | Obnovit jednu zálohu na test prostředí — ověřit funkčnost |

### 3.4 Čtvrtletní

| Úkol | Popis |
|------|-------|
| Security audit | OWASP ASVS v4.0 Level 2 kontrola |
| Key rotation | `APP_KEY` a `BACKUP_ENCRYPTION_KEY` rotace (vyžaduje re-šifraci) |
| Dependency update | `composer update` + `npm update` — security patches |
| Penetration test | Manuální nebo automatizovaný pentest |
| Capacity planning | Zkontrolovat počet spravovaných webů, výkon, storage |

---

## 4. Zálohování WP Monitoru samotného

### 4.1 Co zálohovat

| Data | Priorita | Frekvence |
|------|----------|-----------|
| **Databáze** | Kritická | Denně (před retention cleanup) |
| **`.env` soubor** | Kritická | Při změně |
| **`APP_KEY`** | Kritická | Při změně (offline kopie!) |
| **Zálohy webů** | Vysoká | Jsou už v `storage/backups/` |
| **Logy** | Nízká | Týdně (pro audit) |
| **Kód** | Nízká | Git (GitHub) |

### 4.2 Backup skript

```bash
#!/bin/bash
# /opt/wp-monitor/backup.sh
# Spouštět cron: 0 2 * * * /opt/wp-monitor/backup.sh

BACKUP_DIR="/opt/wp-monitor-backups"
DATE=$(date +%Y%m%d)
RETENTION=7

# DB dump
mysqldump -u wp_monitor -p"$DB_PASSWORD" wp_monitor | gzip > "$BACKUP_DIR/db-$DATE.sql.gz"

# .env a APP_KEY
cp /var/www/wp-monitor/backend/.env "$BACKUP_DIR/env-$DATE"

# Smazat staré zálohy
find "$BACKUP_DIR" -mtime +$RETENTION -delete

echo "WP Monitor backup completed: $DATE"
```

---

## 5. Recovery — obnova po havárii

### 5.1 Úplná obnova na novém serveru

```bash
# 1. Příprava serveru (viz docs/10-deployment-guide.md)

# 2. Obnova kódu
git clone https://github.com/ratesman-maker/WP-Monitor.git /var/www/wp-monitor
cd /var/www/wp-monitor
git checkout v1.0.0  # produkční verze

# 3. Backend
cd backend
composer install --no-dev --optimize-autoloader

# 4. Obnova .env (z backupu)
cp /opt/wp-monitor-backups/env-LATEST .env
# Zkontrolovat APP_KEY — musí být stejný jako před havárií!

# 5. Obnova databáze
gunzip < /opt/wp-monitor-backups/db-LATEST.sql.gz | mysql -u wp_monitor -p wp_monitor

# 6. Migrace (pro jistotu)
php bin/migrate

# 7. Frontend
cd ../frontend
npm ci
npm run build
# Kopírovat dist/ na server

# 8. Storage obnova (zálohy webů)
# Pokud jsou na S3 — automaticky dostupné
# Pokud lokální — obnovit z backupu

# 9. Post-deploy kontrola
php bin/console db:check
curl -s https://wp-monitor.example.com/api/health | jq .

# 10. Restart služeb
sudo systemctl restart php8.3-fpm
sudo systemctl reload apache2
```

### 5.2 Obnova APP_KEY (worst case)

Pokud je `APP_KEY` ztracen a nelze obnovit:

1. **Všechna šifrovaná data jsou neobnovitelná** — WP credentials, API klíče, S3/SFTP credentials
2. Vygeneruj nový `APP_KEY`
3. Pro každý spravovaný web: znovu zadat WP credentials (přes UI)
4. Pro každý storage backend: znovu zadat credentials
5. Re-šifrovat všechna data s novým klíčem

**Lekce:** `APP_KEY` musí být uložen offline (password manager, tištěná kopie v trezoru).

---

## 6. Kontakt a eskalace

| Úroveň | Kdy | Kdo | Akce |
|--------|-----|-----|------|
| **L1** | Drobná chyba, UI bug | Developer | Fix v dalším release |
| **L2** | Funkce nedostupná, 1 web offline | Developer | Fix do 24h |
| **L3** | Více webů offline, DB nedostupná | Admin + Developer | Fix do 4h |
| **L4** | Kompletní výpadek, data loss | Admin + Developer + Management | Okamžitá akce |
