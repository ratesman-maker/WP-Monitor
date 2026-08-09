<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use WPMonitor\Http\JsonErrorHandler;
use WPMonitor\Http\Middleware\CorsMiddleware;
use WPMonitor\Http\Middleware\JsonBodyParserMiddleware;

return function (App $app): void {
    $container = $app->getContainer();
    assert($container instanceof ContainerInterface);
    $settings = $container->get('settings');
    $cors = $settings['cors'];
    $appDebug = (bool) ($_ENV['APP_DEBUG'] ?? 'false');

    // Body parsing + routing (innermost)
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    // Error middleware with JSON error handler (RFC 7807 problem+json)
    $errorMiddleware = new ErrorMiddleware(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        $appDebug,
        true,
        true
    );
    $errorHandler = new JsonErrorHandler(
        $app->getCallableResolver(),
        $app->getResponseFactory()
    );
    $errorMiddleware->setDefaultErrorHandler($errorHandler);
    $app->add($errorMiddleware);

    // JSON body parser
    $app->add(JsonBodyParserMiddleware::class);

    // CORS — added LAST = outermost (handles preflight OPTIONS before anything else)
    $app->add(new CorsMiddleware(
        $cors['origins'],
        $cors['methods'],
        $cors['headers']
    ));
};
