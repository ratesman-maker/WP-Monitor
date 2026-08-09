# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] — 09.08.2026

### Added
- Complete security layer: AES-256-GCM encryption (CryptoService), Argon2id key
  derivation, JWT auth (access 15min + refresh 7d), CSRF double-submit cookies,
  DB-backed rate limiting, append-only audit log, session management with
  wrapped encryption keys.
- Auth module: `/api/auth/{setup,login,logout,refresh,me}` endpoints with
  Argon2id password verification, account lockout after 5 failed attempts,
  and audit logging of every auth event.
- Refresh token now stored in an `httpOnly` `SameSite=Strict` cookie
  (`wpm_refresh`, scoped to `/api/auth`, 7-day Max-Age) — XSS-safe, JS cannot
  read it. Cookie is rotated on every refresh and cleared on logout.
- Frontend auth persistence: `authStore` persists `{user, csrfToken, sessionId}`
  to `sessionStorage` via `zustand/middleware/persist`. Access token stays
  in-memory only (never persisted).
- Silent session restoration on page reload: `useInitAuth` hook detects a
  persisted user with no access token and performs a single `/auth/refresh`
  call (using the httpOnly cookie) to restore the session without re-login.
- 401 refresh interceptor in `api.ts`: on expired access token, automatically
  calls `/auth/refresh`, retries the original request once with the new token.
  Single-flight — concurrent 401s share one refresh promise. Skipped for
  `/auth/{refresh,login,setup}` to avoid loops.
- `ProtectedRoute` loading skeleton during initial session restoration.
- Backend test suite: 121 tests (unit + integration) covering CryptoService,
  KeyDerivation, JwtService, CsrfService, RateLimiter, SessionService,
  AuthService, AuthController (including 6 new cookie tests).
- Frontend test suite: 54 tests covering authStore persistence, api client
  (including 401 interceptor), refresh module (single-flight), useInitAuth
  hook, ProtectedRoute (loading state), App, LoginPage.
- Docker Compose: removed host port mapping for `db` (was conflicting with
  host MariaDB on 3307). App connects via Docker network (`hostname: db`).

### Changed
- `AuthController::refresh()` now reads the refresh token from the
  `wpm_refresh` cookie first, falling back to the request body for backward
  compatibility.
- `AuthController::login()` sets the `wpm_refresh` httpOnly cookie alongside
  the existing `wpm_csrf` cookie.
- `AuthController::logout()` clears both `wpm_csrf` and `wpm_refresh` cookies.
- `authApi.refresh()` no longer takes a `refreshToken` argument — the cookie
  is sent automatically via `credentials: 'include'`.
- `authStore.isAuthenticated` is now derived from `!!token && !!user` instead
  of a stored boolean, so a persisted user without a token (post-reload) is
  correctly treated as unauthenticated until the silent refresh completes.
- `api.ts` response parsing now tolerates empty 200 bodies (previously only
  204 was handled).

### Security
- Refresh token storage moved from JSON response body (readable by XSS) to
  `httpOnly` cookie (not readable by JS). This is the OWASP-recommended
  approach for SPA JWT auth.
- `Secure` cookie flag is set conditionally based on the request scheme
  (`HTTPS` server var or `X-Forwarded-Proto` header) — enabled in production
  (HTTPS), disabled in dev (HTTP via Vite proxy).
- Access token is never persisted to `sessionStorage` or `localStorage` —
  it lives only in React memory for its 15-minute lifetime.
- Refresh cookie scoped to `Path=/api/auth` to minimize exposure surface.
- Failed refresh attempts clear the `wpm_refresh` cookie immediately.

## [0.1.0] — 08.08.2026

### Added
- Project scaffolding: PHP 8.3 + Slim 4 backend, React 18 + TypeScript +
  Vite frontend, Docker Compose (php-fpm, nginx, mariadb, vite dev server).
- GitHub Actions CI: branch rules, code quality checks, Dependabot, CodeQL.
- Project documentation (docs/01-15): architecture, security model, module
  specs, database schema, API spec, development guide, deployment guide.
- Devin CLI configuration: skills (security-coding, performance-coding,
  modularity-coding, project-structure, implementation-plan, changelog),
  MCP servers (mysql, github, playwright, context7, shadcn, sequential-thinking).
