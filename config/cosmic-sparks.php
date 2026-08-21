<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Canonical Spark access hierarchy
    |--------------------------------------------------------------------------
    |
    | These ranks are the single source of truth for Spark entitlement checks.
    | Agency tiers are intentionally separate from Personal tiers.
    |
    */
    'access_levels' => [
        'free' => ['rank' => 0, 'label' => 'Free'],
        'starter' => ['rank' => 10, 'label' => 'Starter'],
        'growth' => ['rank' => 20, 'label' => 'Growth'],
        'pro' => ['rank' => 30, 'label' => 'Pro'],
        'agency_starter' => ['rank' => 110, 'label' => 'Starter Agency'],
        'agency_growth' => ['rank' => 120, 'label' => 'Growth Agency'],
        'agency_pro' => ['rank' => 130, 'label' => 'Pro Agency'],
        'all' => ['rank' => PHP_INT_MAX, 'label' => 'All Sparks'],
    ],

    /*
    | Map the existing pricing collections to official entitlement levels.
    | Individual Spark overrides may be added later without changing services.
    */
    'collections' => [
        'core' => [
            'label' => 'Core Collection',
            'access_level' => 'free',
            'description' => 'Essential reusable sections available to every paid Cosmic plan.',
        ],
        'growth' => [
            'label' => 'Growth Collection',
            'access_level' => 'growth',
            'description' => 'Marketing-focused layouts for Growth and higher plans.',
        ],
        'signature' => [
            'label' => 'Signature Collection',
            'access_level' => 'pro',
            'description' => 'Premium visual and motion sections for Pro and higher plans.',
        ],
    ],

    'overrides' => [
        // Metadata is derived automatically from every Spark key. Add only special
        // semantic aliases here when a Spark's name cannot fully describe its use.
        'hero_slider_premium' => [
            'aliases' => ['hero slider','banner slider','homepage carousel','rotating hero'],
            'media' => 'slider',
            'position_fit' => ['top'],
            'capabilities' => ['supports-slider','supports-multiple-images','overlay-header-safe'],
        ],
        'testimonials_carousel' => [
            'aliases' => ['review slider','testimonial slider','client carousel','customer stories'],
            'media' => 'slider',
            'intent' => ['proof'],
        ],
        'case_studies_grid' => [
            'aliases' => ['portfolio','selected work','projects','work showcase'],
            'intent' => ['showcase','proof'],
        ],
        // 'spark_key' => ['access_level' => 'pro'],
    ],
];
