# WP Monitor — Databázové schéma

## 1. Přehled

Databáze používá MySQL 8.0+ / MariaDB 10.6+ s `utf8mb4` charset a `utf8mb4_unicode_ci` collation. Všechny tabulky používají InnoDB engine pro podporu transakcí a foreign keys.

## 2. ER diagram (high-level)

```
┌──────────┐     ┌──────────────────┐     ┌─────────────────┐
│  users   │────<│ user_site_perms  │>────│     sites       │
│          │     └──────────────────┘     │                 │
│          │                               │  ┌───────────┐ │
│          │                               │  │ site_meta │ │
│          │                               │  └───────────┘ │
│          │                               └────────┬────────┘
│          │                                        │
│          │                               ┌────────▼────────┐
│          │                               │site_credentials │
│          │                               └────────┬────────┘
│          │                                        │
│          │     ┌──────────────────┐               │
│          │     │   site_groups    │>──────────────┘
│          │     └──────────────────┘
│          │     ┌──────────────────┐
│          │     │    site_tags     │
│          │     └──────────────────┘
│          │
│          │     ┌──────────────────┐
│          │<────│   audit_log      │>──── sites
│          │     └──────────────────┘
│          │
│          │     ┌──────────────────┐
│          │<────│  notifications   │
│          │     └──────────────────┘
│          │
│          │     ┌──────────────────┐
│          │<────│  user_sessions   │
│          │     └──────────────────┘
└──────────┘

┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   backups        │>────│     sites        │     │  uptime_checks   │>──── sites
│   backup_schedules│                    │     │  ssl_checks       │
└──────────────────┘                       │     │  response_times  │
                                           │     │  vuln_findings   │
┌──────────────────┐                       │     │  seo_audits      │
│  update_history  │>──── sites            │     │  perf_audits     │
└──────────────────┘                       │     └──────────────────┘
                                           │
┌──────────────────┐                       │     ┌──────────────────┐
│  batch_jobs      │                       │>────│  batch_job_items │
└──────────────────┘                       │     └──────────────────┘
                                           │
┌──────────────────┐                       │     ┌──────────────────┐
│  modules         │                       │     │  module_configs  │>──── modules
└──────────────────┘                       │     └──────────────────┘
                                           │
┌──────────────────┐                       │     ┌──────────────────┐
│  settings        │                       │     │  incidents       │>──── sites
└──────────────────┘                       │     └──────────────────┘
```

## 3. Tabulky — detailní specifikace

### 3.1 Core tabulky

#### 3.1.1 users

```sql
CREATE TABLE users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50) NOT NULL UNIQUE,
    email               VARCHAR(255) NULL UNIQUE,
    role                ENUM('admin','manager','viewer') NOT NULL DEFAULT 'viewer',
    password_salt       BINARY(16) NOT NULL,           -- per-user salt pro key derivation
    verification_token  VARBINARY(255) NOT NULL,        -- šifrovaný známý plaintext pro ověření hesla
    verification_aad    VARBINARY(16) NOT NULL,         -- AAD pro verification token
    failed_login_count  INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until        TIMESTAMP NULL,                 -- account lockout timestamp
    last_login_at       TIMESTAMP NULL,
    last_login_ip       VARCHAR(45) NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Poznámky:**
- `password_salt` — 16 bajtů, generováno `random_bytes(16)` při first setup
- `verification_token` — AES-256-GCM šifrovaný známý plaintext (např. "WPMONITOR_VERIFY"), slouží k ověření master hesla dešifrováním
- `verification_aad` — AAD pro verification token (obsahuje user_id pack)
- Master heslo **není** nikde uloženo — ani hashovaná verze

#### 3.1.2 user_sessions

```sql
CREATE TABLE user_sessions (
    id              VARCHAR(128) PRIMARY KEY,           -- session ID (random)
    user_id         INT UNSIGNED NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    user_agent_hash VARCHAR(64) NOT NULL,               -- SHA-256 hash User-Agent
    encryption_key  VARBINARY(255) NOT NULL,            -- šifrovaný encryption key (šifrovaný APP_KEY)
    jwt_jti         VARCHAR(64) NULL,                   -- JWT ID pro revocation
    csrf_token      VARCHAR(64) NOT NULL,               -- CSRF token
    expires_at      TIMESTAMP NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_jti (jwt_jti)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Poznámky:**
- `encryption_key` — AES-256-GCM šifrovaný pomocí `APP_KEY`, dešifrován pouze během aktivní session
- Session cleanup — expired sessions mazány cronem

#### 3.1.3 sites

```sql
CREATE TABLE sites (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,              -- uživatelský název
    url             VARCHAR(2048) NOT NULL,             -- https://example.com
    wp_username     VARCHAR(100) NOT NULL,              -- WP uživatel pro REST API
    wp_version      VARCHAR(20) NULL,                   -- detekovaná verze WP
    php_version     VARCHAR(20) NULL,                   -- detekovaná verze PHP (MU-plugin)
    mysql_version   VARCHAR(20) NULL,                   -- detekovaná verze MySQL (MU-plugin)
    mu_plugin_version VARCHAR(20) NULL,                 -- verze MU-pluginu (null = není nainstalován)
    status          ENUM('online','offline','degraded','unknown') NOT NULL DEFAULT 'unknown',
    http_status     INT UNSIGNED NULL,                  -- poslední HTTP status code
    response_time_ms INT UNSIGNED NULL,                 -- poslední response time v ms
    last_checked_at TIMESTAMP NULL,
    last_error      TEXT NULL,                          -- poslední chybová zpráva
    metadata        JSON NULL,                          -- dodatečná metadata
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,      -- soft delete (false = skrytý)
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_active (is_active),
    INDEX idx_url (url(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.4 site_credentials

```sql
CREATE TABLE site_credentials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    credential_type ENUM('application_password','mu_plugin_key','ssh_key','sftp_password') NOT NULL,
    encrypted_value VARBINARY(4096) NOT NULL,           -- AES-256-GCM šifrovaný blob (nonce + ciphertext + tag)
    aad             VARBINARY(32) NOT NULL,             -- Additional Authenticated Data (site_id + user_id pack)
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uk_site_credential_type (site_id, credential_type),
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Poznámky:**
- `encrypted_value` — obsahuje nonce (12B) + ciphertext + tag (16B) v jednom blobu
- `aad` — `pack('NN', site_id, user_id)` — zajišťuje, že blob nelze přesunout na jiný web/uživatele
- Application password nikdy v plaintext v DB

#### 3.1.5 site_groups

```sql
CREATE TABLE site_groups (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    description     TEXT NULL,
    color           VARCHAR(7) NULL,                    -- hex barva pro UI (např. #3B82F6)
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_group_members (
    group_id        INT UNSIGNED NOT NULL,
    site_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, site_id),
    FOREIGN KEY (group_id) REFERENCES site_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.6 site_tags

```sql
CREATE TABLE site_tags (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) NOT NULL UNIQUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_tag_map (
    tag_id          INT UNSIGNED NOT NULL,
    site_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (tag_id, site_id),
    FOREIGN KEY (tag_id) REFERENCES site_tags(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.7 site_meta

```sql
CREATE TABLE site_meta (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    meta_key        VARCHAR(191) NOT NULL,
    meta_value      LONGTEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uk_site_meta (site_id, meta_key),
    INDEX idx_meta_key (meta_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Poznámky:**
- Flexibilní úložiště pro metadata, která se často mění (plugin list, theme list, WP settings, etc.)
- `meta_value` je JSON string pro strukturovaná data

#### 3.1.8 user_site_permissions

```sql
CREATE TABLE user_site_permissions (
    user_id         INT UNSIGNED NOT NULL,
    site_id         INT UNSIGNED NOT NULL,
    can_view        BOOLEAN NOT NULL DEFAULT TRUE,
    can_manage      BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, site_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.9 audit_log

```sql
CREATE TABLE audit_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,                  -- NULL pro systémové akce
    action          VARCHAR(100) NOT NULL,              -- 'site.add', 'update.execute', etc.
    site_id         INT UNSIGNED NULL,                  -- NULL pro globální akce
    module          VARCHAR(50) NULL,                   -- 'updates', 'backups', etc.
    status          ENUM('success','failed','partial') NOT NULL,
    details         JSON NULL,                          -- dodatečné informace
    ip_address      VARCHAR(45) NOT NULL,
    user_agent      VARCHAR(255) NULL,
    request_id      VARCHAR(64) NULL,                   -- pro korelaci s request logy
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_site (site_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Poznámky:**
- Append-only — aplikace nikdy neprovádí UPDATE ani DELETE
- DB trigger pro vynucení append-only:

```sql
DELIMITER //
CREATE TRIGGER prevent_audit_log_update
BEFORE UPDATE ON audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log is append-only';
END//

CREATE TRIGGER prevent_audit_log_delete
BEFORE DELETE ON audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Audit log is append-only';
END//
DELIMITER ;
```

#### 3.1.10 settings

```sql
CREATE TABLE settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name        VARCHAR(191) NOT NULL UNIQUE,
    value           LONGTEXT NULL,                      -- JSON encoded value
    is_encrypted    BOOLEAN NOT NULL DEFAULT FALSE,     -- pokud true, value je šifrovaný blob
    data_type       ENUM('string','integer','boolean','json','encrypted') NOT NULL DEFAULT 'string',
    description     VARCHAR(255) NULL,
    updated_by      INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.11 modules

```sql
CREATE TABLE modules (
    id              VARCHAR(50) PRIMARY KEY,            -- 'updates', 'backups', etc.
    name            VARCHAR(100) NOT NULL,
    version         VARCHAR(20) NOT NULL,
    description     TEXT NULL,
    is_enabled      BOOLEAN NOT NULL DEFAULT TRUE,
    is_core         BOOLEAN NOT NULL DEFAULT FALSE,     -- core moduly nelze deaktivovat
    manifest        JSON NOT NULL,                      -- full manifest.json
    installed_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_enabled (is_enabled),
    INDEX idx_core (is_core)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.1.12 module_configs

```sql
CREATE TABLE module_configs (
    module_id       VARCHAR(50) NOT NULL,
    site_id         INT UNSIGNED NULL,                  -- NULL = globální konfigurace
    config          JSON NOT NULL,                      -- module-specific config
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (module_id, site_id),
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.2 Batch operations

#### 3.2.1 batch_jobs

```sql
CREATE TABLE batch_jobs (
    id              VARCHAR(64) PRIMARY KEY,            -- 'batch_abc123'
    user_id         INT UNSIGNED NOT NULL,
    module          VARCHAR(50) NOT NULL,               -- 'updates', 'backups', etc.
    action          VARCHAR(100) NOT NULL,              -- 'batch_update', 'batch_backup', etc.
    site_ids        JSON NOT NULL,                      -- [1, 2, 3, 5]
    options         JSON NULL,                          -- batch-specific options
    status          ENUM('pending','processing','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
    progress_total  INT UNSIGNED NOT NULL,
    progress_done   INT UNSIGNED NOT NULL DEFAULT 0,
    progress_success INT UNSIGNED NOT NULL DEFAULT 0,
    progress_failed INT UNSIGNED NOT NULL DEFAULT 0,
    error_message   TEXT NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_user (user_id),
    INDEX idx_module (module),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.2.2 batch_job_items

```sql
CREATE TABLE batch_job_items (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    batch_id        VARCHAR(64) NOT NULL,
    site_id         INT UNSIGNED NOT NULL,
    status          ENUM('pending','processing','success','failed','skipped') NOT NULL DEFAULT 'pending',
    result          JSON NULL,                          -- detailní výsledek pro daný web
    error_message   TEXT NULL,
    started_at      TIMESTAMP NULL,
    completed_at    TIMESTAMP NULL,

    FOREIGN KEY (batch_id) REFERENCES batch_jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_batch (batch_id),
    INDEX idx_status (status),
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.3 Updates module

#### 3.3.1 update_history

```sql
CREATE TABLE update_history (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    update_type     ENUM('core','plugin','theme') NOT NULL,
    slug            VARCHAR(191) NOT NULL,              -- plugin slug / theme stylesheet / 'wordpress'
    name            VARCHAR(255) NULL,                  -- human-readable name
    from_version    VARCHAR(50) NULL,
    to_version      VARCHAR(50) NOT NULL,
    status          ENUM('success','failed','rolled_back') NOT NULL DEFAULT 'success',
    error_message   TEXT NULL,
    batch_id        VARCHAR(64) NULL,                   -- reference na batch job (pokud součást batche)
    user_id         INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_site (site_id),
    INDEX idx_type (update_type),
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.3.2 update_rules

```sql
CREATE TABLE update_rules (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NULL,                  -- NULL = globální pravidlo
    auto_update_core ENUM('off','minor','major') NOT NULL DEFAULT 'off',
    auto_update_plugins ENUM('off','all','selected') NOT NULL DEFAULT 'off',
    auto_update_themes ENUM('off','all','selected') NOT NULL DEFAULT 'off',
    selected_plugins JSON NULL,                         -- ["yoast-seo","wordfence"] při selected
    excluded_plugins JSON NULL,                         -- ["custom-plugin"] — vždy vyloučeno
    schedule        VARCHAR(100) NULL,                  -- cron expression (např. '0 3 * * 0')
    pre_update_backup BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uk_site_rule (site_id),                  -- jedno pravidlo per web (NULL = globální)
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.4 Backups module

#### 3.4.1 backups

```sql
CREATE TABLE backups (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    backup_type     ENUM('db','files','full') NOT NULL,
    status          ENUM('creating','completed','failed','restoring','restored') NOT NULL DEFAULT 'creating',
    storage_backend ENUM('local','s3','sftp') NOT NULL DEFAULT 'local',
    storage_path    VARCHAR(1024) NOT NULL,             -- cesta/soubor v úložišti
    file_size       BIGINT UNSIGNED NULL,               -- velikost v bajtech
    encrypted       BOOLEAN NOT NULL DEFAULT TRUE,
    checksum        VARCHAR(64) NULL,                   -- SHA-256 hash souboru
    metadata        JSON NULL,                          -- tabulky, počet souborů, etc.
    error_message   TEXT NULL,
    batch_id        VARCHAR(64) NULL,
    user_id         INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    TIMESTAMP NULL,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_site (site_id),
    INDEX idx_type (backup_type),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_batch (batch_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.4.2 backup_schedules

```sql
CREATE TABLE backup_schedules (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    backup_type     ENUM('db','files','full') NOT NULL DEFAULT 'full',
    schedule        VARCHAR(100) NOT NULL,              -- cron expression
    storage_backend ENUM('local','s3','sftp') NOT NULL DEFAULT 'local',
    retention_daily INT UNSIGNED NOT NULL DEFAULT 7,
    retention_weekly INT UNSIGNED NOT NULL DEFAULT 4,
    retention_monthly INT UNSIGNED NOT NULL DEFAULT 12,
    max_age_days    INT UNSIGNED NOT NULL DEFAULT 365,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_run_at     TIMESTAMP NULL,
    next_run_at     TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id),
    INDEX idx_active (is_active),
    INDEX idx_next_run (next_run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.5 Security & Monitoring module

#### 3.5.1 uptime_checks

```sql
CREATE TABLE uptime_checks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    status          ENUM('up','down','degraded') NOT NULL,
    http_status     INT UNSIGNED NULL,
    response_time_ms INT UNSIGNED NULL,
    error_message   TEXT NULL,
    checked_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_time (site_id, checked_at),
    INDEX idx_status (status),
    INDEX idx_checked (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Poznámka:** Tato tabulka roste rychle (1 záznam / 5 min / web). Retence:
- Raw data: 30 dní
- Agregace (hourly): 90 dní
- Agregace (daily): 365 dní
- Cron job provádí agregaci a mazání starých raw dat.

#### 3.5.2 ssl_checks

```sql
CREATE TABLE ssl_checks (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    issuer          VARCHAR(255) NULL,                  -- 'Let's Encrypt', 'DigiCert', etc.
    valid_from      DATE NULL,
    valid_to        DATE NULL,
    days_until_expiry INT NULL,
    is_self_signed  BOOLEAN NULL,
    is_valid        BOOLEAN NULL,
    error_message   TEXT NULL,
    checked_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_time (site_id, checked_at),
    INDEX idx_expiry (days_until_expiry),
    INDEX idx_checked (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.5.3 incidents

```sql
CREATE TABLE incidents (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    type            ENUM('downtime','ssl_expired','vulnerability_critical','performance_degraded') NOT NULL,
    severity        ENUM('info','warning','critical') NOT NULL,
    status          ENUM('active','resolved','acknowledged') NOT NULL DEFAULT 'active',
    started_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at     TIMESTAMP NULL,
    duration_seconds INT UNSIGNED NULL,                 -- v sekundách (downtime)
    details         JSON NULL,
    acknowledged_by INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_site (site_id),
    INDEX idx_type (type),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.5.4 vulnerability_findings

```sql
CREATE TABLE vulnerability_findings (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    entity_type     ENUM('core','plugin','theme') NOT NULL,
    slug            VARCHAR(191) NOT NULL,              -- 'wordpress', 'yoast-seo', etc.
    installed_version VARCHAR(50) NOT NULL,
    vulnerable_versions VARCHAR(255) NULL,              -- rozsah zranitelných verzí
    severity        ENUM('low','medium','high','critical') NOT NULL,
    cve_id          VARCHAR(20) NULL,                   -- 'CVE-2024-12345'
    title           VARCHAR(500) NOT NULL,
    description     TEXT NULL,
    remediation     TEXT NULL,                          -- doporučená náprava
    references      JSON NULL,                          -- odkazy na detaily
    status          ENUM('open','patched','ignored') NOT NULL DEFAULT 'open',
    patched_at      TIMESTAMP NULL,
    scanned_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_entity (entity_type, slug),
    INDEX idx_scanned (scanned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.5.5 security_scores

```sql
CREATE TABLE security_scores (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    score           INT UNSIGNED NOT NULL,              -- 0-100
    grade           ENUM('A','B','C','D','F') NOT NULL,
    factors         JSON NOT NULL,                      -- detailní rozpad score
    scanned_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_time (site_id, scanned_at),
    INDEX idx_score (score),
    INDEX idx_scanned (scanned_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Grade mapping:**
- A: 90-100
- B: 75-89
- C: 60-74
- D: 40-59
- F: 0-39

### 3.6 SEO & Performance module

#### 3.6.1 seo_audits

```sql
CREATE TABLE seo_audits (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    score           INT UNSIGNED NOT NULL,              -- 0-100
    grade           ENUM('A','B','C','D','F') NOT NULL,
    title_tag       VARCHAR(255) NULL,
    title_length    INT UNSIGNED NULL,
    meta_description TEXT NULL,
    meta_desc_length INT UNSIGNED NULL,
    has_og_tags     BOOLEAN NULL,
    has_twitter_cards BOOLEAN NULL,
    has_canonical   BOOLEAN NULL,
    has_sitemap     BOOLEAN NULL,
    has_robots_txt  BOOLEAN NULL,
    heading_structure JSON NULL,                        -- H1-H6 hierarchie
    structured_data JSON NULL,                          -- nalezené JSON-LD bloky
    broken_links    JSON NULL,                          -- seznam broken links
    image_alt_issues JSON NULL,                         -- obrázky bez alt textu
    recommendations JSON NULL,                          -- seznam doporučení
    audited_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_time (site_id, audited_at),
    INDEX idx_score (score),
    INDEX idx_audited (audited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.6.2 performance_audits

```sql
CREATE TABLE performance_audits (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    score           INT UNSIGNED NOT NULL,              -- 0-100
    grade           ENUM('A','B','C','D','F') NOT NULL,
    lcp_ms          INT UNSIGNED NULL,                  -- Largest Contentful Paint (ms)
    cls             DECIMAL(5,3) NULL,                  -- Cumulative Layout Shift
    inp_ms          INT UNSIGNED NULL,                  -- Interaction to Next Paint (ms)
    fcp_ms          INT UNSIGNED NULL,                  -- First Contentful Paint (ms)
    ttfb_ms         INT UNSIGNED NULL,                  -- Time to First Byte (ms)
    page_size_bytes BIGINT UNSIGNED NULL,               -- total page size
    request_count   INT UNSIGNED NULL,                  -- total HTTP requests
    page_size_breakdown JSON NULL,                      -- {html, css, js, images, fonts, other}
    request_breakdown JSON NULL,                        -- {html, css, js, images, fonts, api, other}
    has_gzip         BOOLEAN NULL,
    has_brotli       BOOLEAN NULL,
    has_cdn          BOOLEAN NULL,
    recommendations JSON NULL,                          -- seznam doporučení
    audited_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_time (site_id, audited_at),
    INDEX idx_score (score),
    INDEX idx_audited (audited_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.6.3 analytics_detection

```sql
CREATE TABLE analytics_detection (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    google_analytics BOOLEAN NOT NULL DEFAULT FALSE,
    ga4             BOOLEAN NOT NULL DEFAULT FALSE,
    google_tag_manager BOOLEAN NOT NULL DEFAULT FALSE,
    facebook_pixel  BOOLEAN NOT NULL DEFAULT FALSE,
    hotjar          BOOLEAN NOT NULL DEFAULT FALSE,
    microsoft_clarity BOOLEAN NOT NULL DEFAULT FALSE,
    search_console  BOOLEAN NOT NULL DEFAULT FALSE,    -- detekce verifikace
    other_tools     JSON NULL,                          -- ostatní detekované nástroje
    detected_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site_time (site_id, detected_at),
    INDEX idx_detected (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3.7 Notifications

#### 3.7.1 notifications

```sql
CREATE TABLE notifications (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    channel         ENUM('email','webhook','slack','discord','in_app') NOT NULL,
    event           VARCHAR(100) NOT NULL,              -- 'site_down', 'vulnerability_detected', etc.
    title           VARCHAR(255) NOT NULL,
    message         TEXT NOT NULL,
    severity        ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    metadata        JSON NULL,                          -- dodatečné info (site_id, etc.)
    is_read         BOOLEAN NOT NULL DEFAULT FALSE,
    sent_at         TIMESTAMP NULL,
    read_at         TIMESTAMP NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_unread (user_id, is_read),
    INDEX idx_event (event),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 3.7.2 notification_preferences

```sql
CREATE TABLE notification_preferences (
    user_id         INT UNSIGNED NOT NULL,
    channel         ENUM('email','webhook','slack','discord') NOT NULL,
    endpoint        VARCHAR(1024) NULL,                 -- e-mail adresa / webhook URL
    events          JSON NOT NULL,                      -- ["site_down","vulnerability_critical",...]
    is_enabled      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id, channel),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 4. Migrace

Migrace jsou spravovány přes Doctrine Migrations:

```
backend/
├── migrations/
│   ├── Version20260807_001_create_users_table.php
│   ├── Version20260807_002_create_sites_table.php
│   ├── Version20260807_003_create_audit_log_table.php
│   ├── Version20260807_004_create_modules_tables.php
│   ├── Version20260807_005_create_batch_tables.php
│   ├── Version20260807_006_create_updates_tables.php
│   ├── Version20260807_007_create_backups_tables.php
│   ├── Version20260807_008_create_security_tables.php
│   ├── Version20260807_009_create_seo_perf_tables.php
│   ├── Version20260807_010_create_notifications_tables.php
│   └── Version20260807_011_create_audit_triggers.php
```

**Příklad migrace:**

```php
class Version20260807_001_create_users_table extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql(/* SQL z sekce 3.1.1 */);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
```

## 5. Indexy a výkon

### 5.1 Kritické indexy

| Tabulka | Index | Účel |
|---------|-------|------|
| `audit_log` | `idx_created` | Rychlý výpis nedávných logů |
| `audit_log` | `idx_action` | Filtrování podle typu akce |
| `uptime_checks` | `idx_site_time` | Rychlé načtení uptime historie pro web |
| `vulnerability_findings` | `idx_status` | Rychlé hledání otevřených zranitelností |
| `backups` | `idx_site_created` | Seznam záloh pro web |
| `batch_jobs` | `idx_status` | Hledání aktivních batch operací |
| `notifications` | `idx_unread` | Rychlé načtení nepřečtených notifikací |

### 5.2 Data retention

| Tabulka | Retence | Strategie |
|---------|---------|-----------|
| `audit_log` | 365 dní | Cron mazání starších záznamů |
| `uptime_checks` | 30 dní (raw), 90d (hourly agg), 365d (daily agg) | Agregace + mazání cronem |
| `ssl_checks` | 90 dní | Cron mazání |
| `security_scores` | 365 dní | Cron mazání |
| `seo_audits` | 365 dní | Cron mazání |
| `performance_audits` | 365 dní | Cron mazání |
| `incidents` | 730 dní (2 roky) | Cron mazání |
| `batch_jobs` | 90 dní | Cron mazání po dokončení |
| `notifications` | 90 dní | Cron mazání přečtených |
| `user_sessions` | Expirace | Cron mazání expired sessions |

### 5.3 Agregace uptime dat

```sql
-- Hourly agregace (spouštěno cronem každou hodinu)
INSERT INTO uptime_checks_hourly (site_id, hour, up_count, total_count, avg_response_time)
SELECT
    site_id,
    DATE_FORMAT(checked_at, '%Y-%m-%d %H:00:00') as hour,
    SUM(CASE WHEN status = 'up' THEN 1 ELSE 0 END) as up_count,
    COUNT(*) as total_count,
    AVG(response_time_ms) as avg_response_time
FROM uptime_checks
WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY site_id, hour;

-- Mazání raw dat starších 30 dní
DELETE FROM uptime_checks WHERE checked_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```
