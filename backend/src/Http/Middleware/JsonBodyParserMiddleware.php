<?php

declare(strict_types=1);

namespace WPMonitor\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $contentType = $request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $rawBody = (string) $request->getBody();

            if ($rawBody !== '') {
                $parsedBody = json_decode($rawBody, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response = (new ResponseFactory())->createResponse(400);
                    $json = json_encode([
                        'error' => 'Invalid JSON body',
                        'message' => json_last_error_msg(),
                    ]);
                    $response->getBody()->write($json !== false ? $json : '');

                    return $response->withHeader('Content-Type', 'application/json');
                }

                $request = $request->withParsedBody($parsedBody);
            }
        }

        return $handler->handle($request);
    }
}
