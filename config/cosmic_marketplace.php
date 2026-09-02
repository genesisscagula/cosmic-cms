<?php

return [
    // Local development keeps the marketplace inside the main Laravel app at /marketplace.
    // Production exposes the same Inertia surface on a dedicated public subdomain.
    'domain' => strtolower(trim(env('COSMIC_MARKETPLACE_DOMAIN', 'marketplace.cosmiccms.com'))),
    'scheme' => strtolower(trim(env('COSMIC_MARKETPLACE_SCHEME', 'https'))),
    'local_prefix' => trim(env('COSMIC_MARKETPLACE_LOCAL_PREFIX', 'marketplace'), '/'),
    // Core app URL is used for account/auth/payment handoff from the public marketplace subdomain.
    'core_url' => rtrim(env('COSMIC_MARKETPLACE_CORE_URL', env('APP_URL', 'http://127.0.0.1:8000')), '/'),

    // Marketplace website subscriptions. Batch 2 keeps these values centralized
    // so catalog cards, checkout, and provisioning read the same product promise.
    'plans' => [
        'starter' => [
            'label' => 'Starter',
            'monthly_price_cents' => 4900,
            'currency' => 'USD',
            'page_count' => 5,
        ],
        'growth' => [
            'label' => 'Growth',
            'monthly_price_cents' => 7900,
            'currency' => 'USD',
            'page_count' => 10,
        ],
        'pro' => [
            'label' => 'Pro',
            'monthly_price_cents' => 12900,
            'currency' => 'USD',
            'page_count' => 15,
        ],
    ],


    // Fixed-design Marketplace-only components. These are source-controlled
    // and intentionally excluded from the regular Spark catalog so customers
    // cannot add/remove them through the standard builder flows.
    'sparks' => [
        'marketplace_ledger_hero' => ['template' => 'ledger-start'],
        'marketplace_ledger_services' => ['template' => 'ledger-start'],
        'marketplace_ledger_story' => ['template' => 'ledger-start'],
        'marketplace_ledger_proof' => ['template' => 'ledger-start'],
        'marketplace_ledger_faq' => ['template' => 'ledger-start'],
        'marketplace_ledger_contact' => ['template' => 'ledger-start'],
        'marketplace_ledger_cta' => ['template' => 'ledger-start'],
        'marketplace_ledger_page_hero' => ['template' => 'ledger-start'],
        'marketplace_ember_hero' => ['template' => 'bistro-classic'],
        'marketplace_ember_menu' => ['template' => 'bistro-classic'],
        'marketplace_ember_story' => ['template' => 'bistro-classic'],
        'marketplace_ember_gallery' => ['template' => 'bistro-classic'],
        'marketplace_ember_feature' => ['template' => 'bistro-classic'],
        'marketplace_ember_reviews' => ['template' => 'bistro-classic'],
        'marketplace_ember_private_dining' => ['template' => 'bistro-classic'],
        'marketplace_ember_reservation' => ['template' => 'bistro-classic'],
        'marketplace_ember_location' => ['template' => 'bistro-classic'],
        'marketplace_ember_contact' => ['template' => 'bistro-classic'],
        'marketplace_ember_cta' => ['template' => 'bistro-classic'],
        'marketplace_ember_page_hero' => ['template' => 'bistro-classic'],
        'marketplace_dental_hero' => ['template' => 'smilecare'],
        'marketplace_dental_treatments' => ['template' => 'smilecare'],
        'marketplace_dental_comfort' => ['template' => 'smilecare'],
        'marketplace_dental_process' => ['template' => 'smilecare'],
        'marketplace_dental_team' => ['template' => 'smilecare'],
        'marketplace_dental_proof' => ['template' => 'smilecare'],
        'marketplace_dental_info' => ['template' => 'smilecare'],
        'marketplace_dental_faq' => ['template' => 'smilecare'],
        'marketplace_dental_contact' => ['template' => 'smilecare'],
        'marketplace_dental_cta' => ['template' => 'smilecare'],
        'marketplace_dental_page_hero' => ['template' => 'smilecare'],
        'marketplace_stone_hero' => ['template' => 'buildpro'],
        'marketplace_stone_capabilities' => ['template' => 'buildpro'],
        'marketplace_stone_manifesto' => ['template' => 'buildpro'],
        'marketplace_stone_projects' => ['template' => 'buildpro'],
        'marketplace_stone_process' => ['template' => 'buildpro'],
        'marketplace_stone_metrics' => ['template' => 'buildpro'],
        'marketplace_stone_case_study' => ['template' => 'buildpro'],
        'marketplace_stone_team' => ['template' => 'buildpro'],
        'marketplace_stone_safety' => ['template' => 'buildpro'],
        'marketplace_stone_testimonial' => ['template' => 'buildpro'],
        'marketplace_stone_faq' => ['template' => 'buildpro'],
        'marketplace_stone_contact' => ['template' => 'buildpro'],
        'marketplace_stone_cta' => ['template' => 'buildpro'],
        'marketplace_stone_page_hero' => ['template' => 'buildpro'],
    ],

    'template_statuses' => ['draft', 'published', 'archived'],
];
