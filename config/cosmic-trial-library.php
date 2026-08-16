<?php

return [
    /*
    | Curated trial library. These keys are enforced server-side so a guest
    | cannot bypass the Builder UI and browse/install the full paid catalog.
    */
    'sparks' => [
        'limit' => 10,
        'default' => [
            'hero_headline',
            'hero_centered_cta',
            'feature_image_left',
            'feature_image_right',
            'services_cards',
            'services_bento',
            'testimonials_carousel',
            'faq_accordion',
            'pricing_cards',
            'contact_form_modern',
        ],
        'industry' => [
            'construction' => ['case_studies_grid', 'process_timeline', 'stats_modern'],
            'trades' => ['case_studies_grid', 'process_timeline', 'contact_details'],
            'restaurant' => ['services_cards', 'testimonials_carousel', 'contact_details'],
            'coffee' => ['services_cards', 'testimonials_carousel', 'location_map'],
            'bakery' => ['services_cards', 'testimonials_carousel', 'location_map'],
            'hotel' => ['feature_image_left', 'testimonials_carousel', 'contact_form_modern'],
            'travel' => ['feature_image_left', 'testimonials_carousel', 'contact_form_modern'],
            'real estate' => ['case_studies_grid', 'stats_modern', 'contact_form_modern'],
            'technology' => ['services_bento', 'pricing_cards', 'faq_accordion'],
            'saas' => ['services_bento', 'pricing_cards', 'faq_accordion'],
            'medical' => ['services_cards', 'team_modern', 'testimonials_carousel'],
            'dental' => ['services_cards', 'team_modern', 'testimonials_carousel'],
            'law' => ['services_cards', 'stats_modern', 'testimonials_carousel'],
            'finance' => ['services_cards', 'stats_modern', 'testimonials_carousel'],
            'fitness' => ['services_cards', 'pricing_cards', 'testimonials_carousel'],
            'education' => ['services_cards', 'stats_modern', 'contact_form_modern'],
            'automotive' => ['services_cards', 'case_studies_grid', 'contact_form_modern'],
            'salon' => ['services_cards', 'pricing_cards', 'testimonials_carousel'],
        ],
    ],

    'templates' => [
        'limit' => 10,
        'default' => [
            'split-conversion',
            'bento-launch',
            'corporate-clarity',
            'consulting-forward',
            'portfolio-canvas',
            'modern-showcase',
            'parallax-authority',
            'slider-showcase',
            'editorial-luxe',
            'glass-studio',
        ],
        'industry' => [
            'construction' => ['builder-pro', 'corporate-clarity'],
            'trades' => ['builder-pro', 'corporate-clarity'],
            'restaurant' => ['restaurant-signature', 'editorial-luxe'],
            'coffee' => ['coffee-craft', 'editorial-luxe'],
            'bakery' => ['coffee-craft', 'editorial-luxe'],
            'hotel' => ['hotel-escape', 'luxury-signature'],
            'travel' => ['hotel-escape', 'slider-showcase'],
            'real estate' => ['property-vision', 'luxury-signature'],
            'technology' => ['tech-bento-growth', 'bento-launch'],
            'saas' => ['tech-bento-growth', 'bento-launch'],
            'medical' => ['health-trust', 'corporate-clarity'],
            'dental' => ['health-trust', 'corporate-clarity'],
            'law' => ['advisory-prestige', 'consulting-forward'],
            'finance' => ['advisory-prestige', 'consulting-forward'],
            'fitness' => ['fitness-momentum', 'split-conversion'],
            'education' => ['learning-forward', 'corporate-clarity'],
            'automotive' => ['auto-performance', 'modern-showcase'],
            'agency' => ['cinematic-agency', 'portfolio-canvas'],
        ],
    ],
];
