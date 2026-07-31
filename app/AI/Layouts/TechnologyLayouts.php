<?php

return [
    // Match specific page intents before broad ones. This prevents titles such
    // as "Our Technology Team" from accidentally receiving an About layout.
    'focus' => [
        'team' => [
            'keywords' => ['team', 'our team', 'leadership', 'people', 'experts'],
            'layouts' => [
                ['hero_centered_cta', 'team_modern', 'feature_image_left', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_split_image', 'team_modern', 'feature_image_right', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_editorial_overlay', 'team_modern', 'stats_modern', 'feature_image_left', 'testimonials_carousel', 'hero_centered_cta'],
            ],
        ],
        'contact' => [
            'keywords' => ['contact', 'contact us', 'get in touch', 'book a call', 'request a quote', 'enquire', 'inquiry'],
            'layouts' => [
                ['hero_centered_cta', 'contact_details', 'contact_form_modern', 'location_map', 'image_cta_banner'],
                ['hero_split_image', 'contact_form_modern', 'contact_details', 'location_map', 'hero_centered_cta'],
                ['hero_background_image', 'contact_details', 'contact_form_modern', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
        'pricing' => [
            'keywords' => ['pricing', 'plans', 'packages', 'subscriptions', 'cost'],
            'layouts' => [
                ['hero_centered_cta', 'pricing_cards', 'stats_modern', 'testimonials_carousel', 'faq_accordion', 'image_cta_banner'],
                ['hero_split_image', 'pricing_cards', 'services_cards', 'process_timeline', 'faq_accordion', 'hero_centered_cta'],
                ['hero_background_image', 'pricing_cards', 'stats_modern', 'testimonials_carousel', 'faq_accordion', 'image_cta_banner'],
            ],
        ],
        'portfolio' => [
            'keywords' => ['portfolio', 'our work', 'work', 'projects', 'showcase'],
            'layouts' => [
                ['hero_editorial_overlay', 'case_studies_grid', 'feature_image_left', 'stats_modern', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_split_image', 'case_studies_grid', 'feature_image_right', 'services_cards', 'stats_modern', 'testimonials_carousel', 'hero_centered_cta'],
                ['hero_background_image', 'case_studies_grid', 'feature_image_left', 'feature_image_right', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
        'case_studies' => [
            'keywords' => ['case study', 'case studies', 'success stories', 'client results'],
            'layouts' => [
                ['hero_centered_cta', 'case_studies_grid', 'feature_image_left', 'stats_modern', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_editorial_overlay', 'case_studies_grid', 'feature_image_right', 'services_bento', 'stats_modern', 'testimonials_carousel', 'hero_centered_cta'],
                ['hero_split_image', 'case_studies_grid', 'feature_image_left', 'feature_image_right', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
        'services' => [
            'keywords' => ['services', 'service', 'solutions', 'capabilities', 'web design', 'web development', 'development', 'design'],
            'layouts' => [
                ['hero_video_background', 'services_bento', 'feature_image_right', 'feature_image_left', 'process_timeline', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_background_image', 'services_cards', 'feature_image_left', 'feature_image_right', 'process_timeline', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_split_image', 'services_bento', 'feature_image_right', 'case_studies_grid', 'process_timeline', 'stats_modern', 'testimonials_carousel', 'hero_centered_cta'],
                ['hero_editorial_overlay', 'services_cards', 'feature_image_left', 'case_studies_grid', 'process_timeline', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
        'about' => [
            'keywords' => ['about', 'about us', 'our story', 'story', 'company', 'company profile', 'who we are', 'agency'],
            'layouts' => [
                ['hero_editorial_overlay', 'feature_image_left', 'stats_modern', 'process_timeline', 'team_modern', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_split_image', 'feature_image_right', 'stats_modern', 'process_timeline', 'team_modern', 'testimonials_carousel', 'hero_centered_cta'],
                ['hero_centered_cta', 'feature_image_left', 'feature_image_right', 'stats_modern', 'team_modern', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
        'faq' => [
            'keywords' => ['faq', 'faqs', 'frequently asked questions', 'help', 'support'],
            'layouts' => [
                ['hero_centered_cta', 'faq_accordion', 'contact_details', 'contact_form_modern', 'image_cta_banner'],
                ['hero_split_image', 'faq_accordion', 'services_cards', 'contact_form_modern', 'hero_centered_cta'],
                ['hero_background_image', 'faq_accordion', 'testimonials_carousel', 'contact_details', 'image_cta_banner'],
            ],
        ],
        'blog' => [
            'keywords' => ['blog', 'blogs', 'articles', 'insights', 'news', 'resources'],
            'layouts' => [
                ['hero_centered_cta', 'blog_hub', 'newsletter_cta', 'latest_resources', 'image_cta_banner'],
                ['hero_editorial_overlay', 'blog_hub', 'newsletter_cta', 'latest_resources', 'hero_centered_cta'],
                ['hero_background_image', 'blog_hub', 'newsletter_cta', 'latest_resources', 'image_cta_banner'],
            ],
        ],
        'careers' => [
            'keywords' => ['careers', 'career', 'jobs', 'join our team', 'vacancies', 'open roles'],
            'layouts' => [
                ['hero_centered_cta', 'feature_image_left', 'stats_modern', 'jobs_list', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_split_image', 'feature_image_right', 'team_modern', 'jobs_list', 'testimonials_carousel', 'hero_centered_cta'],
                ['hero_background_image', 'feature_image_left', 'process_timeline', 'jobs_list', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ],
        'home' => [
            'keywords' => ['home', 'homepage', 'landing', 'landing page', 'campaign', 'launch', 'start'],
            'layouts' => [
                ['hero_video_background', 'services_bento', 'feature_image_right', 'stats_modern', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
                ['hero_parallax', 'services_bento', 'feature_image_left', 'stats_modern', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
                ['hero_split_image', 'services_cards', 'feature_image_right', 'feature_image_left', 'stats_modern', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
                ['hero_editorial_overlay', 'services_bento', 'feature_image_right', 'stats_modern', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'pricing_cards', 'image_cta_banner'],
            ],
        ],
    ],

    // Default technology pages remain substantial without forcing homepage
    // length onto simple intent-specific pages.
    'default' => [
        ['hero_video_background', 'services_bento', 'feature_image_right', 'stats_modern', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
        ['hero_split_image', 'services_cards', 'feature_image_left', 'feature_image_right', 'stats_modern', 'case_studies_grid', 'testimonials_carousel', 'image_cta_banner'],
        ['hero_background_image', 'services_bento', 'feature_image_right', 'process_timeline', 'stats_modern', 'case_studies_grid', 'testimonials_carousel', 'image_cta_banner'],
        ['hero_editorial_overlay', 'services_cards', 'feature_image_left', 'stats_modern', 'case_studies_grid', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
    ],
];
