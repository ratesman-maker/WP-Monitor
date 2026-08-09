<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Integration\Auth;

use Doctrine\DBAL\DriverManager;
use Dotenv\Dotenv;
use WPMonitor\Auth\SessionService;
use WPMonitor\Storage\Connection;
use WPMonitor\Tests\TestCase;

/**
 * Integration tests for SessionService against a real MariaDB instance.
 */
final class SessionServiceTest extends TestCase
{
    private Connection $connection;
    private SessionService $sessionService;
    private int $userId;

    private const APP_KEY = 'base64:' . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

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

        $this->cleanup();
        $this->userId = $this->seedUser();

        $this->sessionService = new SessionService($this->connection, self::APP_KEY);
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        parent::tearDown();
    }

    public function testCreateReturnsSessionWithIdAndCsrfToken(): void
    {
        $session = $this->sessionService->create(
            $this->userId,
            '127.0.0.1',
            'TestAgent/1.0',
            random_bytes(32),
            'test-jti-123',
            900
        );

        $this->assertNotEmpty($session['id']);
        $this->assertSame(64, strlen($session['csrfToken']));
        $this->assertGreaterThan(time(), $session['expiresAt']);
    }

    public function testGetReturnsSessionWithDecryptedEncryptionKey(): void
    {
        $encryptionKey = random_bytes(32);
        $session = $this->sessionService->create(
            $this->userId,
            '127.0.0.1',
            'TestAgent/1.0',
            $encryptionKey,
            'test-jti-456',
            900
        );

        $retrieved = $this->sessionService->get($session['id']);

        $this->assertNotNull($retrieved);
        $this->assertSame($session['id'], $retrieved['id']);
        $this->assertSame($this->userId, $retrieved['userId']);
        $this->assertSame('test-jti-456', $retrieved['jwtJti']);
        $this->assertSame($session['csrfToken'], $retrieved['csrfToken']);
        $this->assertSame($encryptionKey, $retrieved['encryptionKey']);
    }

    public function testGetReturnsNullForNonexistentSession(): void
    {
        $this->assertNull($this->sessionService->get('nonexistent-session-id'));
    }

    public function testDestroyRemovesSession(): void
    {
        $session = $this->sessionService->create(
            $this->userId,
            '127.0.0.1',
            'TestAgent/1.0',
            random_bytes(32),
            'jti-1',
            900
        );

        $this->assertNotNull($this->sessionService->get($session['id']));

        $this->sessionService->destroy($session['id']);

        $this->assertNull($this->sessionService->get($session['id']));
    }

    public function testDestroyAllForUserRemovesAllSessions(): void
    {
        $s1 = $this->sessionService->create($this->userId, '127.0.0.1', 'A', random_bytes(32), 'jti-1', 900);
        $s2 = $this->sessionService->create($this->userId, '127.0.0.1', 'B', random_bytes(32), 'jti-2', 900);

        $this->sessionService->destroyAllForUser(1);

        $this->assertNull($this->sessionService->get($s1['id']));
        $this->assertNull($this->sessionService->get($s2['id']));
    }

    public function testCleanupRemovesExpiredSessions(): void
    {
        // Create a session with 1 second timeout
        $session = $this->sessionService->create(
            $this->userId,
            '127.0.0.1',
            'TestAgent/1.0',
            random_bytes(32),
            'jti-expire',
            1
        );

        // Wait for it to expire
        sleep(2);

        $deleted = $this->sessionService->cleanup();
        $this->assertGreaterThanOrEqual(1, $deleted);
        $this->assertNull($this->sessionService->get($session['id']));
    }

    public function testTouchUpdatesLastActivity(): void
    {
        $session = $this->sessionService->create(
            $this->userId,
            '127.0.0.1',
            'TestAgent/1.0',
            random_bytes(32),
            'jti-touch',
            900
        );

        // Should not throw
        $this->sessionService->touch($session['id']);
        $this->expectNotToPerformAssertions();
    }

    private function seedUser(): int
    {
        $this->connection->executeStatement(
            "INSERT INTO users (username, role, password_salt, verification_token, verification_aad, is_active)
             VALUES ('session-test-user', 'admin', ?, ?, ?, 1)",
            [
                random_bytes(16),
                base64_encode(random_bytes(32)),
                pack('NN', 0, 0),
            ]
        );

        return (int) $this->connection->getConnection()->lastInsertId();
    }

    private function cleanup(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->executeStatement('TRUNCATE TABLE user_sessions');
        $this->connection->executeStatement('TRUNCATE TABLE users');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
