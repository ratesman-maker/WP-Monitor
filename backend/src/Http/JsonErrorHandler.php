<?php

declare(strict_types=1);

namespace WPMonitor\Http;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Slim\Handlers\ErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Throwable;

/**
 * JSON error handler producing RFC 7807 problem+json responses.
 *
 * Used as the default error handler for Slim's ErrorMiddleware so that
 * all exceptions (404, 405, 500, runtime errors) return a consistent JSON
 * shape instead of HTML or plain text.
 */
final class JsonErrorHandler extends ErrorHandler
{
    public function __construct(
        CallableResolverInterface $callableResolver,
        \Psr\Http\Message\ResponseFactoryInterface $responseFactory,
        ?LoggerInterface $logger = null
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger);
    }

    protected function respond(): ResponseInterface
    {
        $exception = $this->exception;
        $status = $this->statusCode;
        $title = $this->errorTitle($status);
        $detail = $this->detailMessage($exception, $status);

        $payload = [
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ];

        if ($this->displayErrorDetails) {
            $payload['exception'] = [
                'class' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'message' => $exception->getMessage(),
            ];
        }

        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write(json_encode($payload, JSON_PRETTY_PRINT) ?: '');

        return $response->withHeader('Content-Type', 'application/problem+json');
    }

    private function detailMessage(Throwable $exception, int $status): string
    {
        if ($this->displayErrorDetails) {
            return $exception->getMessage();
        }

        return match ($status) {
            404 => 'The requested resource was not found.',
            405 => 'The requested method is not allowed for this resource.',
            400 => 'The request was invalid.',
            401 => 'Authentication is required to access this resource.',
            403 => 'You do not have permission to access this resource.',
            415 => 'The request media type is not supported.',
            500 => 'An internal server error occurred.',
            503 => 'The service is temporarily unavailable.',
            default => 'An error occurred while processing the request.',
        };
    }

    private function errorTitle(int $status): string
    {
        return match ($status) {
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            415 => 'Unsupported Media Type',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }
}
