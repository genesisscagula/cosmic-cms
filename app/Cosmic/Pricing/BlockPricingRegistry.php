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

            'content_grid_classic' => ['label' => 'Post Grid Classic', 'category' => 'growth', 'credits' => 30],
            'content_grid_editorial' => ['label' => 'Post Grid Editorial', 'category' => 'growth', 'credits' => 30],
            'content_grid_compact' => ['label' => 'Post Grid Compact', 'category' => 'growth', 'credits' => 30],
            'content_featured_entry' => ['label' => 'Featured Post', 'category' => 'growth', 'credits' => 30],
            'content_latest_entries' => ['label' => 'Latest Posts', 'category' => 'growth', 'credits' => 30],
            'content_events_grid' => ['label' => 'Upcoming Events', 'category' => 'growth', 'credits' => 30],
            'events_grid' => ['label' => 'Events Grid', 'category' => 'growth', 'credits' => 30],
            'blog_mini_hero' => ['label' => 'Blog Mini Hero', 'category' => 'growth', 'credits' => 20],
            'mini_hero_minimal' => ['label' => 'Mini Hero Minimal', 'category' => 'growth', 'credits' => 20],
            'mini_hero_split' => ['label' => 'Mini Hero Split Image', 'category' => 'growth', 'credits' => 20],
            'mini_hero_promo' => ['label' => 'Mini Hero Promo', 'category' => 'growth', 'credits' => 20],
            'blog_hub' => ['label' => 'Blog Hub', 'category' => 'growth', 'credits' => 30],
            'newsletter_cta' => ['label' => 'Newsletter CTA', 'category' => 'growth', 'credits' => 20],
            'latest_resources' => ['label' => 'Latest Resources', 'category' => 'growth', 'credits' => 30],

            // Commerce Collection — reusable live catalog Sparks.
            'commerce_product_grid' => ['label' => 'Product Grid', 'category' => 'growth', 'credits' => 30],
            'commerce_catalog_grid' => ['label' => 'Shop Catalog Grid', 'category' => 'growth', 'credits' => 30],
            'commerce_catalog_editorial' => ['label' => 'Shop Catalog Editorial', 'category' => 'growth', 'credits' => 40],
            'commerce_catalog_compact' => ['label' => 'Shop Catalog Compact', 'category' => 'growth', 'credits' => 30],
            'commerce_categories' => ['label' => 'Shop Categories', 'category' => 'growth', 'credits' => 20],
            'commerce_product_gallery' => ['label' => 'Product Gallery', 'category' => 'growth', 'credits' => 30],
            'commerce_price' => ['label' => 'Product Price', 'category' => 'growth', 'credits' => 20],
            'commerce_variation_selector' => ['label' => 'Variation Selector', 'category' => 'growth', 'credits' => 30],
            'commerce_related_products' => ['label' => 'Related Products', 'category' => 'growth', 'credits' => 30],
            'commerce_featured_products' => ['label' => 'Featured Products', 'category' => 'growth', 'credits' => 30],
            'commerce_featured_collection' => ['label' => 'Featured Collection', 'category' => 'growth', 'credits' => 30],
            'commerce_promo_split' => ['label' => 'Promo Split Banner', 'category' => 'growth', 'credits' => 20],
            'commerce_benefits_strip' => ['label' => 'Benefits / Trust Strip', 'category' => 'growth', 'credits' => 20],
            'commerce_mini_cart' => ['label' => 'Mini Cart Shell', 'category' => 'growth', 'credits' => 30],
            'commerce_cart_classic' => ['label' => 'Cart Classic', 'category' => 'growth', 'credits' => 30],
            'commerce_cart_split' => ['label' => 'Cart Split Summary', 'category' => 'growth', 'credits' => 40],
            'commerce_cart_compact' => ['label' => 'Cart Compact', 'category' => 'growth', 'credits' => 30],
            'commerce_checkout_classic' => ['label' => 'Checkout Classic', 'category' => 'growth', 'credits' => 30],
            'commerce_checkout_split' => ['label' => 'Checkout Split', 'category' => 'growth', 'credits' => 40],
            'commerce_checkout_express' => ['label' => 'Checkout Express', 'category' => 'growth', 'credits' => 30],

            // Signature Collection.
            'hero_split_editorial' => ['label' => 'Hero Split Editorial', 'category' => 'signature', 'credits' => 60],
            'hero_floating_glass' => ['label' => 'Floating Glass Hero', 'category' => 'signature', 'credits' => 70],
            'hero_saas_dashboard' => ['label' => 'SaaS Dashboard Hero', 'category' => 'signature', 'credits' => 75],
            'hero_luxury_fullscreen' => ['label' => 'Luxury Fullscreen Hero', 'category' => 'signature', 'credits' => 80],
            'hero_video_premium' => ['label' => 'Video Hero Premium', 'category' => 'signature', 'credits' => 85],
            'hero_ai_conversation' => ['label' => 'AI Conversation Hero', 'category' => 'signature', 'credits' => 90],
            'hero_agency_showcase' => ['label' => 'Agency Showcase Hero', 'category' => 'signature', 'credits' => 95],
            'hero_bento_premium' => ['label' => 'Bento Hero', 'category' => 'signature', 'credits' => 100],
            'services_bento_premium' => ['label' => 'Bento Services Premium', 'category' => 'signature', 'credits' => 105],
            'services_pricing_comparison' => ['label' => 'Pricing Comparison Premium', 'category' => 'signature', 'credits' => 110],
            'services_feature_comparison' => ['label' => 'Feature Comparison Premium', 'category' => 'signature', 'credits' => 115],
            'services_hover_cards' => ['label' => 'Hover Cards Premium', 'category' => 'signature', 'credits' => 120],
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
