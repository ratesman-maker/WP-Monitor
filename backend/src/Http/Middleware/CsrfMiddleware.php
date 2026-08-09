<?php

declare(strict_types=1);

namespace WPMonitor\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use WPMonitor\Auth\SessionServiceInterface;

/**
 * CSRF protection middleware using double-submit cookie pattern.
 *
 * For POST/PUT/DELETE/PATCH requests, validates that:
 *   1. The X-CSRF-Token header is present
 *   2. The wpm_csrf cookie is present
 *   3. Both match the CSRF token stored in the user's session
 *
 * GET/HEAD/OPTIONS requests bypass CSRF validation.
 *
 * The session ID is read from the auth.session_id request attribute
 * (set by AuthMiddleware).
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public const HEADER_NAME = 'X-CSRF-Token';
    public const COOKIE_NAME = 'wpm_csrf';

    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private readonly SessionServiceInterface $sessionService
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $handler->handle($request);
        }

        $sessionId = $request->getAttribute(AuthMiddleware::ATTR_SESSION_ID);
        if (!is_string($sessionId) || $sessionId === '') {
            return $this->forbidden('No active session for CSRF validation.');
        }

        $headerToken = $request->getHeaderLine(self::HEADER_NAME);
        $cookieToken = $this->readCookie($request, self::COOKIE_NAME);

        if ($headerToken === '' || $cookieToken === '') {
            return $this->forbidden('Missing CSRF token.');
        }

        if (!hash_equals($cookieToken, $headerToken)) {
            return $this->forbidden('CSRF token mismatch between header and cookie.');
        }

        $session = $this->sessionService->get($sessionId);
        if ($session === null) {
            return $this->forbidden('Session not found or expired.');
        }

        if (!hash_equals($session['csrfToken'], $headerToken)) {
            return $this->forbidden('CSRF token does not match session.');
        }

        return $handler->handle($request);
    }

    private function readCookie(ServerRequestInterface $request, string $name): string
    {
        $cookies = $request->getCookieParams();
        $value = $cookies[$name] ?? '';

        return is_string($value) ? $value : '';
    }

    private function forbidden(string $detail): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(403);
        $payload = [
            'type' => 'about:blank',
            'title' => 'Forbidden',
            'status' => 403,
            'detail' => $detail,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/problem+json');
    }
}
