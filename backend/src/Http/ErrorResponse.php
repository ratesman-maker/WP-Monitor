<?php

declare(strict_types=1);

namespace WPMonitor\Http;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class ErrorResponse
{
    public static function create(
        int $status,
        string $title,
        string $detail,
        string $type = 'about:blank',
        string $instance = ''
    ): ResponseInterface {
        $response = (new ResponseFactory())->createResponse($status);

        $payload = [
            'type' => $type,
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($instance !== '') {
            $payload['instance'] = $instance;
        }

        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/problem+json');
    }
}
