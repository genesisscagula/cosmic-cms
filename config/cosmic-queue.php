<?php

return [
    'queues' => [
        'mail' => env('COSMIC_MAIL_QUEUE', 'mail'),
        'maintenance' => env('COSMIC_MAINTENANCE_QUEUE', 'maintenance'),
    ],
    'health' => [
        'max_pending_jobs' => (int) env('COSMIC_QUEUE_MAX_PENDING', 500),
        'max_failed_jobs' => (int) env('COSMIC_QUEUE_MAX_FAILED', 25),
        'stale_after_minutes' => (int) env('COSMIC_QUEUE_STALE_MINUTES', 15),
    ],
    'retention' => [
        'failed_job_hours' => (int) env('COSMIC_FAILED_JOB_HOURS', 336),
        'expired_access_days' => (int) env('COSMIC_EXPIRED_ACCESS_DAYS', 30),
    ],
];
