# WP Monitor — API specifikace

## 1. Konvence

### 1.1 Base URL

```
https://wp-monitor.example.com/api
```

### 1.2 Content-Type

Všechny requesty a response používají `application/json; charset=utf-8`.

### 1.3 Autentizace

Všechny endpointy (kromě `/auth/login`) vyžadují JWT token v headeru:

```
Authorization: Bearer <jwt_token>
X-CSRF-Token: <csrf_token>    (pouze pro POST/PUT/DELETE)
```

### 1.4 Standardní response formát

**Úspěch:**

```json
{
    "data": { ... },           // payload (objekt nebo pole)
    "meta": {                  // volitelná metadata
        "pagination": {
            "page": 1,
            "perPage": 25,
            "total": 142,
            "totalPages": 6
        }
    }
}
```

**Chyba:**

```json
{
    "error": {
        "code": "ERROR_CODE",
        "message": " lidsky čitelná zpráva",
        "details": { ... },     // volitelné detaily
        "timestamp": "2026-08-07T05:30:00Z",
        "requestId": "req_abc123"
    }
}
```

### 1.5 HTTP status kódy

| Kód | Význam |
|-----|--------|
| 200 | OK — úspěch |
| 201 | Created — vytvořeno |
| 202 | Accepted — asynchronní operace přijata |
| 204 | No Content — úspěch bez payloadu |
| 400 | Bad Request — neplatný vstup |
| 401 | Unauthorized — chybějící/neplatný token |
| 403 | Forbidden — nedostatečná oprávnění |
| 404 | Not Found — resource neexistuje |
| 409 | Conflict — duplicita / konflikt stavu |
| 422 | Unprocessable Entity — validace selhala |
| 429 | Too Many Requests — rate limit překročen |
| 500 | Internal Server Error |
| 502 | Bad Gateway — chyba komunikace se spravovaným webem |
| 503 | Service Unavailable — dočasně nedostupné |

### 1.6 Error kódy

| Kód | HTTP | Popis |
|-----|------|-------|
| `AUTH_INVALID_CREDENTIALS` | 401 | Neplatné přihlašovací údaje |
| `AUTH_ACCOUNT_LOCKED` | 403 | Účet je uzamčen |
| `AUTH_SESSION_EXPIRED` | 401 | Session vypršela |
| `AUTH_INSUFFICIENT_ROLE` | 403 | Nedostatečná role |
| `CSRF_TOKEN_INVALID` | 403 | Neplatný CSRF token |
| `RATE_LIMIT_EXCEEDED` | 429 | Rate limit překročen |
| `SITE_NOT_FOUND` | 404 | Web neexistuje |
| `SITE_UNREACHABLE` | 502 | Nelze se připojit k webu |
| `SITE_ALREADY_EXISTS` | 409 | Web s tout URL již existuje |
| `CREDENTIAL_DECRYPT_FAILED` | 500 | Dešifrování credentials selhalo |
| `VALIDATION_FAILED` | 422 | Validace vstupu selhala |
| `MODULE_NOT_FOUND` | 404 | Modul neexistuje |
| `MODULE_NOT_ENABLED` | 403 | Modul není aktivován |
| `BACKUP_NOT_FOUND` | 404 | Záloha neexistuje |
| `BATCH_NOT_FOUND` | 404 | Batch operace neexistuje |
| `WP_API_ERROR` | 502 | Chyba WordPress REST API |
| `MU_PLUGIN_REQUIRED` | 400 | Operace vyžaduje MU-plugin |
| `STORAGE_FULL` | 507 | Úložiště je plné |
| `INTERNAL_ERROR` | 500 | Neočekávaná chyba |

### 1.7 Pagination

Query parametry:

| Parametr | Default | Popis |
|----------|---------|-------|
| `page` | 1 | Číslo stránky |
| `perPage` | 25 | Položek na stránku (max 100) |
| `sort` | — | Řazení (např. `name` nebo `-created_at` pro DESC) |
| `filter` | — | Filtrování (syntax záleží na endpointu) |

### 1.8 Field selection

Volitelně lze omezit vrácená pole:

```
GET /api/sites?fields=id,name,url,status
```

---

## 2. Auth API

### 2.1 POST /auth/login

Přihlášení uživatele.

```
POST /api/auth/login
```

**Request:**

```json
{
    "username": "admin",
    "password": "my-master-passphrase"
}
```

**Response 200:**

```json
{
    "data": {
        "token": "eyJhbGciOiJIUzI1NiIs...",
        "csrfToken": "a1b2c3d4e5f6...",
        "expiresIn": 3600,
        "user": {
            "id": 1,
            "username": "admin",
            "role": "admin",
            "email": "admin@example.com"
        }
    }
}
```

**Response 401:**

```json
{
    "error": {
        "code": "AUTH_INVALID_CREDENTIALS",
        "message": "Neplatné přihlašovací údaje"
    }
}
```

**Response 429:**

```json
{
    "error": {
        "code": "RATE_LIMIT_EXCEEDED",
        "message": "Příliš mnoho pokusů. Zkuste to za 14 minut.",
        "details": {
            "retryAfter": 840
        }
    }
}
```

### 2.2 POST /auth/logout

Odhlášení — zničení session + revokace JWT.

```
POST /api/auth/logout
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 204:** (No Content)

### 2.3 POST /auth/verify

Ověření platnosti JWT tokenu.

```
POST /api/auth/verify
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "valid": true,
        "user": {
            "id": 1,
            "username": "admin",
            "role": "admin"
        },
        "expiresAt": "2026-08-07T06:30:00Z"
    }
}
```

### 2.4 POST /auth/password/change

Změna master hesla — rotace všech šifrovaných credentials.

```
POST /api/auth/password/change
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "currentPassword": "old-passphrase",
    "newPassword": "new-passphrase",
    "confirmPassword": "new-passphrase"
}
```

**Response 200:**

```json
{
    "data": {
        "success": true,
        "rotatedCredentials": 42,
        "message": "Master heslo změněno. Bylo rešifrováno 42 přihlašovacích údajů."
    }
}
```

**Response 422:**

```json
{
    "error": {
        "code": "VALIDATION_FAILED",
        "message": "Validace selhala",
        "details": {
            "newPassword": "Heslo musí mít alespoň 12 znaků",
            "confirmPassword": "Hesla se neshodují"
        }
    }
}
```

### 2.5 GET /auth/session

Info o aktuální session.

```
GET /api/auth/session
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "userId": 1,
        "username": "admin",
        "role": "admin",
        "createdAt": "2026-08-07T05:30:00Z",
        "lastActivity": "2026-08-07T05:45:00Z",
        "expiresAt": "2026-08-07T05:45:00Z",
        "ipAddress": "192.168.1.1"
    }
}
```

---

## 3. Users API

### 3.1 GET /users

Seznam uživatelů (pouze admin).

```
GET /api/users?page=1&perPage=25&sort=username
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": 1,
            "username": "admin",
            "email": "admin@example.com",
            "role": "admin",
            "isActive": true,
            "lastLoginAt": "2026-08-07T05:30:00Z",
            "createdAt": "2026-01-01T00:00:00Z"
        }
    ],
    "meta": {
        "pagination": {
            "page": 1,
            "perPage": 25,
            "total": 3,
            "totalPages": 1
        }
    }
}
```

### 3.2 POST /users

Vytvoření uživatele (pouze admin).

```
POST /api/users
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "username": "manager1",
    "email": "manager@example.com",
    "password": "initial-passphrase",
    "role": "manager"
}
```

**Response 201:**

```json
{
    "data": {
        "id": 2,
        "username": "manager1",
        "email": "manager@example.com",
        "role": "manager",
        "isActive": true,
        "createdAt": "2026-08-07T05:30:00Z"
    }
}
```

### 3.3 PUT /users/{id}

Úprava uživatele.

```
PUT /api/users/2
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "email": "new-email@example.com",
    "role": "admin",
    "isActive": true
}
```

**Response 200:**

```json
{
    "data": {
        "id": 2,
        "username": "manager1",
        "email": "new-email@example.com",
        "role": "admin",
        "isActive": true,
        "updatedAt": "2026-08-07T05:35:00Z"
    }
}
```

### 3.4 DELETE /users/{id}

Smazání uživatele.

```
DELETE /api/users/2
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 204:** (No Content)

### 3.5 PUT /users/{id}/permissions

Nastavení per-site oprávnění.

```
PUT /api/users/2/permissions
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "permissions": [
        { "siteId": 1, "canView": true, "canManage": true },
        { "siteId": 2, "canView": true, "canManage": false },
        { "siteId": 3, "canView": false, "canManage": false }
    ]
}
```

**Response 200:**

```json
{
    "data": {
        "userId": 2,
        "permissions": [
            { "siteId": 1, "canView": true, "canManage": true },
            { "siteId": 2, "canView": true, "canManage": false }
        ]
    }
}
```

---

## 4. Sites API

### 4.1 GET /sites

Seznam webů s filtrováním a řazením.

```
GET /api/sites?page=1&perPage=25&sort=-created_at&filter[status]=online&filter[group]=2&search=example
Authorization: Bearer <token>
```

**Query parametry:**

| Parametr | Typ | Popis |
|----------|-----|-------|
| `filter[status]` | string | `online`, `offline`, `degraded`, `unknown` |
| `filter[group]` | int | ID skupiny |
| `filter[tag]` | string | Název tagu |
| `search` | string | Full-text vyhledávání (name, url) |
| `fields` | string | Omezení vrácených polí |

**Response 200:**

```json
{
    "data": [
        {
            "id": 1,
            "name": "Klient - Firma s.r.o.",
            "url": "https://firma.cz",
            "wpUsername": "wpmonitor",
            "wpVersion": "6.4.3",
            "phpVersion": "8.3.0",
            "muPluginVersion": "1.0.0",
            "status": "online",
            "httpStatus": 200,
            "responseTimeMs": 245,
            "lastCheckedAt": "2026-08-07T05:25:00Z",
            "groups": [
                { "id": 1, "name": "Produkční" }
            ],
            "tags": ["e-commerce", "woocommerce"],
            "createdAt": "2026-01-15T10:00:00Z",
            "updatedAt": "2026-08-07T05:25:00Z"
        }
    ],
    "meta": {
        "pagination": {
            "page": 1,
            "perPage": 25,
            "total": 42,
            "totalPages": 2
        }
    }
}
```

### 4.2 POST /sites

Přidání nového webu.

```
POST /api/sites
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "name": "Klient - Firma s.r.o.",
    "url": "https://firma.cz",
    "wpUsername": "wpmonitor",
    "applicationPassword": "xxxx xxxx xxxx xxxx xxxx xxxx",
    "groups": [1],
    "tags": ["e-commerce"],
    "testConnection": true
}
```

**Response 201:**

```json
{
    "data": {
        "id": 43,
        "name": "Klient - Firma s.r.o.",
        "url": "https://firma.cz",
        "wpUsername": "wpmonitor",
        "wpVersion": "6.4.3",
        "phpVersion": null,
        "muPluginVersion": null,
        "status": "online",
        "httpStatus": 200,
        "responseTimeMs": 312,
        "lastCheckedAt": "2026-08-07T05:30:00Z",
        "connectionTest": {
            "success": true,
            "wpVersion": "6.4.3",
            "userRole": "administrator",
            "detectedPlugins": 18,
            "detectedThemes": 3
        },
        "createdAt": "2026-08-07T05:30:00Z"
    }
}
```

**Response 422 (validace):**

```json
{
    "error": {
        "code": "VALIDATION_FAILED",
        "message": "Validace selhala",
        "details": {
            "url": "URL musí začínat https://",
            "applicationPassword": "Application password je povinný"
        }
    }
}
```

**Response 409 (duplicita):**

```json
{
    "error": {
        "code": "SITE_ALREADY_EXISTS",
        "message": "Web s touto URL již existuje",
        "details": {
            "existingSiteId": 15,
            "existingSiteName": "Firma s.r.o. (old)"
        }
    }
}
```

### 4.3 GET /sites/{id}

Detail webu.

```
GET /api/sites/43
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "id": 43,
        "name": "Klient - Firma s.r.o.",
        "url": "https://firma.cz",
        "wpUsername": "wpmonitor",
        "wpVersion": "6.4.3",
        "phpVersion": "8.3.0",
        "mysqlVersion": "10.11.8-MariaDB",
        "muPluginVersion": "1.0.0",
        "status": "online",
        "httpStatus": 200,
        "responseTimeMs": 245,
        "lastCheckedAt": "2026-08-07T05:25:00Z",
        "lastError": null,
        "groups": [
            { "id": 1, "name": "Produkční", "color": "#3B82F6" }
        ],
        "tags": ["e-commerce", "woocommerce"],
        "metadata": {
            "plugins": 18,
            "themes": 3,
            "posts": 142,
            "pages": 28,
            "users": 7,
            "dbSize": "245 MB",
            "diskUsage": "1.2 GB"
        },
        "createdAt": "2026-01-15T10:00:00Z",
        "updatedAt": "2026-08-07T05:25:00Z"
    }
}
```

### 4.4 PUT /sites/{id}

Úprava webu.

```
PUT /api/sites/43
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "name": "Klient - Firma s.r.o. (aktualizováno)",
    "wpUsername": "wpmonitor",
    "applicationPassword": "nové-heslo-xxxx",
    "groups": [1, 3],
    "tags": ["e-commerce", "woocommerce", "vip"]
}
```

**Poznámka:** Pokud `applicationPassword` chybí, credentials se neaktualizují.

**Response 200:**

```json
{
    "data": {
        "id": 43,
        "name": "Klient - Firma s.r.o. (aktualizováno)",
        "url": "https://firma.cz",
        "groups": [
            { "id": 1, "name": "Produkční" },
            { "id": 3, "name": "VIP klienti" }
        ],
        "tags": ["e-commerce", "woocommerce", "vip"],
        "updatedAt": "2026-08-07T05:35:00Z"
    }
}
```

### 4.5 DELETE /sites/{id}

Smazání webu (s potvrzením).

```
DELETE /api/sites/43?confirm=true
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 204:** (No Content)

**Response 400 (bez potvrzení):**

```json
{
    "error": {
        "code": "CONFIRMATION_REQUIRED",
        "message": "Vyžadováno potvrzení — přidejte ?confirm=true"
    }
}
```

### 4.6 POST /sites/{id}/test

Test připojení k webu.

```
POST /api/sites/43/test
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 200:**

```json
{
    "data": {
        "success": true,
        "httpStatus": 200,
        "responseTimeMs": 245,
        "wpVersion": "6.4.3",
        "userRole": "administrator",
        "restApiAvailable": true,
        "muPluginInstalled": true,
        "muPluginVersion": "1.0.0"
    }
}
```

**Response 502:**

```json
{
    "error": {
        "code": "SITE_UNREACHABLE",
        "message": "Nelze se připojit k webu firma.cz",
        "details": {
            "reason": "connection_timeout",
            "timeout": 30
        }
    }
}
```

### 4.7 POST /sites/{id}/refresh

Refresh informací o webu (WP verze, pluginy, šablony).

```
POST /api/sites/43/refresh
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 43,
        "wpVersion": "6.4.3",
        "phpVersion": "8.3.0",
        "plugins": [
            { "slug": "yoast-seo", "name": "Yoast SEO", "version": "20.3", "status": "active", "updateAvailable": true, "newVersion": "20.4" },
            { "slug": "wordfence", "name": "Wordfence Security", "version": "7.11.1", "status": "active", "updateAvailable": false }
        ],
        "themes": [
            { "stylesheet": "storefront", "name": "Storefront", "version": "4.5.0", "status": "active", "updateAvailable": false }
        ],
        "refreshedAt": "2026-08-07T05:40:00Z"
    }
}
```

### 4.8 POST /sites/import

CSV import webů.

```
POST /api/sites/import
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
Content-Type: multipart/form-data
```

**Request:** CSV soubor s hlavičkou:

```csv
name,url,wpUsername,applicationPassword,groups,tags
Firma A,https://firma-a.cz,wpmonitor,xxxx xxxx xxxx xxxx xxxx xxxx,Produkční,e-commerce
Firma B,https://firma-b.cz,wpmonitor,yyyy yyyy yyyy yyyy yyyy yyyy,Produkční,blog
```

**Response 200:**

```json
{
    "data": {
        "total": 2,
        "imported": 2,
        "failed": 0,
        "results": [
            { "row": 1, "siteId": 44, "status": "success", "name": "Firma A" },
            { "row": 2, "siteId": 45, "status": "success", "name": "Firma B" }
        ]
    }
}
```

---

## 5. Dashboard API

### 5.1 GET /dashboard/overview

Agregované metriky pro dashboard.

```
GET /api/dashboard/overview?group=1
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "totalSites": 42,
        "statusBreakdown": {
            "online": 38,
            "offline": 2,
            "degraded": 1,
            "unknown": 1
        },
        "pendingUpdates": {
            "core": 5,
            "plugins": 87,
            "themes": 12,
            "totalSitesWithUpdates": 23
        },
        "securityAlerts": {
            "critical": 2,
            "high": 5,
            "medium": 14,
            "low": 8
        },
        "uptime": {
            "average24h": 99.95,
            "average7d": 99.87,
            "average30d": 99.92
        },
        "sslExpiring": {
            "within7Days": 1,
            "within14Days": 3,
            "within30Days": 7
        },
        "recentActivity": [
            {
                "id": 1234,
                "action": "update.execute",
                "user": "admin",
                "siteName": "Firma s.r.o.",
                "status": "success",
                "createdAt": "2026-08-07T05:20:00Z"
            }
        ],
        "sitesNeedingAttention": [
            {
                "siteId": 15,
                "name": "Offline web",
                "url": "https://offline.cz",
                "issues": ["site_down", "ssl_expiring"]
            }
        ]
    }
}
```

### 5.2 GET /dashboard/widgets

Seznam aktivních dashboard widgetů z modulů.

```
GET /api/dashboard/widgets
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": "pending-updates",
            "moduleId": "updates",
            "title": "Čekající aktualizace",
            "size": "medium",
            "position": 1,
            "refreshInterval": 60,
            "dataUrl": "/api/updates/summary"
        },
        {
            "id": "security-score",
            "moduleId": "security",
            "title": "Security score",
            "size": "small",
            "position": 2,
            "refreshInterval": 300,
            "dataUrl": "/api/security/score"
        }
    ]
}
```

---

## 6. Updates API

### 6.1 GET /updates

Agregovaný přehled dostupných aktualizací napříč weby.

```
GET /api/updates?filter[site]=1,2,3&filter[type]=plugins
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "summary": {
            "totalSites": 42,
            "sitesWithUpdates": 23,
            "totalUpdates": 104,
            "byType": {
                "core": 5,
                "plugins": 87,
                "themes": 12
            }
        },
        "sites": [
            {
                "siteId": 1,
                "siteName": "Firma s.r.o.",
                "updates": [
                    {
                        "type": "core",
                        "slug": "wordpress",
                        "name": "WordPress",
                        "currentVersion": "6.4.2",
                        "newVersion": "6.4.3",
                        "severity": "medium"
                    },
                    {
                        "type": "plugin",
                        "slug": "yoast-seo",
                        "name": "Yoast SEO",
                        "currentVersion": "20.1",
                        "newVersion": "20.4",
                        "severity": "low"
                    }
                ]
            }
        ]
    }
}
```

### 6.2 GET /updates/{siteId}

Dostupné aktualizace pro konkrétní web.

```
GET /api/updates/1
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "wpVersion": "6.4.2",
        "updates": {
            "core": {
                "current": "6.4.2",
                "available": "6.4.3",
                "available": true
            },
            "plugins": [
                {
                    "slug": "yoast-seo",
                    "name": "Yoast SEO",
                    "current": "20.1",
                    "available": "20.4",
                    "updateAvailable": true,
                    "isExcluded": false
                }
            ],
            "themes": [
                {
                    "stylesheet": "storefront",
                    "name": "Storefront",
                    "current": "4.4.0",
                    "available": "4.5.0",
                    "updateAvailable": true
                }
            ]
        },
        "lastCheckedAt": "2026-08-07T05:25:00Z"
    }
}
```

### 6.3 POST /updates/{siteId}/execute

Provedení aktualizace na jednom webu.

```
POST /api/updates/1/execute
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "type": "plugins",           // core | plugins | themes | all
    "slugs": ["yoast-seo"],     // null = všechny; konkrétní = vybrané
    "preBackup": true
}
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "status": "success",
        "updates": [
            {
                "type": "plugin",
                "slug": "yoast-seo",
                "name": "Yoast SEO",
                "fromVersion": "20.1",
                "toVersion": "20.4",
                "status": "success"
            }
        ],
        "backupId": 567,
        "duration": 12.5
    }
}
```

### 6.4 POST /updates/batch

Hromadná aktualizace — asynchronní operace.

```
POST /api/updates/batch
Authorization: Bearer <token>
X-CSSR-Token: <csrf>
```

**Request:**

```json
{
    "siteIds": [1, 2, 3, 5, 8],
    "type": "all",
    "preBackup": true,
    "dryRun": false
}
```

**Response 202:**

```json
{
    "data": {
        "batchId": "batch_abc123",
        "status": "processing",
        "totalSites": 5,
        "statusUrl": "/api/updates/batch/batch_abc123/status"
    }
}
```

### 6.5 GET /updates/batch/{batchId}/status

Status hromadné aktualizace (polling).

```
GET /api/updates/batch/batch_abc123/status
Authorization: Bearer <token>
```

**Response 200 (processing):**

```json
{
    "data": {
        "batchId": "batch_abc123",
        "status": "processing",
        "progress": {
            "total": 5,
            "completed": 2,
            "success": 2,
            "failed": 0,
            "pending": 3
        },
        "currentSite": {
            "siteId": 3,
            "siteName": "Klient C",
            "status": "processing"
        },
        "results": [
            {
                "siteId": 1,
                "siteName": "Firma s.r.o.",
                "status": "success",
                "updatesApplied": 3,
                "duration": 15.2
            },
            {
                "siteId": 2,
                "siteName": "Klient B",
                "status": "success",
                "updatesApplied": 1,
                "duration": 8.7
            }
        ]
    }
}
```

**Response 200 (completed):**

```json
{
    "data": {
        "batchId": "batch_abc123",
        "status": "completed",
        "progress": {
            "total": 5,
            "completed": 5,
            "success": 4,
            "failed": 1,
            "pending": 0
        },
        "results": [
            { "siteId": 1, "siteName": "Firma s.r.o.", "status": "success", "updatesApplied": 3 },
            { "siteId": 2, "siteName": "Klient B", "status": "success", "updatesApplied": 1 },
            { "siteId": 3, "siteName": "Klient C", "status": "success", "updatesApplied": 5 },
            { "siteId": 5, "siteName": "Klient D", "status": "failed", "error": "Connection timeout" },
            { "siteId": 8, "siteName": "Klient E", "status": "success", "updatesApplied": 2 }
        ],
        "startedAt": "2026-08-07T05:30:00Z",
        "completedAt": "2026-08-07T05:31:45Z",
        "totalDuration": 105.3
    }
}
```

### 6.6 POST /updates/batch/preview

Dry-run preview hromadné aktualizace.

```
POST /api/updates/batch/preview
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "siteIds": [1, 2, 3],
    "type": "all"
}
```

**Response 200:**

```json
{
    "data": {
        "totalUpdates": 8,
        "bySite": [
            {
                "siteId": 1,
                "siteName": "Firma s.r.o.",
                "updates": [
                    { "type": "core", "slug": "wordpress", "from": "6.4.2", "to": "6.4.3" },
                    { "type": "plugin", "slug": "yoast-seo", "from": "20.1", "to": "20.4" }
                ]
            }
        ]
    }
}
```

### 6.7 GET /updates/{siteId}/history

Historie aktualizací webu.

```
GET /api/updates/1/history?page=1&perPage=25
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": 123,
            "siteId": 1,
            "updateType": "plugin",
            "slug": "yoast-seo",
            "name": "Yoast SEO",
            "fromVersion": "20.1",
            "toVersion": "20.4",
            "status": "success",
            "user": "admin",
            "batchId": null,
            "createdAt": "2026-08-07T05:30:00Z"
        }
    ],
    "meta": {
        "pagination": { "page": 1, "perPage": 25, "total": 45, "totalPages": 2 }
    }
}
```

### 6.8 POST /updates/{siteId}/rollback/{type}/{slug}

Rollback na předchozí verzi.

```
POST /api/updates/1/rollback/plugin/yoast-seo
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "type": "plugin",
        "slug": "yoast-seo",
        "name": "Yoast SEO",
        "rolledBackFrom": "20.4",
        "rolledBackTo": "20.1",
        "status": "success"
    }
}
```

---

## 7. Backups API

### 7.1 GET /backups

Seznam záloh s filtrováním.

```
GET /api/backups?filter[site]=1&filter[type]=full&filter[date_from]=2026-08-01&filter[date_to]=2026-08-07
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": 567,
            "siteId": 1,
            "siteName": "Firma s.r.o.",
            "backupType": "full",
            "status": "completed",
            "storageBackend": "local",
            "fileSize": 524288000,
            "fileSizeHuman": "500 MB",
            "encrypted": true,
            "createdAt": "2026-08-07T03:00:00Z",
            "completedAt": "2026-08-07T03:02:45Z",
            "duration": 165
        }
    ],
    "meta": {
        "pagination": { "page": 1, "perPage": 25, "total": 30, "totalPages": 2 }
    }
}
```

### 7.2 POST /backups/{siteId}/create

Vytvoření zálohy.

```
POST /api/backups/1/create
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "type": "full",              // db | files | full
    "storageBackend": "local",   // local | s3 | sftp (override globálního nastavení)
    "encrypt": true,
    "compress": "gzip"           // none | gzip | zstd
}
```

**Response 202:**

```json
{
    "data": {
        "backupId": 568,
        "siteId": 1,
        "status": "creating",
        "statusUrl": "/api/backups/568"
    }
}
```

### 7.3 GET /backups/{id}

Detail zálohy.

```
GET /api/backups/568
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "id": 568,
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "backupType": "full",
        "status": "completed",
        "storageBackend": "local",
        "storagePath": "/var/www/wp-monitor/storage/backups/site_1_20260807_full.enc.gz",
        "fileSize": 524288000,
        "fileSizeHuman": "500 MB",
        "encrypted": true,
        "checksum": "sha256:abc123...",
        "metadata": {
            "tables": 42,
            "filesCount": 1856,
            "dbSize": "120 MB",
            "filesSize": "380 MB",
            "excludedPaths": ["cache/*", "uploads/tmp/*"]
        },
        "createdAt": "2026-08-07T03:00:00Z",
        "completedAt": "2026-08-07T03:02:45Z",
        "duration": 165
    }
}
```

### 7.4 GET /backups/{id}/download

Stažení zálohy (streamed response).

```
GET /api/backups/568/download
Authorization: Bearer <token>
```

**Response 200:** `application/octet-stream` s `Content-Disposition: attachment`

### 7.5 POST /backups/{id}/restore

Obnova ze zálohy.

```
POST /api/backups/568/restore
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "confirm": true,             // povinné potvrzení
    "restoreDb": true,
    "restoreFiles": true,
    "backupBeforeRestore": true  // vytvořit zálohu aktuálního stavu před obnovou
}
```

**Response 202:**

```json
{
    "data": {
        "restoreId": "restore_xyz789",
        "backupId": 568,
        "siteId": 1,
        "status": "processing",
        "statusUrl": "/api/backups/restores/restore_xyz789/status"
    }
}
```

### 7.6 DELETE /backups/{id}

Smazání zálohy.

```
DELETE /api/backups/568?confirm=true
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 204:** (No Content)

### 7.7 GET /backups/schedules

Seznam plánovaných záloh.

```
GET /api/backups/schedules
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": 1,
            "siteId": 1,
            "siteName": "Firma s.r.o.",
            "backupType": "full",
            "schedule": "0 3 * * *",
            "scheduleHuman": "Denně ve 3:00",
            "storageBackend": "s3",
            "retention": {
                "daily": 7,
                "weekly": 4,
                "monthly": 12,
                "maxAgeDays": 365
            },
            "isActive": true,
            "lastRunAt": "2026-08-07T03:00:00Z",
            "nextRunAt": "2026-08-08T03:00:00Z"
        }
    ]
}
```

### 7.8 POST /backups/schedules

Vytvoření plánu zálohování.

```
POST /api/backups/schedules
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "siteId": 1,
    "backupType": "full",
    "schedule": "0 3 * * *",
    "storageBackend": "s3",
    "retention": {
        "daily": 7,
        "weekly": 4,
        "monthly": 12,
        "maxAgeDays": 365
    }
}
```

**Response 201:**

```json
{
    "data": {
        "id": 2,
        "siteId": 1,
        "backupType": "full",
        "schedule": "0 3 * * *",
        "isActive": true,
        "nextRunAt": "2026-08-08T03:00:00Z",
        "createdAt": "2026-08-07T05:30:00Z"
    }
}
```

### 7.9 GET /backups/storage/info

Info o úložišti záloh.

```
GET /api/backups/storage/info
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "backend": "local",
        "totalSpace": 107374182400,
        "usedSpace": 53687091200,
        "freeSpace": 53687091200,
        "usedPercent": 50,
        "backupCount": 142,
        "totalBackupSize": 53687091200,
        "oldestBackup": "2026-06-01T03:00:00Z",
        "newestBackup": "2026-08-07T03:00:00Z"
    }
}
```

---

## 8. Security & Monitoring API

### 8.1 GET /monitoring/uptime

Uptime statistiky napříč weby.

```
GET /api/monitoring/uptime?period=24h
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "period": "24h",
        "overallUptime": 99.95,
        "totalSites": 42,
        "sitesUp": 40,
        "sitesDown": 1,
        "sitesDegraded": 1,
        "sites": [
            {
                "siteId": 1,
                "siteName": "Firma s.r.o.",
                "url": "https://firma.cz",
                "status": "up",
                "uptime24h": 100.0,
                "uptime7d": 99.98,
                "uptime30d": 99.95,
                "avgResponseTimeMs": 245,
                "lastCheckedAt": "2026-08-07T05:25:00Z"
            },
            {
                "siteId": 15,
                "siteName": "Offline web",
                "url": "https://offline.cz",
                "status": "down",
                "uptime24h": 87.5,
                "uptime7d": 95.2,
                "uptime30d": 97.8,
                "avgResponseTimeMs": null,
                "lastCheckedAt": "2026-08-07T05:20:00Z",
                "lastError": "Connection refused"
            }
        ]
    }
}
```

### 8.2 GET /monitoring/uptime/{siteId}/history

Downtime historie pro konkrétní web.

```
GET /api/monitoring/uptime/1/history?from=2026-08-01&to=2026-08-07
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "period": { "from": "2026-08-01", "to": "2026-08-07" },
        "uptimePercent": 99.95,
        "incidents": [
            {
                "incidentId": 42,
                "type": "downtime",
                "severity": "warning",
                "startedAt": "2026-08-03T14:20:00Z",
                "resolvedAt": "2026-08-03T14:23:15Z",
                "durationSeconds": 195,
                "durationHuman": "3m 15s",
                "details": { "reason": "Connection timeout" }
            }
        ],
        "responseTimeStats": {
            "avg": 245,
            "p95": 412,
            "p99": 680,
            "min": 120,
            "max": 1200
        }
    }
}
```

### 8.3 GET /monitoring/ssl

SSL status napříč weby.

```
GET /api/monitoring/ssl
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "siteId": 1,
            "siteName": "Firma s.r.o.",
            "url": "https://firma.cz",
            "issuer": "Let's Encrypt",
            "validFrom": "2026-07-01",
            "validTo": "2026-09-29",
            "daysUntilExpiry": 53,
            "isSelfSigned": false,
            "isValid": true,
            "status": "ok"
        },
        {
            "siteId": 7,
            "siteName": "Klient G",
            "url": "https://klient-g.cz",
            "issuer": "DigiCert",
            "validTo": "2026-08-12",
            "daysUntilExpiry": 5,
            "isSelfSigned": false,
            "isValid": true,
            "status": "expiring_soon"
        }
    ]
}
```

### 8.4 GET /security/scan/{siteId}

Security scan výsledky pro web.

```
GET /api/security/scan/1
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "scannedAt": "2026-08-07T04:00:00Z",
        "securityScore": 78,
        "grade": "C",
        "scoreFactors": {
            "coreOutdated": false,
            "coreVulnerabilities": 0,
            "pluginVulnerabilities": 3,
            "themeVulnerabilities": 0,
            "sslValid": true,
            "sslExpiringSoon": false,
            "debugMode": false,
            "fileEditingEnabled": false,
            "xmlrpcEnabled": true,
            "excessAdminAccounts": 0,
            "no2faAdmins": 2
        },
        "vulnerabilities": [
            {
                "id": 101,
                "entityType": "plugin",
                "slug": "old-plugin",
                "name": "Old Plugin",
                "installedVersion": "1.2.0",
                "severity": "high",
                "cveId": "CVE-2024-12345",
                "title": "SQL Injection v Old Plugin",
                "remediation": "Aktualizujte na verzi 1.3.0 nebo novější",
                "status": "open"
            }
        ],
        "recommendations": [
            {
                "priority": "high",
                "action": "Aktualizovat plugin 'Old Plugin' na verzi 1.3.0+",
                "reason": "Zranitelnost CVE-2024-12345 (SQL Injection)"
            },
            {
                "priority": "medium",
                "action": "Zakázat XML-RPC",
                "reason": "XML-RPC je povolen a představuje bezpečnostní riziko"
            },
            {
                "priority": "low",
                "action": "Povolit 2FA pro admin účty",
                "reason": "2 admin účty nemají aktivované 2FA"
            }
        ]
    }
}
```

### 8.5 POST /security/scan/{siteId}

Spuštění security scanu.

```
POST /api/security/scan/1
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 202:**

```json
{
    "data": {
        "scanId": "scan_def456",
        "siteId": 1,
        "status": "processing",
        "statusUrl": "/api/security/scan/1?scanId=scan_def456"
    }
}
```

### 8.6 POST /security/scan/batch

Hromadný security scan.

```
POST /api/security/scan/batch
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "siteIds": [1, 2, 3, 5, 8]
}
```

**Response 202:**

```json
{
    "data": {
        "batchId": "batch_sec_abc",
        "status": "processing",
        "totalSites": 5,
        "statusUrl": "/api/security/scan/batch/batch_sec_abc/status"
    }
}
```

### 8.7 GET /security/score

Security score napříč weby.

```
GET /api/security/score
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "overallScore": 82,
        "overallGrade": "B",
        "totalSites": 42,
        "gradeDistribution": {
            "A": 15,
            "B": 18,
            "C": 6,
            "D": 2,
            "F": 1
        },
        "sites": [
            {
                "siteId": 1,
                "siteName": "Firma s.r.o.",
                "score": 78,
                "grade": "C",
                "openVulnerabilities": 3,
                "scannedAt": "2026-08-07T04:00:00Z"
            }
        ]
    }
}
```

---

## 9. SEO & Performance API

### 9.1 GET /seo/audit/{siteId}

SEO audit výsledky.

```
GET /api/seo/audit/1
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "auditedAt": "2026-08-07T04:30:00Z",
        "score": 85,
        "grade": "B",
        "titleTag": "Firma s.r.o. — Úvod",
        "titleLength": 22,
        "metaDescription": "Profesionální služby pro váš byznys...",
        "metaDescLength": 155,
        "hasOgTags": true,
        "hasTwitterCards": false,
        "hasCanonical": true,
        "hasSitemap": true,
        "hasRobotsTxt": true,
        "headingStructure": {
            "h1": 1,
            "h2": 5,
            "h3": 12,
            "issues": []
        },
        "structuredData": [
            { "type": "Organization", "valid": true },
            { "type": "WebSite", "valid": true }
        ],
        "brokenLinks": [],
        "imageAltIssues": {
            "totalImages": 24,
            "missingAlt": 3,
            "images": ["/wp-content/uploads/img1.jpg", "/wp-content/uploads/banner.png"]
        },
        "recommendations": [
            {
                "priority": "medium",
                "action": "Přidat Twitter Card meta tagy",
                "reason": "Chybí Twitter Card tags pro sociální sdílení"
            },
            {
                "priority": "low",
                "action": "Doplnit alt texty pro 3 obrázky",
                "reason": "3 obrázky nemají alt text"
            }
        ]
    }
}
```

### 9.2 POST /seo/audit/{siteId}

Spuštění SEO auditu.

```
POST /api/seo/audit/1
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 202:**

```json
{
    "data": {
        "auditId": "seo_ghi789",
        "siteId": 1,
        "status": "processing",
        "statusUrl": "/api/seo/audit/1?auditId=seo_ghi789"
    }
}
```

### 9.3 GET /performance/audit/{siteId}

Performance audit výsledky.

```
GET /api/performance/audit/1
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "auditedAt": "2026-08-07T04:30:00Z",
        "score": 72,
        "grade": "C",
        "coreWebVitals": {
            "lcp": {
                "value": 2850,
                "unit": "ms",
                "rating": "needs_improvement"
            },
            "cls": {
                "value": 0.12,
                "unit": "",
                "rating": "needs_improvement"
            },
            "inp": {
                "value": 180,
                "unit": "ms",
                "rating": "good"
            },
            "fcp": {
                "value": 1200,
                "unit": "ms",
                "rating": "needs_improvement"
            },
            "ttfb": {
                "value": 450,
                "unit": "ms",
                "rating": "needs_improvement"
            }
        },
        "pageSize": 3145728,
        "pageSizeHuman": "3.0 MB",
        "pageSizeBreakdown": {
            "html": 45056,
            "css": 262144,
            "js": 1572864,
            "images": 1048576,
            "fonts": 196608,
            "other": 16384
        },
        "requestCount": 47,
        "requestBreakdown": {
            "html": 1,
            "css": 4,
            "js": 12,
            "images": 18,
            "fonts": 4,
            "api": 3,
            "other": 5
        },
        "hasGzip": true,
        "hasBrotli": false,
        "hasCdn": true,
        "recommendations": [
            {
                "priority": "high",
                "action": "Optimalizovat LCP — zmenšit hero obrázek",
                "reason": "LCP 2850ms překračuje doporučených 2500ms",
                "potentialImprovement": "Snížení LCP o ~500ms"
            },
            {
                "priority": "medium",
                "action": "Povolit Brotli kompresi",
                "reason": "Brotli nabízí lepší kompresi než gzip pro textové soubory",
                "potentialImprovement": "Snížení přenosu o ~15%"
            },
            {
                "priority": "medium",
                "action": "Odložit načítání neprioritních JS",
                "reason": "12 JS souborů blokuje renderování",
                "potentialImprovement": "Snížení FCP o ~300ms"
            }
        ]
    }
}
```

### 9.4 GET /performance/history/{siteId}

Historie performance metrik.

```
GET /api/performance/history/1?period=30d&metrics=lcp,cls,inp,score
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "period": "30d",
        "dataPoints": [
            {
                "date": "2026-08-01",
                "score": 68,
                "lcp": 3200,
                "cls": 0.15,
                "inp": 220
            },
            {
                "date": "2026-08-07",
                "score": 72,
                "lcp": 2850,
                "cls": 0.12,
                "inp": 180
            }
        ],
        "trend": {
            "score": "improving",
            "lcp": "improving",
            "cls": "improving",
            "inp": "improving"
        }
    }
}
```

### 9.5 GET /analytics/detection/{siteId}

Detekce analytics nástrojů.

```
GET /api/analytics/detection/1
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "detectedAt": "2026-08-07T04:30:00Z",
        "googleAnalytics": true,
        "ga4": true,
        "googleTagManager": false,
        "facebookPixel": false,
        "hotjar": false,
        "microsoftClarity": true,
        "searchConsole": true,
        "otherTools": [
            { "name": "Clarity", "type": "analytics" }
        ]
    }
}
```

---

## 10. Audit Log API

### 10.1 GET /audit-log

Seznam logů s filtrováním.

```
GET /api/audit-log?page=1&perPage=25&filter[user]=1&filter[site]=1&filter[action]=update.execute&filter[date_from]=2026-08-01&filter[date_to]=2026-08-07&sort=-created_at
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": 1234,
            "userId": 1,
            "username": "admin",
            "action": "update.execute",
            "siteId": 1,
            "siteName": "Firma s.r.o.",
            "module": "updates",
            "status": "success",
            "details": {
                "type": "plugins",
                "slugs": ["yoast-seo"],
                "fromVersion": "20.1",
                "toVersion": "20.4"
            },
            "ipAddress": "192.168.1.1",
            "createdAt": "2026-08-07T05:30:00Z"
        }
    ],
    "meta": {
        "pagination": { "page": 1, "perPage": 25, "total": 1542, "totalPages": 62 }
    }
}
```

### 10.2 GET /audit-log/{id}

Detail log záznamu.

```
GET /api/audit-log/1234
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "id": 1234,
        "userId": 1,
        "username": "admin",
        "action": "update.execute",
        "siteId": 1,
        "siteName": "Firma s.r.o.",
        "module": "updates",
        "status": "success",
        "details": {
            "type": "plugins",
            "slugs": ["yoast-seo"],
            "fromVersion": "20.1",
            "toVersion": "20.4",
            "duration": 12.5,
            "backupId": 567
        },
        "ipAddress": "192.168.1.1",
        "userAgent": "Mozilla/5.0...",
        "requestId": "req_abc123",
        "createdAt": "2026-08-07T05:30:00Z"
    }
}
```

### 10.3 GET /audit-log/export

Export logů.

```
GET /api/audit-log/export?format=csv&filter[date_from]=2026-08-01&filter[date_to]=2026-08-07
Authorization: Bearer <token>
```

**Response 200:** `text/csv` s `Content-Disposition: attachment; filename="audit_log_2026-08-01_2026-08-07.csv"`

---

## 11. Settings & Modules API

### 11.1 GET /settings

Globální nastavení.

```
GET /api/settings
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "sessionTimeout": 900,
        "jwtTtl": 3600,
        "rateLimitLogin": 5,
        "wpClientTimeout": 30,
        "wpClientMaxConcurrency": 10,
        "wpClientRetryCount": 3,
        "backupRetentionDays": 30,
        "auditLogRetentionDays": 365,
        "defaultBackupStorage": "local",
        "clientSideEncryption": false
    }
}
```

### 11.2 PUT /settings

Úprava globálního nastavení (pouze admin).

```
PUT /api/settings
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "sessionTimeout": 1200,
    "wpClientMaxConcurrency": 15
}
```

**Response 200:**

```json
{
    "data": {
        "sessionTimeout": 1200,
        "jwtTtl": 3600,
        "rateLimitLogin": 5,
        "wpClientTimeout": 30,
        "wpClientMaxConcurrency": 15,
        "wpClientRetryCount": 3,
        "backupRetentionDays": 30,
        "auditLogRetentionDays": 365,
        "defaultBackupStorage": "local",
        "clientSideEncryption": false
    }
}
```

### 11.3 GET /modules

Seznam modulů.

```
GET /api/modules
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": "auth",
            "name": "Authentication",
            "version": "1.0.0",
            "isEnabled": true,
            "isCore": true,
            "description": "Autentizace a správa uživatelů"
        },
        {
            "id": "updates",
            "name": "Updates Manager",
            "version": "1.0.0",
            "isEnabled": true,
            "isCore": false,
            "description": "Správa aktualizací WordPress jádra, pluginů a šablon"
        },
        {
            "id": "backups",
            "name": "Backups",
            "version": "1.0.0",
            "isEnabled": true,
            "isCore": false,
            "description": "Zálohování DB a souborů"
        },
        {
            "id": "security",
            "name": "Security & Monitoring",
            "version": "1.0.0",
            "isEnabled": true,
            "isCore": false,
            "description": "Security scanning a monitoring"
        },
        {
            "id": "seo",
            "name": "SEO & Performance",
            "version": "1.0.0",
            "isEnabled": false,
            "isCore": false,
            "description": "SEO a performance audit"
        }
    ]
}
```

### 11.4 PUT /modules/{id}/enable

Aktivace modulu.

```
PUT /api/modules/seo/enable
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 200:**

```json
{
    "data": {
        "id": "seo",
        "isEnabled": true,
        "message": "Modul 'SEO & Performance' byl aktivován"
    }
}
```

### 11.5 PUT /modules/{id}/disable

Deaktivace modulu.

```
PUT /api/modules/seo/disable
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 200:**

```json
{
    "data": {
        "id": "seo",
        "isEnabled": false,
        "message": "Modul 'SEO & Performance' byl deaktivován"
    }
}
```

**Response 400 (core modul):**

```json
{
    "error": {
        "code": "MODULE_IS_CORE",
        "message": "Core modul 'auth' nelze deaktivovat"
    }
}
```

### 11.6 GET /modules/{id}/config

Konfigurace modulu.

```
GET /api/modules/updates/config
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "moduleId": "updates",
        "config": {
            "autoUpdateCore": false,
            "autoUpdatePlugins": false,
            "excludedPlugins": ["akismet"],
            "preUpdateBackup": true
        },
        "configSchema": {
            "autoUpdateCore": {
                "type": "boolean",
                "default": false,
                "label": "Automaticky aktualizovat WordPress jádro"
            },
            "autoUpdatePlugins": {
                "type": "boolean",
                "default": false,
                "label": "Automaticky aktualizovat pluginy"
            },
            "excludedPlugins": {
                "type": "array",
                "default": [],
                "label": "Vyloučené pluginy"
            },
            "preUpdateBackup": {
                "type": "boolean",
                "default": true,
                "label": "Vytvořit zálohu před aktualizací"
            }
        }
    }
}
```

### 11.7 PUT /modules/{id}/config

Úprava konfigurace modulu.

```
PUT /api/modules/updates/config
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "config": {
        "autoUpdateCore": true,
        "autoUpdatePlugins": false,
        "excludedPlugins": ["akismet", "custom-plugin"],
        "preUpdateBackup": true
    }
}
```

**Response 200:**

```json
{
    "data": {
        "moduleId": "updates",
        "config": {
            "autoUpdateCore": true,
            "autoUpdatePlugins": false,
            "excludedPlugins": ["akismet", "custom-plugin"],
            "preUpdateBackup": true
        },
        "updatedAt": "2026-08-07T05:35:00Z"
    }
}
```

### 11.8 GET /system/info

Systémové informace.

```
GET /api/system/info
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": {
        "appVersion": "1.0.0",
        "phpVersion": "8.3.0",
        "mysqlVersion": "10.11.8-MariaDB",
        "serverSoftware": "nginx/1.25.3",
        "memoryLimit": "512M",
        "maxExecutionTime": 120,
        "diskFreeSpace": 53687091200,
        "diskTotalSpace": 107374182400,
        "modules": {
            "total": 8,
            "enabled": 7,
            "disabled": 1
        },
        "sites": {
            "total": 42,
            "online": 38,
            "offline": 2,
            "degraded": 1,
            "unknown": 1
        },
        "phpExtensions": {
            "openssl": true,
            "sodium": true,
            "curl": true,
            "mbstring": true,
            "intl": true,
            "pdo_mysql": true,
            "opcache": true,
            "apcu": false
        }
    }
}
```

---

## 12. Notifications API

### 12.1 GET /notifications

Seznam notifikací pro aktuálního uživatele.

```
GET /api/notifications?filter[unread]=true&page=1&perPage=25
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "id": 1001,
            "event": "site_down",
            "title": "Web je nedostupný",
            "message": "Web 'Offline web' (https://offline.cz) je nedostupný již 5 minut.",
            "severity": "critical",
            "metadata": {
                "siteId": 15,
                "siteName": "Offline web",
                "url": "https://offline.cz"
            },
            "isRead": false,
            "createdAt": "2026-08-07T05:20:00Z"
        }
    ],
    "meta": {
        "pagination": { "page": 1, "perPage": 25, "total": 5, "totalPages": 1 }
    }
}
```

### 12.2 PUT /notifications/{id}/read

Označení notifikace jako přečtené.

```
PUT /api/notifications/1001/read
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 204:** (No Content)

### 12.3 PUT /notifications/read-all

Označení všech notifikací jako přečtených.

```
PUT /api/notifications/read-all
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Response 204:** (No Content)

### 12.4 GET /notifications/preferences

Nastavení notifikací.

```
GET /api/notifications/preferences
Authorization: Bearer <token>
```

**Response 200:**

```json
{
    "data": [
        {
            "channel": "email",
            "endpoint": "admin@example.com",
            "events": ["site_down", "vulnerability_critical", "ssl_expiring", "backup_failed"],
            "isEnabled": true
        },
        {
            "channel": "webhook",
            "endpoint": "https://hooks.slack.com/services/...",
            "events": ["site_down", "update_completed"],
            "isEnabled": true
        }
    ]
}
```

### 12.5 PUT /notifications/preferences

Úprava nastavení notifikací.

```
PUT /api/notifications/preferences
Authorization: Bearer <token>
X-CSRF-Token: <csrf>
```

**Request:**

```json
{
    "preferences": [
        {
            "channel": "email",
            "endpoint": "admin@example.com",
            "events": ["site_down", "vulnerability_critical", "ssl_expiring", "backup_failed"],
            "isEnabled": true
        }
    ]
}
```

**Response 200:**

```json
{
    "data": [
        {
            "channel": "email",
            "endpoint": "admin@example.com",
            "events": ["site_down", "vulnerability_critical", "ssl_expiring", "backup_failed"],
            "isEnabled": true
        }
    ]
}
```

---

## 13. Rate limiting headers

Všechny response obsahují rate limit headers (kde relevantní):

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1723000060
Retry-After: 840              (pouze při 429)
```
