<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use WPMonitor\Auth\JwtService;
use WPMonitor\Auth\SessionServiceInterface;
use WPMonitor\Http\Middleware\AuthMiddleware;
use WPMonitor\Tests\TestCase;

final class AuthMiddlewareTest extends TestCase
{
    private const APP_KEY = 'base64:' . 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';
    private const ISSUER = 'wp-monitor-test';

    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = new JwtService(self::APP_KEY, self::ISSUER, 900, 604800);
    }

    private function createMiddleware(SessionServiceInterface $sessionService): AuthMiddleware
    {
        return new AuthMiddleware($this->jwtService, $sessionService);
    }

    private function createRequest(string $authHeader = ''): ServerRequestInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/protected');
        if ($authHeader !== '') {
            $request = $request->withHeader('Authorization', $authHeader);
        }

        return $request;
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->expects('handle')->andReturnUsing(function (ServerRequestInterface $request): ResponseInterface {
            return (new ResponseFactory())->createResponse(200);
        });

        return $handler;
    }

    private function mockSessionService(?string $sessionId): SessionServiceInterface
    {
        $sessionService = \Mockery::mock(SessionServiceInterface::class);
        $sessionService->allows('findByJti')->andReturn($sessionId);

        return $sessionService;
    }

    public function testRejectsRequestWithoutAuthorizationHeader(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService('session-id'));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process($this->createRequest(), $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->getHeaderLine('Content-Type'));
    }

    public function testRejectsRequestWithNonBearerAuthorizationHeader(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService('session-id'));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process($this->createRequest('Basic abc123'), $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRejectsRequestWithInvalidToken(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process($this->createRequest('Bearer invalid.token.here'), $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testRejectsRequestWithValidTokenButRevokedSession(): void
    {
        $issued = $this->jwtService->issueAccessToken(42, 'admin');
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('Bearer ' . $issued['token']),
            $handler
        );

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertStringContainsString('revoked', $body['detail']);
    }

    public function testAllowsRequestWithValidTokenAndActiveSession(): void
    {
        $issued = $this->jwtService->issueAccessToken(42, 'admin');
        $middleware = $this->createMiddleware($this->mockSessionService('active-session-id'));

        $request = $this->createRequest('Bearer ' . $issued['token']);
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSetsAuthAttributesOnRequest(): void
    {
        $issued = $this->jwtService->issueAccessToken(42, 'admin');
        $middleware = $this->createMiddleware($this->mockSessionService('active-session-id'));

        $capturedRequest = null;
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->expects('handle')->andReturnUsing(function (ServerRequestInterface $request) use (&$capturedRequest): ResponseInterface {
            $capturedRequest = $request;

            return (new ResponseFactory())->createResponse(200);
        });

        $middleware->process($this->createRequest('Bearer ' . $issued['token']), $handler);

        $this->assertNotNull($capturedRequest);
        $this->assertSame(42, $capturedRequest->getAttribute(AuthMiddleware::ATTR_USER_ID));
        $this->assertSame('admin', $capturedRequest->getAttribute(AuthMiddleware::ATTR_ROLE));
        $this->assertSame($issued['jti'], $capturedRequest->getAttribute(AuthMiddleware::ATTR_JTI));
        $this->assertSame('active-session-id', $capturedRequest->getAttribute(AuthMiddleware::ATTR_SESSION_ID));
    }

    public function testRejectsExpiredToken(): void
    {
        $service = new JwtService(self::APP_KEY, self::ISSUER, 0, 0);
        $issued = $service->issueAccessToken(42, 'admin');
        sleep(1);

        $middleware = $this->createMiddleware($this->mockSessionService('session-id'));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('Bearer ' . $issued['token']),
            $handler
        );

        $this->assertSame(401, $response->getStatusCode());
    }
}
