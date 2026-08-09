<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use WPMonitor\Auth\AuditLogService;
use WPMonitor\Auth\AuditLogSubscriber;
use WPMonitor\Auth\AuthService;
use WPMonitor\Auth\JwtService;
use WPMonitor\Auth\SessionService;
use WPMonitor\Auth\SessionServiceInterface;
use WPMonitor\Http\Controller\AuthController;
use WPMonitor\Http\Middleware\AuthMiddleware;
use WPMonitor\Http\Middleware\CsrfMiddleware;
use WPMonitor\Http\Middleware\RateLimitMiddleware;
use WPMonitor\Security\KeyDerivationService;
use WPMonitor\Storage\Connection;

return [
    // Settings
    'settings' => function (ContainerInterface $c) {
        return require __DIR__ . '/settings.php';
    },

    // DBAL Connection
    Connection::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        $db = $settings['db'];

        return new Connection(DriverManager::getConnection([
            'dbname' => $db['name'],
            'user' => $db['user'],
            'password' => $db['password'],
            'host' => $db['host'],
            'port' => $db['port'],
            'driver' => 'pdo_mysql',
            'charset' => $db['charset'],
        ]));
    },

    // Logger
    LoggerInterface::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        $log = $settings['log'];

        $logger = new Logger('wp-monitor');
        $logger->pushHandler(new StreamHandler(
            __DIR__ . '/../' . $log['path'] . '/app.log',
            $log['level'] === 'debug' ? Logger::DEBUG : Logger::WARNING
        ));

        return $logger;
    },

    // Event Dispatcher (for audit log events)
    EventDispatcherInterface::class => function (ContainerInterface $c) {
        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($c->get(AuditLogSubscriber::class));

        return $dispatcher;
    },

    // Audit Log
    AuditLogSubscriber::class => function (ContainerInterface $c) {
        return new AuditLogSubscriber($c->get(Connection::class));
    },
    AuditLogService::class => function (ContainerInterface $c) {
        return new AuditLogService($c->get(EventDispatcherInterface::class));
    },

    // Security — key derivation (Argon2id)
    KeyDerivationService::class => function () {
        return new KeyDerivationService();
    },

    // JWT
    JwtService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        $app = $settings['app'];
        $auth = $settings['auth'];

        return new JwtService(
            $app['key'],
            $auth['jwt_issuer'],
            $auth['jwt_ttl'],
            $auth['jwt_refresh_ttl']
        );
    },

    // Session
    SessionService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');

        return new SessionService(
            $c->get(Connection::class),
            $settings['app']['key']
        );
    },
    SessionServiceInterface::class => function (ContainerInterface $c) {
        return $c->get(SessionService::class);
    },

    // Auth
    AuthService::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        $auth = $settings['auth'];

        return new AuthService(
            $c->get(Connection::class),
            $c->get(KeyDerivationService::class),
            $c->get(JwtService::class),
            $c->get(SessionService::class),
            $c->get(AuditLogService::class),
            $auth['lockout_threshold'],
            $auth['lockout_duration']
        );
    },

    // Middleware
    AuthMiddleware::class => function (ContainerInterface $c) {
        return new AuthMiddleware(
            $c->get(JwtService::class),
            $c->get(SessionService::class)
        );
    },
    CsrfMiddleware::class => function (ContainerInterface $c) {
        return new CsrfMiddleware($c->get(SessionService::class));
    },
    RateLimitMiddleware::class => function (ContainerInterface $c) {
        $settings = $c->get('settings');
        $auth = $settings['auth'];

        return new RateLimitMiddleware(
            $c->get(Connection::class),
            $auth['rate_limit_login'],
            $auth['rate_limit_api']
        );
    },

    // Controllers
    AuthController::class => function (ContainerInterface $c) {
        return new AuthController(
            $c->get(AuthService::class),
            $c->get(RateLimitMiddleware::class)
        );
    },
];
