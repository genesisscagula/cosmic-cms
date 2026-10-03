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
            'version' => max(11, (int) ($template->version ?? 1)),
            'updated_at' => now(),
        ]);

        $pages = [
            'faq' => [
                'seo' => [
                    'title' => 'Property FAQ | Harbor & Key Realty',
                    'description' => 'Practical answers about buying, selling, inspections, offers, appraisals, campaigns, and property decisions across Cebu.',
                    'indexable' => true,
                ],
                'blocks' => [
                    'marketplace_harbor_page_hero' => [
                        'eyebrow' => 'HARBOR & KEY · PROPERTY FAQ',
                        'heading' => 'Clear answers before the next property decision.',
                        'text' => 'Buying, selling, inspecting, or planning ahead? Start with the practical questions that help you move through Cebu property decisions with better context.',
                        'image_url' => 'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=2200&q=90',
                        '_content_brief' => 'Premium Harbor FAQ hero: navy overlay, warm-gold eyebrow, editorial serif heading, practical Cebu property tone.',
                    ],
                    'marketplace_harbor_faq' => [
                        'eyebrow' => 'PROPERTY QUESTIONS',
                        'heading' => 'The questions worth asking early.',
                        'text' => 'Every transaction is different, but these are the practical topics we regularly help clients think through before a viewing, offer, appraisal, or campaign.',
                        'items' => [
                            ['q' => 'Where should I start if I want to buy in Cebu?', 'a' => 'Begin with your comfortable budget, timing, preferred areas, and non-negotiables. From there, we can narrow the search around lifestyle, access, property type, and realistic options.'],
                            ['q' => 'When should I arrange financing?', 'a' => 'Before serious inspections whenever possible. A clear financing range helps sharpen the shortlist and lets you respond more confidently when the right property appears.'],
                            ['q' => 'What should I look for during a property inspection?', 'a' => 'Look beyond finishes. Consider condition, layout, natural light, access, surrounding development, building or subdivision rules, and details that may affect future cost or usability.'],
                            ['q' => 'What happens after an offer is accepted?', 'a' => 'The next stage usually involves documents, agreed conditions, due diligence, financing or payment coordination, and handover. Your legal and finance advisers should confirm requirements for your specific transaction.'],
                            ['q' => 'How do you approach a seller appraisal?', 'a' => 'We look at recent comparable activity, current competing stock, buyer demand, property condition, location, presentation, and the timing of your intended sale.'],
                            ['q' => 'What does a property campaign usually include?', 'a' => 'The mix depends on the property, but it may include professional presentation, listing assets, digital promotion, buyer follow-up, viewings, feedback, and negotiation strategy.'],
                            ['q' => 'Can you arrange private property viewings?', 'a' => 'Yes. Private inspections can be coordinated around current property availability and seller access, with the key property details shared before the appointment.'],
                            ['q' => 'Do you only work in Cebu City?', 'a' => 'No. Harbor & Key is positioned around Metro Cebu and nearby property markets, including Lapu-Lapu, Mactan, Talisay, and selected surrounding areas.'],
                        ],
                        '_content_brief' => 'Full Harbor property FAQ. Keep answers practical, locally relevant, and clear that legal/finance requirements depend on the client and transaction.',
                    ],
                    'marketplace_harbor_paths' => [
                        'eyebrow' => 'HOW WE CAN HELP',
                        'heading' => 'Bring us the question. We will help clarify the next step.',
                        'text' => 'Whether the decision is about a search, sale, viewing, or local area, the goal is the same: reduce noise and make the next action useful.',
                        'cta_label' => 'ASK A PROPERTY QUESTION',
                        'cta_url' => '/contact',
                        'items' => [
                            ['title' => 'Buying a property', 'text' => 'Refine the brief, compare locations, prepare for inspections, and understand the offer process.'],
                            ['title' => 'Selling a property', 'text' => 'Discuss appraisal, positioning, presentation, campaign timing, and negotiation strategy.'],
                            ['title' => 'Arranging a viewing', 'text' => 'Check current availability, property details, access, and a suitable inspection schedule.'],
                            ['title' => 'Understanding an area', 'text' => 'Compare Cebu locations through lifestyle, access, development, and the kind of property each area offers.'],
                        ],
                        '_content_brief' => 'FAQ-page service paths connecting common questions to existing Harbor buyer, seller, viewing, and neighborhood journeys.',
                    ],
                    'marketplace_harbor_contact' => [
                        'eyebrow' => 'STILL HAVE A QUESTION?',
                        'heading' => 'Tell us what you are trying to work out.',
                        'text' => 'Share the property, area, timing, or decision you need help with. We will start with the useful details and point you toward the next step.',
                        'phone' => '+63 912 345 6789',
                        'email' => 'hello@harborandkey.com',
                        'address' => '8F The Waterfront Tower · Lahug, Cebu City 6000',
                        'hours' => 'Mon–Sat · 9:00 AM–6:00 PM',
                        'button_label' => 'CONTACT HARBOR & KEY',
                        'button_url' => '/contact',
                        'image_url' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1900&q=90',
                        '_content_brief' => 'FAQ closing CTA linking into the dedicated Harbor contact page.',
                    ],
                ],
            ],
            'contact' => [
                'seo' => [
                    'title' => 'Contact Harbor & Key Realty | Cebu',
                    'description' => 'Contact Harbor & Key Realty about buying, selling, property appraisals, private viewings, investing, or local property questions across Cebu.',
                    'indexable' => true,
                ],
                'blocks' => [
                    'marketplace_harbor_page_hero' => [
                        'eyebrow' => 'HARBOR & KEY · CONTACT',
                        'heading' => 'Start with the property decision in front of you.',
                        'text' => 'Tell us whether you are buying, selling, investing, arranging a viewing, or simply planning ahead. We will begin with the details that make the next conversation useful.',
                        'image_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=2200&q=90',
                        '_content_brief' => 'Premium Harbor contact hero using the same navy/gold/editorial system as the homepage and all completed inner pages.',
                    ],
                    'marketplace_harbor_contact' => [
                        'eyebrow' => 'CONTACT HARBOR & KEY',
                        'heading' => 'Tell us how we can help.',
                        'text' => 'A useful first message can be simple: the property or area, what you want to achieve, and your preferred timing. Add as much detail as you already have.',
                        'phone' => '+63 912 345 6789',
                        'email' => 'hello@harborandkey.com',
                        'address' => '8F The Waterfront Tower · Lahug, Cebu City 6000',
                        'hours' => 'Mon–Sat · 9:00 AM–6:00 PM',
                        'button_label' => 'EMAIL HARBOR & KEY',
                        'button_url' => 'mailto:hello@harborandkey.com',
                        'image_url' => 'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1900&q=90',
                        'show_form' => true,
                        'form_heading' => 'Start your enquiry.',
                        'form_text' => 'Choose the type of property conversation you need and share the key details. On a published Cosmic site, this form uses the standard contact-submission flow.',
                        'form_button_label' => 'SEND ENQUIRY',
                        'form_note' => 'A viewing or consultation is confirmed only after Harbor & Key responds.',
                        'enquiry_types' => [
                            ['label' => 'Buying a property'],
                            ['label' => 'Selling / appraisal'],
                            ['label' => 'Private viewing'],
                            ['label' => 'Investment enquiry'],
                            ['label' => 'Neighborhood / local advice'],
                            ['label' => 'General enquiry'],
                        ],
                        '_content_brief' => 'Dedicated contact-page variant. Preserve the Harbor split composition and functional Cosmic contact form on published/exported sites.',
                    ],
                    'marketplace_harbor_faq' => [
                        'eyebrow' => 'BEFORE YOU SEND',
                        'heading' => 'A few details that make the first reply more useful.',
                        'text' => 'You do not need a complete brief. These quick answers explain what helps us respond with the right context.',
                        'items' => [
                            ['q' => 'What should I include in a buyer enquiry?', 'a' => 'Your preferred Cebu areas, approximate budget range, ideal property type, timing, and any non-negotiables are enough to start.'],
                            ['q' => 'What should I include for a seller appraisal?', 'a' => 'Share the property location, type, approximate size, current condition, any key features, and the timing you are considering.'],
                            ['q' => 'How do I request a private viewing?', 'a' => 'Mention the property you want to inspect and a few preferred time windows. We will confirm availability and access before the appointment.'],
                            ['q' => 'Which areas do you cover?', 'a' => 'The template is positioned around Metro Cebu and nearby markets, including Cebu City, Lapu-Lapu and Mactan, Talisay, and selected surrounding areas.'],
                            ['q' => 'When can I expect a response?', 'a' => 'Harbor & Key lists client availability Monday to Saturday, 9:00 AM to 6:00 PM. Actual response time depends on the live business setup after this template is personalized.'],
                        ],
                        '_content_brief' => 'Contact-specific FAQ with no guaranteed response-time or availability claims beyond the displayed demo business hours.',
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
                    'metadata' => json_encode(['marketplace_batch' => 'harbor-inner-batch5', 'page' => $slug], JSON_UNESCAPED_SLASHES),
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

        // Final route cleanup: Harbor has no Blog page, so the homepage editorial cards point to live inner pages instead.
        $home = DB::table('marketplace_template_pages')
            ->where('marketplace_template_id', $template->id)
            ->where('slug', 'home')
            ->first();
        if ($home) {
            $hero = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $home->id)
                ->where('spark_key', 'marketplace_harbor_hero')
                ->first();
            if ($hero) {
                $heroContent = json_decode((string) $hero->content, true) ?: [];
                $heroContent['secondary_label'] = 'EXPLORE CEBU';
                $heroContent['secondary_url'] = '/neighborhoods';
                DB::table('marketplace_template_blocks')->where('id', $hero->id)->update([
                    'content' => json_encode($heroContent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }

            $insights = DB::table('marketplace_template_blocks')
                ->where('marketplace_template_page_id', $home->id)
                ->where('spark_key', 'marketplace_harbor_home_insights')
                ->first();
            if ($insights) {
                $content = json_decode((string) $insights->content, true) ?: [];
                $content['eyebrow'] = 'PROPERTY GUIDES';
                $content['heading'] = 'Useful Context for Your Next Move';
                $content['view_all_label'] = 'EXPLORE PROPERTY FAQ';
                $content['view_all_url'] = '/faq';
                $content['read_more_label'] = 'Explore Guide';
                $content['items'] = [
                    ['date' => 'NEIGHBORHOOD GUIDE', 'title' => 'How to Compare Cebu Locations Beyond the Property Photos', 'url' => '/neighborhoods', 'image_url' => 'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1000&q=88'],
                    ['date' => 'BUYER GUIDE', 'title' => 'A Clearer Way to Prepare for a Cebu Property Search', 'url' => '/buyers', 'image_url' => 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=88'],
                    ['date' => 'SELLER GUIDE', 'title' => 'What Strong Property Positioning Looks Like Before Launch', 'url' => '/sellers', 'image_url' => 'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1000&q=88'],
                ];
                $content['_content_brief'] = 'Evergreen Harbor property-guide cards linking only to pages that exist in this 10-page template. No dead Blog route.';
                DB::table('marketplace_template_blocks')->where('id', $insights->id)->update([
                    'content' => json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'metadata' => json_encode(['marketplace_batch' => 'harbor-inner-batch5', 'page' => 'home-route-cleanup'], JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Forward-only Marketplace design iteration.
    }
};
