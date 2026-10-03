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
        // Keep TLS verification enabled even on Windows/XAMPP where PHP's
        // curl.cainfo/openssl.cafile are often unset. A project-level Mozilla
        // CA bundle is shipped as the safe default; PAYPAL_CA_BUNDLE can point
        // to a machine-managed bundle instead.
        'ssl_verify' => env('PAYPAL_SSL_VERIFY', true),
        'ca_bundle' => env('PAYPAL_CA_BUNDLE', base_path('resources/certs/cacert.pem')),
        // Local development only: on localhost PayPal Sandbox, bypass TLS
        // certificate verification from the first request. This avoids Windows/
        // XAMPP cURL error 60 caused by a missing/intercepted local CA chain.
        // Live mode and production can never use this bypass.
        'allow_insecure_local_fallback' => env('PAYPAL_ALLOW_INSECURE_LOCAL_FALLBACK', true),
        // Local/sandbox resilience: when a new Cosmic plan does not yet have a
        // PayPal sandbox billing-plan ID, create a matching sandbox product/plan
        // automatically. Live mode still requires explicit configured plan IDs.
        'auto_provision_sandbox_plans' => env('PAYPAL_AUTO_PROVISION_SANDBOX_PLANS', true),
        // Deprecated compatibility mirror. New code must resolve plan IDs through
        // PayPalPlanBindingService, backed by config/cosmic-plans.php.
        'plan_ids' => collect(require __DIR__.'/cosmic-plans.php')
            ->mapWithKeys(fn (array $plan, string $key) => [
                $key => data_get($plan, 'billing.paypal_plan_id'),
            ])
            ->all(),
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
            'key' => 'agency_starter',
            'label' => 'Starter Agency',
            'sites' => 'Up to 3 websites',
            'description' => 'For freelancers managing a small client portfolio.',
        ],
        [
            'key' => 'agency_growth',
            'label' => 'Growth Agency',
            'sites' => 'Up to 10 websites',
            'description' => 'For growing teams managing multiple active clients.',
        ],
        [
            'key' => 'agency_pro',
            'label' => 'Pro Agency',
            'sites' => 'Unlimited websites',
            'description' => 'Full agency operations, insights, teams, and white label.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Unified plan registry
    |--------------------------------------------------------------------------
    |
    | Capabilities are the source of truth. UI and backend guards must inspect
    | capabilities instead of branching on individual plan names.
    |
    */
    'plans' => require __DIR__.'/cosmic-plans.php',

];
