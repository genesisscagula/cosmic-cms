<?php

return [
    'terms_version' => env('COSMIC_TERMS_VERSION', '2026-08-05'),
    'privacy_version' => env('COSMIC_PRIVACY_VERSION', '2026-08-05'),
    'cookie_version' => env('COSMIC_COOKIE_VERSION', '2026-08-05'),
    'company_name' => env('COSMIC_LEGAL_COMPANY', 'Cosmic CMS'),
    'contact_email' => env('COSMIC_LEGAL_EMAIL', env('MAIL_FROM_ADDRESS')),
    'effective_date' => env('COSMIC_LEGAL_EFFECTIVE_DATE', 'August 5, 2026'),
];
