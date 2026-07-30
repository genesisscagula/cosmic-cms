<?php

namespace App\Cosmic\Pricing;

class BlockPricingRegistry
{
    public static function all(): array
    {
        return [
            // Core Collection — first 15 blocks available to Starter.
            'hero_headline' => ['label' => 'Hero Headline', 'category' => 'core', 'credits' => 1],
            'hero_centered_cta' => ['label' => 'Centered CTA Hero', 'category' => 'core', 'credits' => 1],
            'feature_image_left' => ['label' => 'Feature Image Left', 'category' => 'core', 'credits' => 1],
            'feature_image_right' => ['label' => 'Feature Image Right', 'category' => 'core', 'credits' => 1],
            'services_cards' => ['label' => 'Services Cards', 'category' => 'core', 'credits' => 1],
            'services_bento' => ['label' => 'Services Bento', 'category' => 'core', 'credits' => 2],
            'stats_modern' => ['label' => 'Modern Stats', 'category' => 'core', 'credits' => 1],
            'testimonials_carousel' => ['label' => 'Testimonials', 'category' => 'core', 'credits' => 1],
            'faq_accordion' => ['label' => 'FAQ Accordion', 'category' => 'core', 'credits' => 1],
            'image_cta_banner' => ['label' => 'Image CTA Banner', 'category' => 'core', 'credits' => 1],
            'pricing_cards' => ['label' => 'Pricing Cards', 'category' => 'core', 'credits' => 2],
            'team_modern' => ['label' => 'Team Modern', 'category' => 'core', 'credits' => 1],
            'contact_form_modern' => ['label' => 'Contact Form', 'category' => 'core', 'credits' => 1],
            'contact_details' => ['label' => 'Contact Details', 'category' => 'core', 'credits' => 1],
            'location_map' => ['label' => 'Location Map', 'category' => 'core', 'credits' => 1],

            // Growth Collection.
            'hero_background_image' => ['label' => 'Background Image Hero', 'category' => 'growth', 'credits' => 3],
            'hero_editorial_overlay' => ['label' => 'Editorial Overlay Hero', 'category' => 'growth', 'credits' => 3],
            'hero_split_image' => ['label' => 'Split Image Hero', 'category' => 'growth', 'credits' => 3],
            'hero_floating_cards' => ['label' => 'Floating Cards Hero', 'category' => 'growth', 'credits' => 4],
            'process_timeline' => ['label' => 'Process Timeline', 'category' => 'growth', 'credits' => 3],
            'case_studies_grid' => ['label' => 'Case Studies Grid', 'category' => 'growth', 'credits' => 3],
            'jobs_list' => ['label' => 'Jobs List', 'category' => 'growth', 'credits' => 2],
            'events_grid' => ['label' => 'Events Grid', 'category' => 'growth', 'credits' => 3],
            'blog_mini_hero' => ['label' => 'Blog Mini Hero', 'category' => 'growth', 'credits' => 2],
            'blog_hub' => ['label' => 'Blog Hub', 'category' => 'growth', 'credits' => 3],
            'newsletter_cta' => ['label' => 'Newsletter CTA', 'category' => 'growth', 'credits' => 2],
            'latest_resources' => ['label' => 'Latest Resources', 'category' => 'growth', 'credits' => 3],

            // Signature Collection.
            'hero_video_style' => ['label' => 'Video Style Hero', 'category' => 'signature', 'credits' => 5],
            'hero_video_background' => ['label' => 'Video Background Hero', 'category' => 'signature', 'credits' => 5],
        ];
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? ['label' => str($type)->headline()->toString(), 'category' => 'growth', 'credits' => 2];
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
