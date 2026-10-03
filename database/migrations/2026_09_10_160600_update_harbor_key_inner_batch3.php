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
            'version' => max(9, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);

        $pages = [
            'buyers' => [
                'seo' => [
                    'title' => 'For Buyers | Harbor & Key Realty',
                    'description' => 'Buy a home in Cebu with a clear plan, curated local guidance, purposeful inspections, and support from search through settlement.',
                    'indexable' => true,
                ],
                'blocks' => [
                    'marketplace_harbor_page_hero' => [
                        'eyebrow' => 'HARBOR & KEY · FOR BUYERS',
                        'heading' => 'Buy with clarity. Move with confidence.',
                        'text' => 'From the first shortlist to the final handover, we help you make sense of Cebu’s neighborhoods, property choices, timing, and negotiations without adding more noise.',
                        'image_url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=2200&q=90',
                        '_content_brief' => 'Premium Harbor buyer hero: deep navy overlay, warm-gold eyebrow, elegant serif heading, restrained Cebu-focused copy, and the same visual rhythm as the homepage.',
                    ],
                    'marketplace_harbor_paths' => [
                        'eyebrow' => 'THE BUYER JOURNEY',
                        'heading' => 'A considered path from brief to keys.',
                        'text' => 'Good buying decisions start before the first inspection. We build the search around your priorities, then keep each next step clear as the right options emerge.',
                        'cta_label' => 'START YOUR PROPERTY SEARCH',
                        'cta_url' => '/contact',
                        'items' => [
                            ['title' => 'Get purchase-ready', 'text' => 'Clarify budget, finance readiness, timing, must-haves, and the compromises you are comfortable making.'],
                            ['title' => 'Curate the shortlist', 'text' => 'Focus the search on Cebu locations and properties that genuinely fit your lifestyle, plans, and long-term value goals.'],
                            ['title' => 'Inspect with purpose', 'text' => 'Compare condition, setting, access, pricing context, and practical due diligence before emotion takes over.'],
                            ['title' => 'Offer to handover', 'text' => 'Navigate the offer, negotiation, conditions, documentation, coordination, and final handover with a clear line of communication.'],
                        ],
                        '_content_brief' => 'Buyer-specific four-step process. Keep the navy/gold Harbor visual system and premium restrained tone.',
                    ],
                    'marketplace_harbor_neighborhood' => [
                        'eyebrow' => 'WHERE TO LOOK',
                        'heading' => 'Choose the Cebu address that fits the life around the home.',
                        'text' => 'The right property is also about commute, schools, shoreline, city access, weekend rhythm, and how the area may serve you years from now.',
                        'items' => [
                            ['title' => 'Mactan & Punta Engaño', 'text' => 'Waterfront residences, resort access, airport convenience, and a strong coastal lifestyle close to the sea.'],
                            ['title' => 'Banilad & Talamban', 'text' => 'Established residential areas near schools, retail, dining, business districts, and major Cebu City routes.'],
                            ['title' => 'Busay & Cebu Highlands', 'text' => 'Elevated homes with cooler air, wider outlooks, more privacy, and a quieter pace above the city.'],
                        ],
                        '_content_brief' => 'Buyer-focused Cebu neighborhood context; full Neighborhood Guide expansion continues in Batch 4.',
                    ],
                    'marketplace_harbor_faq' => [
                        'eyebrow' => 'BUYER QUESTIONS',
                        'heading' => 'Useful answers before you make an offer.',
                        'text' => 'We cover the practical details early so the buying process feels more deliberate and less reactive.',
                        'items' => [
                            ['q' => 'When should I arrange financing?', 'a' => 'Before serious inspections whenever possible. Knowing your comfortable range makes the shortlist sharper and helps you move decisively when the right property appears.'],
                            ['q' => 'Can you help compare different Cebu areas?', 'a' => 'Yes. We look beyond the listing itself and discuss access, lifestyle, nearby amenities, local demand, and how each location fits your priorities.'],
                            ['q' => 'What should I look for during an inspection?', 'a' => 'Condition, layout, natural light, surrounding development, access, building or subdivision rules, and any details that may affect future cost or usability.'],
                            ['q' => 'What happens after an offer is accepted?', 'a' => 'We keep communication moving around conditions, documents, timing, inspections, and the handover to your legal and finance professionals through settlement.'],
                        ],
                        '_content_brief' => 'Buyer FAQ styled in the same premium editorial system. Keep answers practical and avoid legal or finance guarantees.',
                    ],
                    'marketplace_harbor_contact' => [
                        'eyebrow' => 'START YOUR SEARCH',
                        'heading' => 'Tell us what the right home needs to feel like.',
                        'text' => 'Share your preferred areas, budget range, timing, and must-haves. We will help turn a broad Cebu property search into a focused shortlist.',
                        'phone' => '+63 912 345 6789',
                        'email' => 'hello@harborandkey.com',
                        'address' => '8F The Waterfront Tower · Lahug, Cebu City 6000',
                        'button_label' => 'BOOK A BUYER CONSULTATION',
                        'button_url' => 'mailto:hello@harborandkey.com',
                        'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1900&q=90',
                        '_content_brief' => 'Buyer conversion CTA aligned with the Harbor homepage coastal image, deep navy overlay, warm-gold button, and compact contact details.',
                    ],
                ],
            ],
            'sellers' => [
                'seo' => [
                    'title' => 'For Sellers | Harbor & Key Realty',
                    'description' => 'Sell your Cebu property with thoughtful pricing, considered presentation, a focused campaign, and clear guidance from appraisal through settlement.',
                    'indexable' => true,
                ],
                'blocks' => [
                    'marketplace_harbor_page_hero' => [
                        'eyebrow' => 'HARBOR & KEY · FOR SELLERS',
                        'heading' => 'Sell with a strategy built around your property.',
                        'text' => 'Strong campaigns begin with the right positioning. We combine local market context, thoughtful presentation, clear communication, and disciplined negotiation from appraisal to settlement.',
                        'image_url' => 'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=2200&q=90',
                        '_content_brief' => 'Premium Harbor seller hero matching the homepage: cinematic property image, navy overlay, gold eyebrow, serif type, and concise Cebu-market positioning.',
                    ],
                    'marketplace_harbor_market' => [
                        'eyebrow' => 'POSITIONING THE SALE',
                        'heading' => 'Price, presentation, and timing should tell one clear story.',
                        'text' => 'We assess recent comparable activity, current competing stock, buyer expectations, the property’s strongest features, and the conditions around your preferred selling window before shaping the campaign.',
                        'image_url' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1600&q=90',
                        'quote' => 'The goal is not simply to put a property online. It is to give the right buyers a clear reason to act.',
                        '_content_brief' => 'Seller strategy split section using Harbor cream, white, navy, gold, and serif typography. Keep claims grounded and advisory rather than guaranteed.',
                    ],
                    'marketplace_harbor_paths' => [
                        'eyebrow' => 'THE SELLER JOURNEY',
                        'heading' => 'A focused campaign from appraisal to handover.',
                        'text' => 'Every property has a different audience, selling point, and timing. We shape the campaign around those specifics instead of forcing the same formula onto every listing.',
                        'cta_label' => 'REQUEST AN APPRAISAL',
                        'cta_url' => '/contact',
                        'items' => [
                            ['title' => 'Appraise & position', 'text' => 'Review comparable activity, competing listings, property strengths, timing, and the pricing position most likely to support your objectives.'],
                            ['title' => 'Prepare & present', 'text' => 'Prioritize the improvements, styling, photography, copy, and details that make the property easier for buyers to understand.'],
                            ['title' => 'Launch & qualify', 'text' => 'Bring the property to market with focused presentation, manage enquiries and inspections, and keep feedback useful rather than noisy.'],
                            ['title' => 'Negotiate & settle', 'text' => 'Compare offers in context, negotiate deliberately, coordinate agreed conditions, and keep the path to settlement organized.'],
                        ],
                        '_content_brief' => 'Seller-specific four-step campaign process using the same premium navy/gold process language as the buyer page.',
                    ],
                    'marketplace_harbor_proof' => [
                        'eyebrow' => 'A CALMER SALE EXPERIENCE',
                        'heading' => 'Clear advice when every decision affects the campaign.',
                        'quote' => 'We want sellers to understand the reasoning behind each recommendation, not feel pushed into the next step.',
                        'name' => 'Harbor & Key',
                        'role' => 'Cebu property advisors',
                        'stats' => [
                            ['value' => 'TAILORED', 'label' => 'campaign strategy'],
                            ['value' => '1:1', 'label' => 'seller communication'],
                            ['value' => 'END-TO-END', 'label' => 'campaign coordination'],
                        ],
                        '_content_brief' => 'Seller proof section must stay grounded: emphasize process, communication, and tailored execution rather than fabricated sales statistics.',
                    ],
                    'marketplace_harbor_contact' => [
                        'eyebrow' => 'CONFIDENTIAL APPRAISAL',
                        'heading' => 'Start with a clearer view of where your property sits today.',
                        'text' => 'Tell us about the property, your preferred timing, and what a successful move looks like. We will begin with a practical conversation about positioning and next steps.',
                        'phone' => '+63 912 345 6789',
                        'email' => 'hello@harborandkey.com',
                        'address' => '8F The Waterfront Tower · Lahug, Cebu City 6000',
                        'button_label' => 'REQUEST AN APPRAISAL',
                        'button_url' => 'mailto:hello@harborandkey.com',
                        'image_url' => 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1900&q=90',
                        '_content_brief' => 'Seller appraisal CTA matching Harbor homepage coastal/premium styling and avoiding unsupported valuation promises.',
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
                    'settings' => json_encode(['theme' => 'auto']),
                    'metadata' => json_encode(['marketplace_batch' => 'harbor-inner-batch3', 'page' => $slug], JSON_UNESCAPED_SLASHES),
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
