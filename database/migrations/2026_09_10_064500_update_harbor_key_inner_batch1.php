<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_templates') || ! Schema::hasTable('marketplace_template_pages') || ! Schema::hasTable('marketplace_template_blocks')) return;

        $template = DB::table('marketplace_templates')->where('slug', 'harbor-key-realty')->first();
        if (! $template) return;

        DB::table('marketplace_templates')->where('id', $template->id)->update([
            'version' => max(7, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);

        $about = DB::table('marketplace_template_pages')
            ->where('marketplace_template_id', $template->id)
            ->where('slug', 'about')
            ->first();
        if (! $about) return;

        $blocks = [
            'marketplace_harbor_page_hero' => [
                'eyebrow' => 'HARBOR & KEY · ABOUT',
                'heading' => 'Local knowledge. Personal guidance. A better way to move in Cebu.',
                'text' => 'Rooted in Cebu, we combine local knowledge, considered presentation, and one-to-one guidance for buyers, sellers, and investors.',
                'image_url' => 'https://images.unsplash.com/photo-1494526585095-c41746248156?auto=format&fit=crop&w=2200&q=88',
                '_content_brief' => 'Keep this About hero visually aligned with the Harbor & Key homepage: deep navy image overlay, warm-gold eyebrow, premium serif display type, restrained copy, and Cebu-focused positioning.',
            ],
            'marketplace_harbor_market' => [
                'eyebrow' => 'LOCAL KNOWLEDGE',
                'heading' => 'Cebu is more than a market. It is a collection of distinct places.',
                'text' => 'From the pace of Cebu City to the coastal lifestyle of Mactan, we pair property data with street-level context so each decision fits the way you want to live or invest.',
                'image_url' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1600&q=90',
                'quote' => 'The right property decision starts with understanding the place around it.',
                '_content_brief' => 'Keep this About story as a premium image-and-copy split using the homepage navy, warm-gold, white, and soft-cream design language. Preserve the Cebu-local narrative.',
            ],
            'marketplace_harbor_proof' => [
                'eyebrow' => 'WHY CLIENTS CHOOSE US',
                'heading' => 'A relationship-first approach, backed by thoughtful execution.',
                'quote' => 'The experience should feel clear from the first conversation to the final handover.',
                'name' => 'Harbor & Key',
                'role' => 'Cebu property advisors',
                'stats' => [
                    ['value' => 'CEBU-WIDE', 'label' => 'local market coverage'],
                    ['value' => '1:1', 'label' => 'personal guidance'],
                    ['value' => 'MON–SAT', 'label' => 'client availability'],
                ],
                '_content_brief' => 'Keep this About proof section understated and premium: navy testimonial panel, cream stat cards, serif typography, and warm-gold accents. Do not introduce unrelated colors.',
            ],
            'marketplace_harbor_contact' => [
                'eyebrow' => 'YOUR NEXT MOVE STARTS HERE',
                'heading' => 'Let’s talk about your next move in Cebu.',
                'text' => 'Whether you are buying, selling, investing, or simply planning ahead, start with a useful conversation and a clear next step.',
                'phone' => '+63 912 345 6789',
                'email' => 'hello@harborandkey.com',
                'address' => '8F The Waterfront Tower · Lahug, Cebu City 6000',
                'button_label' => 'BOOK A CONSULTATION',
                'button_url' => 'mailto:hello@harborandkey.com',
                'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1900&q=90',
                '_content_brief' => 'Keep this final About CTA aligned with the homepage coastal CTA: full-width image, deep navy overlay, warm-gold action, compact contact details, and Cebu-focused copy.',
            ],
        ];

        foreach (array_values($blocks) as $position => $content) {
            $sparkKey = array_keys($blocks)[$position];
            $desired = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $about->id)
                ->where('spark_key', $sparkKey)
                ->orderBy('id')
                ->first();
            $atPosition = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $about->id)
                ->where('sort_order', $position)
                ->orderBy('id')
                ->first();

            if ($desired && $atPosition && $desired->id !== $atPosition->id) {
                DB::table('marketplace_template_blocks')->where('id', $atPosition->id)->delete();
                $atPosition = null;
            }

            $payload = [
                'spark_key' => $sparkKey,
                'sort_order' => $position,
                'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'settings' => json_encode(['theme' => 'auto']),
                'metadata' => json_encode(['marketplace_batch' => 'harbor-inner-batch1', 'page' => 'about'], JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if ($desired) {
                DB::table('marketplace_template_blocks')->where('id', $desired->id)->update($payload);
            } elseif ($atPosition) {
                DB::table('marketplace_template_blocks')->where('id', $atPosition->id)->update($payload);
            } else {
                $payload['marketplace_template_page_id'] = $about->id;
                $payload['created_at'] = now();
                DB::table('marketplace_template_blocks')->insert($payload);
            }
        }

        DB::table('marketplace_template_blocks')
            ->where('marketplace_template_page_id', $about->id)
            ->where('sort_order', '>=', count($blocks))
            ->delete();
    }

    public function down(): void
    {
        // Forward-only Marketplace design iteration.
    }
};
