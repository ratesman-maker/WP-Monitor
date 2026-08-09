<?php

declare(strict_types=1);

namespace WPMonitor\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use WPMonitor\Auth\JwtException;
use WPMonitor\Auth\JwtService;
use WPMonitor\Auth\SessionServiceInterface;

/**
 * JWT authentication middleware.
 *
 * Extracts the Bearer token from the Authorization header, verifies it via
 * JwtService, and checks that the token's JTI matches an active session.
 * On success, attaches the following request attributes:
 *   - userId: int
 *   - role: string
 *   - jti: string
 *   - sessionId: string
 *
 * On failure, returns a 401 problem+json response.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public const ATTR_USER_ID = 'auth.user_id';
    public const ATTR_ROLE = 'auth.role';
    public const ATTR_JTI = 'auth.jti';
    public const ATTR_SESSION_ID = 'auth.session_id';

    public function __construct(
        private readonly JwtService $jwtService,
        private readonly SessionServiceInterface $sessionService
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $authHeader = $request->getHeaderLine('Authorization');

        if ($authHeader === '' || !str_starts_with($authHeader, 'Bearer ')) {
            return $this->unauthorized($request, 'Missing or invalid Authorization header.');
        }

        $token = substr($authHeader, 7);

        try {
            $claims = $this->jwtService->verify($token, JwtService::TYPE_ACCESS);
        } catch (JwtException $e) {
            return $this->unauthorized($request, $e->getMessage());
        }

        $jti = (string) ($claims['jti'] ?? '');
        $userId = (int) ($claims['sub'] ?? 0);

        if ($jti === '' || $userId === 0) {
            return $this->unauthorized($request, 'Token is missing required claims.');
        }

        // Check that the session is still active (JTI revocation)
        // We look up sessions by jwt_jti — if not found, the token is revoked
        $session = $this->findSessionByJti($jti);
        if ($session === null) {
            return $this->unauthorized($request, 'Session has been revoked.');
        }

        $request = $request
            ->withAttribute(self::ATTR_USER_ID, $userId)
            ->withAttribute(self::ATTR_ROLE, (string) ($claims['role'] ?? 'viewer'))
            ->withAttribute(self::ATTR_JTI, $jti)
            ->withAttribute(self::ATTR_SESSION_ID, $session);

        return $handler->handle($request);
    }

    private function findSessionByJti(string $jti): ?string
    {
        // SessionService::get expects a session ID, not a JTI.
        // For JTI-based revocation, we query the DB directly.
        // This is a lightweight indexed lookup on user_sessions.jwt_jti.
        // We return the session ID so downstream code can use it.
        $rows = $this->sessionService->findByJti($jti);

        return $rows;
    }

    private function unauthorized(ServerRequestInterface $request, string $detail): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(401);
        $payload = [
            'type' => 'about:blank',
            'title' => 'Unauthorized',
            'status' => 401,
            'detail' => $detail,
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/problem+json');
    }
}
