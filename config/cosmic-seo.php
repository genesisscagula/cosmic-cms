<?php

return [
    'site_name' => env('SEO_SITE_NAME', 'Cosmic CMS'),
    'base_url' => rtrim(env('SEO_BASE_URL', env('APP_URL', 'https://www.cosmiccms.com')), '/'),
    'default_title' => 'AI Website Builder for Modern Business Websites | Cosmic CMS',
    'default_description' => 'Build a modern, responsive business website with Cosmic CMS, an AI website builder for generating, customizing, and publishing professional websites faster.',
    'default_image' => env('SEO_DEFAULT_IMAGE', '/images/cosmic-cms-social-preview.png'),
    'twitter_card' => 'summary_large_image',
];
