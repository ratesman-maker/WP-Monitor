<?php

declare(strict_types=1);

/**
 * Migration: Create core tables (12 tables + audit_log triggers)
 *
 * Creates: users, user_sessions, sites, site_credentials, site_groups,
 * site_group_map, site_tags, site_tag_map, site_meta, user_site_permissions,
 * audit_log (+ append-only triggers), settings, modules, module_configs
 */

return [
    'up' => [
        // 1. users
        <<<SQL
CREATE TABLE users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username            VARCHAR(50) NOT NULL UNIQUE,
    email               VARCHAR(255) NULL UNIQUE,
    role                ENUM('admin','manager','viewer') NOT NULL DEFAULT 'viewer',
    password_salt       BINARY(16) NOT NULL,
    verification_token  VARBINARY(255) NOT NULL,
    verification_aad    VARBINARY(16) NOT NULL,
    failed_login_count  INT UNSIGNED NOT NULL DEFAULT 0,
    locked_until        TIMESTAMP NULL,
    last_login_at       TIMESTAMP NULL,
    last_login_ip       VARCHAR(45) NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 2. user_sessions
        <<<SQL
CREATE TABLE user_sessions (
    id              VARCHAR(128) PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    ip_address      VARCHAR(45) NOT NULL,
    user_agent_hash VARCHAR(64) NOT NULL,
    encryption_key  VARBINARY(255) NOT NULL,
    jwt_jti         VARCHAR(64) NULL,
    csrf_token      VARCHAR(64) NOT NULL,
    expires_at      TIMESTAMP NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_activity   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_expires (expires_at),
    INDEX idx_jti (jwt_jti)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 3. sites
        <<<SQL
CREATE TABLE sites (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    url             VARCHAR(2048) NOT NULL,
    wp_username     VARCHAR(100) NOT NULL,
    wp_version      VARCHAR(20) NULL,
    php_version     VARCHAR(20) NULL,
    mysql_version   VARCHAR(20) NULL,
    mu_plugin_version VARCHAR(20) NULL,
    status          ENUM('online','offline','degraded','unknown') NOT NULL DEFAULT 'unknown',
    http_status     INT UNSIGNED NULL,
    response_time_ms INT UNSIGNED NULL,
    last_checked_at TIMESTAMP NULL,
    last_error      TEXT NULL,
    metadata        JSON NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 4. site_credentials
        <<<SQL
CREATE TABLE site_credentials (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    site_id         INT UNSIGNED NOT NULL,
    credential_type ENUM('wp_rest','ftp','sftp','ssh') NOT NULL,
    encrypted_data  VARBINARY(1024) NOT NULL,
    nonce           VARBINARY(32) NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    UNIQUE KEY uk_site_type (site_id, credential_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 5. site_groups
        <<<SQL
CREATE TABLE site_groups (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL UNIQUE,
    description     TEXT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 5b. site_group_map
        <<<SQL
CREATE TABLE site_group_map (
    group_id        INT UNSIGNED NOT NULL,
    site_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, site_id),
    FOREIGN KEY (group_id) REFERENCES site_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 6. site_tags
        <<<SQL
CREATE TABLE site_tags (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(50) NOT NULL UNIQUE,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 6b. site_tag_map
        <<<SQL
CREATE TABLE site_tag_map (
    tag_id          INT UNSIGNED NOT NULL,
    site_id         INT UNSIGNED NOT NULL,
    PRIMARY KEY (tag_id, site_id),
    FOREIGN KEY (tag_id) REFERENCES site_tags(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 7. site_meta
        <<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 8. user_site_permissions
        <<<SQL
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 9. audit_log (append-only)
        <<<SQL
CREATE TABLE audit_log (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NULL,
    action          VARCHAR(100) NOT NULL,
    site_id         INT UNSIGNED NULL,
    module          VARCHAR(50) NULL,
    status          ENUM('success','failed','partial') NOT NULL,
    details         JSON NULL,
    ip_address      VARCHAR(45) NOT NULL,
    user_agent      VARCHAR(255) NULL,
    request_id      VARCHAR(64) NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_site (site_id),
    INDEX idx_action (action),
    INDEX idx_module (module),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 9b. audit_log append-only trigger — prevent UPDATE
        <<<SQL
CREATE TRIGGER audit_log_no_update
BEFORE UPDATE ON audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'audit_log is append-only — UPDATE is forbidden';
END
SQL,

        // 9c. audit_log append-only trigger — prevent DELETE
        <<<SQL
CREATE TRIGGER audit_log_no_delete
BEFORE DELETE ON audit_log
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
    SET MESSAGE_TEXT = 'audit_log is append-only — DELETE is forbidden';
END
SQL,

        // 10. settings
        <<<SQL
CREATE TABLE settings (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name        VARCHAR(191) NOT NULL UNIQUE,
    value           LONGTEXT NULL,
    is_encrypted    BOOLEAN NOT NULL DEFAULT FALSE,
    data_type       ENUM('string','integer','boolean','json','encrypted') NOT NULL DEFAULT 'string',
    description     VARCHAR(255) NULL,
    updated_by      INT UNSIGNED NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 11. modules
        <<<SQL
CREATE TABLE modules (
    id              VARCHAR(50) PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    version         VARCHAR(20) NOT NULL,
    description     TEXT NULL,
    is_enabled      BOOLEAN NOT NULL DEFAULT TRUE,
    is_core         BOOLEAN NOT NULL DEFAULT FALSE,
    manifest        JSON NOT NULL,
    installed_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_enabled (is_enabled),
    INDEX idx_core (is_core)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,

        // 12. module_configs
        <<<SQL
CREATE TABLE module_configs (
    module_id       VARCHAR(50) NOT NULL,
    site_id         INT UNSIGNED NULL,
    config          JSON NOT NULL,
    created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (module_id, site_id),
    FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE,
    INDEX idx_site (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
    ],
    'down' => [
        'DROP TRIGGER IF EXISTS audit_log_no_delete',
        'DROP TRIGGER IF EXISTS audit_log_no_update',
        'DROP TABLE IF EXISTS module_configs',
        'DROP TABLE IF EXISTS modules',
        'DROP TABLE IF EXISTS settings',
        'DROP TABLE IF EXISTS audit_log',
        'DROP TABLE IF EXISTS user_site_permissions',
        'DROP TABLE IF EXISTS site_meta',
        'DROP TABLE IF EXISTS site_tag_map',
        'DROP TABLE IF EXISTS site_tags',
        'DROP TABLE IF EXISTS site_group_map',
        'DROP TABLE IF EXISTS site_groups',
        'DROP TABLE IF EXISTS site_credentials',
        'DROP TABLE IF EXISTS sites',
        'DROP TABLE IF EXISTS user_sessions',
        'DROP TABLE IF EXISTS users',
    ],
];
