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

        $home = DB::table('marketplace_template_pages')
            ->where('marketplace_template_id', $template->id)
            ->where('is_home', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
        if (! $home) {
            return;
        }

        $listingContent = [
            'eyebrow' => 'FEATURED PROPERTIES',
            'heading' => 'Exceptional Homes.',
            'accent_heading' => 'Extraordinary Living.',
            'view_all_label' => 'VIEW ALL PROPERTIES',
            'view_all_url' => '/listings',
            'items' => [
                ['status' => 'FOR SALE', 'title' => 'Modern Waterfront Villa', 'location' => 'Lapu-Lapu City, Cebu', 'beds' => '4', 'baths' => '4', 'area' => '320 m²', 'price' => '₱28,500,000', 'image_url' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=90'],
                ['status' => 'FOR SALE', 'title' => 'Contemporary Family Home', 'location' => 'Talamban, Cebu City', 'beds' => '5', 'baths' => '4', 'area' => '280 m²', 'price' => '₱18,750,000', 'image_url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=90'],
                ['status' => 'FOR SALE', 'title' => 'Luxury Condo with Ocean View', 'location' => 'IT Park, Cebu City', 'beds' => '2', 'baths' => '2', 'area' => '120 m²', 'price' => '₱12,900,000', 'image_url' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=90'],
                ['status' => 'VIEW RENT', 'title' => 'Elegant House for Rent', 'location' => 'Banilad, Cebu City', 'beds' => '4', 'baths' => '3', 'area' => '250 m²', 'price' => '₱85,000 /month', 'image_url' => 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=90'],
            ],
            '_content_brief' => 'Keep this Harbor & Key homepage section as a premium four-card Cebu property showcase. Luna may rewrite listing details and media for the customer, but must preserve the navy, white, warm-gold layout language.',
        ];

        $aboutContent = [
            'eyebrow' => 'ABOUT HARBOR & KEY REALTY',
            'heading' => 'Trusted Local Experts.',
            'accent_heading' => 'Dedicated to You.',
            'text' => 'With deep roots in Cebu’s most desirable communities, we deliver personalized real estate solutions backed by integrity, expertise, and a passion for people.',
            'image_url' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1500&q=90',
            'points' => [
                'In-depth local market knowledge',
                'Personalized service tailored to your goals',
                'Commitment to transparency & results',
            ],
            'button_label' => 'LEARN MORE ABOUT US',
            'button_url' => '/about',
            '_content_brief' => 'Keep this Harbor & Key homepage about section as a premium image-and-copy split. Luna may personalize company story, bullet points, CTA, and media while preserving the purchased design system.',
        ];

        $listing = DB::table('marketplace_template_blocks')
            ->where('marketplace_template_page_id', $home->id)
            ->whereIn('spark_key', ['marketplace_harbor_home_listings', 'marketplace_harbor_listings'])
            ->orderByRaw("CASE WHEN spark_key = 'marketplace_harbor_home_listings' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->first();

        if (! $listing) {
            $listing = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $home->id)
                ->where('sort_order', 1)
                ->orderBy('id')
                ->first();
        }

        if ($listing) {
            DB::table('marketplace_template_blocks')->where('id', $listing->id)->update([
                'spark_key' => 'marketplace_harbor_home_listings',
                'content' => json_encode($listingContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'metadata' => json_encode(['marketplace_batch' => 'harbor-home-batch2', 'home_only' => true]),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('marketplace_template_blocks')->insert([
                'marketplace_template_page_id' => $home->id,
                'spark_key' => 'marketplace_harbor_home_listings',
                'sort_order' => 1,
                'content' => json_encode($listingContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'settings' => json_encode(['theme' => 'auto']),
                'metadata' => json_encode(['marketplace_batch' => 'harbor-home-batch2', 'home_only' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $about = DB::table('marketplace_template_blocks')
            ->where('marketplace_template_page_id', $home->id)
            ->whereIn('spark_key', ['marketplace_harbor_home_about', 'marketplace_harbor_market'])
            ->orderByRaw("CASE WHEN spark_key = 'marketplace_harbor_home_about' THEN 0 ELSE 1 END")
            ->orderBy('sort_order')
            ->first();

        if (! $about) {
            $about = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $home->id)
                ->where('sort_order', 2)
                ->orderBy('id')
                ->first();
        }

        if ($about) {
            DB::table('marketplace_template_blocks')->where('id', $about->id)->update([
                'spark_key' => 'marketplace_harbor_home_about',
                'content' => json_encode($aboutContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'metadata' => json_encode(['marketplace_batch' => 'harbor-home-batch2', 'home_only' => true]),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('marketplace_template_blocks')->insert([
                'marketplace_template_page_id' => $home->id,
                'spark_key' => 'marketplace_harbor_home_about',
                'sort_order' => 2,
                'content' => json_encode($aboutContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'settings' => json_encode(['theme' => 'auto']),
                'metadata' => json_encode(['marketplace_batch' => 'harbor-home-batch2', 'home_only' => true]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('marketplace_templates')->where('id', $template->id)->update([
            'version' => max(3, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Marketplace design iterations are content migrations; do not roll back customer-visible catalog content.
    }
};
