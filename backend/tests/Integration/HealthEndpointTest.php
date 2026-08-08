<?php

declare(strict_types=1);

namespace WPMonitor\Tests\Integration;

use DI\ContainerBuilder;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use WPMonitor\Tests\TestCase;

final class HealthEndpointTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(__DIR__ . '/../../config/container.php');
        $container = $containerBuilder->build();

        AppFactory::setContainer($container);
        $this->app = AppFactory::create();

        (require __DIR__ . '/../../config/middleware.php')($this->app);
        (require __DIR__ . '/../../config/routes.php')($this->app);
    }

    public function testHealthEndpointReturns200(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $this->app->handle($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testHealthEndpointReturnsJsonContentType(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $this->app->handle($request);

        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testHealthEndpointReturnsOkStatus(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $this->app->handle($request);

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        $this->assertSame('ok', $data['status']);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function testHealthEndpointReturnsValidTimestamp(): void
    {
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $this->app->handle($request);

        $data = json_decode((string) $response->getBody(), true);
        $timestamp = $data['timestamp'];

        // ISO 8601 format (e.g. 2026-08-08T19:15:36+00:00)
        $this->assertNotFalse(\DateTime::createFromFormat(\DateTimeInterface::ATOM, $timestamp));
    }
}
