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

        $communities = [
            'eyebrow' => 'EXPLORE PRIME LOCATIONS',
            'heading' => 'Featured Communities',
            'view_all_label' => 'VIEW ALL COMMUNITIES',
            'view_all_url' => '/neighborhoods',
            'items' => [
                ['title' => 'Mactan Island', 'subtitle' => 'Resort living by the sea', 'url' => '/neighborhoods', 'image_url' => 'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1100&q=88'],
                ['title' => 'Cebu Business Park', 'subtitle' => 'The city’s premier lifestyle hub', 'url' => '/neighborhoods', 'image_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1100&q=88'],
                ['title' => 'Talisay City', 'subtitle' => 'Peaceful living, close to everything', 'url' => '/neighborhoods', 'image_url' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1100&q=88'],
                ['title' => 'Bantayan Island', 'subtitle' => 'Island life, unspoiled beauty', 'url' => '/neighborhoods', 'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1100&q=88'],
            ],
            '_content_brief' => 'Keep this Harbor & Key homepage community section as four premium image cards for prime Cebu locations. Luna may rewrite places, captions, links, and media while preserving the purchased navy, white, and warm-gold design language.',
        ];

        $solutions = [
            'eyebrow' => 'BUY. SELL. RENT.',
            'heading' => 'Solutions for Every Move',
            'items' => [
                ['icon' => 'buy', 'title' => 'Buy a Home', 'text' => 'Find your dream home with confidence. We’ll guide you every step of the way.', 'button_label' => 'Explore Homes', 'button_url' => '/buyers'],
                ['icon' => 'sell', 'title' => 'Sell Your Property', 'text' => 'Get top value for your property with our proven marketing and local expertise.', 'button_label' => 'List Your Property', 'button_url' => '/sellers'],
                ['icon' => 'rent', 'title' => 'Rent with Ease', 'text' => 'Discover quality rentals that fit your lifestyle and budget.', 'button_label' => 'View Rentals', 'button_url' => '/listings'],
            ],
            '_content_brief' => 'Keep this Harbor & Key homepage section as three balanced Buy, Sell, and Rent service cards with gold line icons and understated white cards. Luna may personalize copy and links without switching to generic Sparks.',
        ];

        $process = [
            'eyebrow' => 'HOW WE HELP YOU',
            'heading' => 'A Seamless Path to Your Perfect Home',
            'items' => [
                ['number' => '01', 'title' => 'Discover', 'text' => 'Tell us what you’re looking for and your must-haves.'],
                ['number' => '02', 'title' => 'Explore', 'text' => 'We’ll curate the best options that match your needs.'],
                ['number' => '03', 'title' => 'Decide', 'text' => 'Tour, compare, and choose the one that feels right.'],
                ['number' => '04', 'title' => 'Own', 'text' => 'We handle the details from offer to closing.'],
            ],
            '_content_brief' => 'Keep this Harbor & Key homepage process as a four-step horizontal journey with gold numbered circles, a fine connector line, and centered premium typography. Luna may rewrite the steps but should preserve this layout pattern.',
        ];

        $upsert = function (string $sparkKey, int $sortOrder, array $content) use ($home): void {
            $desired = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $home->id)
                ->where('spark_key', $sparkKey)
                ->orderBy('id')
                ->first();

            $atSort = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $home->id)
                ->where('sort_order', $sortOrder)
                ->first();

            if ($desired && $atSort && $desired->id !== $atSort->id) {
                DB::table('marketplace_template_blocks')->where('id', $atSort->id)->delete();
                $atSort = null;
            }

            $payload = [
                'spark_key' => $sparkKey,
                'sort_order' => $sortOrder,
                'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'settings' => json_encode(['theme' => 'auto']),
                'metadata' => json_encode(['marketplace_batch' => 'harbor-home-batch3', 'home_only' => true]),
                'updated_at' => now(),
            ];

            if ($desired) {
                DB::table('marketplace_template_blocks')->where('id', $desired->id)->update($payload);
                return;
            }

            if ($atSort) {
                DB::table('marketplace_template_blocks')->where('id', $atSort->id)->update($payload);
                return;
            }

            $payload['marketplace_template_page_id'] = $home->id;
            $payload['created_at'] = now();
            DB::table('marketplace_template_blocks')->insert($payload);
        };

        $upsert('marketplace_harbor_home_communities', 3, $communities);
        $upsert('marketplace_harbor_home_solutions', 4, $solutions);
        $upsert('marketplace_harbor_home_process', 5, $process);

        DB::table('marketplace_templates')->where('id', $template->id)->update([
            'version' => max(4, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Marketplace design iterations are intentionally forward-only catalog content migrations.
    }
};
