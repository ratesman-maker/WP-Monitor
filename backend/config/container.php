<?php

declare(strict_types=1);

use Doctrine\DBAL\DriverManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
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
];
