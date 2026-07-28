<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Page Intent Layout Rules
    |--------------------------------------------------------------------------
    |
    | Arrange specific page intents before broader intents.
    |
    | Example:
    | "Our Team" should match the `team` intent before the broader `about`
    | intent. The first matching intent should win.
    |
    */

    'focus' => [

        /*
        |--------------------------------------------------------------------------
        | Team / Staff Page
        |--------------------------------------------------------------------------
        */

        'team' => [
            'keywords' => [
                'team',
                'our team',
                'meet the team',
                'staff',
                'our staff',
                'people',
                'leadership',
                'management team',
                'directors',
                'experts',
            ],

            'layouts' => [
                [
                    'hero_centered_cta',
                    'team_grid',
                    'feature_image_right',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
                [
                    'hero_background_image',
                    'team_grid',
                    'stats_modern',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
                [
                    'hero_editorial_overlay',
                    'team_grid',
                    'feature_image_left',
                    'image_cta_banner',
                ],
                [
                    'hero_split_image',
                    'team_grid',
                    'stats_modern',
                    'hero_centered_cta',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Contact Page
        |--------------------------------------------------------------------------
        */

        'contact' => [
            'keywords' => [
                'contact',
                'contact us',
                'get in touch',
                'reach us',
                'enquire',
                'enquiry',
                'inquiry',
                'send a message',
                'book a call',
                'request a quote',
                'request quote',
                'get a quote',
                'talk to us',
                'locations',
                'find us',
            ],

            'layouts' => [
                [
                    'hero_centered_cta',
                    'contact_form',
                    'contact_details',
                    'map_embed',
                ],
                [
                    'hero_background_image',
                    'contact_form',
                    'contact_details',
                    'image_cta_banner',
                ],
                [
                    'hero_split_image',
                    'contact_form',
                    'contact_details',
                    'faq_accordion',
                ],
                [
                    'hero_editorial_overlay',
                    'contact_details',
                    'contact_form',
                    'map_embed',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | FAQ Page
        |--------------------------------------------------------------------------
        */

        'faq' => [
            'keywords' => [
                'faq',
                'faqs',
                'frequently asked questions',
                'questions',
                'help',
                'help centre',
                'help center',
                'support',
                'common questions',
            ],

            'layouts' => [
                [
                    'hero_centered_cta',
                    'faq_accordion',
                    'contact_form',
                    'image_cta_banner',
                ],
                [
                    'hero_background_image',
                    'faq_accordion',
                    'services_cards',
                    'hero_centered_cta',
                ],
                [
                    'hero_editorial_overlay',
                    'faq_accordion',
                    'contact_details',
                    'image_cta_banner',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Portfolio / Case Studies / Projects
        |--------------------------------------------------------------------------
        */

        'case_studies' => [
            'keywords' => [
                'case studies',
                'case study',
                'portfolio',
                'projects',
                'our work',
                'work',
                'recent work',
                'success stories',
                'results',
                'client stories',
            ],

            'layouts' => [
                [
                    'hero_background_image',
                    'case_studies_grid',
                    'stats_modern',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
                [
                    'hero_editorial_overlay',
                    'case_studies_grid',
                    'feature_image_right',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
                [
                    'hero_split_image',
                    'case_studies_grid',
                    'process_timeline',
                    'hero_centered_cta',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Careers / Jobs Page
        |--------------------------------------------------------------------------
        */

        'careers' => [
            'keywords' => [
                'careers',
                'career',
                'jobs',
                'job openings',
                'vacancies',
                'vacancy',
                'join our team',
                'work with us',
                'employment',
                'opportunities',
            ],

            'layouts' => [
                [
                    'hero_background_image',
                    'feature_image_left',
                    'services_cards',
                    'jobs_list',
                    'image_cta_banner',
                ],
                [
                    'hero_split_image',
                    'stats_modern',
                    'feature_image_right',
                    'jobs_list',
                    'contact_form',
                ],
                [
                    'hero_editorial_overlay',
                    'services_bento',
                    'jobs_list',
                    'hero_centered_cta',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Events Page
        |--------------------------------------------------------------------------
        */

        'events' => [
            'keywords' => [
                'events',
                'event',
                'upcoming events',
                'calendar',
                'workshops',
                'seminars',
                'conferences',
                'webinars',
            ],

            'layouts' => [
                [
                    'hero_background_image',
                    'events_grid',
                    'feature_image_right',
                    'image_cta_banner',
                ],
                [
                    'hero_centered_cta',
                    'events_grid',
                    'testimonials_carousel',
                    'contact_form',
                ],
                [
                    'hero_editorial_overlay',
                    'events_grid',
                    'stats_modern',
                    'image_cta_banner',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Blog / News Page
        |--------------------------------------------------------------------------
        */

        'blog' => [
            'keywords' => [
                'blog',
                'blogs',
                'news',
                'articles',
                'insights',
                'updates',
                'resources',
                'latest news',
                'latest articles',
            ],

            'layouts' => [
                [
                    'hero_centered_cta',
                    'posts_grid',
                    'newsletter_signup',
                    'image_cta_banner',
                ],
                [
                    'hero_background_image',
                    'posts_grid',
                    'feature_image_right',
                    'newsletter_signup',
                ],
                [
                    'hero_editorial_overlay',
                    'featured_posts',
                    'posts_grid',
                    'image_cta_banner',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Pricing Page
        |--------------------------------------------------------------------------
        */

        'pricing' => [
            'keywords' => [
                'pricing',
                'price',
                'prices',
                'plans',
                'packages',
                'subscriptions',
                'membership',
                'fees',
                'cost',
            ],

            'layouts' => [
                [
                    'hero_video_background',
                    'pricing_cards',
                    'stats_modern',
                    'testimonials_carousel',
                    'faq_accordion',
                    'image_cta_banner',
                ],
                [
                    'hero_centered_cta',
                    'pricing_cards',
                    'stats_modern',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
                [
                    'hero_split_image',
                    'feature_image_right',
                    'pricing_cards',
                    'process_timeline',
                    'faq_accordion',
                    'hero_centered_cta',
                ],
                [
                    'hero_background_image',
                    'services_cards',
                    'pricing_cards',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Services Page
        |--------------------------------------------------------------------------
        */

        'services' => [
            'keywords' => [
                'services',
                'service',
                'our services',
                'solutions',
                'capabilities',
                'what we do',
                'expertise',
                'offerings',
            ],

            'layouts' => [
                [
                    'hero_video_background',
                    'services_bento',
                    'feature_image_right',
                    'process_timeline',
                    'stats_modern',
                    'image_cta_banner',
                ],
                [
                    'hero_background_image',
                    'services_bento',
                    'feature_image_right',
                    'process_timeline',
                    'stats_modern',
                    'image_cta_banner',
                ],
                [
                    'hero_split_image',
                    'services_cards',
                    'feature_image_left',
                    'testimonials_carousel',
                    'faq_accordion',
                    'hero_centered_cta',
                ],
                [
                    'hero_editorial_overlay',
                    'services_bento',
                    'process_timeline',
                    'stats_modern',
                    'image_cta_banner',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | About Page
        |--------------------------------------------------------------------------
        |
        | Keep this below `team`, because Team is a more specific page intent.
        |
        */

        'about' => [
            'keywords' => [
                'about',
                'about us',
                'our story',
                'story',
                'company',
                'company profile',
                'who we are',
                'our company',
                'our history',
                'mission',
                'vision',
                'values',
            ],

            'layouts' => [
                [
                    'hero_video_background',
                    'feature_image_left',
                    'stats_modern',
                    'process_timeline',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
                [
                    'hero_centered_cta',
                    'feature_image_left',
                    'stats_modern',
                    'process_timeline',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
                [
                    'hero_editorial_overlay',
                    'feature_image_right',
                    'services_cards',
                    'stats_modern',
                    'hero_centered_cta',
                ],
                [
                    'hero_split_image',
                    'feature_image_left',
                    'process_timeline',
                    'testimonials_carousel',
                    'image_cta_banner',
                ],
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Homepage
        |--------------------------------------------------------------------------
        */

        'home' => [
            'keywords' => [
                'home',
                'homepage',
                'landing',
                'landing page',
                'start',
                'main page',
            ],

            'layouts' => [
                [
                    'hero_video_background',
                    'services_bento',
                    'feature_image_right',
                    'stats_modern',
                    'testimonials_carousel',
                    'pricing_cards',
                    'image_cta_banner',
                ],
                [
                    'hero_split_image',
                    'services_bento',
                    'feature_image_right',
                    'stats_modern',
                    'testimonials_carousel',
                    'pricing_cards',
                    'image_cta_banner',
                ],
                [
                    'hero_background_image',
                    'services_cards',
                    'feature_image_left',
                    'process_timeline',
                    'stats_modern',
                    'pricing_cards',
                    'image_cta_banner',
                ],
                [
                    'hero_editorial_overlay',
                    'services_bento',
                    'feature_image_right',
                    'testimonials_carousel',
                    'pricing_cards',
                    'image_cta_banner',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Layouts
    |--------------------------------------------------------------------------
    |
    | Used when no specific page intent matches.
    |
    */

    'default' => [
        [
            'hero_centered_cta',
            'feature_image_right',
            'services_cards',
            'testimonials_carousel',
            'image_cta_banner',
        ],
        [
            'hero_split_image',
            'services_bento',
            'feature_image_right',
            'stats_modern',
            'image_cta_banner',
        ],
        [
            'hero_background_image',
            'services_cards',
            'feature_image_left',
            'process_timeline',
            'image_cta_banner',
        ],
        [
            'hero_editorial_overlay',
            'services_bento',
            'stats_modern',
            'feature_image_right',
            'testimonials_carousel',
            'image_cta_banner',
        ],
    ],
];