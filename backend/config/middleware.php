<?php

declare(strict_types=1);

use Slim\App;
use Slim\Middleware\ErrorMiddleware;
use WPMonitor\Http\Middleware\JsonBodyParserMiddleware;

return function (App $app): void {
    // Error middleware
    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();

    $errorMiddleware = new ErrorMiddleware(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        (bool) ($_ENV['APP_DEBUG'] ?? 'false'),
        true,
        true
    );
    $app->add($errorMiddleware);

    // JSON body parser
    $app->add(JsonBodyParserMiddleware::class);
};
