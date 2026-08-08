<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Health check
    $app->group('/api', function (RouteCollectorProxy $group): void {
        $group->get('/health', function ($request, $response) {
            $payload = [
                'status' => 'ok',
                'version' => $_ENV['APP_VERSION'] ?? '0.1.0',
                'timestamp' => gmdate('c'),
            ];
            $json = json_encode($payload, JSON_PRETTY_PRINT);
            $response->getBody()->write($json !== false ? $json : '');

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        });
    });
};
