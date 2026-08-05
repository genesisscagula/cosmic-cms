<?php

return [
    'production_url' => env('APP_URL'),
    'require_https' => env('COSMIC_LAUNCH_REQUIRE_HTTPS', true),
    'require_queue' => env('COSMIC_LAUNCH_REQUIRE_QUEUE', true),
    'require_mail' => env('COSMIC_LAUNCH_REQUIRE_MAIL', true),
    'require_paypal' => env('COSMIC_LAUNCH_REQUIRE_PAYPAL', true),
    'minimum_php_version' => env('COSMIC_MINIMUM_PHP_VERSION', '8.2.0'),
    'minimum_free_disk_mb' => (int) env('COSMIC_LAUNCH_MIN_FREE_DISK_MB', 1024),
    'required_extensions' => ['curl', 'json', 'mbstring', 'openssl', 'pdo', 'tokenizer', 'xml', 'zip'],
    'required_writable_paths' => [
        storage_path(),
        storage_path('framework'),
        storage_path('logs'),
        base_path('bootstrap/cache'),
    ],
    'required_env' => [
        'APP_KEY',
        'APP_URL',
        'DB_CONNECTION',
        'CACHE_STORE',
        'SESSION_DRIVER',
        'QUEUE_CONNECTION',
        'MAIL_MAILER',
    ],
    'production_forbidden' => [
        'APP_DEBUG' => ['true', '1'],
        'APP_ENV' => ['local', 'testing'],
        'QUEUE_CONNECTION' => ['sync'],
        'MAIL_MAILER' => ['log', 'array'],
    ],
];
