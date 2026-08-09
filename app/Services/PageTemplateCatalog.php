<?php

namespace App\Services;

class PageTemplateCatalog
{
    public const PURCHASE_CREDITS = 100;
    public const PERSONALIZE_CREDITS = 50;

    public static function all(): array
    {
        return [
            [
                'key' => 'parallax-authority', 'name' => 'Parallax Authority',
                'description' => 'A high-impact business landing page with parallax depth, proof, services and a strong close.',
                'tags' => ['Parallax', 'Business', 'Premium'], 'featured' => true,
                'sections' => ['hero_parallax', 'stats_modern', 'services_bento_premium', 'process_timeline', 'testimonials_carousel', 'image_cta_banner'],
            ],
            [
                'key' => 'slider-showcase', 'name' => 'Slider Showcase',
                'description' => 'A cinematic slider-led page for visual brands, property, hospitality and premium services.',
                'tags' => ['Slider', 'Luxury', 'Showcase'], 'featured' => true,
                'sections' => ['hero_slider_fade', 'feature_image_left', 'services_hover_cards', 'case_studies_grid', 'testimonials_carousel', 'contact_form_modern'],
            ],
            [
                'key' => 'split-conversion', 'name' => 'Split Conversion',
                'description' => 'A focused split-hero composition built for lead generation and professional services.',
                'tags' => ['Split', 'Business', 'Conversion'], 'featured' => false,
                'sections' => ['hero_split_image', 'services_feature_comparison', 'feature_image_right', 'stats_modern', 'faq_accordion', 'contact_form_modern'],
            ],
            [
                'key' => 'bento-launch', 'name' => 'Bento Launch',
                'description' => 'A modern product and startup page combining a premium bento hero with modular proof.',
                'tags' => ['Bento', 'SaaS', 'Modern'], 'featured' => true,
                'sections' => ['hero_bento_premium', 'services_bento_premium', 'feature_image_left', 'stats_modern', 'pricing_cards', 'faq_accordion'],
            ],
            [
                'key' => 'editorial-luxe', 'name' => 'Editorial Luxe',
                'description' => 'An editorial, image-forward page for luxury, creative and lifestyle brands.',
                'tags' => ['Editorial', 'Luxury', 'Creative'], 'featured' => false,
                'sections' => ['hero_editorial_overlay', 'feature_image_right', 'services_bento', 'case_studies_grid', 'testimonials_carousel', 'image_cta_banner'],
            ],
        ];
    }

    public static function find(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }
}
