<?php

declare(strict_types=1);

namespace WPMonitor\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use WPMonitor\Storage\Connection;

/**
 * Rate limiting middleware using a DB-backed sliding window.
 *
 * APCu is not available in the Docker image, so we use a simple DB table
 * (`rate_limit_buckets`) to track request counts per identifier+route.
 *
 * The identifier is:
 *   - For login: client IP (rate limit per IP)
 *   - For API: user ID (if authenticated) or client IP
 *
 * The window is 60 seconds. If the count exceeds the limit, a 429 response
 * is returned with a Retry-After header.
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly Connection $connection,
        private readonly int $loginLimit,
        private readonly int $apiLimit
    ) {
    }

    /**
     * Rate limiting is applied via explicit calls in controllers, not as
     * global middleware. This method just passes through.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Rate limiting is applied via explicit calls, not as global middleware.
        // This method just passes through — actual checking is done by check().
        return $handler->handle($request);
    }

    /**
     * Check if a request is within the rate limit. Returns true if allowed,
     * false if rate-limited.
     */
    public function isAllowed(string $identifier, string $route, int $limit): bool
    {
        $this->ensureTable();
        $windowStart = time() - self::WINDOW_SECONDS;

        // Clean old entries
        $this->connection->executeStatement(
            'DELETE FROM rate_limit_buckets WHERE window_start < ?',
            [$windowStart]
        );

        // Count requests in current window
        $rows = $this->connection->executeQuery(
            'SELECT request_count FROM rate_limit_buckets
             WHERE identifier = ? AND route = ? AND window_start >= ?',
            [$identifier, $route, $windowStart]
        );

        if (count($rows) === 0) {
            // First request in window
            $this->connection->executeStatement(
                'INSERT INTO rate_limit_buckets (identifier, route, window_start, request_count)
                 VALUES (?, ?, ?, 1)',
                [$identifier, $route, time()]
            );

            return true;
        }

        $count = (int) $rows[0]['request_count'];

        if ($count >= $limit) {
            return false;
        }

        // Increment count
        $this->connection->executeStatement(
            'UPDATE rate_limit_buckets SET request_count = request_count + 1
             WHERE identifier = ? AND route = ?',
            [$identifier, $route]
        );

        return true;
    }

    /**
     * Create a 429 Too Many Requests response.
     */
    public function tooManyRequestsResponse(): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(429);
        $payload = [
            'type' => 'about:blank',
            'title' => 'Too Many Requests',
            'status' => 429,
            'detail' => 'Rate limit exceeded. Please try again later.',
        ];

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response
            ->withHeader('Content-Type', 'application/problem+json')
            ->withHeader('Retry-After', (string) self::WINDOW_SECONDS);
    }

    public function loginLimit(): int
    {
        return $this->loginLimit;
    }

    public function apiLimit(): int
    {
        return $this->apiLimit;
    }

    public function windowSeconds(): int
    {
        return self::WINDOW_SECONDS;
    }

    private function ensureTable(): void
    {
        $this->connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS rate_limit_buckets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                identifier VARCHAR(128) NOT NULL,
                route VARCHAR(50) NOT NULL,
                window_start INT NOT NULL,
                request_count INT UNSIGNED NOT NULL DEFAULT 1,
                UNIQUE KEY uniq_identifier_route (identifier, route)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}
