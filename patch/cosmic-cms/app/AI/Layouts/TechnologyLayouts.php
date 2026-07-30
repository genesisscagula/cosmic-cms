<?php

return [
    // Page intent chooses a suitable group first. PHP then picks one variation
    // from that group, keeping output varied without making a Pricing or About
    // page feel like a random homepage.
    'focus' => [
        'home' => [
            'keywords' => ['home', 'homepage', 'landing', 'start'],
            'layouts' => [
                ['hero_video_background', 'services_bento', 'feature_image_right', 'stats_modern', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
                ['hero_split_image', 'services_bento', 'feature_image_right', 'stats_modern', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
                ['hero_background_image', 'services_cards', 'feature_image_left', 'process_timeline', 'stats_modern', 'pricing_cards', 'image_cta_banner'],
                ['hero_editorial_overlay', 'services_bento', 'feature_image_right', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
            ],
        ],
        'about' => [
            'keywords' => ['about', 'story', 'our story', 'company', 'company profile', 'team', 'our team', 'who we are'],
            'layouts' => [
                ['hero_video_background', 'feature_image_left', 'stats_modern', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_centered_cta', 'feature_image_left', 'stats_modern', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_editorial_overlay', 'feature_image_right', 'services_cards', 'stats_modern', 'hero_centered_cta'],
                ['hero_split_image', 'feature_image_left', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
        'services' => [
            'keywords' => ['services', 'service', 'solutions', 'capabilities'],
            'layouts' => [
                ['hero_video_background', 'services_bento', 'feature_image_right', 'process_timeline', 'stats_modern', 'image_cta_banner'],
                ['hero_background_image', 'services_bento', 'feature_image_right', 'process_timeline', 'stats_modern', 'image_cta_banner'],
                ['hero_split_image', 'services_cards', 'feature_image_left', 'testimonials_carousel', 'hero_centered_cta'],
                ['hero_editorial_overlay', 'services_bento', 'process_timeline', 'stats_modern', 'image_cta_banner'],
            ],
        ],
        'pricing' => [
            'keywords' => ['pricing', 'plans', 'packages'],
            'layouts' => [
                ['hero_video_background', 'pricing_cards', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_centered_cta', 'pricing_cards', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_split_image', 'feature_image_right', 'pricing_cards', 'process_timeline', 'hero_centered_cta'],
                ['hero_background_image', 'services_cards', 'pricing_cards', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
    ],
    'default' => [
        ['hero_video_background', 'services_bento', 'feature_image_right', 'stats_modern', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
        ['hero_split_image', 'services_bento', 'feature_image_right', 'stats_modern', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
        ['hero_background_image', 'services_cards', 'feature_image_left', 'process_timeline', 'pricing_cards', 'image_cta_banner'],
        ['hero_editorial_overlay', 'services_bento', 'stats_modern', 'feature_image_right', 'testimonials_carousel', 'image_cta_banner'],
    ],
];
