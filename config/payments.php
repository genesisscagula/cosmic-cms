<?php

return [
    'stripe' => [
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
    'paymongo' => [
        'secret' => env('PAYMONGO_SECRET_KEY'),
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
        'methods' => array_values(array_filter(explode(',', env('PAYMONGO_PAYMENT_METHODS', 'card,gcash,paymaya')))),
    ],
    'plans' => [
        'starter' => ['label' => 'Starter', 'price_usd' => 49, 'credits' => 30],
        'growth' => ['label' => 'Growth', 'price_usd' => 79, 'credits' => 70],
        'pro' => ['label' => 'Pro', 'price_usd' => 129, 'credits' => 200],
    ],
];
