<?php

return [
    'stale_pending_hours' => (int) env('COSMIC_ONBOARDING_STALE_HOURS', 24),
    'stale_processing_minutes' => (int) env('COSMIC_PROVISIONING_STALE_MINUTES', 30),
    'schedule_daily_at' => env('COSMIC_ONBOARDING_QA_TIME', '09:35'),
];
