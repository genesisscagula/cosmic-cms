<?php

return [
    'auth' => [
        'login_per_minute' => 5,
        'register_per_hour' => 8,
        'password_reset_per_hour' => 5,
    ],
    'trial' => [
        'create_per_hour' => 10,
        'email_per_hour' => 8,
        'regenerate_per_week' => 2,
    ],
    'ai' => [
        'per_minute' => 12,
        'per_hour' => 120,
    ],
    'uploads' => [
        'per_minute' => 20,
        'per_hour' => 200,
    ],
    'public_preview' => [
        'per_minute' => 120,
    ],
    'forms' => [
        'per_minute' => 10,
        'per_hour' => 60,
    ],
    'workspace_writes' => [
        'per_minute' => 30,
    ],
    'api' => [
        'analytics_per_minute' => 240,
        'contact_per_minute' => 30,
        'bridge_per_minute' => 120,
        'webhook_per_minute' => 120,
    ],
];
