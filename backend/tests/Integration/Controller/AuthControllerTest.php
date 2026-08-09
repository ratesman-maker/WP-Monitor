<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Integration\Controller;

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use WPMonitor\Storage\Connection;
use WPMonitor\Tests\TestCase;

/**
 * Integration tests for AuthController endpoints.
 *
 * Uses the full Slim app with real DI container and real DB.
 */
final class AuthControllerTest extends TestCase
{
    private App $app;
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../..');
        $dotenv->safeLoad();

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../../config/container.php');
        $container = $containerBuilder->build();

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();

        (require __DIR__ . '/../../../config/middleware.php')($this->app);
        (require __DIR__ . '/../../../config/routes.php')($this->app);

        $this->connection = $container->get(Connection::class);
        $this->cleanupTables();
    }

    protected function tearDown(): void
    {
        $this->cleanupTables();
        parent::tearDown();
    }

    public function testSetupCreatesAdminUser(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/setup')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'setupadmin',
            'password' => 'SetupPassword123',
            'email' => 'setup@example.com',
        ]) ?: '');

        $response = $this->app->handle($request);

        $this->assertSame(201, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('Setup complete.', $body['message']);
        $this->assertSame('setupadmin', $body['user']['username']);
    }

    public function testSetupFailsWhenUsersAlreadyExist(): void
    {
        $this->seedUser();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/setup')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'another',
            'password' => 'AnotherPassword123',
        ]) ?: '');

        $response = $this->app->handle($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testLoginSucceedsWithCorrectCredentials(): void
    {
        $this->seedUser();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'testuser',
            'password' => 'TestPassword123456',
        ]) ?: '');

        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($body['token']);
        $this->assertNotEmpty($body['refreshToken']);
        $this->assertNotEmpty($body['csrfToken']);
        $this->assertSame('testuser', $body['user']['username']);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $this->seedUser();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'testuser',
            'password' => 'WrongPassword123',
        ]) ?: '');

        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testMeReturns401WithoutToken(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/auth/me');
        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testMeReturnsUserWithValidToken(): void
    {
        $loginResult = $this->loginViaApi();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/auth/me')
            ->withHeader('Authorization', 'Bearer ' . $loginResult['token']);
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('testuser', $body['username']);
    }

    public function testLogoutRevokesSession(): void
    {
        $loginResult = $this->loginViaApi();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/logout')
            ->withHeader('Authorization', 'Bearer ' . $loginResult['token'])
            ->withHeader('X-CSRF-Token', $loginResult['csrfToken'])
            ->withCookieParams(['wpm_csrf' => $loginResult['csrfToken']]);
        $response = $this->app->handle($request);

        $this->assertSame(204, $response->getStatusCode());

        // /me should now return 401
        $meRequest = (new ServerRequestFactory())->createServerRequest('GET', '/api/auth/me')
            ->withHeader('Authorization', 'Bearer ' . $loginResult['token']);
        $meResponse = $this->app->handle($meRequest);

        $this->assertSame(401, $meResponse->getStatusCode());
    }

    public function testRefreshIssuesNewTokens(): void
    {
        $loginResult = $this->loginViaApi();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/refresh')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'refreshToken' => $loginResult['refreshToken'],
        ]) ?: '');

        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($body['token']);
        $this->assertNotSame($loginResult['token'], $body['token']);
    }

    public function testRefreshFailsWithInvalidToken(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/refresh')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'refreshToken' => 'invalid.token.here',
        ]) ?: '');

        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testLoginSetsRefreshCookie(): void
    {
        $this->seedUser();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'testuser',
            'password' => 'TestPassword123456',
        ]) ?: '');

        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $setCookies = $response->getHeader('Set-Cookie');
        $refreshCookie = $this->findCookie($setCookies, 'wpm_refresh');
        $this->assertNotNull($refreshCookie, 'Login response must set wpm_refresh cookie');
        $this->assertStringContainsString('HttpOnly', $refreshCookie, 'Refresh cookie must be HttpOnly');
        $this->assertStringContainsString('SameSite=Strict', $refreshCookie, 'Refresh cookie must be SameSite=Strict');
        $this->assertStringContainsString('Path=/api/auth', $refreshCookie, 'Refresh cookie must be scoped to /api/auth');
        $this->assertStringContainsString('Max-Age=604800', $refreshCookie, 'Refresh cookie must have 7-day Max-Age');
    }

    public function testRefreshReadsFromCookie(): void
    {
        $loginResult = $this->loginViaApiWithCookies();

        // Send refresh request with cookie only (no body) — should still work
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/refresh')
            ->withCookieParams(['wpm_refresh' => $loginResult['refreshToken']]);

        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertNotEmpty($body['token']);
        $this->assertNotSame($loginResult['token'], $body['token']);
    }

    public function testRefreshRotatesCookie(): void
    {
        $loginResult = $this->loginViaApiWithCookies();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/refresh')
            ->withCookieParams(['wpm_refresh' => $loginResult['refreshToken']]);

        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
        $setCookies = $response->getHeader('Set-Cookie');
        $refreshCookie = $this->findCookie($setCookies, 'wpm_refresh');
        $this->assertNotNull($refreshCookie, 'Refresh response must rotate the wpm_refresh cookie');
        $this->assertStringContainsString('Max-Age=604800', $refreshCookie);
        // The new cookie value must differ from the original refresh token
        $this->assertStringNotContainsString('wpm_refresh=' . $loginResult['refreshToken'] . ';', $refreshCookie);
    }

    public function testRefreshClearsCookieOnFailure(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/refresh')
            ->withCookieParams(['wpm_refresh' => 'invalid.token.here']);

        $response = $this->app->handle($request);

        $this->assertSame(401, $response->getStatusCode());
        $setCookies = $response->getHeader('Set-Cookie');
        $refreshCookie = $this->findCookie($setCookies, 'wpm_refresh');
        $this->assertNotNull($refreshCookie, 'Failed refresh must clear the wpm_refresh cookie');
        $this->assertStringContainsString('Max-Age=0', $refreshCookie);
    }

    public function testLogoutClearsRefreshCookie(): void
    {
        $loginResult = $this->loginViaApiWithCookies();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/logout')
            ->withHeader('Authorization', 'Bearer ' . $loginResult['token'])
            ->withHeader('X-CSRF-Token', $loginResult['csrfToken'])
            ->withCookieParams([
                'wpm_csrf' => $loginResult['csrfToken'],
                'wpm_refresh' => $loginResult['refreshToken'],
            ]);
        $response = $this->app->handle($request);

        $this->assertSame(204, $response->getStatusCode());
        $setCookies = $response->getHeader('Set-Cookie');
        $refreshCookie = $this->findCookie($setCookies, 'wpm_refresh');
        $this->assertNotNull($refreshCookie, 'Logout must clear the wpm_refresh cookie');
        $this->assertStringContainsString('Max-Age=0', $refreshCookie);
    }

    public function testRefreshCookieNotSecureOverHttp(): void
    {
        $this->seedUser();

        // Default ServerRequestFactory creates http:// requests
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'testuser',
            'password' => 'TestPassword123456',
        ]) ?: '');

        $response = $this->app->handle($request);

        $setCookies = $response->getHeader('Set-Cookie');
        $refreshCookie = $this->findCookie($setCookies, 'wpm_refresh');
        $this->assertNotNull($refreshCookie);
        $this->assertStringNotContainsString('Secure', $refreshCookie, 'Refresh cookie must NOT be Secure over http');
    }

    /**
     * @param array<int,string> $setCookies
     */
    private function findCookie(array $setCookies, string $name): ?string
    {
        foreach ($setCookies as $cookie) {
            if (str_starts_with($cookie, $name . '=')) {
                return $cookie;
            }
        }

        return null;
    }

    /**
     * @return array{token: string, refreshToken: string, csrfToken: string}
     */
    private function loginViaApiWithCookies(): array
    {
        $this->seedUser();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'testuser',
            'password' => 'TestPassword123456',
        ]) ?: '');

        $response = $this->app->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        return [
            'token' => $body['token'],
            'refreshToken' => $body['refreshToken'],
            'csrfToken' => $body['csrfToken'],
        ];
    }

    /**
     * @return array{token: string, refreshToken: string, csrfToken: string}
     */
    private function loginViaApi(): array
    {
        $this->seedUser();

        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/login')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'testuser',
            'password' => 'TestPassword123456',
        ]) ?: '');

        $response = $this->app->handle($request);
        $body = json_decode((string) $response->getBody(), true);

        return [
            'token' => $body['token'],
            'refreshToken' => $body['refreshToken'],
            'csrfToken' => $body['csrfToken'],
        ];
    }

    private function seedUser(): void
    {
        // Use the setup endpoint to create a user (proper Argon2id + verification token)
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/auth/setup')
            ->withHeader('Content-Type', 'application/json');
        $request->getBody()->write(json_encode([
            'username' => 'testuser',
            'password' => 'TestPassword123456',
            'email' => 'test@example.com',
        ]) ?: '');

        $this->app->handle($request);
    }

    private function cleanupTables(): void
    {
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $this->connection->executeStatement('DROP TABLE IF EXISTS rate_limit_buckets');
        $this->connection->executeStatement('TRUNCATE TABLE audit_log');
        $this->connection->executeStatement('TRUNCATE TABLE user_sessions');
        $this->connection->executeStatement('TRUNCATE TABLE users');
        $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
