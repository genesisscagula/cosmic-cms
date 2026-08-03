<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'smart_images' => [
        // SMART_IMAGE_PROVIDER remains supported for backward compatibility.
        'provider' => env('SMART_IMAGE_PROVIDER', 'unsplash'),
        'providers' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SMART_IMAGE_PROVIDERS', env('SMART_IMAGE_PROVIDER', 'unsplash')))
        ))),
        'timeout' => (int) env('SMART_IMAGE_TIMEOUT', 8),
        'cache' => filter_var(env('SMART_IMAGE_CACHE', true), FILTER_VALIDATE_BOOL),
        'cache_ttl' => (int) env('SMART_IMAGE_CACHE_TTL', 2592000),
        'min_score' => (int) env('SMART_IMAGE_MIN_SCORE', 2),
        'query_builder_version' => '4.2.0.4',
        'ranking_version' => '4.2.0.4',
    ],

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY'),
    ],

    'pexels' => [
        'api_key' => env('PEXELS_API_KEY'),
    ],

    'pixabay' => [
        'api_key' => env('PIXABAY_API_KEY'),
    ],

    'cosmic' => [
        'publish_webhook_url' => env('COSMIC_PUBLISH_WEBHOOK_URL'),
        'static_sync_url' => env('COSMIC_STATIC_SYNC_URL'),
        'static_sync_token' => env('COSMIC_STATIC_SYNC_TOKEN'),
        // Static exports can be hosted on a different domain from the CMS.
        // Keep their /storage assets anchored to the CMS instead of the target site.
        'asset_base_url' => env('COSMIC_ASSET_BASE_URL', 'https://cosmiccms.com'),
    ],

];
