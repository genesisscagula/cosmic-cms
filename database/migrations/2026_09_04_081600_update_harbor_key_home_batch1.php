<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_templates') || ! Schema::hasTable('marketplace_template_pages') || ! Schema::hasTable('marketplace_template_blocks')) {
            return;
        }

        $template = DB::table('marketplace_templates')->where('slug', 'harbor-key-realty')->first();
        if (! $template) {
            return;
        }

        $design = [
            'key' => 'harbor-key-luxury-waterfront', 'header_mode' => 'dark',
            'page' => '#ffffff', 'heading' => '#0d2340', 'body' => '#68727e',
            'primary' => '#071f3d', 'primary_hover' => '#0b2b50', 'accent' => '#c6a052',
            'surface' => '#ffffff', 'surface_alt' => '#f7f5f1', 'border' => '#e1e4e6',
            'on_primary' => '#ffffff', 'button_primary' => '#c6a052', 'button_text' => '#ffffff',
            'font_heading' => 'Inter, ui-sans-serif, system-ui, sans-serif',
            'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
            'font_display' => 'Georgia, Times New Roman, serif',
            'components' => [
                'button_radius' => 4, 'card_radius' => 6, 'image_radius' => 0,
                'section_spacing' => 96, 'content_width' => 1536,
                'header_variant' => 'harbor_luxury', 'footer_variant' => 'harbor_coastal',
                'hero_style' => 'luxury_waterfront_overlay', 'card_style' => 'property_listing_grid',
                'accent_usage' => 'navy_gold', 'surface_language' => 'navy_white_warm_gold',
            ],
        ];

        $themeSettings = [
            'primary' => 'coastal', 'secondary' => 'white', 'tertiary' => 'surface', 'auto' => false,
            'marketplace_template' => true, 'marketplace_design' => $design, 'components' => $design['components'],
        ];

        $header = [
            'type' => 'glassmorphism_header', 'logo_text' => 'HARBOR & KEY REALTY',
            'cta_label' => 'BOOK A CONSULTATION', 'cta_url' => '/contact', 'menu' => [],
            'phone_enabled' => true, 'phone_text' => '+63 912 345 6789',
            'overlay' => false, 'custom_shell_mode' => true, 'allow_light_logo_filter' => true,
            'custom_style' => [
                'background_color' => '#071f3d', 'text_color' => '#ffffff', 'nav_color' => '#ffffff',
                'cta_background' => '#c6a052', 'cta_color' => '#ffffff', 'cta_radius' => 3,
                'height' => 80, 'padding_x' => 78, 'nav_size' => 12, 'logo_tone' => 'light',
            ],
            'marketplace_variant' => 'harbor_luxury',
        ];

        DB::table('marketplace_templates')->where('id', $template->id)->update([
            'theme_settings' => json_encode($themeSettings, JSON_UNESCAPED_SLASHES),
            'global_header' => json_encode($header, JSON_UNESCAPED_SLASHES),
            'version' => max(2, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);

        $home = DB::table('marketplace_template_pages')
            ->where('marketplace_template_id', $template->id)
            ->where('is_home', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
        if (! $home) {
            return;
        }

        $hero = [
            'eyebrow' => 'PREMIUM REAL ESTATE, PERSONALIZED FOR YOU',
            'heading' => 'Find Your Dream Home.',
            'accent_heading' => 'Key to Your New Harbor.',
            'text' => 'Harbor & Key Realty connects you to exceptional properties and experiences. Let us help you find a place to live, invest, and thrive.',
            'primary_label' => 'BROWSE PROPERTIES', 'primary_url' => '/listings',
            'secondary_label' => 'WATCH VIDEO', 'secondary_url' => '#video',
            'image_url' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=2200&q=90',
            'location_label' => 'LOCATION', 'location_placeholder' => 'City, Neighborhood, or ZIP',
            'property_type_label' => 'PROPERTY TYPE', 'property_type_value' => 'All Types',
            'price_range_label' => 'PRICE RANGE', 'price_range_value' => 'Any Price',
            'beds_label' => 'BEDS', 'beds_value' => 'Any', 'baths_label' => 'BATHS', 'baths_value' => 'Any',
            'search_label' => 'SEARCH PROPERTIES',
            'benefits' => [
                ['icon' => 'diamond', 'title' => 'Premium Properties', 'text' => 'Handpicked, high-quality listings you can trust.'],
                ['icon' => 'key', 'title' => 'Expert Guidance', 'text' => 'Local expertise and dedicated support every step of the way.'],
                ['icon' => 'shield', 'title' => 'Trusted & Transparent', 'text' => 'Honest service and clear communication always.'],
                ['icon' => 'home', 'title' => 'Invest in Your Future', 'text' => 'Smart real estate choices for long-term value.'],
            ],
            '_content_brief' => 'Create a premium Harbor & Key Realty homepage with a luxury waterfront hero, property search controls, and a concise trust strip. Preserve the navy, white, and warm-gold art direction.',
        ];

        $firstBlock = DB::table('marketplace_template_blocks')
            ->where('marketplace_template_page_id', $home->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        if ($firstBlock) {
            DB::table('marketplace_template_blocks')->where('id', $firstBlock->id)->update([
                'spark_key' => 'marketplace_harbor_hero',
                'content' => json_encode($hero, JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('marketplace_template_blocks')->insert([
                'marketplace_template_page_id' => $home->id,
                'spark_key' => 'marketplace_harbor_hero',
                'sort_order' => 0,
                'content' => json_encode($hero, JSON_UNESCAPED_SLASHES),
                'settings' => null,
                'metadata' => json_encode(['marketplace_batch' => 'harbor-home-batch1']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Marketplace design iterations are content migrations; do not roll back customer-visible catalog content.
    }
};
