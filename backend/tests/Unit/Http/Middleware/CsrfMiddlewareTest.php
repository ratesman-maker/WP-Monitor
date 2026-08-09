<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use WPMonitor\Auth\SessionServiceInterface;
use WPMonitor\Http\Middleware\AuthMiddleware;
use WPMonitor\Http\Middleware\CsrfMiddleware;
use WPMonitor\Tests\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    private const CSRF_TOKEN = 'a]bccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc'; // 64-char
    private const SESSION_ID = 'test-session-id-123';

    private function createMiddleware(SessionServiceInterface $sessionService): CsrfMiddleware
    {
        return new CsrfMiddleware($sessionService);
    }

    private function createRequest(
        string $method,
        string $csrfHeader = '',
        string $csrfCookie = '',
        ?string $sessionId = null
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, '/api/test');

        if ($csrfHeader !== '') {
            $request = $request->withHeader(CsrfMiddleware::HEADER_NAME, $csrfHeader);
        }

        $cookies = [];
        if ($csrfCookie !== '') {
            $cookies[CsrfMiddleware::COOKIE_NAME] = $csrfCookie;
        }
        $request = $request->withCookieParams($cookies);

        if ($sessionId !== null) {
            $request = $request->withAttribute(AuthMiddleware::ATTR_SESSION_ID, $sessionId);
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

    private function mockSessionService(?array $sessionData): SessionServiceInterface
    {
        $sessionService = \Mockery::mock(SessionServiceInterface::class);
        $sessionService->allows('get')->andReturn($sessionData);

        return $sessionService;
    }

    public function testGetRequestsBypassCsrfValidation(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $response = $middleware->process(
            $this->createRequest('GET'),
            $this->createHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHeadRequestsBypassCsrfValidation(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $response = $middleware->process(
            $this->createRequest('HEAD'),
            $this->createHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOptionsRequestsBypassCsrfValidation(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $response = $middleware->process(
            $this->createRequest('OPTIONS'),
            $this->createHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostWithoutSessionIdReturns403(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('POST', self::CSRF_TOKEN, self::CSRF_TOKEN),
            $handler
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPostWithoutCsrfHeaderReturns403(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('POST', '', self::CSRF_TOKEN, self::SESSION_ID),
            $handler
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPostWithoutCsrfCookieReturns403(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('POST', self::CSRF_TOKEN, '', self::SESSION_ID),
            $handler
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPostWithMismatchedHeaderAndCookieReturns403(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('POST', 'header-token', 'cookie-token', self::SESSION_ID),
            $handler
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPostWithValidTokensButNoSessionReturns403(): void
    {
        $middleware = $this->createMiddleware($this->mockSessionService(null));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('POST', self::CSRF_TOKEN, self::CSRF_TOKEN, self::SESSION_ID),
            $handler
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    public function testPostWithValidTokensAndSessionSucceeds(): void
    {
        $session = [
            'id' => self::SESSION_ID,
            'userId' => 1,
            'jwtJti' => 'jti',
            'csrfToken' => self::CSRF_TOKEN,
            'encryptionKey' => 'key',
        ];
        $middleware = $this->createMiddleware($this->mockSessionService($session));

        $response = $middleware->process(
            $this->createRequest('POST', self::CSRF_TOKEN, self::CSRF_TOKEN, self::SESSION_ID),
            $this->createHandler()
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPostWithTokenNotMatchingSessionReturns403(): void
    {
        $session = [
            'id' => self::SESSION_ID,
            'userId' => 1,
            'jwtJti' => 'jti',
            'csrfToken' => 'different-token-from-session',
            'encryptionKey' => 'key',
        ];
        $middleware = $this->createMiddleware($this->mockSessionService($session));
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process(
            $this->createRequest('POST', self::CSRF_TOKEN, self::CSRF_TOKEN, self::SESSION_ID),
            $handler
        );

        $this->assertSame(403, $response->getStatusCode());
    }
}
