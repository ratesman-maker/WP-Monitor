<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use WPMonitor\Http\Middleware\CorsMiddleware;
use WPMonitor\Tests\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    private const ALLOWED_ORIGIN = 'http://localhost:5173';

    private function createMiddleware(
        array $origins = [self::ALLOWED_ORIGIN],
        array $methods = ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization', 'X-CSRF-Token']
    ): CorsMiddleware {
        return new CorsMiddleware($origins, $methods, $headers);
    }

    private function createRequest(
        string $method,
        string $path = '/api/test',
        string $origin = ''
    ): ServerRequestInterface {
        $request = (new ServerRequestFactory())->createServerRequest($method, $path);
        if ($origin !== '') {
            $request = $request->withHeader('Origin', $origin);
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

    public function testAddsCorsHeadersForAllowedOrigin(): void
    {
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/api/test', self::ALLOWED_ORIGIN);
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(self::ALLOWED_ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('Origin', $response->getHeaderLine('Vary'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testReturns204ForPreflightOptions(): void
    {
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('OPTIONS', '/api/test', self::ALLOWED_ORIGIN);
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(self::ALLOWED_ORIGIN, $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', $response->getHeaderLine('Access-Control-Allow-Methods'));
        $this->assertStringContainsString('Content-Type', $response->getHeaderLine('Access-Control-Allow-Headers'));
    }

    public function testOmitsAllowOriginForDisallowedOrigin(): void
    {
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/api/test', 'http://evil.com');
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testWildcardOriginReflectsRequestOrigin(): void
    {
        $middleware = $this->createMiddleware(origins: ['*']);
        $request = $this->createRequest('GET', '/api/test', 'http://anywhere.com');
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame('http://anywhere.com', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function testNoCorsHeadersWhenNoOriginHeader(): void
    {
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/api/test');
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
        // Methods/Headers/Credentials are always set regardless of origin
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
    }

    public function testPreflightWithoutOriginStillReturns204(): void
    {
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('OPTIONS', '/api/test');
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $response = $middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}
