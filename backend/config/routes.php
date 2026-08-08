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
            $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200);
        });
    });
};
