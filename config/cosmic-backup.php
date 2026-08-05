<?php

return [
    'enabled' => env('COSMIC_BACKUP_ENABLED', true),
    'disk' => env('COSMIC_BACKUP_DISK', 'local'),
    'path' => trim(env('COSMIC_BACKUP_PATH', 'backups/cosmic'), '/'),
    'database' => [
        'enabled' => env('COSMIC_BACKUP_DATABASE', true),
        'connection' => env('COSMIC_BACKUP_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
        'mysqldump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
        'pg_dump_binary' => env('PG_DUMP_BINARY', 'pg_dump'),
    ],
    'files' => [
        'enabled' => env('COSMIC_BACKUP_FILES', true),
        'paths' => [
            storage_path('app/public'),
        ],
    ],
    'retention' => [
        'daily_days' => (int) env('COSMIC_BACKUP_DAILY_DAYS', 7),
        'weekly_weeks' => (int) env('COSMIC_BACKUP_WEEKLY_WEEKS', 4),
        'monthly_months' => (int) env('COSMIC_BACKUP_MONTHLY_MONTHS', 6),
    ],
    'health' => [
        'maximum_age_hours' => (int) env('COSMIC_BACKUP_MAX_AGE_HOURS', 30),
        'minimum_bytes' => (int) env('COSMIC_BACKUP_MINIMUM_BYTES', 1024),
    ],
];
