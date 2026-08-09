<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Integration\Http\Middleware;

use Doctrine\DBAL\DriverManager;
use Dotenv\Dotenv;
use WPMonitor\Http\Middleware\RateLimitMiddleware;
use WPMonitor\Storage\Connection;
use WPMonitor\Tests\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    private Connection $connection;
    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../..');
        $dotenv->safeLoad();

        $this->connection = new Connection(DriverManager::getConnection([
            'dbname' => $_ENV['DB_NAME'] ?? 'wp_monitor',
            'user' => $_ENV['DB_USER'] ?? 'wp_monitor',
            'password' => $_ENV['DB_PASSWORD'] ?? 'secret',
            'host' => $_ENV['DB_HOST'] ?? 'db',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
        ]));

        $this->connection->executeStatement('DROP TABLE IF EXISTS rate_limit_buckets');

        $this->middleware = new RateLimitMiddleware($this->connection, 5, 60);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS rate_limit_buckets');
        parent::tearDown();
    }

    public function testIsAllowedReturnsTrueForFirstRequest(): void
    {
        $this->assertTrue($this->middleware->isAllowed('ip-1', 'login', 5));
    }

    public function testIsAllowedAllowsRequestsUnderLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->middleware->isAllowed('ip-2', 'login', 5));
        }
    }

    public function testIsAllowedBlocksRequestsOverLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->middleware->isAllowed('ip-3', 'login', 5));
        }

        $this->assertFalse($this->middleware->isAllowed('ip-3', 'login', 5));
    }

    public function testIsAllowedTracksDifferentIdentifiersSeparately(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($this->middleware->isAllowed('ip-a', 'login', 5));
        }

        // Different IP should still be allowed
        $this->assertTrue($this->middleware->isAllowed('ip-b', 'login', 5));
    }

    public function testIsAllowedTracksDifferentRoutesSeparately(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->middleware->isAllowed('ip-c', 'login', 5);
        }

        // Same IP, different route should be allowed
        $this->assertTrue($this->middleware->isAllowed('ip-c', 'api', 60));
    }

    public function testTooManyRequestsResponseHasCorrectStatus(): void
    {
        $response = $this->middleware->tooManyRequestsResponse();

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
        $this->assertNotSame('', $response->getHeaderLine('Retry-After'));
    }

    public function testTooManyRequestsResponseContainsErrorMessage(): void
    {
        $response = $this->middleware->tooManyRequestsResponse();
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame(429, $body['status']);
        $this->assertStringContainsString('Rate limit', $body['detail']);
    }
}
