<?php

declare(strict_types=1);

namespace WPMonitor\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class CorsMiddleware implements MiddlewareInterface
{
    /**
     * @param array<int,string> $allowedOrigins
     * @param array<int,string> $allowedMethods
     * @param array<int,string> $allowedHeaders
     */
    public function __construct(
        private readonly array $allowedOrigins,
        private readonly array $allowedMethods,
        private readonly array $allowedHeaders
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $origin = $request->getHeaderLine('Origin');

        // Preflight OPTIONS — respond immediately without invoking handler
        if ($request->getMethod() === 'OPTIONS') {
            $response = (new ResponseFactory())->createResponse(204);

            return $this->withCorsHeaders($response, $origin);
        }

        $response = $handler->handle($request);

        return $this->withCorsHeaders($response, $origin);
    }

    private function withCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $allowedOrigin = $this->resolveOrigin($origin);

        if ($allowedOrigin !== '') {
            $response = $response
                ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
                ->withHeader('Vary', 'Origin');
        }

        return $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Max-Age', '600');
    }

    private function resolveOrigin(string $origin): string
    {
        if ($origin === '') {
            return '';
        }

        // Wildcard allows any origin — but per spec, cannot be combined with credentials.
        // For dev convenience we still reflect the request origin when wildcard is configured.
        if (in_array('*', $this->allowedOrigins, true)) {
            return $origin;
        }

        if (in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return '';
    }
}
