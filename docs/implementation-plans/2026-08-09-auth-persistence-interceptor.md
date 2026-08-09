# Implementační plán — Auth Persistence & Refresh Interceptor

## Metadata

| Pole | Hodnota |
|------|---------|
| Název | Auth Persistence & Refresh Interceptor (httpOnly cookie) |
| Typ | funkce |
| Priorita | P1 |
| Status | completed |
| Vytvořeno | 09.08.2026 |
| Autor | Devin (GLM-5.2 High) |
| Související skill | `.devin/skills/security-coding/SKILL.md`, `.devin/skills/implementation-plan/SKILL.md` |
| Související dokumentace | `docs/03-security-model.md`, `docs/implementation-plans/2026-08-08-security-layer.md` (sekce 15.8/15.9) |

## 1. Cíl

Doplnit P1 položky z sekce 15.8 security layer plánu:

1. **Token persistence** — uživatel zůstane přihlášen i po reloadu stránky (bez nutnosti znovu zadávat heslo)
2. **Refresh interceptor** — při 401 (expirovaný access token) automaticky zavolat `/auth/refresh` a retry původní request; při selhání refreshu → logout
3. **CHANGELOG.md** — zaznamenat security layer + tuto iteraci
4. **Git commit + push** — po schválení uživatelem

**Zvolená strategie: httpOnly cookie pro refresh token** (OWASP-aligned, viz sekce 3.1).

## 2. Reálný stav (před implementací)

### 2.1 Backend

Existuje:
- `backend/src/Http/Controller/AuthController.php` — `setup/login/logout/refresh/me`, login nastavuje `wpm_csrf` cookie (HttpOnly=false, SameSite=Strict, Max-Age=900)
- `backend/src/Auth/AuthService.php` — `login()` vrací `{token, refreshToken, csrfToken, sessionId, user}`, `refresh($refreshToken)` vrací jen `{token, refreshToken}` (čte z body)
- `backend/src/Auth/JwtService.php` — issueAccessToken (15 min), issueRefreshToken (7 dní)
- `backend/src/Http/Middleware/CorsMiddleware.php` — **už má `Access-Control-Allow-Credentials: true`** ✅
- `backend/config/routes.php` — `/api/auth/{setup,login,refresh}` public, `/api/auth/{logout,me}` s AuthMiddleware

Chybí:
- Refresh token se neposílá v httpOnly cookie — jen v JSON response
- `/auth/refresh` nečte refresh token z cookie (jen z body)
- `/auth/refresh` nevrací `csrfToken`/`sessionId`/`user` (jen `{token, refreshToken}`)
- Logout nemaže refresh cookie

### 2.2 Frontend

Existuje:
- `frontend/src/stores/authStore.ts` — Zustand store, **in-memory** (po reload vše zmizí), `login()`/`logout()` akce
- `frontend/src/lib/api.ts` — fetch wrapper, `credentials: 'include'`, CSRF header pro POST/PUT/DELETE, **žádný 401 interceptor**
- `frontend/src/modules/auth/api.ts` — `authApi.{login,logout,refresh,me,setup}` — refresh posílá `{refreshToken}` v body
- `frontend/src/modules/auth/hooks/{useLogin,useLogout,useMe}.ts` — TanStack Query hooks
- `frontend/src/components/ProtectedRoute.tsx` — redirect na `/login` pokud `!isAuthenticated` (po reload → logout)
- `frontend/src/App.tsx` — Routes, Sidebar, logout button
- `frontend/package.json` — `zustand@^5.0.0`, `@tanstack/react-query@^5.59.0`

Chybí:
- Persistence store (po reload se vše ztratí)
- App init fáze (pokus o refresh při startu)
- 401 interceptor v `api.ts`
- Loading stav v `ProtectedRoute` během init fáze

### 2.3 Databáze

Beze změny. Žádné nové tabulky, sloupce ani migrace. Refresh token rotace už existuje (JwtService vydává nový při každém refresh).

### 2.4 Rozdíl oproti dokumentaci

- `docs/03-security-model.md` popisuje JWT flow, ale nehttpOnly cookie strategii — bude potřeba doplnit poznámku (P2, mimo tento plán)
- Plán 15.9 doporučoval `sessionStorage` pro token — **tato iterace přepíná na httpOnly cookie** (bezpečnější, viz 3.1)

## 3. Priority (bez výjimek)

### 3.1 Bezpečnost

**Rozhodnutí: httpOnly cookie pro refresh token** (odchylka od plánu 15.9, ale v souladu s `security-coding` skillem):

| Token | Uložení | Životnost | XSS riziko |
|-------|--------|-----------|------------|
| Access token | **in-memory** (Zustand) | 15 min | Krádnutelný, ale krátké TTL |
| Refresh token | **httpOnly cookie** | 7 dní | **Nekrádnutelný** — `document.cookie` nevrátí httpOnly |
| csrfToken | sessionStorage | 15 min | Čitelný (potřebný pro double-submit), ne citlivý |
| sessionId | sessionStorage | session | Identifikátor, ne citlivý |
| user | sessionStorage | session | Veřejná data (id, username, role) |

**Cookie atributy:**
- `HttpOnly` — JS nevidí refresh token (XSS ochrana)
- `SameSite=Strict` — CSRF ochrana (cookie se nepošle cross-origin)
- `Secure` — jen přes HTTPS (v dev přes Vite proxy na http — viz poznámka níže)
- `Path=/api/auth` — cookie se pošle jen na auth endpointy (minimalizuje exposure)
- `Max-Age=604800` — 7 dní

**Dev poznámka:** V Docker dev prostředí je komunikace přes http (Vite proxy → nginx → php-fpm). `Secure` cookie by se neposlala. Řešení: `Secure` nastavovat jen když request přišel přes HTTPS (`$request->getUri()->getScheme() === 'https'`). V dev se nastaví bez `Secure`, v prod s `Secure`.

**CSRF pro refresh endpoint:** `/auth/refresh` je POST bez AuthMiddleware. SameSite=Strict cookie je primární CSRF ochrana (cross-origin POST cookie nepošle). CSRF token pro refresh není potřeba (SameSite=Strict stačí).

**Logout při selhání refreshu:** Pokud refresh selže (401), interceptor zavolá `authStore.logout()` a redirect na `/login`. Stav se nevymaže ze sessionStorage — `logout()` akce v store musí sessionStorage persistenci vyčistit.

### 3.2 Rychlost

- Refresh interceptor používá **single-flight** — pokud 5 requestů dostane 401 naráz, refresh se zavolá jen 1×, ostatní čekají na promise a retry s novým tokenem
- App init refresh — 1 request při startu, neblokuje render (ProtectedRoute ukáže loading)
- sessionStorage persist — rychlejší než localStorage (sync, blokuje main thread krátce), ale data jsou malá (~200 bytes)

### 3.3 Modularita

- `authStore` — persist middleware zapouzdřen v store, volající kód neví o storage
- `api.ts` — interceptor je interní detail `apiRequest`, veřejné API (`api.get/post/...`) se nemění
- Refresh logika — jeden modul `frontend/src/modules/auth/lib/refresh.ts` (single-flight + queue), používán api.ts i App init
- Backend — cookie helper metody v AuthController (privátní, jako existující `setCsrfCookie`)

### 3.4 UI komponenty (striktní pravidla)

- Žádné nové UI komponenty — jen loading stav v `ProtectedRoute` (existující komponenta, použije `lucide-react` spinner nebo shadcn `Skeleton`)
- Pro loading stav použijeme existující shadcn komponentu — ověřit přes `shadcn` MCP zda `Skeleton` je už nainstalovaná, jinak `npx shadcn@latest add skeleton`

## 4. Architektura

### 4.1 Backend

```
backend/src/Http/Controller/AuthController.php
├── login()     — kromě JSON response: Set-Cookie wpm_refresh (httpOnly, SameSite=Strict, Secure?, Path=/api/auth, Max-Age=604800)
├── refresh()   — čte refresh token z cookie (fallback na body pro zpětnou kompatibilitu); vrací {token, refreshToken, csrfToken?, sessionId?, user?}
├── logout()    — Set-Cookie wpm_refresh=; Max-Age=0 (smazat cookie)
└── setRefreshCookie() / clearRefreshCookie() — privátní helpery (jako existující setCsrfCookie)
```

**Refresh endpoint změna:** Aby frontend po reloadu mohl obnovit csrfToken/sessionId/user (které persistuje v sessionStorage jen pro aktuální session), refresh endpoint při úspěchu:
- Pokud session stále existuje (sessionId z cookie? ne — session je vázána na access token jti) → vrací jen `{token, refreshToken}`
- **Jednodušší řešení:** refresh vrací jen `{token, refreshToken}`. csrfToken/sessionId/user zůstávají v sessionStorage z původního loginu. Pokud session na backendu expirovala, access token se nevydá a refresh selže → logout.

**Refresh token rotace:** JwtService už vydává nový refresh při každém refresh call. Backend tedy musí při refreshu **rotovat i cookie** (Set-Cookie s novým refresh tokenem).

### 4.2 Frontend

```
frontend/src/
├── stores/authStore.ts
│   ├── persist middleware (zustand/middleware/persist + createJSONStorage)
│   ├── persistuje jen: {user, csrfToken, sessionId} (partialize)
│   ├── NE persistuje: token, refreshToken (in-memory / httpOnly cookie)
│   ├── login() — nastaví vše (token do memory, ostatní persistováno)
│   ├── logout() — smaže vše + vyčistí persisted state
│   ├── setAccessToken(token) — nová akce pro refresh (aktualizuje jen token)
│   └── isAuthenticated — computed: !!token && !!user
├── lib/api.ts
│   ├── apiRequest() — při 401 zavolá refreshIfPossible(), retry jednou, pak throw
│   └── refreshIfPossible() — single-flight, čte refresh z cookie (auto), updatuje store
├── modules/auth/lib/refresh.ts
│   ├── refreshPromise: Promise | null — single-flight
│   └── refreshAccessToken() — volá /auth/refresh, updatuje store, vrací token
├── modules/auth/api.ts
│   └── refresh() — bez param (cookie se pošle auto přes credentials:include)
├── modules/auth/hooks/useInitAuth.ts (NOVÝ)
│   └── useInitAuth() — při mount zavolá refresh, vrátí {isInitializing, isAuthenticated}
├── components/ProtectedRoute.tsx
│   └── zobrazí loading během init fáze (Skeleton)
└── App.tsx
    └── useInitAuth() v AppContent — init fáze před render routes
```

### 4.3 Databáze

Beze změny.

## 5. API endpointy

| Metoda | Cesta | Popis | Role | Request | Response |
|--------|-------|-------|------|---------|----------|
| POST | /api/auth/login | login | veřejný | `{username, password}` | `{token, refreshToken, csrfToken, sessionId, user}` + `Set-Cookie: wpm_refresh=...` |
| POST | /api/auth/refresh | refresh access token | veřejný (cookie) | cookie `wpm_refresh` (fallback body `{refreshToken}`) | `{token, refreshToken}` + `Set-Cookie: wpm_refresh=...` (rotace) |
| POST | /api/auth/logout | logout | auth | cookie | 204 + `Set-Cookie: wpm_refresh=; Max-Age=0` |

## 6. Kroky implementace

### Fáze 0: Načtení stylu kódování
- [x] Backend konvence — ověřeno: `final` třídy, `readonly` props, strict_types, PHPDoc
- [x] Frontend konvence — ověřeno: named exports, `import type`, async/await, strict TS
- [x] CORS — `Allow-Credentials: true` už nastaveno ✅
- [x] `credentials: 'include'` v api.ts už nastaveno ✅

### Fáze 1: Backend — httpOnly cookie pro refresh token
- [ ] `AuthController::login()` — po úspěchu nastavit `Set-Cookie: wpm_refresh=<token>; HttpOnly; SameSite=Strict; Path=/api/auth; Max-Age=604800` (+ `Secure` pokud HTTPS)
- [ ] `AuthController::refresh()` — číst refresh token z cookie `wpm_refresh` (fallback na body `{refreshToken}` pro zpětnou kompatibilitu); po úspěchu rotovat cookie (Set-Cookie s novým tokenem)
- [ ] `AuthController::logout()` — nastavit `Set-Cookie: wpm_refresh=; Max-Age=0` (smazat cookie)
- [ ] Přidat privátní helpery `setRefreshCookie()`, `clearRefreshCookie()`, `readRefreshCookie($request)`, `isSecureRequest($request)`
- [ ] **Zpětná kompatibilita:** refresh endpoint přijímá refresh token z cookie **nebo** z body — starý frontend (kdyby) stále funguje

### Fáze 2: Frontend — authStore persistence
- [ ] `authStore.ts` — přidat `persist` middleware s `createJSONStorage(() => sessionStorage)`
- [ ] `partialize` — persistovat jen `{user, csrfToken, sessionId}` (NE token, NE refreshToken)
- [ ] `name` — `'wpm-auth'` (klíč v sessionStorage)
- [ ] `logout()` — musí vyčistit persisted state (persist middleware to dělá automaticky při setState na initial)
- [ ] Nová akce `setAccessToken(token: string)` — pro refresh interceptor (aktualizuje jen token, ne persistuje)
- [ ] `isAuthenticated` — změnit na computed selector: `!!token && !!user` (místo boolean flagu) — po reload má user z persist, ale token null → není authenticated dokud refresh nedoběhne

### Fáze 3: Frontend — refresh modul + interceptor
- [ ] `frontend/src/modules/auth/lib/refresh.ts` — `refreshAccessToken()` se single-flight (module-level `refreshPromise: Promise<string | null> | null`)
- [ ] `frontend/src/modules/auth/api.ts` — `authApi.refresh()` bez parametrů (cookie auto)
- [ ] `frontend/src/lib/api.ts` — v `apiRequest()` při 401: zavolat `refreshAccessToken()`, pokud úspěch → retry s novým tokenem, pokud fail → `authStore.logout()` + throw
- [ ] **Nekonečná smyčka ochrana:** refresh request sám nesmí triggerovat interceptor (skip interceptor pro `/auth/refresh`)
- [ ] **Retry limit:** jen 1 retry na request (ne rekurzivní)

### Fáze 4: Frontend — App init + ProtectedRoute loading
- [ ] `frontend/src/modules/auth/hooks/useInitAuth.ts` — při mount zavolá `refreshAccessToken()`, vrátí `{isInitializing, isAuthenticated}`
- [ ] `App.tsx` — `AppContent` použije `useInitAuth()`, během `isInitializing` ProtectedRoute ukáže loading
- [ ] `ProtectedRoute.tsx` — přidat loading stav (Skeleton nebo spinner) když `isInitializing && user` (máme persisted user, čekáme na token)
- [ ] Pokud init refresh selže a user je null → redirect na `/login` (existující chování)

### Fáze 5: Testy
- [ ] **Backend:** `AuthControllerTest` — test login nastaví cookie, refresh čte z cookie, logout maže cookie
- [ ] **Frontend `authStore.test.ts`:** aktualizovat — test persistence (po reload simuluje `sessionStorage.getItem`), test `setAccessToken`, test `logout` vyčistí persisted
- [ ] **Frontend `api.test.ts`:** přidat — test 401 → refresh → retry, test refresh fail → logout, test single-flight (2x 401 → 1 refresh call), test skip interceptor pro `/auth/refresh`
- [ ] **Frontend `App.test.tsx`:** aktualizovat — test init fáze (loading), test authenticated po init
- [ ] **Frontend `ProtectedRoute.test.tsx`:** test loading stav

### Fáze 6: Validace
- [ ] `docker compose exec app composer test` — bez chyb
- [ ] `docker compose exec app composer analyse` — PHPStan level 8 bez chyb
- [ ] `docker compose exec frontend npm run test` — bez chyb
- [ ] `docker compose exec frontend npm run lint` — bez chyb
- [ ] `docker compose exec frontend npm run typecheck` — bez chyb
- [ ] `docker compose exec frontend npm run build` — bez chyb
- [ ] Playwright — login → reload → stále authenticated; logout → reload → na /login

### Fáze 7: CHANGELOG + git
- [ ] `CHANGELOG.md` — `## [0.2.0] — 09.08.2026` (MINOR — nová funkce: auth persistence + refresh interceptor + security layer)
- [ ] `CHANGELOG_CS.md` — česká verze (gitignored)
- [ ] `git add` + commit: `feat(auth): add token persistence via httpOnly cookie and 401 refresh interceptor`
- [ ] `git push origin main` (po schválení uživatelem)

## 7. Testy

### 7.1 Backend
- `AuthControllerTest::loginSetsRefreshCookie` — assert `Set-Cookie` header obsahuje `wpm_refresh=...; HttpOnly; SameSite=Strict`
- `AuthControllerTest::refreshReadsFromCookie` — pošle cookie místo body, assert 200 + nový token
- `AuthControllerTest::refreshFallsBackToBody` — zpětná kompatibilita
- `AuthControllerTest::refreshRotatesCookie` — assert nový `Set-Cookie` v refresh response
- `AuthControllerTest::logoutClearsRefreshCookie` — assert `Set-Cookie: wpm_refresh=; Max-Age=0`
- `AuthControllerTest::refreshCookieSecureInHttps` — assert `Secure` flag při HTTPS requestu

### 7.2 Frontend
- `authStore` — persistuje `{user, csrfToken, sessionId}` do sessionStorage, NE token
- `authStore.setAccessToken` — aktualizuje token, neinvaliduje persist
- `authStore.logout` — vyčistí sessionStorage klíč `wpm-auth`
- `api` — 401 na `/api/sites` → refresh → retry s novým tokenem → 200
- `api` — refresh fail → `authStore.logout()` + throw
- `api` — 2 paralelní 401 → 1 refresh call (single-flight)
- `api` — 401 na `/auth/refresh` → neinterceptor (skip), throw
- `useInitAuth` — při mount zavolá refresh, `isInitializing` true→false
- `ProtectedRoute` — loading stav když `isInitializing`
- `App` — po init refresh úspěch → authenticated render

## 8. Validace

- [ ] `docker compose exec app composer test` — bez chyb
- [ ] `docker compose exec app composer analyse` — PHPStan level 8 bez chyb
- [ ] `docker compose exec frontend npm run test` — bez chyb
- [ ] `docker compose exec frontend npm run lint` — bez chyb
- [ ] `docker compose exec frontend npm run typecheck` — bez chyb
- [ ] `docker compose exec frontend npm run build` — bez chyb
- [ ] Playwright — login → reload → authenticated; logout → reload → /login
- [ ] curl — `curl -v -X POST .../auth/login` ukáže `Set-Cookie: wpm_refresh=...; HttpOnly`
- [ ] curl — `curl -v -b cookies.txt -X POST .../auth/refresh` (bez body) → 200

## 8b. Ladění chyb (striktní pravidla)

Per `implementation-plan` skill — žádné naslepo pokusy, nejdřív dokumentace (context7 pro zustand persist), logování do `frontend/.debug.log` pokud testy selžou.

## 9. Rizika a mitigace

| Riziko | Pravděpodobnost | Dopad | Mitigace |
|--------|----------------|-------|----------|
| `Secure` cookie v dev (http) se nepošle | střední | střední | Detekce scheme — `Secure` jen při HTTPS |
| Single-flight race condition | nízká | střední | Modul-level promise, čistý po resolve/reject |
| Nekonečná smyčka 401↔refresh | střední | vysoký | Skip interceptor pro `/auth/refresh`, retry limit 1 |
| Persist middleware hydratace race | střední | střední | `skipHydration: true` + explicitní rehydrate v useInitAuth |
| Backend testy neprojdou kvůli cookie headeru | střední | nízký | Slim Psr7 `withHeader('Set-Cookie', ...)` — ověřit multi-header support |
| sessionStorage nedostupné (SSR, private mode) | nízká | nízký | createJSONStorage s try/catch fallback na in-memory |

## 10. Rollback strategie

- **Kód:** Vše na main branch — `git revert <commit>` pro revert obou commitů (security layer + persistence)
- **DB:** Beze změny — nic k rollback
- **Dependencies:** Žádné nové balíčky (zustand persist je součástí zustand@5)
- **Cookies:** Pro existující sessiony — refresh cookie expiruje za 7 dní自然, nepotřebuje aktivní cleanup

## 11. Cross-module impact

| Modul | Dopad | Míra | Poznámka |
|-------|-------|------|----------|
| Auth (backend) | Cookie helpery v AuthController | nízká | Privátní metody, nemění veřejné API |
| Auth (frontend) | authStore, api.ts, hooks | střední | Změna isAuthenticated logiky — všechny konzumenti (ProtectedRoute, App, Sidebar) |
| Sites (budoucí) | Žádný | žádná | Použije api.ts s interceptorem transparentně |
| Dashboard (budoucí) | Žádný | žádná | Stejně jako Sites |

- **EventDispatcher eventy:** Žádné nové eventy
- **Sdílené služby:** `api.ts` se mění — všechny moduly používající `api` získají 401 interceptor automaticky
- **DB tabulky:** Beze změny

## 12. Poznámky

- **Odchylka od plánu 15.9:** Původní doporučení bylo `sessionStorage` pro token. Tato iterace přepíná na httpOnly cookie pro refresh token (bezpečnější, OWASP-aligned). Důvod: sessionStorage je XSS-zranitelný stejně jako localStorage — httpOnly cookie je nekradnutelný JS.
- **Zpětná kompatibilita:** `/auth/refresh` přijímá refresh token z cookie **nebo** z body — starý klient (kdyby) stále funguje.
- **Security skill compliance:** Access token v memory (ne localStorage/sessionStorage) ✅, refresh token v httpOnly cookie ✅, CSRF přes SameSite=Strict + double-submit ✅.
- **Verze:** 0.2.0 (MINOR — nová funkce). Security layer z předchozí iterace se zaznamená v témže changelog záznamu (nebyl dosud commitnut).
