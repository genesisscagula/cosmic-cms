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
        if (! $template) return;

        DB::table('marketplace_templates')->where('id', $template->id)->update([
            'version' => max(10, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);

        $pages = [
            'neighborhoods' => [
                'seo' => [
                    'title' => 'Cebu Neighborhood Guide | Harbor & Key Realty',
                    'description' => 'Explore Mactan, Cebu Business Park, Banilad, Talamban, Busay, Talisay, and other Cebu property locations through lifestyle and local context.',
                    'indexable' => true,
                ],
                'blocks' => [
                    'marketplace_harbor_page_hero' => [
                        'eyebrow' => 'HARBOR & KEY · NEIGHBORHOOD GUIDE',
                        'heading' => 'Find the part of Cebu that fits the life around the home.',
                        'text' => 'Explore coastal, city, established residential, and hillside locations through the everyday details that matter beyond the property itself.',
                        'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2200&q=90',
                        '_content_brief' => 'Premium Harbor neighborhood hero with coastal Cebu mood, navy overlay, gold eyebrow, editorial serif heading, and concise local context.',
                    ],
                    'marketplace_harbor_neighborhood' => [
                        'eyebrow' => 'EXPLORE CEBU',
                        'heading' => 'Six distinct settings. Six different ways to live here.',
                        'text' => 'Use this guide as a starting point for the lifestyle around the property—from waterfront and city-center convenience to established family pockets and cooler hillside addresses.',
                        'items' => [
                            ['title' => 'Mactan & Punta Engaño', 'meta' => 'COASTAL', 'text' => 'Waterfront homes, resort corridors, airport access, and island living with the sea close to everyday life.', 'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1200&q=88'],
                            ['title' => 'Cebu Business Park', 'meta' => 'CITY LIFESTYLE', 'text' => 'Condominium living close to offices, retail, dining, parks, and the center of Cebu City’s business rhythm.', 'image_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1200&q=88'],
                            ['title' => 'Banilad & Talamban', 'meta' => 'ESTABLISHED', 'text' => 'Long-established residential pockets near schools, shopping, dining, and major routes across north Cebu City.', 'image_url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=88'],
                            ['title' => 'Busay & Cebu Highlands', 'meta' => 'HIGHLANDS', 'text' => 'Elevated residences with cooler air, broader outlooks, privacy, and a quieter pace above the city.', 'image_url' => 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=88'],
                            ['title' => 'Talisay City', 'meta' => 'SOUTH CEBU', 'text' => 'Residential communities with access to Cebu South Road Properties, city conveniences, and a more relaxed suburban feel.', 'image_url' => 'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1200&q=88'],
                            ['title' => 'Mactan Newtown', 'meta' => 'RESORT URBAN', 'text' => 'A planned coastal district combining condominiums, hospitality, leisure, and convenient access around Lapu-Lapu City.', 'image_url' => 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1200&q=88'],
                        ],
                        '_content_brief' => 'Full Neighborhood Guide: six image-led Cebu area cards. Preserve Harbor navy/gold overlays, restrained card geometry, serif headings, and responsive 3/2/1-column behavior.',
                    ],
                    'marketplace_harbor_market' => [
                        'eyebrow' => 'HOW TO READ THE LOCATION',
                        'heading' => 'A neighborhood decision is a lifestyle decision first.',
                        'text' => 'We look at the everyday details around a property: airport and business-district access, schools, retail, shoreline, elevation, traffic patterns, nearby development, and the kind of daily rhythm each area supports.',
                        'image_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1600&q=90',
                        'quote' => 'The best address is the one that still makes sense after the property photos stop doing the talking.',
                        '_content_brief' => 'Editorial split section connecting local context to a practical property decision. Avoid hard market-performance claims.',
                    ],
                    'marketplace_harbor_listings' => [
                        'eyebrow' => 'HOMES ACROSS CEBU',
                        'heading' => 'Homes across the places clients ask about most.',
                        'text' => 'A sample of homes across waterfront, city, established residential, and hillside Cebu locations.',
                        'items' => [
                            ['status' => 'FOR SALE', 'title' => 'Modern Waterfront Villa', 'location' => 'Punta Engaño, Lapu-Lapu City', 'beds' => '4', 'baths' => '4', 'area' => '320 m²', 'price' => '₱28,500,000', 'image_url' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=90', 'url' => '/property-detail'],
                            ['status' => 'FOR SALE', 'title' => 'Contemporary Family Home', 'location' => 'Talamban, Cebu City', 'beds' => '5', 'baths' => '4', 'area' => '280 m²', 'price' => '₱18,750,000', 'image_url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=90', 'url' => '/property-detail'],
                            ['status' => 'FOR SALE', 'title' => 'Hillside Residence', 'location' => 'Busay, Cebu City', 'beds' => '4', 'baths' => '4', 'area' => '360 m²', 'price' => '₱24,800,000', 'image_url' => 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=90', 'url' => '/property-detail'],
                        ],
                        '_content_brief' => 'Use three existing Harbor listing cards as location-linked examples at the end of the neighborhood page.',
                    ],
                ],
            ],
            'reviews' => [
                'seo' => [
                    'title' => 'Results & Reviews | Harbor & Key Realty',
                    'description' => 'See the client-experience principles behind Harbor & Key Realty, with transparent demo testimonial content ready to be replaced by verified client reviews.',
                    'indexable' => true,
                ],
                'blocks' => [
                    'marketplace_harbor_page_hero' => [
                        'eyebrow' => 'HARBOR & KEY · RESULTS & REVIEWS',
                        'heading' => 'Property guidance remembered for how clearly it was handled.',
                        'text' => 'A premium property experience is more than a headline result. It is useful advice, thoughtful communication, and knowing what comes next at every stage.',
                        'image_url' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=2200&q=90',
                        '_content_brief' => 'Premium reviews hero using Harbor editorial type, navy overlay, gold eyebrow, and a client-experience rather than exaggerated-results message.',
                    ],
                    'marketplace_harbor_proof' => [
                        'eyebrow' => 'SAMPLE CLIENT STORIES',
                        'heading' => 'What clear, well-managed property guidance can feel like.',
                        'quote' => '',
                        'name' => '',
                        'role' => '',
                        'stats' => [
                            ['value' => '1:1', 'label' => 'personal communication'],
                            ['value' => 'LOCAL', 'label' => 'Cebu market context'],
                            ['value' => 'CLEAR', 'label' => 'next-step guidance'],
                        ],
                        'reviews' => [
                            ['label' => 'DEMO REVIEW', 'quote' => 'We always knew what the next step was. The shortlist stayed focused, the advice was practical, and the whole search felt much less overwhelming.', 'name' => 'Sample buyer', 'role' => 'Cebu City'],
                            ['label' => 'DEMO REVIEW', 'quote' => 'The campaign recommendations were explained clearly, especially around presentation and positioning. We felt informed rather than pressured.', 'name' => 'Sample seller', 'role' => 'Lapu-Lapu City'],
                            ['label' => 'DEMO REVIEW', 'quote' => 'What stood out was the communication. Questions were answered directly and we could make each decision with the right context in front of us.', 'name' => 'Sample investor', 'role' => 'Metro Cebu'],
                        ],
                        'disclaimer' => 'Demo testimonial copy for the Harbor & Key marketplace template. Replace with verified client reviews before publishing a live website.',
                        '_content_brief' => 'Three premium testimonial cards with explicit demo labels/disclaimer. Never present invented reviews or sales outcomes as verified claims.',
                    ],
                    'marketplace_harbor_market' => [
                        'eyebrow' => 'WHAT GOOD ADVICE LOOKS LIKE',
                        'heading' => 'The outcome matters. So does the way you get there.',
                        'text' => 'A property move can carry a lot of decisions at once. Our approach is built around useful context, prompt communication, deliberate negotiation, and keeping the next step understandable from brief through handover.',
                        'image_url' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1600&q=90',
                        'quote' => 'A strong client experience should feel informed, calm, and properly managed—not rushed.',
                        '_content_brief' => 'Grounded client-experience split section. No fabricated price, speed, ranking, or sales-performance statistics.',
                    ],
                    'marketplace_harbor_contact' => [
                        'eyebrow' => 'START WITH A CONVERSATION',
                        'heading' => 'Looking for the same kind of clarity on your next move?',
                        'text' => 'Tell us whether you are buying, selling, or investing and what you need help thinking through. We will start with the property, the location, and the decision in front of you.',
                        'phone' => '+63 912 345 6789',
                        'email' => 'hello@harborandkey.com',
                        'address' => '8F The Waterfront Tower · Lahug, Cebu City 6000',
                        'button_label' => 'BOOK A CONSULTATION',
                        'button_url' => 'mailto:hello@harborandkey.com',
                        'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1900&q=90',
                        '_content_brief' => 'Reviews-page conversion CTA using Harbor coastal image, deep navy overlay, warm-gold action, and the same contact treatment as the other inner pages.',
                    ],
                ],
            ],
        ];

        foreach ($pages as $slug => $config) {
            $page = DB::table('marketplace_template_pages')
                ->where('marketplace_template_id', $template->id)
                ->where('slug', $slug)
                ->first();
            if (! $page) continue;

            DB::table('marketplace_template_pages')->where('id', $page->id)->update([
                'page_style' => 'premium',
                'seo' => json_encode($config['seo'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

            $sparkKeys = array_keys($config['blocks']);
            foreach (array_values($config['blocks']) as $position => $blockContent) {
                $sparkKey = $sparkKeys[$position];
                $atPosition = DB::table('marketplace_template_blocks')
                    ->where('marketplace_template_page_id', $page->id)
                    ->where('sort_order', $position)
                    ->orderBy('id')
                    ->first();

                $payload = [
                    'spark_key' => $sparkKey,
                    'sort_order' => $position,
                    'content' => json_encode($blockContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'settings' => json_encode(['theme' => 'auto']),
                    'metadata' => json_encode(['marketplace_batch' => 'harbor-inner-batch4', 'page' => $slug], JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ];

                if ($atPosition) {
                    DB::table('marketplace_template_blocks')->where('id', $atPosition->id)->update($payload);
                } else {
                    $payload['marketplace_template_page_id'] = $page->id;
                    $payload['created_at'] = now();
                    DB::table('marketplace_template_blocks')->insert($payload);
                }
            }

            DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $page->id)
                ->where('sort_order', '>=', count($config['blocks']))
                ->delete();
        }
    }

    public function down(): void
    {
        // Forward-only Marketplace design iteration.
    }
};
