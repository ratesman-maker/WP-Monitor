<?php

declare(strict_types=1);

return [
    'app' => [
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
        'key' => $_ENV['APP_KEY'] ?? '',
        'url' => $_ENV['APP_URL'] ?? 'http://localhost:8080',
        'version' => $_ENV['APP_VERSION'] ?? '0.1.0',
    ],
    'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? 'wp_monitor',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    ],
    'cors' => [
        'origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),
        'methods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,PUT,DELETE,OPTIONS'),
        'headers' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization,X-CSRF-Token'),
    ],
    'log' => [
        'level' => $_ENV['LOG_LEVEL'] ?? 'warning',
        'path' => $_ENV['LOG_PATH'] ?? 'storage/logs',
        'max_files' => (int) ($_ENV['LOG_MAX_FILES'] ?? 30),
    ],
];
