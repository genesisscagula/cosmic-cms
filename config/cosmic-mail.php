<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trial Email Safety
    |--------------------------------------------------------------------------
    |
    | Set COSMIC_TRIAL_MAIL_ENABLED=false to suppress real SMTP delivery.
    | In local/dev, enable explicitly when you want to test Resend end-to-end.
    */
    'trial_mail_enabled' => (bool) env('COSMIC_TRIAL_MAIL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Public URL for email links
    |--------------------------------------------------------------------------
    |
    | Leave blank to use APP_URL. In production this may be pinned to
    | https://www.cosmiccms.com even if workers run behind another hostname.
    */
    'public_url' => rtrim((string) env('COSMIC_PUBLIC_URL', env('APP_URL', 'http://localhost')), '/'),

    /*
    |--------------------------------------------------------------------------
    | Optional development recipient override
    |--------------------------------------------------------------------------
    |
    | When set, all trial emails are sent to this address instead of the
    | captured lead. Useful for local/staging SMTP verification.
    */
    'dev_recipient' => trim((string) env('COSMIC_TRIAL_MAIL_DEV_RECIPIENT', '')),
];
