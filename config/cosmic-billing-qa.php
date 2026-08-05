<?php

return [
    'stale_pending_hours' => (int) env('COSMIC_BILLING_STALE_PENDING_HOURS', 24),
    'stuck_webhook_minutes' => (int) env('COSMIC_BILLING_STUCK_WEBHOOK_MINUTES', 15),
    'failed_webhook_warning_count' => (int) env('COSMIC_BILLING_FAILED_WEBHOOK_WARNING_COUNT', 1),
    'schedule_daily_at' => env('COSMIC_BILLING_QA_DAILY_AT', '09:15'),
];
