<?php

declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use WPMonitor\Http\Controller\AuthController;
use WPMonitor\Http\Middleware\AuthMiddleware;

return function (App $app): void {
    $app->group('/api', function (RouteCollectorProxy $group): void {
        // Health check (public)
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

        // Auth routes
        $group->group('/auth', function (RouteCollectorProxy $auth): void {
            // Public auth routes (no AuthMiddleware)
            $auth->post('/setup', AuthController::class . ':setup');
            $auth->post('/login', AuthController::class . ':login');
            $auth->post('/refresh', AuthController::class . ':refresh');

            // Protected auth routes (require AuthMiddleware)
            $auth->post('/logout', AuthController::class . ':logout')
                ->add(AuthMiddleware::class);
            $auth->get('/me', AuthController::class . ':me')
                ->add(AuthMiddleware::class);
        });
    });
};
