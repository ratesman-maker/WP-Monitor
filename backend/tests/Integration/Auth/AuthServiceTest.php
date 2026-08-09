<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Integration\Auth;

use Doctrine\DBAL\DriverManager;
use Dotenv\Dotenv;
use Symfony\Component\EventDispatcher\EventDispatcher;
use WPMonitor\Auth\AuditLogService;
use WPMonitor\Auth\AuditLogSubscriber;
use WPMonitor\Auth\AuthService;
use WPMonitor\Auth\JwtService;
use WPMonitor\Auth\SessionService;
use WPMonitor\Security\KeyDerivationService;
use WPMonitor\Storage\Connection;
use WPMonitor\Tests\TestCase;

/**
 * Integration tests for AuthService against a real MariaDB instance.
 *
 * Each test cleans up the users, user_sessions, and audit_log tables to avoid
 * cross-test contamination. The admin user is re-seeded per test as needed.
 */
final class AuthServiceTest extends TestCase
{
    private Connection $connection;
    private AuthService $authService;
    private SessionService $sessionService;

    private const TEST_USERNAME = 'testadmin';
    private const TEST_PASSWORD = 'TestPassword123456';
    private const APP_KEY = 'base64:' . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
    private const ISSUER = 'wp-monitor-test';

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

        $this->cleanupTables();

        $keyDerivation = new KeyDerivationService();
        $jwtService = new JwtService(self::APP_KEY, self::ISSUER, 900, 604800);
        $this->sessionService = new SessionService($this->connection, self::APP_KEY);

        $dispatcher = new EventDispatcher();
        $subscriber = new AuditLogSubscriber($this->connection);
        $dispatcher->addSubscriber($subscriber);
        $auditLog = new AuditLogService($dispatcher);

        $this->authService = new AuthService(
            $this->connection,
            $keyDerivation,
            $jwtService,
            $this->sessionService,
            $auditLog,
            5,
            900
        );
    }

    protected function tearDown(): void
    {
        $this->cleanupTables();
        parent::tearDown();
    }

    public function testSetupCreatesAdminUser(): void
    {
        $result = $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD, 'test@example.com');

        $this->assertGreaterThan(0, $result['id']);

        $users = $this->connection->executeQuery(
            'SELECT id, username, role, email FROM users WHERE id = ?',
            [$result['id']]
        );
        $this->assertCount(1, $users);
        $this->assertSame('admin', $users[0]['role']);
        $this->assertSame('test@example.com', $users[0]['email']);
    }

    public function testSetupFailsIfUsersAlreadyExist(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->expectException(\WPMonitor\Auth\AuthException::class);
        $this->authService->setup('another', self::TEST_PASSWORD);
    }

    public function testSetupRejectsShortPassword(): void
    {
        $this->expectException(\WPMonitor\Auth\AuthException::class);
        $this->authService->setup('admin', 'short');
    }

    public function testSetupRejectsShortUsername(): void
    {
        $this->expectException(\WPMonitor\Auth\AuthException::class);
        $this->authService->setup('ab', self::TEST_PASSWORD);
    }

    public function testLoginSucceedsWithCorrectPassword(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD, 'test@example.com');

        $result = $this->authService->login(
            self::TEST_USERNAME,
            self::TEST_PASSWORD,
            '127.0.0.1',
            'TestAgent/1.0'
        );

        $this->assertNotEmpty($result['token']);
        $this->assertNotEmpty($result['refreshToken']);
        $this->assertNotEmpty($result['csrfToken']);
        $this->assertNotEmpty($result['sessionId']);
        $this->assertSame(self::TEST_USERNAME, $result['user']['username']);
        $this->assertSame('admin', $result['user']['role']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->expectException(\WPMonitor\Auth\AuthException::class);
        $this->authService->login(self::TEST_USERNAME, 'WrongPassword123', '127.0.0.1', 'TestAgent/1.0');
    }

    public function testLoginFailsWithNonexistentUser(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);

        $this->expectException(\WPMonitor\Auth\AuthException::class);
        $this->authService->login('nonexistent', self::TEST_PASSWORD, '127.0.0.1', 'TestAgent/1.0');
    }

    public function testFailedLoginIncrementsFailCount(): void
    {
        $setup = $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);

        try {
            $this->authService->login(self::TEST_USERNAME, 'WrongPassword123', '127.0.0.1', 'TestAgent/1.0');
        } catch (\WPMonitor\Auth\AuthException) {
            // expected
        }

        $users = $this->connection->executeQuery(
            'SELECT failed_login_count FROM users WHERE id = ?',
            [$setup['id']]
        );
        $this->assertSame(1, (int) $users[0]['failed_login_count']);
    }

    public function testSuccessfulLoginResetsFailCount(): void
    {
        $setup = $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);

        // Cause one failed login
        try {
            $this->authService->login(self::TEST_USERNAME, 'WrongPassword123', '127.0.0.1', 'TestAgent/1.0');
        } catch (\WPMonitor\Auth\AuthException) {
            // expected
        }

        // Now login successfully
        $this->authService->login(self::TEST_USERNAME, self::TEST_PASSWORD, '127.0.0.1', 'TestAgent/1.0');

        $users = $this->connection->executeQuery(
            'SELECT failed_login_count FROM users WHERE id = ?',
            [$setup['id']]
        );
        $this->assertSame(0, (int) $users[0]['failed_login_count']);
    }

    public function testLogoutDestroysSession(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);
        $result = $this->authService->login(
            self::TEST_USERNAME,
            self::TEST_PASSWORD,
            '127.0.0.1',
            'TestAgent/1.0'
        );

        $session = $this->sessionService->get($result['sessionId']);
        $this->assertNotNull($session);

        $this->authService->logout($result['sessionId'], $result['user']['id'], '127.0.0.1', 'TestAgent/1.0');

        $sessionAfter = $this->sessionService->get($result['sessionId']);
        $this->assertNull($sessionAfter);
    }

    public function testGetUserReturnsUserById(): void
    {
        $setup = $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD, 'test@example.com');

        $user = $this->authService->getUser($setup['id']);

        $this->assertNotNull($user);
        $this->assertSame(self::TEST_USERNAME, $user['username']);
        $this->assertSame('test@example.com', $user['email']);
    }

    public function testGetUserReturnsNullForNonexistentId(): void
    {
        $this->assertNull($this->authService->getUser(99999));
    }

    public function testRefreshIssuesNewTokens(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);
        $loginResult = $this->authService->login(
            self::TEST_USERNAME,
            self::TEST_PASSWORD,
            '127.0.0.1',
            'TestAgent/1.0'
        );

        $refreshed = $this->authService->refresh($loginResult['refreshToken']);

        $this->assertNotEmpty($refreshed['token']);
        $this->assertNotEmpty($refreshed['refreshToken']);
        $this->assertNotSame($loginResult['token'], $refreshed['token']);
    }

    public function testAuditLogRecordsLoginSuccess(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);
        $this->authService->login(self::TEST_USERNAME, self::TEST_PASSWORD, '127.0.0.1', 'TestAgent/1.0');

        $logs = $this->connection->executeQuery(
            "SELECT action, status FROM audit_log WHERE action = 'auth.login' AND status = 'success'"
        );
        $this->assertCount(1, $logs);
    }

    public function testAuditLogRecordsLoginFailure(): void
    {
        $this->authService->setup(self::TEST_USERNAME, self::TEST_PASSWORD);

        try {
            $this->authService->login(self::TEST_USERNAME, 'WrongPassword123', '127.0.0.1', 'TestAgent/1.0');
        } catch (\WPMonitor\Auth\AuthException) {
            // expected
        }

        $logs = $this->connection->executeQuery(
            "SELECT action, status FROM audit_log WHERE action = 'auth.login' AND status = 'failed'"
        );
        $this->assertCount(1, $logs);
    }

    private function cleanupTables(): void
    {
        // TRUNCATE bypasses the append-only trigger on audit_log
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->executeStatement('TRUNCATE TABLE audit_log');
        $this->connection->executeStatement('TRUNCATE TABLE user_sessions');
        $this->connection->executeStatement('TRUNCATE TABLE users');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
