<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Unit\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use WPMonitor\Http\Middleware\JsonBodyParserMiddleware;
use WPMonitor\Tests\TestCase;

final class JsonBodyParserMiddlewareTest extends TestCase
{
    private function createRequest(string $body, string $contentType = 'application/json'): ServerRequestInterface
    {
        $stream = (new StreamFactory())->createStream($body);
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/test')
            ->withBody($stream)
            ->withHeader('Content-Type', $contentType);

        return $request;
    }

    private function createHandler(): RequestHandlerInterface
    {
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->expects('handle')->andReturnUsing(function (ServerRequestInterface $request): ResponseInterface {
            $response = (new ResponseFactory())->createResponse(200);
            $response->getBody()->write(json_encode(['parsedBody' => $request->getParsedBody()]) ?: '');

            return $response->withHeader('Content-Type', 'application/json');
        });

        return $handler;
    }

    public function testParsesValidJsonBody(): void
    {
        $body = '{"name":"test","value":42}';
        $request = $this->createRequest($body);
        $middleware = new JsonBodyParserMiddleware();
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame(['name' => 'test', 'value' => 42], $data['parsedBody']);
    }

    public function testRejectsInvalidJsonBody(): void
    {
        $body = '{"name": invalid}';
        $request = $this->createRequest($body);
        $handler = \Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldNotReceive('handle');

        $middleware = new JsonBodyParserMiddleware();
        $response = $middleware->process($request, $handler);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame('Invalid JSON body', $data['error']);
    }

    public function testIgnoresNonJsonContentType(): void
    {
        $body = 'raw text data';
        $request = $this->createRequest($body, 'text/plain');
        $middleware = new JsonBodyParserMiddleware();
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertNull($data['parsedBody']);
    }

    public function testIgnoresEmptyBody(): void
    {
        $request = $this->createRequest('');
        $middleware = new JsonBodyParserMiddleware();
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHandlesJsonArrayBody(): void
    {
        $body = '[1, 2, 3]';
        $request = $this->createRequest($body);
        $middleware = new JsonBodyParserMiddleware();
        $response = $middleware->process($request, $this->createHandler());

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getBody(), true);
        $this->assertSame([1, 2, 3], $data['parsedBody']);
    }
}
