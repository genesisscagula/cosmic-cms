<?php

return [
    'enabled' => (bool) env('COSMIC_MONITORING_ENABLED', true),

    'correlation_header' => env('COSMIC_CORRELATION_HEADER', 'X-Cosmic-Request-ID'),

    'slow_query_ms' => (int) env('COSMIC_SLOW_QUERY_MS', 750),

    'health' => [
        'max_log_age_minutes' => (int) env('COSMIC_MAX_LOG_AGE_MINUTES', 1440),
        'max_log_size_mb' => (int) env('COSMIC_MAX_LOG_SIZE_MB', 100),
        'minimum_free_disk_mb' => (int) env('COSMIC_MIN_FREE_DISK_MB', 1024),
        'fail_on_unwritable_log_path' => true,
    ],

    'retention' => [
        'application_days' => (int) env('COSMIC_LOG_RETENTION_DAYS', 30),
        'security_days' => (int) env('COSMIC_SECURITY_LOG_RETENTION_DAYS', 90),
        'performance_days' => (int) env('COSMIC_PERFORMANCE_LOG_RETENTION_DAYS', 14),
    ],

    'redacted_keys' => [
        'password', 'password_confirmation', 'current_password', 'token',
        'access_token', 'refresh_token', 'authorization', 'cookie',
        'client_secret', 'secret', 'api_key', 'paypal_client_secret',
        'deployment_connector_secret',
    ],
];
