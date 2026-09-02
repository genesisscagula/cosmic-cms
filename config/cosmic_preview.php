<?php

return [
    // local = /preview/{slug}; subdomain = https://{slug}.preview.example.com
    'mode' => env('COSMIC_PREVIEW_MODE', env('APP_ENV', 'production') === 'local' ? 'local' : 'subdomain'),
    'base_url' => rtrim(env('COSMIC_PREVIEW_BASE_URL', env('APP_URL', 'http://127.0.0.1:8000').'/preview'), '/'),
    'domain' => strtolower(trim(env('COSMIC_PREVIEW_DOMAIN', 'preview.cosmiccms.com'))),
    'scheme' => strtolower(trim(env('COSMIC_PREVIEW_SCHEME', 'https'))),
    'disk' => env('COSMIC_PREVIEW_DISK', 'local'),
    'root' => trim(env('COSMIC_PREVIEW_ROOT', 'cosmic-previews'), '/'),

    // Avoid infrastructure-looking hostnames even though every site lives below
    // the dedicated preview domain.
    'reserved_slugs' => [
        'www', 'api', 'admin', 'app', 'mail', 'ftp', 'smtp', 'imap', 'pop',
        'status', 'health', 'assets', 'static', 'cdn', 'preview', 'dashboard', 'marketplace',
    ],
];
