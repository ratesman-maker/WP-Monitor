<?php

declare(strict_types=1);

namespace WPMonitor\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use WPMonitor\Auth\AuthException;
use WPMonitor\Auth\AuthService;
use WPMonitor\Http\Middleware\AuthMiddleware;
use WPMonitor\Http\Middleware\RateLimitMiddleware;

/**
 * Auth controller — thin controller delegating to AuthService.
 *
 * Endpoints:
 *   POST /api/auth/setup    — first setup (only if 0 users)
 *   POST /api/auth/login    — login with master password
 *   POST /api/auth/logout   — logout (revoke session)
 *   POST /api/auth/refresh  — refresh JWT token
 *   GET  /api/auth/me       — current user info
 */
final class AuthController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RateLimitMiddleware $rateLimiter
    ) {
    }

    public function setup(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->parsedBody($request);
        $username = (string) ($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');
        $email = $body['email'] ?? null;
        if (!is_string($email)) {
            $email = null;
        }

        try {
            $result = $this->authService->setup($username, $password, $email);
        } catch (AuthException $e) {
            return $this->errorResponse($response, 400, $e->getMessage(), $e->reason());
        }

        $payload = [
            'message' => 'Setup complete.',
            'user' => ['id' => $result['id'], 'username' => $username],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $ip = $this->clientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');

        // Rate limit by IP
        if (!$this->rateLimiter->isAllowed($ip, 'login', $this->rateLimiter->loginLimit())) {
            return $this->rateLimiter->tooManyRequestsResponse();
        }

        $body = $this->parsedBody($request);
        $username = (string) ($body['username'] ?? '');
        $password = (string) ($body['password'] ?? '');

        try {
            $result = $this->authService->login($username, $password, $ip, $userAgent);
        } catch (AuthException $e) {
            $status = match ($e->reason()) {
                AuthException::REASON_ACCOUNT_LOCKED => 423,
                AuthException::REASON_ACCOUNT_INACTIVE => 403,
                default => 401,
            };

            return $this->errorResponse($response, $status, $e->getMessage(), $e->reason());
        }

        // Set CSRF cookie (HttpOnly=false so frontend JS can read it for double-submit)
        $response = $this->setCsrfCookie($response, $result['csrfToken']);
        // Set refresh token cookie (HttpOnly — JS cannot read it, XSS-safe)
        $response = $this->setRefreshCookie($response, $result['refreshToken'], $request);

        $payload = [
            'token' => $result['token'],
            // refreshToken is also returned in body for backward compatibility,
            // but the frontend SHOULD rely on the httpOnly cookie instead.
            'refreshToken' => $result['refreshToken'],
            'csrfToken' => $result['csrfToken'],
            'sessionId' => $result['sessionId'],
            'user' => $result['user'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function logout(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $sessionId = (string) ($request->getAttribute(AuthMiddleware::ATTR_SESSION_ID) ?? '');
        $userId = (int) ($request->getAttribute(AuthMiddleware::ATTR_USER_ID) ?? 0);
        $ip = $this->clientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');

        if ($sessionId === '' || $userId === 0) {
            return $this->errorResponse($response, 401, 'Not authenticated.', 'not_authenticated');
        }

        $this->authService->logout($sessionId, $userId, $ip, $userAgent);

        // Clear CSRF cookie
        $response = $this->clearCsrfCookie($response);
        // Clear refresh token cookie
        $response = $this->clearRefreshCookie($response, $request);

        return $response->withStatus(204);
    }

    public function refresh(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Read refresh token from httpOnly cookie (preferred) or body (backward compatibility)
        $refreshToken = $this->readRefreshCookie($request);
        if ($refreshToken === '') {
            $body = $this->parsedBody($request);
            $refreshToken = (string) ($body['refreshToken'] ?? '');
        }

        if ($refreshToken === '') {
            return $this->errorResponse($response, 400, 'Refresh token is required.', 'missing_token');
        }

        try {
            $result = $this->authService->refresh($refreshToken);
        } catch (AuthException $e) {
            // Clear the refresh cookie on failure — it is invalid/expired
            $response = $this->clearRefreshCookie($response, $request);

            return $this->errorResponse($response, 401, $e->getMessage(), $e->reason());
        }

        // Rotate the refresh cookie with the new token
        $response = $this->setRefreshCookie($response, $result['refreshToken'], $request);

        $payload = [
            'token' => $result['token'],
            'refreshToken' => $result['refreshToken'],
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    public function me(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = (int) ($request->getAttribute(AuthMiddleware::ATTR_USER_ID) ?? 0);

        $user = $this->authService->getUser($userId);
        if ($user === null) {
            return $this->errorResponse($response, 404, 'User not found.', 'user_not_found');
        }

        $response->getBody()->write(json_encode($user, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    }

    private function clientIp(ServerRequestInterface $request): string
    {
        $server = $request->getServerParams();

        return (string) ($server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    /**
     * @return array<string,mixed>
     */
    private function parsedBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body)) {
            return $body;
        }

        return [];
    }

    private function setCsrfCookie(ResponseInterface $response, string $token): ResponseInterface
    {
        return $response->withHeader(
            'Set-Cookie',
            'wpm_csrf=' . $token . '; Path=/; HttpOnly=false; SameSite=Strict; Max-Age=900'
        );
    }

    private function clearCsrfCookie(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader(
            'Set-Cookie',
            'wpm_csrf=; Path=/; HttpOnly=false; SameSite=Strict; Max-Age=0'
        );
    }

    /**
     * Set the refresh token as an httpOnly cookie (XSS-safe — JS cannot read it).
     * Uses withAddedHeader so it does not overwrite the CSRF cookie set in login().
     */
    private function setRefreshCookie(
        ResponseInterface $response,
        string $token,
        ServerRequestInterface $request
    ): ResponseInterface {
        $secure = $this->isSecureRequest($request);

        return $response->withAddedHeader(
            'Set-Cookie',
            'wpm_refresh=' . $token
                . '; Path=/api/auth; HttpOnly; SameSite=Strict'
                . ($secure ? '; Secure' : '')
                . '; Max-Age=604800' // 7 days
        );
    }

    /**
     * Clear the refresh token cookie by setting Max-Age=0.
     * Uses withAddedHeader so it does not overwrite the CSRF cookie cleared in logout().
     */
    private function clearRefreshCookie(
        ResponseInterface $response,
        ServerRequestInterface $request
    ): ResponseInterface {
        $secure = $this->isSecureRequest($request);

        return $response->withAddedHeader(
            'Set-Cookie',
            'wpm_refresh=; Path=/api/auth; HttpOnly; SameSite=Strict'
                . ($secure ? '; Secure' : '')
                . '; Max-Age=0'
        );
    }

    /**
     * Read the refresh token from the wpm_refresh cookie, if present.
     */
    private function readRefreshCookie(ServerRequestInterface $request): string
    {
        $cookies = $request->getCookieParams();

        return (string) ($cookies['wpm_refresh'] ?? '');
    }

    /**
     * Detect whether the request was made over HTTPS (for the Secure cookie flag).
     */
    private function isSecureRequest(ServerRequestInterface $request): bool
    {
        $uri = $request->getUri();
        if ($uri->getScheme() === 'https') {
            return true;
        }

        // Behind a reverse proxy, the connection to the proxy may be HTTPS even if
        // the internal request is http. Trust standard forwarded headers.
        $server = $request->getServerParams();
        if (isset($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') {
            return true;
        }
        if (isset($server['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $server['HTTP_X_FORWARDED_PROTO']) === 'https') {
            return true;
        }

        return false;
    }

    private function errorResponse(
        ResponseInterface $response,
        int $status,
        string $detail,
        string $reason
    ): ResponseInterface {
        $payload = [
            'type' => 'about:blank',
            'title' => $this->titleForStatus($status),
            'status' => $status,
            'detail' => $detail,
            'reason' => $reason,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response
            ->withHeader('Content-Type', 'application/problem+json')
            ->withStatus($status);
    }

    private function titleForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            423 => 'Locked',
            429 => 'Too Many Requests',
            default => 'Error',
        };
    }
}
