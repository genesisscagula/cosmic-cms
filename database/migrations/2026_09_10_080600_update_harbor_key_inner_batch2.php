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
            'version' => max(8, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);

        $listings = [
            ['status'=>'FOR SALE','title'=>'Modern Waterfront Villa','location'=>'Punta Engaño, Lapu-Lapu City','beds'=>'4','baths'=>'4','area'=>'320 m²','price'=>'₱28,500,000','image_url'=>'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=90','url'=>'/property-detail'],
            ['status'=>'FOR SALE','title'=>'Contemporary Family Home','location'=>'Talamban, Cebu City','beds'=>'5','baths'=>'4','area'=>'280 m²','price'=>'₱18,750,000','image_url'=>'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=90','url'=>'/property-detail'],
            ['status'=>'FOR SALE','title'=>'Luxury Condo with Ocean View','location'=>'Cebu IT Park, Cebu City','beds'=>'2','baths'=>'2','area'=>'120 m²','price'=>'₱12,900,000','image_url'=>'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=90','url'=>'/property-detail'],
            ['status'=>'FOR SALE','title'=>'Hillside Residence','location'=>'Busay, Cebu City','beds'=>'4','baths'=>'4','area'=>'360 m²','price'=>'₱24,800,000','image_url'=>'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=90','url'=>'/property-detail'],
            ['status'=>'FOR SALE','title'=>'Courtyard Home','location'=>'Banilad, Cebu City','beds'=>'4','baths'=>'3','area'=>'250 m²','price'=>'₱21,500,000','image_url'=>'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1200&q=90','url'=>'/property-detail'],
            ['status'=>'FOR SALE','title'=>'Coastal Penthouse','location'=>'Mactan Newtown, Lapu-Lapu City','beds'=>'3','baths'=>'3','area'=>'210 m²','price'=>'₱19,800,000','image_url'=>'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1200&q=90','url'=>'/property-detail'],
        ];

        $pages = [
            'listings' => [
                'seo' => ['title'=>'Featured Listings | Harbor & Key Realty','description'=>'Explore curated homes for sale across Cebu City, Mactan, and sought-after surrounding communities.','indexable'=>true],
                'blocks' => [
                    'marketplace_harbor_page_hero' => [
                        'eyebrow' => 'HARBOR & KEY · FEATURED LISTINGS',
                        'heading' => 'Exceptional homes. Distinct Cebu addresses.',
                        'text' => 'Explore a curated collection of residences across Cebu City and Mactan, selected for location, design, lifestyle, and long-term value.',
                        'image_url' => 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=2200&q=90',
                        '_content_brief' => 'Premium Harbor & Key listings hero using the homepage deep navy, warm gold, serif display type, and Cebu-specific property positioning.',
                    ],
                    'marketplace_harbor_listings' => [
                        'eyebrow' => 'FEATURED PROPERTIES',
                        'heading' => 'Exceptional homes across Cebu.',
                        'text' => 'A curated collection of homes chosen for location, design, lifestyle, and long-term value.',
                        'items' => $listings,
                        '_content_brief' => 'Use the premium Harbor property-card language: white cards, deep navy, warm-gold accents, Cebu locations, Philippine peso pricing, compact property specs, and clear detail links.',
                    ],
                    'marketplace_harbor_neighborhood' => [
                        'eyebrow' => 'LOCAL CONTEXT',
                        'heading' => 'The right home starts with the right part of Cebu.',
                        'text' => 'Compare waterfront access, city convenience, family-friendly enclaves, and hillside outlooks before choosing the address that fits your plans.',
                        'items' => [
                            ['title'=>'Mactan & Punta Engaño','text'=>'Waterfront residences, resort access, airport convenience, and strong lifestyle appeal.'],
                            ['title'=>'Banilad & Talamban','text'=>'Established residential pockets close to schools, retail, dining, and major city routes.'],
                            ['title'=>'Busay & Cebu Highlands','text'=>'Elevated homes with cooler air, wider outlooks, and a quieter pace above the city.'],
                        ],
                        '_content_brief' => 'Keep this Cebu-local supporting section; full neighborhood visual refinement continues in Harbor inner Batch 4.',
                    ],
                    'marketplace_harbor_contact' => [
                        'eyebrow'=>'PRIVATE VIEWINGS','heading'=>'Found a property worth seeing in person?','text'=>'Tell us which home caught your attention and we will arrange a private viewing around your schedule.','phone'=>'+63 912 345 6789','email'=>'hello@harborandkey.com','address'=>'8F The Waterfront Tower · Lahug, Cebu City 6000','button_label'=>'ARRANGE A VIEWING','button_url'=>'mailto:hello@harborandkey.com','image_url'=>'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1900&q=90',
                        '_content_brief'=>'Premium coastal viewing CTA using the homepage navy/gold design system.',
                    ],
                ],
            ],
            'property-detail' => [
                'seo' => ['title'=>'Modern Waterfront Villa | Harbor & Key Realty','description'=>'Explore a four-bedroom waterfront villa in Punta Engaño, Lapu-Lapu City, Cebu, presented by Harbor & Key Realty.','indexable'=>true],
                'blocks' => [
                    'marketplace_harbor_property' => [
                        'eyebrow'=>'FEATURED PROPERTY','status'=>'FOR SALE','heading'=>'Modern Waterfront Villa','location'=>'Punta Engaño, Lapu-Lapu City, Cebu','text'=>'A refined four-bedroom waterfront residence shaped around natural light, generous entertaining spaces, and a seamless connection to the sea.','image_url'=>'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1800&q=90','gallery'=>['https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1100&q=88','https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1100&q=88','https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1100&q=88'],'price'=>'₱28,500,000','beds'=>'4','baths'=>'4','parking'=>'2','area'=>'320 m²','features'=>['Waterfront outlook','Private pool & terrace','Open-plan living','Chef’s kitchen','Primary suite with balcony','24/7 gated security'],'button_label'=>'ARRANGE A PRIVATE VIEWING','button_url'=>'/contact','secondary_label'=>'BACK TO LISTINGS','secondary_url'=>'/listings',
                        '_content_brief'=>'Premium Cebu property-detail presentation with cinematic lead image, gallery, specs, highlights, peso pricing, and private-viewing conversion.',
                    ],
                    'marketplace_harbor_neighborhood' => [
                        'eyebrow'=>'THE LOCATION','heading'=>'Punta Engaño puts the sea at the center of everyday life.','text'=>'A sought-after Mactan address balancing resort-style coastal living with practical access to the airport, Cebu City, marinas, dining, and leisure destinations.','items'=>[
                            ['title'=>'Waterfront lifestyle','text'=>'Ocean outlooks, resort amenities, marinas, and an easy rhythm close to the shoreline.'],
                            ['title'=>'Connected to the city','text'=>'Convenient access to Mactan-Cebu International Airport and the bridges into Cebu City.'],
                            ['title'=>'Long-term appeal','text'=>'A high-interest corridor for end users, second-home buyers, and lifestyle-led property investment.'],
                        ],
                        '_content_brief'=>'Property-specific Mactan context; full neighborhood visual refinement continues in Batch 4.',
                    ],
                    'marketplace_harbor_contact' => [
                        'eyebrow'=>'PRIVATE INSPECTION','heading'=>'See the waterfront villa at its best.','text'=>'Arrange a private inspection and we will walk you through the residence, its setting, and the practical details behind the opportunity.','phone'=>'+63 912 345 6789','email'=>'hello@harborandkey.com','address'=>'8F The Waterfront Tower · Lahug, Cebu City 6000','button_label'=>'BOOK A PRIVATE VIEWING','button_url'=>'mailto:hello@harborandkey.com','image_url'=>'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1900&q=90',
                        '_content_brief'=>'High-conversion property inspection CTA aligned with Harbor homepage styling.',
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

            foreach (array_values($config['blocks']) as $position => $content) {
                $sparkKey = array_keys($config['blocks'])[$position];
                $atPosition = DB::table('marketplace_template_blocks')
                    ->where('marketplace_template_page_id', $page->id)
                    ->where('sort_order', $position)
                    ->orderBy('id')
                    ->first();

                $payload = [
                    'spark_key' => $sparkKey,
                    'sort_order' => $position,
                    'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'settings' => json_encode(['theme'=>'auto']),
                    'metadata' => json_encode(['marketplace_batch'=>'harbor-inner-batch2','page'=>$slug], JSON_UNESCAPED_SLASHES),
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
