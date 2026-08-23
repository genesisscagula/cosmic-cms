<?php

return [
    'queues' => [
        'mail' => env('COSMIC_MAIL_QUEUE', 'mail'),
        'maintenance' => env('COSMIC_MAINTENANCE_QUEUE', 'maintenance'),
        'ai_builds' => env('COSMIC_AI_QUEUE', 'ai'),
    ],
    'health' => [
        'max_pending_jobs' => (int) env('COSMIC_QUEUE_MAX_PENDING', 500),
        'max_failed_jobs' => (int) env('COSMIC_QUEUE_MAX_FAILED', 25),
        'stale_after_minutes' => (int) env('COSMIC_QUEUE_STALE_MINUTES', 15),
    ],
    'performance' => [
        // Keep retry_after comfortably above the longest image job timeout.
        'image_job_timeout' => (int) env('COSMIC_IMAGE_JOB_TIMEOUT', 150),
        'image_job_retry_window_minutes' => (int) env('COSMIC_IMAGE_RETRY_WINDOW_MINUTES', 10),
        'image_connect_timeout' => (int) env('COSMIC_IMAGE_CONNECT_TIMEOUT', 4),
        'image_download_timeout' => (int) env('COSMIC_IMAGE_DOWNLOAD_TIMEOUT', 10),
        'image_download_retries' => (int) env('COSMIC_IMAGE_DOWNLOAD_RETRIES', 1),
        'image_retry_backoff' => [2, 5, 10, 20],
        'builder_poll_interval_ms' => (int) env('COSMIC_MEDIA_POLL_INTERVAL_MS', 2500),
    ],
    'retention' => [
        'failed_job_hours' => (int) env('COSMIC_FAILED_JOB_HOURS', 336),
        'expired_access_days' => (int) env('COSMIC_EXPIRED_ACCESS_DAYS', 30),
    ],
];
