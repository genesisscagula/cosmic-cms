<?php

namespace App\Cosmic\Pricing;

class BlockPricingRegistry
{
    public static function all(): array
    {
        return [
            // Core Collection — first 15 blocks available to Starter.
            'hero_headline' => ['label' => 'Hero Headline', 'category' => 'core', 'credits' => 10],
            'hero_centered_cta' => ['label' => 'Centered CTA Hero', 'category' => 'core', 'credits' => 10],
            'feature_image_left' => ['label' => 'Feature Image Left', 'category' => 'core', 'credits' => 10],
            'feature_image_right' => ['label' => 'Feature Image Right', 'category' => 'core', 'credits' => 10],
            'services_cards' => ['label' => 'Services Cards', 'category' => 'core', 'credits' => 10],
            'services_bento' => ['label' => 'Services Bento', 'category' => 'core', 'credits' => 20],
            'stats_modern' => ['label' => 'Modern Stats', 'category' => 'core', 'credits' => 10],
            'testimonials_carousel' => ['label' => 'Testimonials', 'category' => 'core', 'credits' => 10],
            'faq_accordion' => ['label' => 'FAQ Accordion', 'category' => 'core', 'credits' => 10],
            'image_cta_banner' => ['label' => 'Image CTA Banner', 'category' => 'core', 'credits' => 10],
            'pricing_cards' => ['label' => 'Pricing Cards', 'category' => 'core', 'credits' => 20],
            'team_modern' => ['label' => 'Team Modern', 'category' => 'core', 'credits' => 10],
            'contact_form_modern' => ['label' => 'Contact Form', 'category' => 'core', 'credits' => 10],
            'contact_details' => ['label' => 'Contact Details', 'category' => 'core', 'credits' => 10],
            'location_map' => ['label' => 'Location Map', 'category' => 'core', 'credits' => 10],

            // Growth Collection.
            'hero_background_image' => ['label' => 'Background Image Hero', 'category' => 'growth', 'credits' => 30],
            'hero_slider_fade' => ['label' => 'Fade Slider Hero', 'category' => 'signature', 'credits' => 150],
            'hero_editorial_overlay' => ['label' => 'Editorial Overlay Hero', 'category' => 'growth', 'credits' => 30],
            'hero_split_image' => ['label' => 'Split Image Hero', 'category' => 'growth', 'credits' => 30],
            'hero_floating_cards' => ['label' => 'Floating Cards Hero', 'category' => 'growth', 'credits' => 40],
            'process_timeline' => ['label' => 'Process Timeline', 'category' => 'growth', 'credits' => 30],
            'case_studies_grid' => ['label' => 'Case Studies Grid', 'category' => 'growth', 'credits' => 30],
            'jobs_list' => ['label' => 'Jobs List', 'category' => 'growth', 'credits' => 20],
            'events_grid' => ['label' => 'Events Grid', 'category' => 'growth', 'credits' => 30],
            'blog_mini_hero' => ['label' => 'Blog Mini Hero', 'category' => 'growth', 'credits' => 20],
            'blog_hub' => ['label' => 'Blog Hub', 'category' => 'growth', 'credits' => 30],
            'newsletter_cta' => ['label' => 'Newsletter CTA', 'category' => 'growth', 'credits' => 20],
            'latest_resources' => ['label' => 'Latest Resources', 'category' => 'growth', 'credits' => 30],

            // Signature Collection.
            'hero_parallax' => ['label' => 'Hero Parallax', 'category' => 'signature', 'credits' => 100],
            'hero_video_style' => ['label' => 'Video Style Hero', 'category' => 'signature', 'credits' => 50],
            'hero_video_background' => ['label' => 'Video Background Hero', 'category' => 'signature', 'credits' => 50],
        ];
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? ['label' => str($type)->headline()->toString(), 'category' => 'growth', 'credits' => 20];
    }

    public static function cost(string $type): int
    {
        return (int) self::get($type)['credits'];
    }

    public static function estimate(array $types): int
    {
        return collect($types)->sum(fn ($type) => self::cost((string) $type));
    }
}
