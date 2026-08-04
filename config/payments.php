<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Active payment routing
    |--------------------------------------------------------------------------
    |
    | Philippine visitors use PayMongo. Everyone else uses PayPal.
    | Cloudflare normally provides the CF-IPCountry header. When the country
    | cannot be detected, the application should safely fall back to PayPal.
    |
    */
    'routing' => [
        'philippines_country_code' => 'PH',
        'philippines_provider' => 'paymongo',
        'international_provider' => 'paypal',
        'fallback_provider' => 'paypal',
        'country_header' => env('PAYMENT_COUNTRY_HEADER', 'CF-IPCountry'),
    ],

    /*
    |--------------------------------------------------------------------------
    | PayPal
    |--------------------------------------------------------------------------
    */
    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', false),
        'mode' => env('PAYPAL_MODE', 'sandbox'),
        'client_id' => env('PAYPAL_CLIENT_ID'),
        'client_secret' => env('PAYPAL_CLIENT_SECRET'),
        'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        'currency' => env('PAYPAL_CURRENCY', 'USD'),
        'plan_ids' => [
            'starter' => env('PAYPAL_PLAN_STARTER_ID'),
            'growth' => env('PAYPAL_PLAN_GROWTH_ID'),
            'pro' => env('PAYPAL_PLAN_PRO_ID'),
        ],
        'base_url' => env('PAYPAL_MODE', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | PayMongo
    |--------------------------------------------------------------------------
    */
    'paymongo' => [
        'enabled' => env('PAYMONGO_ENABLED', false),
        'secret' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'methods' => array_values(array_filter(explode(
            ',',
            env('PAYMONGO_PAYMENT_METHODS', 'card,gcash,paymaya')
        ))),
        'currency' => 'PHP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe (disabled for V1, retained for future re-enable)
    |--------------------------------------------------------------------------
    */
    'stripe' => [
        'enabled' => env('STRIPE_ENABLED', false),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],


    'website_upgrade_options' => [
        [
            'key' => 'business',
            'label' => 'Business',
            'sites' => '3 websites',
            'description' => 'Manage several business websites from one account.',
        ],
        [
            'key' => 'agency',
            'label' => 'Agency',
            'sites' => '10 websites',
            'description' => 'Built for growing client work and shared operations.',
        ],
        [
            'key' => 'agency_pro',
            'label' => 'Agency Pro',
            'sites' => 'Unlimited websites',
            'description' => 'Maximum capacity for established agencies.',
        ],
    ],

    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'price_usd' => 49,
            'credits' => 30,
            'capabilities' => ['plan_type' => 'personal', 'max_sites' => 1],
        ],
        'growth' => [
            'label' => 'Growth',
            'price_usd' => 79,
            'credits' => 70,
            'capabilities' => ['plan_type' => 'personal', 'max_sites' => 1],
        ],
        'pro' => [
            'label' => 'Pro',
            'price_usd' => 129,
            'credits' => 200,
            'capabilities' => ['plan_type' => 'personal', 'max_sites' => 1],
        ],
    ],
];
