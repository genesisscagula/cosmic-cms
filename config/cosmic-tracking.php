<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google / marketing measurement
    |--------------------------------------------------------------------------
    |
    | Keep these empty in source control. Add the real IDs to production .env.
    | Optional scripts are loaded only after the visitor grants the matching
    | analytics / marketing consent in Cosmic CMS's cookie banner.
    |
    */
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION'),
    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID'),
    'google_tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID'),
    'google_ads_id' => env('GOOGLE_ADS_ID'),
];
