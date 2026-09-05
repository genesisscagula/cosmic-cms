<?php

namespace Database\Seeders;

use App\Models\MarketplaceTemplate;
use App\Models\MarketplaceTemplateNavigationItem;
use App\Models\MarketplaceTemplatePage;
use App\Services\TemplateCompositionBalancer;
use App\Services\PageTemplateCatalog;
use App\Services\SparkCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketplaceWebsiteTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $definition) {
            DB::transaction(fn () => $this->seedTemplate($definition));
        }
    }

    /** @param array<string, mixed> $definition */
    private function seedTemplate(array $definition): void
    {
        $template = MarketplaceTemplate::query()->updateOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $definition['name'],
                'industry_slug' => $definition['industry_slug'],
                'industry_label' => $definition['industry_label'],
                'style_slug' => $definition['style_slug'],
                'plan' => $definition['plan'],
                'status' => MarketplaceTemplate::STATUS_PUBLISHED,
                'credit_price' => $definition['credit_price'],
                'currency' => 'USD',
                'page_count' => count($definition['pages']),
                'summary' => $definition['summary'],
                'description' => $definition['description'],
                'thumbnail_url' => $definition['thumbnail_url'],
                'preview_url' => null,
                'theme_key' => $definition['theme_key'],
                'theme_settings' => $this->themeSettings($definition['theme_key'], $definition['marketplace_design'] ?? null),
                'global_header' => $this->header($definition['name']),
                'global_footer' => $this->footer($definition['name'], $definition['industry_label']),
                'features' => $definition['features'],
                'tags' => $definition['tags'],
                'seo' => [
                    'title' => $definition['name'].' '.$definition['industry_label'].' Website Template | Cosmic CMS',
                    'description' => $definition['summary'],
                ],
                'onboarding_schema' => $this->onboardingSchema($definition['industry_slug']),
                'source_bundle_key' => $definition['source_bundle_key'],
                'is_featured' => true,
                'is_customizable' => true,
                'ai_personalization_enabled' => true,
                'website_care_included' => true,
                'sort_order' => $definition['sort_order'],
                'version' => (int) ($definition['version'] ?? 1),
                'published_at' => now(),
            ],
        );

        // Built-in seed templates are versioned product inventory. Re-running this
        // seeder refreshes only these slugs and guarantees a deterministic bundle.
        $template->navigationItems()->delete();
        $template->pages()->delete();

        /** @var array<string, MarketplaceTemplatePage> $pageMap */
        $pageMap = [];
        foreach ($definition['pages'] as $index => $pageDefinition) {
            $page = $template->pages()->create([
                'name' => $pageDefinition['name'],
                'slug' => $pageDefinition['slug'],
                'page_intent' => $pageDefinition['intent'],
                'page_style' => $definition['style_slug'],
                'sort_order' => $index,
                'is_home' => $pageDefinition['slug'] === 'home',
                'seo' => [
                    'title' => $pageDefinition['name'].' | '.$definition['name'],
                    'description' => $pageDefinition['summary'] ?? $definition['summary'],
                    'indexable' => true,
                ],
                'metadata' => [
                    'marketplace_seed' => true,
                    'content_status' => 'prewritten',
                    'ai_personalization' => 'rewrite_for_customer',
                ],
            ]);
            $pageMap[$pageDefinition['slug']] = $page;
        }

        foreach ($definition['pages'] as $pageDefinition) {
            $page = $pageMap[$pageDefinition['slug']];
            $parentSlug = $pageDefinition['parent'] ?? null;
            if ($parentSlug && isset($pageMap[$parentSlug])) {
                $page->forceFill(['parent_id' => $pageMap[$parentSlug]->id])->save();
            }

            $fixedMarketplaceRecipe = collect($pageDefinition['recipe'] ?? [])
                ->contains(fn ($sparkKey) => is_string($sparkKey) && str_starts_with($sparkKey, 'marketplace_'));

            // Fixed-design Marketplace templates own their exact section recipe.
            // Do not replace or auto-balance these with the regular Spark catalog:
            // customers are buying the visual composition they previewed.
            if ($fixedMarketplaceRecipe) {
                $catalogTemplate = null;
                $sourceRecipe = array_values($pageDefinition['recipe']);
                $balanced = [
                    'sections' => $sourceRecipe,
                    'auto_balanced' => false,
                    'balanced_replacements' => [],
                ];
            } else {
                $catalogTemplate = $this->catalogTemplateFor($definition, $pageDefinition);
                $sourceRecipe = $catalogTemplate['sections'] ?? $pageDefinition['recipe'];
                $balanced = app(TemplateCompositionBalancer::class)->balance([
                    'key' => 'marketplace/'.$definition['slug'].'/'.$pageDefinition['slug'],
                    'industry' => [$definition['industry_slug']],
                    'style' => [$definition['style_slug']],
                    'sections' => $sourceRecipe,
                ]);
            }
            $recipe = $balanced['sections'] ?? $sourceRecipe;

            foreach ($recipe as $position => $sparkKey) {
                $page->blocks()->create([
                    'spark_key' => $sparkKey,
                    'sort_order' => $position,
                    'content' => $this->starterContent(
                        $definition,
                        $pageDefinition,
                        $sparkKey,
                        $position,
                    ),
                    'settings' => ['theme' => 'auto'],
                    'metadata' => [
                        'source' => $fixedMarketplaceRecipe ? 'marketplace-fixed-design-v2' : 'marketplace-seed-v1',
                        'catalog_template_key' => $catalogTemplate['key'] ?? null,
                        'personalize_with_luna' => true,
                        'image_rhythm_balanced' => (bool) ($balanced['auto_balanced'] ?? false),
                        'balance_replacement' => collect($balanced['balanced_replacements'] ?? [])->firstWhere('index', $position),
                    ],
                ]);
            }
        }

        $this->seedNavigation($template, $pageMap, $this->normalizeNavigation($definition['navigation']));
    }

    /**
     * Keep public headers scannable while preserving every seeded destination.
     * A template may contain 15 pages, but only six navigation groups plus the
     * primary CTA should compete for space in the desktop header.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeNavigation(array $items): array
    {
        $primary = array_values(array_filter($items, fn (array $item) => ! ($item['is_cta'] ?? false)));
        $ctas = array_values(array_filter($items, fn (array $item) => (bool) ($item['is_cta'] ?? false)));
        $cta = $ctas[0] ?? null;
        $availablePrimarySlots = $cta ? 6 : 7;

        if (count($primary) <= $availablePrimarySlots) {
            return $cta ? [...$primary, $cta] : $primary;
        }

        // Reserve the final non-CTA slot for a predictable overflow dropdown.
        $visible = array_slice($primary, 0, $availablePrimarySlots - 1);
        $overflow = array_slice($primary, $availablePrimarySlots - 1);
        $visible[] = [
            'label' => 'More',
            'url' => '#',
            'is_cta' => false,
            'children' => $overflow,
        ];

        if ($cta) {
            $visible[] = $cta;
        }

        return $visible;
    }

    /**
     * @param array<string, MarketplaceTemplatePage> $pageMap
     * @param array<int, array<string, mixed>> $items
     */
    private function seedNavigation(MarketplaceTemplate $template, array $pageMap, array $items, ?MarketplaceTemplateNavigationItem $parent = null): void
    {
        foreach ($items as $index => $item) {
            $page = isset($item['page']) ? ($pageMap[$item['page']] ?? null) : null;
            $nav = $template->navigationItems()->create([
                'marketplace_template_page_id' => $page?->id,
                'parent_id' => $parent?->id,
                'label' => $item['label'],
                'url' => $item['url'] ?? null,
                'sort_order' => $index,
                'is_cta' => (bool) ($item['is_cta'] ?? false),
                'target' => '_self',
                'metadata' => ['marketplace_seed' => true],
            ]);

            if (! empty($item['children'])) {
                $this->seedNavigation($template, $pageMap, $item['children'], $nav);
            }
        }
    }

    /** @return array<string, mixed> */
    private function starterContent(array $template, array $page, string $sparkKey, int $position): array
    {
        if (str_starts_with($sparkKey, 'marketplace_')) {
            return $this->marketplaceStarterContent($template, $page, $sparkKey, $position);
        }

        $business = $template['name'];
        $industry = $template['industry_label'];
        $pageName = $page['name'];
        $serviceLabels = $template['service_labels'];
        $images = $this->industryImages($template['industry_slug']);
        $isHero = str_starts_with($sparkKey, 'hero_');
        $isContact = str_starts_with($sparkKey, 'contact_');
        $isServices = str_starts_with($sparkKey, 'services_');
        $isProof = str_starts_with($sparkKey, 'stats_') || str_starts_with($sparkKey, 'testimonials_');
        $usesServiceItems = $isServices || str_contains($sparkKey, 'construction_');
        $usesPortfolioItems = str_starts_with($sparkKey, 'portfolio_') || str_starts_with($sparkKey, 'gallery_');
        $usesStatItems = str_starts_with($sparkKey, 'stats_') || str_contains($sparkKey, 'metric_') || str_contains($sparkKey, 'proof_');

        $heading = match (true) {
            $page['slug'] === 'home' && $template['industry_slug'] === 'accounting' => 'Clear numbers. Confident business decisions.',
            $page['slug'] === 'home' && $template['industry_slug'] === 'restaurant' => 'Exceptional food, made for memorable moments.',
            $page['slug'] === 'home' && $template['industry_slug'] === 'construction' => 'Built with quality. Delivered with confidence.',
            $page['slug'] === 'home' && $template['industry_slug'] === 'dental' => 'Modern dental care with a human touch.',
            $isServices => $pageName === 'Services' || $pageName === 'Treatments' || $pageName === 'Menu'
                ? 'Everything you need, clearly explained.'
                : $pageName.' designed around your needs.',
            $isProof => 'Trusted experience, backed by real outcomes.',
            $isContact => 'Ready to take the next step?',
            default => $pageName.' with clarity and confidence.',
        };

        $text = $page['summary'] ?? "A polished {$pageName} page for {$industry} businesses, ready for Luna to personalize with the customer's real details.";

        $content = [
            'eyebrow' => strtoupper($industry),
            'tagline' => $industry,
            'heading' => $heading,
            'title' => $heading,
            'text' => $text,
            'description' => $text,
            'subtext' => $text,
            'button_text' => $isHero ? 'Get Started' : ($isContact ? 'Contact Us' : 'Learn More'),
            'button_url' => $isContact ? '#contact' : '/contact',
            'secondary_button_text' => $isHero ? 'Explore Services' : null,
            'secondary_button_url' => $isHero ? '/services' : null,
            'image_url' => $images[$position % count($images)],
            'image_url_2' => $images[($position + 1) % count($images)],
            'image_url_3' => $images[($position + 2) % count($images)],
            'background_image_url' => $images[$position % count($images)],
            'image_alt' => $pageName.' at '.$business,
            'slides' => array_map(fn (string $image, int $index) => [
                'eyebrow' => strtoupper($industry),
                'heading' => $index === 0 ? $heading : $serviceLabels[$index % count($serviceLabels)],
                'text' => $index === 0 ? $text : "Explore {$serviceLabels[$index % count($serviceLabels)]} from {$business}.",
                'image_url' => $image,
                'button_label' => $index === 0 ? 'Get Started' : 'Learn More',
                'button_url' => $index === 0 ? '/contact' : '/services',
            ], array_slice($images, 0, 3), array_keys(array_slice($images, 0, 3))),
            'stats' => [
                ['value' => '10+', 'label' => 'Years of experience'],
                ['value' => '500+', 'label' => 'Customers supported'],
                ['value' => '4.9/5', 'label' => 'Average client rating'],
            ],
            'testimonials' => [
                ['quote' => "{$business} made the process clear, professional, and easy from start to finish.", 'name' => 'Happy Customer', 'role' => 'Local client'],
                ['quote' => 'Responsive, knowledgeable, and focused on the details that mattered most.', 'name' => 'Business Owner', 'role' => 'Customer'],
            ],
            '_content_brief' => [
                'page' => $pageName,
                'industry' => $industry,
                'position' => $position,
                'instruction' => 'Preserve this page purpose and Spark choice, then rewrite all customer-facing copy for the subscribed business during Luna onboarding.',
            ],
        ];

        if ($usesServiceItems || $usesPortfolioItems) {
            $content['items'] = array_map(
                fn (string $label, int $index) => [
                    'icon' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'label' => $usesPortfolioItems ? 'Featured work' : 'Service',
                    'title' => $label,
                    'heading' => $label,
                    'text' => "Professional {$label} support from {$business}, with clear communication and a straightforward next step.",
                    'description' => "Professional {$label} support from {$business}, with clear communication and a straightforward next step.",
                    'image_url' => $images[$index % count($images)],
                    'button_text' => 'Learn More',
                    'button_url' => '/contact',
                ],
                $serviceLabels,
                array_keys($serviceLabels),
            );
        } elseif ($usesStatItems) {
            $content['items'] = array_map(fn (array $stat, int $index) => [
                'title' => $stat['value'],
                'value' => $stat['value'],
                'label' => $stat['label'],
                'text' => $stat['label'],
                'image_url' => $images[$index % count($images)],
            ], $content['stats'], array_keys($content['stats']));
        }

        return $content;
    }


    /** @return array<string, mixed> */
    private function marketplaceStarterContent(array $template, array $page, string $sparkKey, int $position): array
    {
        $business = (string) $template['name'];
        $pageName = (string) $page['name'];
        $summary = (string) ($page['summary'] ?? $template['summary']);
        $services = array_values((array) ($template['service_labels'] ?? []));
        $images = $this->industryImages((string) $template['industry_slug']);

        $brief = [
            'page' => $pageName,
            'industry' => (string) $template['industry_label'],
            'position' => $position,
            'fixed_marketplace_design' => true,
            'instruction' => 'Rewrite customer-facing content and media only. Preserve this Marketplace Spark type, order, layout, and design identity.',
        ];

        if (str_starts_with($sparkKey, 'marketplace_ledger_')) {
            $pageHeadings = [
                'about' => 'A better accounting relationship starts with understanding your business.',
                'services' => 'Accounting support built around the decisions you need to make.',
                'faq' => 'Straight answers to common accounting questions.',
                'contact' => 'Start with a useful conversation about your business.',
            ];

            return match ($sparkKey) {
                'marketplace_ledger_hero' => [
                    'eyebrow' => 'ACCOUNTING · TAX · BUSINESS ADVISORY',
                    'heading' => 'Clear numbers.',
                    'accent_heading' => 'Confident business decisions.',
                    'text' => 'We help businesses stay compliant, understand their numbers, and make better decisions with reliable accounting and practical advice.',
                    'primary_label' => 'Book a Consultation', 'primary_url' => '/contact',
                    'secondary_label' => 'Explore Our Services', 'secondary_url' => '/services',
                    'image_url' => $images[0],
                    'stats' => [['value' => '150+', 'label' => 'Businesses supported'], ['value' => '99%', 'label' => 'On-time compliance'], ['value' => '12+', 'label' => 'Years experience']],
                    '_content_brief' => $brief,
                ],
                'marketplace_ledger_services' => [
                    'eyebrow' => $page['slug'] === 'home' ? 'WHY CHOOSE US' : 'OUR SERVICES',
                    'heading' => $page['slug'] === 'home' ? 'More than numbers. We deliver clarity.' : 'Practical accounting support for every stage of business.',
                    'text' => $summary,
                    'items' => array_map(fn (string $label, int $index) => [
                        'icon' => ['▤', '✓', '↗', '◇'][$index % 4],
                        'title' => $label,
                        'text' => "Clear, reliable {$label} support from {$business}, shaped around your business and the decisions ahead.",
                    ], $services, array_keys($services)),
                    '_content_brief' => $brief,
                ],
                'marketplace_ledger_story' => [
                    'eyebrow' => 'A BETTER ACCOUNTING RELATIONSHIP',
                    'heading' => 'Advice that starts with understanding your business.',
                    'text' => $summary,
                    'image_url' => $images[1],
                    'quote' => 'Your reports should explain the business — not make it harder to understand.',
                    '_content_brief' => $brief,
                ],
                'marketplace_ledger_proof' => [
                    'eyebrow' => 'PROVEN, PRACTICAL, PERSONAL',
                    'heading' => 'Financial confidence you can see.',
                    '_content_brief' => $brief,
                ],
                'marketplace_ledger_faq' => [
                    'eyebrow' => $page['slug'] === 'contact' ? 'START A CONVERSATION' : 'COMMON QUESTIONS',
                    'heading' => $page['slug'] === 'contact' ? 'Ready to talk about what you need from your numbers?' : 'Useful answers before we get started.',
                    'button_label' => $page['slug'] === 'contact' ? 'Request a Consultation' : 'Talk to an Accountant',
                    'button_url' => '/contact',
                    '_content_brief' => $brief,
                ],
                'marketplace_ledger_contact' => [
                    'eyebrow' => 'START A CONVERSATION',
                    'heading' => 'Ready to make the next step clear?',
                    'text' => $summary,
                    'phone' => '(02) 5550 0148', 'email' => 'hello@ledgerpoint.example',
                    'address' => 'Level 6 · 42 Market Street · Sydney NSW', 'hours' => 'Mon–Fri · 8:30am–5:30pm',
                    'button_label' => 'Request a Consultation', 'button_url' => 'mailto:hello@ledgerpoint.example',
                    'image_url' => $images[2],
                    '_content_brief' => $brief,
                ],
                'marketplace_ledger_cta' => [
                    'eyebrow' => 'READY WHEN YOU ARE',
                    'heading' => $page['slug'] === 'contact' ? 'Let’s make the next step clear.' : 'Make the next financial decision with more clarity.',
                    'text' => 'A better accounting relationship starts with a useful conversation.',
                    'button_label' => 'Book a Consultation', 'button_url' => '/contact',
                    '_content_brief' => $brief,
                ],
                'marketplace_ledger_page_hero' => [
                    'eyebrow' => strtoupper('LEDGERPOINT · '.$pageName),
                    'heading' => $pageHeadings[$page['slug']] ?? $pageName.' with clarity and confidence.',
                    'text' => $summary,
                    '_content_brief' => $brief,
                ],
                default => ['heading' => $pageName, 'text' => $summary, '_content_brief' => $brief],
            };
        }

        if (str_starts_with($sparkKey, 'marketplace_ember_')) {
            $pageHeadings = [
                'about' => 'The story behind the room, the fire, and the food.',
                'menu' => 'Seasonal cooking, written one plate at a time.',
                'lunch' => 'A slower kind of lunch.',
                'dinner' => 'Dinner shaped by flame and season.',
                'private-dining' => 'A table of your own.',
                'gallery' => 'A look inside Ember & Olive.',
                'reviews' => 'Guest notes from around the table.',
                'faq' => 'Good to know before you arrive.',
                'contact' => 'Your table is waiting.',
            ];

            return match ($sparkKey) {
                'marketplace_ember_hero' => [
                    'eyebrow' => 'EMBER & OLIVE · PORTLAND',
                    'heading' => 'Fire-crafted food.',
                    'accent_heading' => 'Gathered moments.',
                    'text' => 'Seasonal ingredients, open flames, and warm hospitality. A dining experience rooted in craft and connection.',
                    'primary_label' => 'Reserve a Table', 'primary_url' => '/contact',
                    'secondary_label' => 'View the Menu', 'secondary_url' => '/menu',
                    'image_url' => $images[3],
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_menu' => [
                    'eyebrow' => $page['slug'] === 'home' ? 'SIGNATURE EXPERIENCE' : ($page['slug'] === 'lunch' ? 'LUNCH' : ($page['slug'] === 'dinner' ? 'DINNER' : 'FROM THE KITCHEN')),
                    'heading' => $page['slug'] === 'home' ? 'From our kitchen to your table.' : ($page['slug'] === 'lunch' ? 'Bright plates for a long lunch.' : ($page['slug'] === 'dinner' ? 'Fire, season, and a little theatre.' : 'A menu led by season, fire, and restraint.')),
                    'text' => $summary,
                    'button_label' => $page['slug'] === 'menu' ? 'Reserve a Table' : 'View the Full Menu',
                    'button_url' => $page['slug'] === 'menu' ? '/contact' : '/menu',
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_story' => [
                    'eyebrow' => 'OUR STORY', 'heading' => 'Rooted in fire. Inspired by tradition.',
                    'text' => 'Ember & Olive is where timeless techniques meet modern flair. Our open kitchen, warm ambience, and genuine hospitality create the perfect setting for memorable meals and meaningful moments.',
                    'image_url' => $images[1], 'image_url_2' => $images[3],
                    'quote' => 'Good food brings people together. Great food leaves a lasting impression.',
                    'quote_by' => 'Chef & Founder, Marcus Hale',
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_gallery' => [
                    'eyebrow' => 'AT EMBER & OLIVE',
                    'heading' => $page['slug'] === 'gallery' ? 'Food, light, people, and the room around it.' : 'A glimpse of the table and the room.',
                    'images' => array_map(fn (string $image) => ['image_url' => $image], $images),
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_feature' => [
                    'items' => [
                        ['icon' => '◌', 'title' => 'Seasonal Ingredients', 'text' => 'Thoughtfully sourced from local farms and trusted producers.'],
                        ['icon' => '♨', 'title' => 'Chef-Driven Menu', 'text' => 'Creative dishes inspired by fire, flavor, and the changing seasons.'],
                        ['icon' => '◇', 'title' => 'Private Events', 'text' => 'Intimate gatherings and celebrations, beautifully tailored.'],
                        ['icon' => '⌁', 'title' => 'Handcrafted Cocktails', 'text' => 'Curated pours and original creations, mixed with care.'],
                    ],
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_reviews' => [
                    'quote' => 'Every detail was perfect. The food, the service, the ambience—Ember & Olive is our new favorite place.',
                    'name' => 'Jessica L.', 'role' => 'Guest',
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_private_dining' => [
                    'eyebrow' => 'PRIVATE DINING', 'heading' => 'Celebrate in our space.',
                    'text' => 'From intimate dinners to milestone celebrations, our private dining experiences are tailored to you. Exceptional food, attentive service, and an atmosphere your guests won’t forget.',
                    'button_label' => 'Inquire About Events', 'button_url' => '/private-dining', 'image_url' => $images[3],
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_reservation' => [
                    'eyebrow' => 'RESERVATIONS', 'heading' => 'We’ll save you a seat.',
                    'text' => 'Join us for an unforgettable dining experience. Reserve your table and let the evening begin.',
                    'phone' => '(555) 123-4567', 'email' => 'hello@emberandolive.com',
                    'button_label' => 'Find a Table', 'image_url' => $images[2],
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_location' => [
                    'address_heading' => 'Find Us', 'address' => "123 Hearthwood Lane\nPortland, OR 97201",
                    'directions_label' => 'Get Directions', 'hours_heading' => 'Hours',
                    'hours' => "Mon – Thu     5:00pm – 10:00pm\nFri – Sat       5:00pm – 11:00pm\nSunday          Closed",
                    'image_url' => $images[3], '_content_brief' => $brief,
                ],
                'marketplace_ember_contact' => [
                    'eyebrow' => $page['slug'] === 'private-dining' ? 'PLAN YOUR EVENT' : 'RESERVATIONS & CONTACT',
                    'heading' => $page['slug'] === 'private-dining' ? 'Tell us what you’re planning.' : 'Your table is waiting.',
                    'text' => $summary, 'image_url' => $images[3],
                    'button_label' => $page['slug'] === 'private-dining' ? 'Enquire About Private Dining' : 'Reserve a Table',
                    'button_url' => '#reserve',
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_cta' => [
                    'eyebrow' => 'COME JOIN US', 'heading' => 'Dinner tastes better when the table is full.',
                    'text' => 'Book your next lunch, dinner, or celebration at Ember & Olive.',
                    'button_label' => 'Reserve a Table', 'button_url' => '/contact',
                    '_content_brief' => $brief,
                ],
                'marketplace_ember_page_hero' => [
                    'eyebrow' => strtoupper('EMBER & OLIVE · '.$pageName),
                    'heading' => $pageHeadings[$page['slug']] ?? $pageName,
                    'text' => $summary,
                    '_content_brief' => $brief,
                ],
                default => ['heading' => $pageName, 'text' => $summary, '_content_brief' => $brief],
            };
        }

        if (str_starts_with($sparkKey, 'marketplace_northfield_')) {
            $pageHeadings = [
                'about' => 'A legal practice built around clarity, discretion, and practical advice.',
                'services' => 'Clear legal guidance for the matters that shape what happens next.',
                'faq' => 'Useful answers before you engage a lawyer.',
                'contact' => 'Start with a confidential, focused conversation.',
            ];

            return match ($sparkKey) {
                'marketplace_northfield_hero' => [
                    'eyebrow' => 'NORTHFIELD LEGAL · PRACTICAL COUNSEL',
                    'heading' => 'Clear advice for', 'accent_heading' => 'important decisions.',
                    'text' => 'Calm, commercially aware legal guidance for businesses, property matters, families, and estates.',
                    'primary_label' => 'Request a Consultation', 'primary_url' => '/contact',
                    'secondary_label' => 'Explore Practice Areas', 'secondary_url' => '/services',
                    'image_url' => $images[0], 'trust_label' => 'Confidential consultations', 'trust_text' => 'Clear scope · Practical next steps',
                    '_content_brief' => $brief,
                ],
                'marketplace_northfield_practice' => [
                    'eyebrow' => 'PRACTICE AREAS',
                    'heading' => $page['slug'] === 'home' ? 'Legal support that stays focused on the decision in front of you.' : 'Practical guidance across the matters clients bring to Northfield.',
                    'text' => $summary,
                    'items' => array_map(fn (string $label, int $index) => [
                        'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'title' => $label,
                        'text' => "Clear, practical {$label} advice from {$business}, explained in straightforward language and shaped around the decision ahead.",
                    ], $services, array_keys($services)),
                    '_content_brief' => $brief,
                ],
                'marketplace_northfield_authority' => [
                    'eyebrow' => 'THE NORTHFIELD APPROACH',
                    'heading' => $page['slug'] === 'about' ? 'Senior legal advice without unnecessary complexity.' : 'Measured advice. Clear communication. No unnecessary theatre.',
                    'text' => $summary,
                    'quote' => 'Our role is to make complex matters easier to understand and easier to act on.',
                    'image_url' => $images[1],
                    '_content_brief' => $brief,
                ],
                'marketplace_northfield_process' => [
                    'eyebrow' => 'HOW WE WORK', 'heading' => 'A clear process from first conversation to next step.',
                    '_content_brief' => $brief,
                ],
                'marketplace_northfield_proof' => [
                    'eyebrow' => 'CLIENT CONFIDENCE', 'heading' => 'Advice built around clarity, discretion, and practical outcomes.',
                    'quote' => 'Northfield made a complicated matter feel manageable from the first meeting.',
                    'name' => 'Client testimonial', 'role' => 'Commercial matter',
                    '_content_brief' => $brief,
                ],
                'marketplace_northfield_team' => [
                    'eyebrow' => 'OUR LAWYERS', 'heading' => 'Experienced counsel, accessible when it matters.', 'text' => $summary,
                    '_content_brief' => $brief,
                ],
                'marketplace_northfield_faq' => [
                    'eyebrow' => 'COMMON QUESTIONS', 'heading' => 'Useful answers before we get started.',
                    '_content_brief' => $brief,
                ],
                'marketplace_northfield_contact' => [
                    'eyebrow' => 'CONFIDENTIAL CONSULTATION', 'heading' => 'Start with a clear conversation.', 'text' => $summary,
                    'phone' => '(02) 5550 0196', 'email' => 'hello@northfieldlegal.example',
                    'address' => 'Level 8 · 65 King Street · Sydney NSW',
                    'button_label' => 'Request a Consultation', 'button_url' => 'mailto:hello@northfieldlegal.example',
                    'image_url' => $images[2], '_content_brief' => $brief,
                ],
                'marketplace_northfield_cta' => [
                    'eyebrow' => 'READY WHEN YOU ARE', 'heading' => 'Make the next legal step clearer.',
                    'text' => 'A useful legal relationship starts with a focused conversation about what matters now.',
                    'button_label' => 'Request a Consultation', 'button_url' => '/contact', '_content_brief' => $brief,
                ],
                'marketplace_northfield_page_hero' => [
                    'eyebrow' => strtoupper('NORTHFIELD LEGAL · '.$pageName),
                    'heading' => $pageHeadings[$page['slug']] ?? $pageName,
                    'text' => $summary, '_content_brief' => $brief,
                ],
                default => ['heading' => $pageName, 'text' => $summary, '_content_brief' => $brief],
            };
        }

        if (str_starts_with($sparkKey, 'marketplace_clearflow_')) {
            $pageHeadings = ['about' => 'Local plumbing built around clear communication and dependable workmanship.', 'services' => 'Fast help for urgent problems and planned plumbing work.', 'faq' => 'Straight answers before the plumber arrives.', 'contact' => 'Tell us what needs fixing and how urgent it is.'];
            return match ($sparkKey) {
                'marketplace_clearflow_hero' => ['eyebrow'=>'CLEARFLOW PLUMBING · LOCAL & RESPONSIVE','heading'=>'Fast plumbing help.','accent_heading'=>'Done properly.','text'=>'Reliable repairs, maintenance, drains, hot water, and installations with clear arrival windows and practical advice.','primary_label'=>'Call a Plumber','primary_url'=>'tel:+61255500314','secondary_label'=>'Book Online','secondary_url'=>'/contact','image_url'=>$images[0],'badge'=>'Same-day help available','_content_brief'=>$brief],
                'marketplace_clearflow_services' => ['eyebrow'=>'PLUMBING SERVICES','heading'=>'The right fix, without the runaround.','text'=>$summary,'items'=>array_map(fn(string $label)=>['title'=>$label,'text'=>"Professional {$label} from {$business}, with clear communication and tidy workmanship."],$services),'_content_brief'=>$brief],
                'marketplace_clearflow_trust' => ['eyebrow'=>'WHY CLEARFLOW','heading'=>'Straight answers. Respectful service. Work that lasts.','text'=>$summary,'quote'=>'Clean workmanship and clear communication are part of the job.','image_url'=>$images[1],'_content_brief'=>$brief],
                'marketplace_clearflow_process' => ['eyebrow'=>'HOW IT WORKS','heading'=>'From problem to fixed in three clear steps.','_content_brief'=>$brief],
                'marketplace_clearflow_proof' => ['eyebrow'=>'LOCAL CUSTOMER PROOF','heading'=>'Plumbing help people feel comfortable recommending.','quote'=>'ClearFlow arrived when they said they would, found the problem quickly, and left everything clean.','name'=>'Local customer','role'=>'Recent plumbing repair','_content_brief'=>$brief],
                'marketplace_clearflow_faq' => ['eyebrow'=>'COMMON QUESTIONS','heading'=>'Useful answers before we arrive.','_content_brief'=>$brief],
                'marketplace_clearflow_contact' => ['eyebrow'=>'BOOK A PLUMBER','heading'=>'Tell us what needs fixing.','text'=>$summary,'phone'=>'(02) 5550 0314','email'=>'hello@clearflow.example','button_label'=>'Call ClearFlow','button_url'=>'tel:+61255500314','image_url'=>$images[2],'_content_brief'=>$brief],
                'marketplace_clearflow_cta' => ['eyebrow'=>'NEED A PLUMBER?','heading'=>'Stop the problem before it becomes a bigger one.','text'=>'Call ClearFlow for responsive local plumbing help.','button_label'=>'Call Now','button_url'=>'tel:+61255500314','_content_brief'=>$brief],
                'marketplace_clearflow_page_hero' => ['eyebrow'=>strtoupper('CLEARFLOW PLUMBING · '.$pageName),'heading'=>$pageHeadings[$page['slug']] ?? $pageName,'text'=>$summary,'_content_brief'=>$brief],
                default => ['heading'=>$pageName,'text'=>$summary,'_content_brief'=>$brief],
            };
        }

        if (str_starts_with($sparkKey, 'marketplace_harbor_')) {
            $pageHeadings = [
                'about' => 'Property advice built around local knowledge and calm decision-making.',
                'listings' => 'A considered collection of homes currently on the market.',
                'property-detail' => 'A closer look at the home, the setting, and the opportunity.',
                'buyers' => 'Buy with more context, less noise, and a clearer next step.',
                'sellers' => 'A sale campaign designed around the property and the market in front of it.',
                'neighborhoods' => 'Understand the places behind the property search.',
                'reviews' => 'Results measured in confidence, communication, and strong outcomes.',
                'faq' => 'Useful answers before the next property move.',
                'contact' => 'Start a property conversation with local context.',
            ];
            return match ($sparkKey) {
                'marketplace_harbor_hero' => [
                    'eyebrow'=>'PREMIUM REAL ESTATE, PERSONALIZED FOR YOU',
                    'heading'=>'Find Your Dream Home.',
                    'accent_heading'=>'Key to Your New Harbor.',
                    'text'=>'Harbor & Key Realty connects you to exceptional properties and experiences. Let us help you find a place to live, invest, and thrive.',
                    'primary_label'=>'BROWSE PROPERTIES','primary_url'=>'/listings',
                    'secondary_label'=>'WATCH VIDEO','secondary_url'=>'#video',
                    'image_url'=>'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=2200&q=90',
                    'location_label'=>'LOCATION','location_placeholder'=>'City, Neighborhood, or ZIP',
                    'property_type_label'=>'PROPERTY TYPE','property_type_value'=>'All Types',
                    'price_range_label'=>'PRICE RANGE','price_range_value'=>'Any Price',
                    'beds_label'=>'BEDS','beds_value'=>'Any','baths_label'=>'BATHS','baths_value'=>'Any',
                    'search_label'=>'SEARCH PROPERTIES',
                    'benefits'=>[
                        ['icon'=>'diamond','title'=>'Premium Properties','text'=>'Handpicked, high-quality listings you can trust.'],
                        ['icon'=>'key','title'=>'Expert Guidance','text'=>'Local expertise and dedicated support every step of the way.'],
                        ['icon'=>'shield','title'=>'Trusted & Transparent','text'=>'Honest service and clear communication always.'],
                        ['icon'=>'home','title'=>'Invest in Your Future','text'=>'Smart real estate choices for long-term value.'],
                    ],
                    '_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_listings' => [
                    'eyebrow'=>'FEATURED PROPERTIES',
                    'heading'=>'Exceptional Homes.',
                    'accent_heading'=>'Extraordinary Living.',
                    'view_all_label'=>'VIEW ALL PROPERTIES','view_all_url'=>'/listings',
                    'items'=>[
                        ['status'=>'FOR SALE','title'=>'Modern Waterfront Villa','location'=>'Lapu-Lapu City, Cebu','beds'=>'4','baths'=>'4','area'=>'320 m²','price'=>'₱28,500,000','image_url'=>'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=1200&q=90'],
                        ['status'=>'FOR SALE','title'=>'Contemporary Family Home','location'=>'Talamban, Cebu City','beds'=>'5','baths'=>'4','area'=>'280 m²','price'=>'₱18,750,000','image_url'=>'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1200&q=90'],
                        ['status'=>'FOR SALE','title'=>'Luxury Condo with Ocean View','location'=>'IT Park, Cebu City','beds'=>'2','baths'=>'2','area'=>'120 m²','price'=>'₱12,900,000','image_url'=>'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=90'],
                        ['status'=>'VIEW RENT','title'=>'Elegant House for Rent','location'=>'Banilad, Cebu City','beds'=>'4','baths'=>'3','area'=>'250 m²','price'=>'₱85,000 /month','image_url'=>'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=90'],
                    ],
                    '_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_about' => [
                    'eyebrow'=>'ABOUT HARBOR & KEY REALTY',
                    'heading'=>'Trusted Local Experts.',
                    'accent_heading'=>'Dedicated to You.',
                    'text'=>'With deep roots in Cebu’s most desirable communities, we deliver personalized real estate solutions backed by integrity, expertise, and a passion for people.',
                    'image_url'=>'https://images.unsplash.com/photo-1600607688969-a5bfcd646154?auto=format&fit=crop&w=1500&q=90',
                    'points'=>['In-depth local market knowledge','Personalized service tailored to your goals','Commitment to transparency & results'],
                    'button_label'=>'LEARN MORE ABOUT US','button_url'=>'/about',
                    '_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_communities' => [
                    'eyebrow'=>'EXPLORE PRIME LOCATIONS','heading'=>'Featured Communities','view_all_label'=>'VIEW ALL COMMUNITIES','view_all_url'=>'/neighborhoods',
                    'items'=>[
                        ['title'=>'Mactan Island','subtitle'=>'Resort living by the sea','url'=>'/neighborhoods','image_url'=>'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1100&q=88'],
                        ['title'=>'Cebu Business Park','subtitle'=>'The city’s premier lifestyle hub','url'=>'/neighborhoods','image_url'=>'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1100&q=88'],
                        ['title'=>'Talisay City','subtitle'=>'Peaceful living, close to everything','url'=>'/neighborhoods','image_url'=>'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1100&q=88'],
                        ['title'=>'Bantayan Island','subtitle'=>'Island life, unspoiled beauty','url'=>'/neighborhoods','image_url'=>'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=1100&q=88'],
                    ],'_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_solutions' => [
                    'eyebrow'=>'BUY. SELL. RENT.','heading'=>'Solutions for Every Move','items'=>[
                        ['icon'=>'buy','title'=>'Buy a Home','text'=>'Find your dream home with confidence. We’ll guide you every step of the way.','button_label'=>'Explore Homes','button_url'=>'/buyers'],
                        ['icon'=>'sell','title'=>'Sell Your Property','text'=>'Get top value for your property with our proven marketing and local expertise.','button_label'=>'List Your Property','button_url'=>'/sellers'],
                        ['icon'=>'rent','title'=>'Rent with Ease','text'=>'Discover quality rentals that fit your lifestyle and budget.','button_label'=>'View Rentals','button_url'=>'/listings'],
                    ],'_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_process' => [
                    'eyebrow'=>'HOW WE HELP YOU','heading'=>'A Seamless Path to Your Perfect Home','items'=>[
                        ['number'=>'01','title'=>'Discover','text'=>'Tell us what you’re looking for and your must-haves.'],
                        ['number'=>'02','title'=>'Explore','text'=>'We’ll curate the best options that match your needs.'],
                        ['number'=>'03','title'=>'Decide','text'=>'Tour, compare, and choose the one that feels right.'],
                        ['number'=>'04','title'=>'Own','text'=>'We handle the details from offer to closing.'],
                    ],'_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_testimonials' => [
                    'eyebrow'=>'CLIENT LOVE','heading'=>'What Our Clients Say','items'=>[
                        ['quote'=>'Harbor & Key Realty made our home buying journey smooth and stress-free. Their team is professional, responsive, and truly cares.','name'=>'Jasmine Leith','location'=>'Lapu-Lapu City, Cebu','avatar_url'=>'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=240&q=86'],
                        ['quote'=>'We sold our property above market value in just three weeks. Their marketing and local network are unmatched.','name'=>'Ronald S.','location'=>'Cebu City','avatar_url'=>'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=240&q=86'],
                        ['quote'=>'Exceptional service from start to finish. I now enjoy my dream home overlooking the ocean.','name'=>'Michael A.','location'=>'Talisay City','avatar_url'=>'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=240&q=86'],
                    ],'_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_team' => [
                    'eyebrow'=>'MEET OUR TEAM','heading'=>'The People Behind Your Next Move','items'=>[
                        ['name'=>'Kaye Rivera','role'=>'Real Estate Broker','bio'=>'Specializes in luxury homes & waterfront properties.','image_url'=>'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=600&q=88'],
                        ['name'=>'James Mendoza','role'=>'Senior Property Advisor','bio'=>'Expert in investments and high-value properties.','image_url'=>'https://images.unsplash.com/photo-1560250097-0b93528c311a?auto=format&fit=crop&w=600&q=88'],
                        ['name'=>'Angela Torres','role'=>'Client Relations Manager','bio'=>'Dedicated to providing a seamless client experience.','image_url'=>'https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=600&q=88'],
                        ['name'=>'Mark Lim','role'=>'Leasing Specialist','bio'=>'Helps clients find the perfect rental homes and spaces.','image_url'=>'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?auto=format&fit=crop&w=600&q=88'],
                    ],'_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_insights' => [
                    'eyebrow'=>'LATEST INSIGHTS','heading'=>'Real Estate Tips & Updates','view_all_label'=>'VIEW ALL ARTICLES','view_all_url'=>'/blog','read_more_label'=>'Read More','items'=>[
                        ['date'=>'May 10, 2024','title'=>'Why Waterfront Homes in Cebu Are a Smart Investment','url'=>'/blog','image_url'=>'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1000&q=88'],
                        ['date'=>'Apr 24, 2024','title'=>'Top 5 Family-Friendly Communities in Cebu to Consider','url'=>'/blog','image_url'=>'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=1000&q=88'],
                        ['date'=>'Apr 05, 2024','title'=>'Renting vs. Buying: What’s Best for You in 2024?','url'=>'/blog','image_url'=>'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1000&q=88'],
                    ],'_content_brief'=>$brief,
                ],
                'marketplace_harbor_home_cta' => [
                    'eyebrow'=>'YOUR NEXT MOVE STARTS HERE','heading'=>'Ready to Find Your Place by the Sea?','text'=>'Let’s work together to find a home that matches your lifestyle and goals.',
                    'primary_label'=>'BOOK A CONSULTATION','primary_url'=>'/contact','secondary_label'=>'EXPLORE PROPERTIES','secondary_url'=>'/listings',
                    'image_url'=>'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?auto=format&fit=crop&w=2200&q=90','_content_brief'=>$brief,
                ],
                'marketplace_harbor_listings' => ['eyebrow'=>'FEATURED HOMES','heading'=>$page['slug']==='listings'?'Homes currently worth a closer look.':'Properties worth a closer look.','text'=>$summary,'items'=>[
                    ['title'=>'Harbour House','meta'=>'3 Bed · 2 Bath · 2 Car','price'=>'Guide $2.4M','location'=>'Balmoral','image_url'=>$images[0]],
                    ['title'=>'The Esplanade','meta'=>'2 Bed · 2 Bath · 1 Car','price'=>'Guide $1.65M','location'=>'Manly','image_url'=>$images[1]],
                    ['title'=>'Garden Terrace','meta'=>'4 Bed · 3 Bath · 2 Car','price'=>'Auction','location'=>'Mosman','image_url'=>$images[2]],
                ],'_content_brief'=>$brief],
                'marketplace_harbor_market' => ['eyebrow'=>'LOCAL KNOWLEDGE','heading'=>$page['slug']==='sellers'?'Position the property for the market that exists now.':'Property decisions are easier with context.','text'=>$summary,'image_url'=>$images[1],'quote'=>'Good advice is not about pushing a transaction. It is about knowing when, why, and how to move.','_content_brief'=>$brief],
                'marketplace_harbor_paths' => ['eyebrow'=>'HOW WE HELP','heading'=>'A clear path whether you are buying, selling, or planning ahead.','_content_brief'=>$brief],
                'marketplace_harbor_neighborhood' => ['eyebrow'=>'NEIGHBORHOOD GUIDE','heading'=>'Know the streets, not just the suburb.','text'=>$summary,'_content_brief'=>$brief],
                'marketplace_harbor_proof' => ['eyebrow'=>'CLIENT RESULTS','heading'=>'Calm advice when the stakes feel high.','quote'=>'Harbor & Key understood the market, explained every decision, and negotiated a result we felt genuinely good about.','name'=>'Recent client','role'=>'Sydney harbour market','_content_brief'=>$brief],
                'marketplace_harbor_property' => ['eyebrow'=>'FEATURED PROPERTY','heading'=>'A harbour-side home designed around light and outlook.','text'=>$summary,'image_url'=>$images[0],'price'=>'Guide $2.4M','meta'=>'4 Bed · 3 Bath · 2 Car · 612 sqm','button_label'=>'Arrange an Inspection','button_url'=>'/contact','_content_brief'=>$brief],
                'marketplace_harbor_faq' => ['eyebrow'=>'PROPERTY FAQ','heading'=>'Useful answers before the next move.','_content_brief'=>$brief],
                'marketplace_harbor_contact' => ['eyebrow'=>'START A PROPERTY CONVERSATION','heading'=>'Buying, selling, or simply planning ahead?','text'=>$summary,'phone'=>'(02) 5550 0472','email'=>'hello@harborandkey.example','address'=>'8 Marina Walk · Neutral Bay NSW','button_label'=>$page['slug']==='listings'?'Arrange a Viewing':'Request an Appraisal','button_url'=>'mailto:hello@harborandkey.example','image_url'=>$images[2],'_content_brief'=>$brief],
                'marketplace_harbor_page_hero' => ['eyebrow'=>strtoupper('HARBOR & KEY · '.$pageName),'heading'=>$pageHeadings[$page['slug']] ?? $pageName,'text'=>$summary,'_content_brief'=>$brief],
                default => ['heading'=>$pageName,'text'=>$summary,'_content_brief'=>$brief],
            };
        }

        if (str_starts_with($sparkKey, 'marketplace_purespace_')) {
            $pageHeadings=['about'=>'A cleaning service built around care, consistency, and clear standards.','services'=>'Cleaning options shaped around the way each space is used.','home-cleaning'=>'Reliable home cleaning that makes the week feel lighter.','office-cleaning'=>'A cleaner workplace without disrupting the workday.','deep-move-cleaning'=>'Detailed cleaning for resets, handovers, and fresh starts.','checklist'=>'A clear room-by-room standard you can understand.','reviews'=>'Why customers choose PureSpace again.','faq'=>'Useful answers before your clean.','contact'=>'Tell us about the space and the clean you need.'];
            return match ($sparkKey) {
                'marketplace_purespace_hero'=>['eyebrow'=>'PURESPACE CLEANING · HEALTHY SPACES','heading'=>'A cleaner space.','accent_heading'=>'A lighter day.','text'=>$summary,'primary_label'=>'Get a Quote','primary_url'=>'/contact','secondary_label'=>'Explore Services','secondary_url'=>'/services','image_url'=>$images[0],'badge'=>'Trusted teams · clear checklists','_content_brief'=>$brief],
                'marketplace_purespace_services'=>['eyebrow'=>'CLEANING SERVICES','heading'=>'Choose the clean that fits the space.','text'=>$summary,'items'=>array_map(fn(string $label)=>['title'=>$label,'text'=>"Thoughtful {$label} from {$business}, delivered with clear standards and dependable service."],$services),'_content_brief'=>$brief],
                'marketplace_purespace_promise'=>['eyebrow'=>'THE PURESPACE PROMISE','heading'=>'Clean should feel calm, considered, and consistent.','text'=>$summary,'image_url'=>$images[1],'quote'=>'A good clean is not just what you can see. It is how the whole space feels afterward.','_content_brief'=>$brief],
                'marketplace_purespace_checklist'=>['eyebrow'=>'OUR CHECKLIST','heading'=>'A repeatable standard in every room.','_content_brief'=>$brief],
                'marketplace_purespace_proof'=>['eyebrow'=>'CUSTOMER NOTES','heading'=>'The kind of clean people rebook.','quote'=>'PureSpace made the whole process easy. The team was lovely, the checklist was clear, and the house felt genuinely refreshed.','name'=>'Recent customer','role'=>'Recurring clean','_content_brief'=>$brief],
                'marketplace_purespace_faq'=>['eyebrow'=>'CLEANING FAQ','heading'=>'Useful answers before your clean.','_content_brief'=>$brief],
                'marketplace_purespace_contact'=>['eyebrow'=>'GET A QUOTE','heading'=>'Tell us about the space.','text'=>$summary,'phone'=>'(02) 5550 0638','email'=>'hello@purespace.example','button_label'=>'Request a Quote','button_url'=>'mailto:hello@purespace.example','image_url'=>$images[2],'_content_brief'=>$brief],
                'marketplace_purespace_cta'=>['eyebrow'=>'READY FOR A RESET?','heading'=>'Come home to a space that already feels done.','text'=>'Choose your service and request a quote in a few simple details.','button_label'=>'Get a Quote','button_url'=>'/contact','_content_brief'=>$brief],
                'marketplace_purespace_page_hero'=>['eyebrow'=>strtoupper('PURESPACE CLEANING · '.$pageName),'heading'=>$pageHeadings[$page['slug']] ?? $pageName,'text'=>$summary,'_content_brief'=>$brief],
                default=>['heading'=>$pageName,'text'=>$summary,'_content_brief'=>$brief],
            };
        }

        if (str_starts_with($sparkKey, 'marketplace_dental_')) {
            $pageHeadings = [
                'about' => 'A dental clinic designed to make care feel easier.',
                'treatments' => 'Find the right care for your smile.',
                'general-dentistry' => 'Everyday dentistry with long-term health in mind.',
                'cosmetic-dentistry' => 'Confident smiles, planned with restraint and care.',
                'emergency-dental' => 'Urgent dental help, without the uncertainty.',
                'team' => 'Meet the people behind your care.',
                'patient-info' => 'Everything you need before your visit.',
                'faq' => 'Clear answers to common dental questions.',
                'contact' => 'Book a visit that feels straightforward from the start.',
            ];

            $treatmentHeading = match ((string) $page['slug']) {
                'general-dentistry' => 'Prevention, maintenance, and everyday dental care.',
                'cosmetic-dentistry' => 'Thoughtful cosmetic options for a natural-looking result.',
                'emergency-dental' => 'Fast help for pain, damage, and urgent concerns.',
                default => 'Everything you need for a healthier, more confident smile.',
            };

            return match ($sparkKey) {
                'marketplace_dental_hero' => [
                    'eyebrow' => 'MODERN DENTISTRY · GENTLE CARE',
                    'heading' => 'Dental care that feels', 'accent_heading' => 'clear, calm, and personal.',
                    'text' => 'Thoughtful dentistry, modern technology, and a team that takes the time to make every visit feel easier.',
                    'primary_label' => 'Book an Appointment', 'primary_url' => '/contact',
                    'secondary_label' => 'Explore Treatments', 'secondary_url' => '/treatments',
                    'image_url' => $images[0], 'trust_label' => 'New patients welcome', 'trust_text' => 'Same-week appointments available',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_treatments' => [
                    'eyebrow' => $page['slug'] === 'home' ? 'TREATMENTS' : strtoupper(str_replace('-', ' ', (string) $page['slug'])),
                    'heading' => $treatmentHeading,
                    'text' => $summary,
                    'items' => array_map(fn (string $label, int $index) => [
                        'icon' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'title' => $label,
                        'text' => "Clear, patient-first {$label} care from {$business}, explained in straightforward language before treatment begins.",
                    ], $services, array_keys($services)),
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_comfort' => [
                    'eyebrow' => 'A CALMER DENTAL EXPERIENCE',
                    'heading' => $page['slug'] === 'cosmetic-dentistry' ? 'Treatment should look natural and feel considered.' : 'Good dentistry starts with feeling understood.',
                    'text' => $summary,
                    'image_url' => $images[1],
                    'quote' => 'No rushed appointments. No confusing treatment plans. Just clear care built around you.',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_process' => [
                    'eyebrow' => $page['slug'] === 'patient-info' ? 'YOUR VISIT' : 'WHAT TO EXPECT',
                    'heading' => $page['slug'] === 'patient-info' ? 'A simple visit, explained before you arrive.' : 'A simple path from first visit to feeling better.',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_team' => [
                    'eyebrow' => 'MEET THE TEAM', 'heading' => 'Experienced clinicians. Warm, human care.', 'text' => $summary,
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_proof' => [
                    'eyebrow' => 'PATIENT CONFIDENCE',
                    'heading' => $page['slug'] === 'cosmetic-dentistry' ? 'Results built around your face, not a trend.' : 'Care measured in trust, not just treatment.',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_info' => [
                    'eyebrow' => $page['slug'] === 'emergency-dental' ? 'NEED URGENT HELP?' : 'PATIENT INFORMATION',
                    'heading' => $page['slug'] === 'emergency-dental' ? 'Know what to do when dental pain cannot wait.' : 'Know what to expect before you arrive.',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_faq' => [
                    'eyebrow' => 'COMMON QUESTIONS', 'heading' => 'Answers that make the next step easier.',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_contact' => [
                    'eyebrow' => $page['slug'] === 'emergency-dental' ? 'URGENT APPOINTMENTS' : 'BOOK AN APPOINTMENT',
                    'heading' => $page['slug'] === 'emergency-dental' ? 'Dental pain? Contact the clinic now.' : 'Ready for a dental visit that feels easier?',
                    'text' => $summary, 'image_url' => $images[3],
                    'phone' => '(02) 5550 0226', 'email' => 'hello@brightlinedental.example',
                    'address' => 'Suite 4 · 86 Harbour Road · Sydney NSW', 'hours' => 'Mon–Fri 8am–6pm · Sat 8am–1pm',
                    'button_label' => $page['slug'] === 'emergency-dental' ? 'Call Brightline' : 'Request Appointment',
                    'button_url' => $page['slug'] === 'emergency-dental' ? 'tel:+61255500226' : 'mailto:hello@brightlinedental.example',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_cta' => [
                    'eyebrow' => 'NEW PATIENTS WELCOME',
                    'heading' => 'A healthier smile can start with one easy appointment.',
                    'text' => 'Book your first visit or talk to our team about the right next step.',
                    'button_label' => 'Book an Appointment', 'button_url' => '/contact',
                    '_content_brief' => $brief,
                ],
                'marketplace_dental_page_hero' => [
                    'eyebrow' => strtoupper('BRIGHTLINE DENTAL · '.$pageName),
                    'heading' => $pageHeadings[$page['slug']] ?? $pageName,
                    'text' => $summary,
                    '_content_brief' => $brief,
                ],
                default => ['heading' => $pageName, 'text' => $summary, '_content_brief' => $brief],
            };
        }

        if (str_starts_with($sparkKey, 'marketplace_stone_')) {
            $pageHeadings = [
                'about' => 'Built on discipline, accountability, and the detail that keeps projects moving.',
                'services' => 'Construction capability backed by one accountable delivery team.',
                'commercial-construction' => 'Commercial construction without the operational guesswork.',
                'residential-construction' => 'Residential work where coordination matters as much as finish.',
                'fit-outs' => 'Fast programmes. Tight sites. Controlled delivery.',
                'project-management' => 'Control the project before the project controls you.',
                'projects' => 'Work that proves how we deliver.',
                'featured-project' => 'One project. Every decision visible.',
                'team' => 'Experienced people who stay close to the work.',
                'process' => 'A delivery method built to reduce uncertainty.',
                'safety-quality' => 'Safety and quality engineered into the programme.',
                'testimonials' => 'What clients remember after handover.',
                'faq' => 'Straight answers before the first site meeting.',
                'contact' => 'Start with the brief. Build from clarity.',
            ];

            $capabilityHeading = match ((string) $page['slug']) {
                'commercial-construction' => 'Commercial delivery built around live business constraints.',
                'residential-construction' => 'Build quality starts with coordination behind the finish.',
                'fit-outs' => 'Make every week of the programme count.',
                default => 'Built for complexity. Managed for certainty.',
            };

            return match ($sparkKey) {
                'marketplace_stone_hero' => [
                    'eyebrow' => 'BUILT WITH INTENT · DELIVERED WITH DISCIPLINE',
                    'heading' => 'We build places', 'accent_heading' => 'made to perform.',
                    'text' => 'Commercial, residential, and fit-out projects delivered with disciplined planning, clear communication, and uncompromising attention to detail.',
                    'primary_label' => 'Start a Project', 'primary_url' => '/contact',
                    'secondary_label' => 'View Projects', 'secondary_url' => '/projects',
                    'image_url' => $images[0], 'badge' => 'EST. 2008 · SYDNEY',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_capabilities' => [
                    'eyebrow' => 'CAPABILITIES', 'heading' => $capabilityHeading, 'text' => $summary,
                    'items' => array_map(fn (string $label, int $index) => [
                        'number' => str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'title' => $label,
                        'text' => "{$label} delivered by {$business} with disciplined planning, site coordination, quality control, and accountable communication.",
                    ], $services, array_keys($services)),
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_manifesto' => [
                    'eyebrow' => 'HOW WE WORK',
                    'heading' => $page['slug'] === 'about' ? 'Good construction is built on clear accountability.' : 'Good construction is decided long before concrete is poured.',
                    'text' => $summary, 'image_url' => $images[1], 'quote' => 'Plan hard. Communicate early. Build once.',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_projects' => [
                    'eyebrow' => 'SELECTED WORK',
                    'heading' => $page['slug'] === 'projects' ? 'Projects that show the full range of the work.' : 'Projects that prove the process.',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_process' => [
                    'eyebrow' => $page['slug'] === 'project-management' ? 'PROJECT CONTROL' : 'DELIVERY METHOD',
                    'heading' => $page['slug'] === 'project-management' ? 'Turn complexity into a controlled sequence of decisions.' : 'A controlled path from brief to handover.',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_metrics' => [
                    'eyebrow' => 'PROOF IN DELIVERY', 'heading' => 'Performance you can measure.',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_case_study' => [
                    'eyebrow' => 'FEATURED PROJECT',
                    'heading' => $page['slug'] === 'featured-project' ? 'A project story told through constraints, decisions, and result.' : 'Harbour Workplace — built around a live business.',
                    'text' => $summary, 'image_url' => $images[2],
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_team' => [
                    'eyebrow' => 'PEOPLE BEHIND THE WORK',
                    'heading' => 'Experienced enough to anticipate. Close enough to stay accountable.',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_safety' => [
                    'eyebrow' => 'SAFETY · QUALITY · CONTROL',
                    'heading' => 'Systems that protect people, programme, and finish.', 'text' => $summary,
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_testimonial' => [
                    'eyebrow' => 'CLIENT PERSPECTIVE',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_faq' => [
                    'eyebrow' => 'BEFORE WE START', 'heading' => 'Straight answers before the first site meeting.',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_contact' => [
                    'eyebrow' => $page['slug'] === 'faq' ? 'STILL HAVE A QUESTION?' : 'START A PROJECT',
                    'heading' => $page['slug'] === 'faq' ? 'Talk through the project before you commit to the next step.' : 'Bring us the brief. We’ll help make the path clear.',
                    'text' => $summary, 'image_url' => $images[3],
                    'phone' => '(02) 5550 0418', 'email' => 'projects@stonebridge.example', 'address' => '14 Foundry Street · Alexandria NSW',
                    'button_label' => 'Request a Project Call', 'button_url' => 'mailto:projects@stonebridge.example',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_cta' => [
                    'eyebrow' => 'READY TO BUILD?', 'heading' => 'Start with a better plan.',
                    'text' => 'Bring us your project goals, constraints, and timing. We’ll help define the next step.',
                    'button_label' => 'Request a Quote', 'button_url' => '/contact',
                    '_content_brief' => $brief,
                ],
                'marketplace_stone_page_hero' => [
                    'eyebrow' => strtoupper('STONEBRIDGE · '.$pageName),
                    'heading' => $pageHeadings[$page['slug']] ?? $pageName,
                    'text' => $summary,
                    '_content_brief' => $brief,
                ],
                default => ['heading' => $pageName, 'text' => $summary, '_content_brief' => $brief],
            };
        }

        return ['heading' => $pageName, 'text' => $summary, '_content_brief' => $brief];
    }

    /** @return array<int, string> */
    private function industryImages(string $industry): array
    {
        $sets = [
            'accounting' => ['1497366754035-f200968a6e72', '1454165804606-c3d57bc86b40', '1554224155-8d04cb21cd6c', '1556761175-b413da4baf72'],
            'construction' => ['1504307651254-35680f356dfd', '1541888946425-d81bb19240f5', '1487958449943-2429e8be8625', '1531835551805-16d864c8d311'],
            'restaurant' => ['1504674900247-0877df9cc836', '1515003197210-e0cd71810b5f', '1414235077428-338989a2e8c0', '1552566626-52f8b828add9'],
            'dental' => ['1609840114035-3c981b782dfe', '1629909613654-28e377c37b09', '1588776814546-1ffcf47267a5', '1606811971618-4486d14f3f99'],
            'law-firm' => ['1589829545856-d10d557cf95f', '1450101499163-c8848c66ca85', '1436450412740-6b988f486c6b', '1505664194779-8beaceb93744'],
            'real-estate' => ['1560518883-ce09059eeffa', '1600585154340-be6161a56a0c', '1600607687939-ce8a6c25118c', '1600566753190-17f0baa2a6c3'],
            'plumbing' => ['1607472586893-edb57bdc0e39', '1585704032915-c3400ca199e7', '1504148455328-c376907d081c', '1558618666-fcd25c85cd64'],
            'cleaning-services' => ['1581578731548-c64695cc6952', '1527515637462-cff94eecc1ac', '1585421514738-01798e348b17', '1563453392212-326f5e854473'],
            'salon-spa' => ['1560066984-138dadb4c035', '1522337360788-8b13dee7a37e', '1544161515-4ab6ce6db874', '1487412947147-5cebf100ffc2'],
            'glass-aluminum' => ['1487958449943-2429e8be8625', '1497366811353-6870744d04b2', '1486406146926-c627a92ad1ab', '1497366754035-f200968a6e72'],
        ];
        $ids = $sets[$industry] ?? $sets['accounting'];
        return array_map(fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1600&q=84", $ids);
    }

    /** @return array<string, mixed>|null */
    private function catalogTemplateFor(array $template, array $page): ?array
    {
        $aliases = [
            'accounting' => ['accounting', 'finance', 'bookkeeping', 'professional services'],
            'construction' => ['construction', 'builder', 'contractor'],
            'restaurant' => ['restaurant', 'dining', 'hospitality', 'food'],
            'dental' => ['dental', 'dentist', 'clinic', 'healthcare'],
            'law-firm' => ['law', 'legal', 'professional services'],
            'real-estate' => ['real estate', 'property', 'realtor'],
            'plumbing' => ['plumbing', 'trades', 'local services', 'home services'],
            'cleaning-services' => ['cleaning', 'local services', 'home services'],
            'salon-spa' => ['salon', 'beauty', 'wellness', 'spa'],
            'glass-aluminum' => ['glass', 'aluminum', 'windows', 'construction'],
        ];
        $needles = $aliases[$template['industry_slug']] ?? [$template['industry_slug']];
        $intentNeedles = array_values(array_filter([strtolower((string) ($page['intent'] ?? '')), strtolower((string) ($page['name'] ?? '')), strtolower((string) ($page['slug'] ?? ''))]));
        $planRanks = ['starter' => 10, 'growth' => 20, 'pro' => 30];
        $planRank = $planRanks[$template['plan']] ?? 10;

        $candidates = collect(PageTemplateCatalog::all())
            ->filter(function (array $candidate) use ($planRanks, $planRank) {
                $sections = array_values(array_filter($candidate['sections'] ?? [], 'is_string'));
                if ($sections === []) return false;
                foreach ($sections as $section) {
                    $spark = SparkCatalog::find($section);
                    $rank = $planRanks[(string) ($spark['access_level'] ?? 'pro')] ?? 999;
                    if (! $spark || $rank > $planRank) return false;
                }
                return true;
            })
            ->map(function (array $candidate) use ($needles, $intentNeedles) {
                $industryText = strtolower(implode(' ', (array) ($candidate['industry'] ?? [])));
                $intentText = strtolower(implode(' ', array_merge((array) ($candidate['intent'] ?? []), (array) ($candidate['features'] ?? []), [(string) ($candidate['name'] ?? ''), (string) ($candidate['description'] ?? '')])));
                $industryScore = collect($needles)->sum(fn (string $needle) => str_contains($industryText, $needle) ? 12 : 0);
                $intentScore = collect($intentNeedles)->sum(fn (string $needle) => $needle !== '' && str_contains($intentText, str_replace('-', ' ', $needle)) ? 4 : 0);
                return [...$candidate, '_marketplace_score' => $industryScore + $intentScore];
            })
            ->filter(fn (array $candidate) => ($candidate['_marketplace_score'] ?? 0) > 0)
            ->sortByDesc(fn (array $candidate) => sprintf('%04d-%03d', (int) $candidate['_marketplace_score'], (int) ($candidate['quality_score'] ?? 0)))
            ->take(12)
            ->values();

        if ($candidates->isEmpty()) return null;
        $offset = (int) ((float) sprintf('%u', crc32($template['slug'].'/'.$page['slug'])) % $candidates->count());
        return $candidates->get($offset);
    }

    /** @return array<int, array<string, mixed>> */
    private function additionalTemplates(int $starterPrice, int $growthPrice, int $proPrice): array
    {
        $starterRecipe = [
            'home' => ['hero_background_image', 'services_cards', 'stats_modern', 'testimonials_carousel', 'image_cta_banner'],
            'about' => ['hero_headline', 'about_founder_story', 'stats_modern', 'image_cta_banner'],
            'services' => ['hero_headline', 'services_grid_premium', 'process_timeline', 'faq_accordion', 'image_cta_banner'],
            'faq' => ['hero_headline', 'faq_accordion', 'testimonials_carousel', 'image_cta_banner'],
            'contact' => ['hero_headline', 'contact_details', 'contact_form_modern', 'location_map'],
        ];
        $growthRecipe = [
            'home' => ['hero_split_editorial', 'services_bento_premium', 'stats_modern', 'testimonials_portrait_cards_premium', 'cta_book_demo_premium'],
            'about' => ['hero_split_editorial', 'about_founder_visual_premium', 'about_timeline_story', 'cta_editorial_banner_premium'],
            'hub' => ['hero_headline', 'services_editorial_premium', 'services_feature_premium', 'faq_accordion', 'cta_book_demo_premium'],
            'detail' => ['hero_background_image', 'services_cards', 'process_visual_steps_premium', 'faq_accordion', 'cta_book_demo_premium'],
            'visual' => ['hero_background_image', 'services_showcase_premium', 'testimonials_portrait_cards_premium', 'cta_image_split_premium'],
            'process' => ['hero_headline', 'process_visual_steps_premium', 'services_bento_premium', 'cta_editorial_banner_premium'],
            'reviews' => ['hero_headline', 'testimonials_portrait_cards_premium', 'testimonials_carousel', 'cta_book_demo_premium'],
            'faq' => ['hero_headline', 'faq_accordion', 'contact_faq_premium', 'cta_book_demo_premium'],
            'contact' => ['hero_headline', 'contact_image_form_premium', 'contact_map_premium', 'contact_availability_board_premium'],
        ];
        $proRecipe = [
            'home' => ['hero_ken_burns_premium', 'construction_capability_split_premium', 'stats_photo_metrics_premium', 'portfolio_cinematic_grid_premium', 'testimonials_featured_story_premium', 'cta_background_media_premium'],
            'about' => ['hero_editorial_image_sequence_premium', 'about_image_manifesto_premium', 'about_journey_gallery_premium', 'trust_image_proof_premium', 'cta_editorial_banner_premium'],
            'hub' => ['hero_split_slider_premium', 'services_visual_directory_premium', 'services_staggered_media_premium', 'process_media_roadmap_premium', 'cta_background_media_premium'],
            'detailA' => ['hero_reveal_parallax_premium', 'construction_service_photos_premium', 'features_visual_split_premium', 'proof_metric_gallery_premium', 'cta_media_cards_premium'],
            'detailB' => ['hero_cinematic_slider_premium', 'services_feature_premium', 'features_image_stack_premium', 'testimonials_image_wall_premium', 'cta_image_split_premium'],
            'detailC' => ['hero_split_slider_premium', 'services_staggered_media_premium', 'process_image_timeline_premium', 'stats_achievements_premium', 'cta_floating_panel_premium'],
            'portfolio' => ['hero_cinematic_slider_premium', 'portfolio_fullbleed_projects_premium', 'portfolio_project_panels_premium', 'proof_case_story_premium', 'cta_background_media_premium'],
            'case' => ['hero_scroll_morph_premium', 'content_asymmetric_story_premium', 'gallery_story_tiles_premium', 'stats_visual_mosaic_premium', 'testimonials_client_spotlight_premium'],
            'team' => ['hero_editorial_overlay', 'team_people_mosaic_premium', 'team_leadership_split_premium', 'content_visual_quote_premium', 'cta_editorial_banner_premium'],
            'process' => ['hero_pinned_story_premium', 'process_numbered_panels_premium', 'process_image_timeline_premium', 'faq_accordion', 'cta_background_media_premium'],
            'proof' => ['hero_grid_pulse_tech_premium', 'trust_certification_cards_premium', 'stats_visual_mosaic_premium', 'content_media_manifesto_premium'],
            'reviews' => ['hero_headline', 'testimonials_image_wall_premium', 'testimonials_client_spotlight_premium', 'proof_metric_gallery_premium'],
            'faq' => ['hero_headline', 'faq_accordion', 'contact_faq_premium', 'cta_floating_panel_premium'],
            'contact' => ['hero_split_image', 'contact_visual_inquiry_premium', 'contact_office_cards_premium', 'location_photo_cards_premium'],
        ];

        return [
            $this->compactTemplate('northfield-legal', 'Northfield Legal', 'law-firm', 'Law Firm', 'starter', $starterPrice, 'burgundy', 'editorial', 50, 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?auto=format&fit=crop&w=1400&q=84', ['Business Law', 'Property Law', 'Estate Planning', 'Dispute Resolution'], [
                $this->page('Home', 'home', 'home', ['marketplace_northfield_hero', 'marketplace_northfield_practice', 'marketplace_northfield_authority', 'marketplace_northfield_process', 'marketplace_northfield_proof', 'marketplace_northfield_cta'], 'Establish authority, introduce practice areas, explain the firm approach, and invite a confidential consultation.'),
                $this->page('About the Firm', 'about', 'about', ['marketplace_northfield_page_hero', 'marketplace_northfield_authority', 'marketplace_northfield_team', 'marketplace_northfield_process', 'marketplace_northfield_cta'], 'Introduce the lawyers, values, working style, and client-first approach.'),
                $this->page('Practice Areas', 'services', 'services', ['marketplace_northfield_page_hero', 'marketplace_northfield_practice', 'marketplace_northfield_process', 'marketplace_northfield_proof', 'marketplace_northfield_cta'], 'Explain common legal matters in clear, practical language.'),
                $this->page('Legal FAQs', 'faq', 'faq', ['marketplace_northfield_page_hero', 'marketplace_northfield_faq', 'marketplace_northfield_process', 'marketplace_northfield_contact'], 'Answer questions about consultations, fees, confidentiality, and preparation.'),
                $this->page('Contact', 'contact', 'contact', ['marketplace_northfield_page_hero', 'marketplace_northfield_contact', 'marketplace_northfield_faq'], 'Provide a discreet route to request a consultation.'),
            ]),
            $this->compactTemplate('clearflow-plumbing', 'ClearFlow Plumbing', 'plumbing', 'Plumbing', 'starter', $starterPrice, 'aqua', 'clean', 60, 'https://images.unsplash.com/photo-1607472586893-edb57bdc0e39?auto=format&fit=crop&w=1400&q=84', ['Emergency Repairs', 'Leak Detection', 'Drain Clearing', 'Hot Water'], [
                $this->page('Home', 'home', 'home', ['marketplace_clearflow_hero', 'marketplace_clearflow_services', 'marketplace_clearflow_trust', 'marketplace_clearflow_process', 'marketplace_clearflow_proof', 'marketplace_clearflow_cta'], 'Lead with rapid help, trusted workmanship, core services, a clear process, and a call-now action.'),
                $this->page('About', 'about', 'about', ['marketplace_clearflow_page_hero', 'marketplace_clearflow_trust', 'marketplace_clearflow_process', 'marketplace_clearflow_proof', 'marketplace_clearflow_cta'], 'Share experience, service standards, clean workmanship, and local responsiveness.'),
                $this->page('Plumbing Services', 'services', 'services', ['marketplace_clearflow_page_hero', 'marketplace_clearflow_services', 'marketplace_clearflow_process', 'marketplace_clearflow_proof', 'marketplace_clearflow_cta'], 'Explain emergency repairs, leaks, drains, hot water, maintenance, and installations.'),
                $this->page('FAQ', 'faq', 'faq', ['marketplace_clearflow_page_hero', 'marketplace_clearflow_faq', 'marketplace_clearflow_process', 'marketplace_clearflow_contact'], 'Answer questions about emergencies, call-outs, estimates, parts, and warranties.'),
                $this->page('Book a Plumber', 'contact', 'contact', ['marketplace_clearflow_page_hero', 'marketplace_clearflow_contact', 'marketplace_clearflow_faq'], 'Capture job details and make urgent phone contact easy.'),
            ]),
            $this->compactTemplate('harbor-key-realty', 'Harbor & Key Realty', 'real-estate', 'Real Estate', 'growth', $growthPrice, 'coastal', 'premium', 70, 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?auto=format&fit=crop&w=1400&q=84', ['Buy a Home', 'Sell a Property', 'Property Valuation', 'Local Market Advice'], [
                $this->page('Home', 'home', 'home', ['marketplace_harbor_hero','marketplace_harbor_home_listings','marketplace_harbor_home_about','marketplace_harbor_home_communities','marketplace_harbor_home_solutions','marketplace_harbor_home_process','marketplace_harbor_home_testimonials','marketplace_harbor_home_team','marketplace_harbor_home_insights','marketplace_harbor_home_cta'], 'Feature standout homes, prime Cebu communities, buyer and seller solutions, and a simple four-step path to a move.'),
                $this->page('About', 'about', 'about', ['marketplace_harbor_page_hero','marketplace_harbor_market','marketplace_harbor_proof','marketplace_harbor_contact'], 'Introduce the agency through local knowledge, calm advice, and client outcomes.'),
                $this->page('Featured Listings', 'listings', 'portfolio', ['marketplace_harbor_page_hero','marketplace_harbor_listings','marketplace_harbor_neighborhood','marketplace_harbor_contact'], 'Showcase available properties with useful local and lifestyle context.'),
                $this->page('Property Detail', 'property-detail', 'portfolio', ['marketplace_harbor_property','marketplace_harbor_neighborhood','marketplace_harbor_contact'], 'Demonstrate a premium property page with features, context, and inspection enquiry.', 'listings'),
                $this->page('For Buyers', 'buyers', 'services', ['marketplace_harbor_page_hero','marketplace_harbor_paths','marketplace_harbor_neighborhood','marketplace_harbor_faq','marketplace_harbor_contact'], 'Guide buyers from readiness and search through offer and settlement.'),
                $this->page('For Sellers', 'sellers', 'services', ['marketplace_harbor_page_hero','marketplace_harbor_market','marketplace_harbor_paths','marketplace_harbor_proof','marketplace_harbor_contact'], 'Explain appraisal, positioning, campaign strategy, negotiation, and settlement.'),
                $this->page('Neighborhood Guide', 'neighborhoods', 'content', ['marketplace_harbor_page_hero','marketplace_harbor_neighborhood','marketplace_harbor_market','marketplace_harbor_listings'], 'Present local areas, amenities, lifestyle, and market context.'),
                $this->page('Results & Reviews', 'reviews', 'testimonials', ['marketplace_harbor_page_hero','marketplace_harbor_proof','marketplace_harbor_market','marketplace_harbor_contact'], 'Build confidence with recent outcomes and client stories.'),
                $this->page('Property FAQ', 'faq', 'faq', ['marketplace_harbor_page_hero','marketplace_harbor_faq','marketplace_harbor_paths','marketplace_harbor_contact'], 'Answer buying, selling, inspection, offer, and campaign questions.'),
                $this->page('Contact', 'contact', 'contact', ['marketplace_harbor_page_hero','marketplace_harbor_contact','marketplace_harbor_faq'], 'Capture appraisal, viewing, buying, and general enquiries.'),
            ]),
            $this->compactTemplate('purespace-cleaning', 'PureSpace Cleaning', 'cleaning-services', 'Cleaning Services', 'growth', $growthPrice, 'mint', 'fresh', 80, 'https://images.unsplash.com/photo-1581578731548-c64695cc6952?auto=format&fit=crop&w=1400&q=84', ['Home Cleaning', 'Office Cleaning', 'Deep Cleaning', 'Move Cleaning'], [
                $this->page('Home', 'home', 'home', ['marketplace_purespace_hero','marketplace_purespace_services','marketplace_purespace_promise','marketplace_purespace_checklist','marketplace_purespace_proof','marketplace_purespace_cta'], 'Present a healthy-space promise with services, proof, and quote CTA.'),
                $this->page('About', 'about', 'about', ['marketplace_purespace_page_hero','marketplace_purespace_promise','marketplace_purespace_checklist','marketplace_purespace_proof','marketplace_purespace_contact'], 'Explain the team, standards, products, and quality checks.'),
                $this->page('Services', 'services', 'services', ['marketplace_purespace_page_hero','marketplace_purespace_services','marketplace_purespace_checklist','marketplace_purespace_cta'], 'Help customers compare recurring, deep, moving, and commercial cleaning.'),
                $this->page('Home Cleaning', 'home-cleaning', 'services', ['marketplace_purespace_page_hero','marketplace_purespace_services','marketplace_purespace_checklist','marketplace_purespace_proof','marketplace_purespace_contact'], 'Detail recurring home cleaning options and inclusions.', 'services'),
                $this->page('Office Cleaning', 'office-cleaning', 'services', ['marketplace_purespace_page_hero','marketplace_purespace_promise','marketplace_purespace_services','marketplace_purespace_checklist','marketplace_purespace_contact'], 'Explain flexible schedules, hygiene priorities, and quality checks.', 'services'),
                $this->page('Deep & Move Cleaning', 'deep-move-cleaning', 'services', ['marketplace_purespace_page_hero','marketplace_purespace_services','marketplace_purespace_checklist','marketplace_purespace_cta'], 'Cover one-off deep cleaning and move preparation.', 'services'),
                $this->page('Our Checklist', 'checklist', 'process', ['marketplace_purespace_page_hero','marketplace_purespace_checklist','marketplace_purespace_promise','marketplace_purespace_cta'], 'Show room-by-room inclusions and optional extras.'),
                $this->page('Reviews', 'reviews', 'testimonials', ['marketplace_purespace_page_hero','marketplace_purespace_proof','marketplace_purespace_promise','marketplace_purespace_contact'], 'Share customer experiences from homes, offices, and property managers.'),
                $this->page('FAQ', 'faq', 'faq', ['marketplace_purespace_page_hero','marketplace_purespace_faq','marketplace_purespace_checklist','marketplace_purespace_contact'], 'Answer access, supplies, pets, rescheduling, and pricing questions.'),
                $this->page('Get a Quote', 'contact', 'contact', ['marketplace_purespace_page_hero','marketplace_purespace_contact','marketplace_purespace_faq'], 'Capture property size, service type, timing, and contact details.'),
            ]),
            $this->compactTemplate('maison-bloom', 'Maison Bloom', 'salon-spa', 'Salon & Spa', 'pro', $proPrice, 'rose', 'luxury', 90, 'https://images.unsplash.com/photo-1560066984-138dadb4c035?auto=format&fit=crop&w=1400&q=84', ['Hair Studio', 'Skin Rituals', 'Massage & Body', 'Bridal Services'], $this->proPages($proRecipe, 'beauty')),
            $this->compactTemplate('vistaline-glass-aluminum', 'VistaLine Glass & Aluminum', 'glass-aluminum', 'Glass & Aluminum Installation', 'pro', $proPrice, 'steel', 'architectural', 100, 'https://images.unsplash.com/photo-1487958449943-2429e8be8625?auto=format&fit=crop&w=1400&q=84', ['Windows & Doors', 'Curtain Walls', 'Shopfronts', 'Railings & Enclosures'], $this->proPages($proRecipe, 'glass')),
        ];
    }

    /** @param array<int, array<string, mixed>> $pages */
    private function compactTemplate(string $slug, string $name, string $industrySlug, string $industryLabel, string $plan, int $price, string $theme, string $style, int $order, string $image, array $services, array $pages): array
    {
        $nav = collect($pages)->reject(fn ($page) => in_array($page['slug'], ['property-detail'], true))->map(fn ($page) => $this->nav($page['name'], $page['slug'], $page['slug'] === 'contact'))->values()->all();
        $template = ['slug' => $slug, 'name' => $name, 'industry_slug' => $industrySlug, 'industry_label' => $industryLabel, 'style_slug' => $style, 'plan' => $plan, 'credit_price' => $price, 'theme_key' => $theme, 'source_bundle_key' => $slug, 'sort_order' => $order, 'summary' => "A complete, premium {$industryLabel} website with industry-specific pages, realistic content, and clear conversion paths.", 'description' => "An editable {$plan} Cosmic CMS template for {$industryLabel} businesses, ready for Luna personalization without changing its designed structure.", 'thumbnail_url' => $image, 'features' => [count($pages).'-page website', 'AI content setup', 'Industry-specific content', 'Editable Cosmic Sparks', 'Website care'], 'tags' => array_values(array_unique([$industrySlug, $industryLabel, $style, $plan])), 'service_labels' => $services, 'pages' => $pages, 'navigation' => $nav];

        if ($name === 'Northfield Legal') {
            $template['marketplace_design'] = [
                'key' => 'northfield-legal-editorial', 'header_mode' => 'light',
                'page' => '#f7f4ee', 'heading' => '#171819', 'body' => '#6d6861',
                'primary' => '#6f1d2b', 'primary_hover' => '#8c2d3f', 'accent' => '#b28a53',
                'surface' => '#fffdf8', 'surface_alt' => '#e9e0d2', 'border' => '#d8cfc1',
                'on_primary' => '#ffffff', 'button_primary' => '#6f1d2b', 'button_text' => '#ffffff',
                'font_heading' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'font_display' => 'Georgia, Times New Roman, serif',
                'components' => [
                    'button_radius' => 0, 'card_radius' => 0, 'image_radius' => 0,
                    'section_spacing' => 112, 'content_width' => 1400,
                    'header_variant' => 'northfield_editorial', 'footer_variant' => 'northfield_editorial',
                    'hero_style' => 'legal_editorial_split', 'card_style' => 'legal_index_grid',
                    'accent_usage' => 'restrained_gold', 'surface_language' => 'ivory_sand_burgundy',
                ],
            ];
            $template['features'] = ['5-page bespoke legal website', 'Dedicated legal Sparks', 'AI content setup', 'Reusable Marketplace design kit', 'Builder + live export ready'];
        }

        if ($name === 'ClearFlow Plumbing') {
            $template['marketplace_design'] = [
                'key' => 'clearflow-service-system', 'header_mode' => 'dark',
                'page' => '#eef8fb', 'heading' => '#102a43', 'body' => '#5f7180',
                'primary' => '#0b78c8', 'primary_hover' => '#0865aa', 'accent' => '#28b7c8',
                'surface' => '#ffffff', 'surface_alt' => '#eef8fb', 'border' => '#cfe1e8',
                'on_primary' => '#ffffff', 'button_primary' => '#d9f45a', 'button_text' => '#102a43',
                'font_heading' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'components' => [
                    'button_radius' => 0, 'card_radius' => 0, 'image_radius' => 0,
                    'section_spacing' => 112, 'content_width' => 1400,
                    'header_variant' => 'clearflow_service', 'footer_variant' => 'clearflow_service',
                    'hero_style' => 'urgent_service_split', 'card_style' => 'service_number_grid',
                    'accent_usage' => 'aqua_lime_action', 'surface_language' => 'navy_ice_white',
                ],
            ];
            $template['features'] = ['5-page bespoke plumbing website', 'Dedicated service Sparks', 'AI content setup', 'Reusable Marketplace design kit', 'Builder + live export ready'];
        }

        if ($name === 'Harbor & Key Realty') {
            $template['marketplace_design'] = [
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
            $template['features'] = ['10-page bespoke real estate website', 'Dedicated property Sparks', 'AI content setup', 'Reusable Marketplace design kit', 'Builder + live export ready'];
            $template['version'] = 6;
        }


        if ($name === 'PureSpace Cleaning') {
            $template['marketplace_design'] = [
                'key' => 'purespace-fresh-system', 'header_mode' => 'light',
                'page' => '#fbfffd', 'heading' => '#173d3b', 'body' => '#607873',
                'primary' => '#173f3d', 'primary_hover' => '#0f302e', 'accent' => '#39a98e',
                'surface' => '#ffffff', 'surface_alt' => '#eef8f4', 'border' => '#d9e9e3',
                'on_primary' => '#ffffff', 'button_primary' => '#173f3d', 'button_text' => '#ffffff',
                'font_heading' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                'components' => [
                    'button_radius' => 999, 'card_radius' => 20, 'image_radius' => 28,
                    'section_spacing' => 112, 'content_width' => 1400,
                    'header_variant' => 'purespace_fresh', 'footer_variant' => 'purespace_fresh',
                    'hero_style' => 'fresh_service_split', 'card_style' => 'soft_service_grid',
                    'accent_usage' => 'mint_emerald', 'surface_language' => 'white_mint_deep_green',
                ],
            ];
            $template['features'] = ['10-page bespoke cleaning website', 'Dedicated cleaning Sparks', 'AI content setup', 'Reusable Marketplace design kit', 'Builder + live export ready'];
        }

        return $template;
    }

    private function proPages(array $r, string $kind): array
    {
        $beauty = $kind === 'beauty';
        $labels = $beauty
            ? [['Our Story','about','about'],['Services','services','services'],['Hair Studio','hair','services'],['Skin Rituals','skin','services'],['Massage & Body','body','services'],['Bridal','bridal','services'],['Specialists','team','team'],['Gallery','gallery','portfolio'],['Memberships','memberships','pricing'],['Journal','journal','blog'],['Guest Reviews','reviews','testimonials'],['Visit the Maison','visit','location'],['FAQ','faq','faq'],['Book an Appointment','contact','contact']]
            : [['Company','about','about'],['Systems & Services','services','services'],['Windows & Doors','windows-doors','services'],['Curtain Walls & Facades','facades','services'],['Shopfronts','shopfronts','services'],['Railings & Enclosures','railings-enclosures','services'],['Projects','projects','portfolio'],['Commercial Case Study','commercial-case-study','case-studies'],['Residential Case Study','residential-case-study','case-studies'],['Our Process','process','process'],['Technical & Quality','quality','proof'],['Testimonials','reviews','testimonials'],['FAQ','faq','faq'],['Request a Quote','contact','contact']];
        $homeRecipe = $beauty
            ? ['hero_cinematic_slider_premium', 'services_visual_directory_premium', 'stats_achievements_premium', 'process_numbered_panels_premium', 'testimonials_client_spotlight_premium', 'cta_editorial_banner_premium']
            : ['hero_ken_burns_premium', 'construction_capability_split_premium', 'stats_modern', 'process_numbered_panels_premium', 'portfolio_cinematic_grid_premium', 'testimonials_featured_story_premium', 'cta_background_media_premium'];
        $pages = [$this->page('Home', 'home', 'home', $homeRecipe, $beauty ? 'Introduce signature treatments, atmosphere, specialists, and booking.' : 'Lead with precision, performance, project imagery, and specification support.')];
        $keys = ['about','hub','detailA','detailB','detailC','detailA','team','portfolio','proof','case','reviews','proof','faq','contact'];
        foreach ($labels as $i => [$name, $slug, $intent]) {
            $parent = in_array($slug, ['hair','skin','body','bridal','windows-doors','facades','shopfronts','railings-enclosures'], true) ? 'services' : (str_contains($slug, 'case-study') ? 'projects' : null);
            $pages[] = $this->page($name, $slug, $intent, $r[$keys[$i]], "A complete {$name} page with polished industry-specific information and a clear next step.", $parent);
        }
        return $pages;
    }

    /** @return array<string, mixed> */
    private function themeSettings(string $theme, ?array $marketplaceDesign = null): array
    {
        return array_filter([
            'primary' => $theme,
            'secondary' => 'white',
            'tertiary' => 'surface',
            'auto' => $marketplaceDesign === null,
            'marketplace_template' => true,
            // Fixed Marketplace templates carry an independent art direction.
            // The regular Cosmic color-family system remains only as a legacy
            // fallback for templates that have not been rebuilt yet.
            'marketplace_design' => $marketplaceDesign,
            'components' => is_array($marketplaceDesign) ? ($marketplaceDesign['components'] ?? null) : null,
        ], static fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    private function header(string $name): array
    {
        if ($name === 'Ember & Olive') {
            return [
                'type' => 'glassmorphism_header',
                'logo_text' => $name,
                'cta_label' => 'Reserve a Table',
                'cta_url' => '/contact',
                'menu' => [],
                'overlay' => false,
                'custom_shell_mode' => true,
                'custom_style' => [
                    'background_color' => '#171713', 'text_color' => '#f3ecdf', 'nav_color' => '#f3ecdf',
                    'cta_background' => '#d5a153', 'cta_color' => '#17140f', 'cta_radius' => 3,
                    'height' => 86, 'padding_x' => 50, 'nav_size' => 13, 'logo_tone' => 'light',
                ],
                'marketplace_variant' => 'ember_editorial',
            ];
        }

        if ($name === 'Northfield Legal') {
            return [
                'type' => 'glassmorphism_header',
                'logo_text' => $name,
                'cta_label' => 'Request a Consultation',
                'cta_url' => '/contact',
                'menu' => [],
                'overlay' => false,
                'custom_shell_mode' => true,
                'allow_light_logo_filter' => true,
                'custom_style' => [
                    'background_color' => '#fffdf8', 'text_color' => '#171819', 'nav_color' => '#383431',
                    'cta_background' => '#6f1d2b', 'cta_color' => '#ffffff', 'cta_radius' => 0,
                    'height' => 86, 'padding_x' => 50, 'nav_size' => 13, 'logo_tone' => 'dark',
                ],
                'marketplace_variant' => 'northfield_editorial',
            ];
        }

        if ($name === 'ClearFlow Plumbing') {
            return [
                'type' => 'glassmorphism_header', 'logo_text' => $name,
                'cta_label' => 'Call a Plumber', 'cta_url' => 'tel:+61255500314', 'menu' => [],
                'overlay' => false, 'custom_shell_mode' => true, 'allow_light_logo_filter' => true,
                'custom_style' => [
                    'background_color' => '#102a43', 'text_color' => '#ffffff', 'nav_color' => '#dcebf1',
                    'cta_background' => '#d9f45a', 'cta_color' => '#102a43', 'cta_radius' => 0,
                    'height' => 86, 'padding_x' => 50, 'nav_size' => 13, 'logo_tone' => 'light',
                ],
                'marketplace_variant' => 'clearflow_service',
            ];
        }

        if ($name === 'Harbor & Key Realty') {
            return [
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
        }

        if ($name === 'PureSpace Cleaning') {
            return [
                'type' => 'glassmorphism_header', 'logo_text' => $name,
                'cta_label' => 'Get a Quote', 'cta_url' => '/contact', 'menu' => [],
                'overlay' => false, 'custom_shell_mode' => true, 'allow_light_logo_filter' => true,
                'custom_style' => [
                    'background_color' => '#fbfffd', 'text_color' => '#173d3b', 'nav_color' => '#46645f',
                    'cta_background' => '#173f3d', 'cta_color' => '#ffffff', 'cta_radius' => 999,
                    'height' => 86, 'padding_x' => 50, 'nav_size' => 13, 'logo_tone' => 'dark',
                ],
                'marketplace_variant' => 'purespace_fresh',
            ];
        }

        if ($name === 'Brightline Dental') {
            return [
                'type' => 'glassmorphism_header',
                'logo_text' => $name,
                'cta_label' => 'Book Appointment',
                'cta_url' => '/contact',
                'menu' => [],
                'overlay' => false,
                'custom_shell_mode' => true,
                'allow_light_logo_filter' => true,
                'custom_style' => [
                    'background_color' => '#fbfdfd', 'text_color' => '#17304a', 'nav_color' => '#17304a',
                    'cta_background' => '#17304a', 'cta_color' => '#ffffff', 'cta_radius' => 999,
                    'height' => 84, 'padding_x' => 50, 'nav_size' => 14, 'logo_tone' => 'dark',
                ],
                'marketplace_variant' => 'brightline_clinic',
            ];
        }

        if ($name === 'Stonebridge Construction') {
            return [
                'type' => 'glassmorphism_header',
                'logo_text' => $name,
                'cta_label' => 'Request a Quote',
                'cta_url' => '/contact',
                'menu' => [],
                'overlay' => false,
                'custom_shell_mode' => true,
                'allow_light_logo_filter' => true,
                'custom_style' => [
                    'background_color' => '#0f1112', 'text_color' => '#ffffff', 'nav_color' => '#d7d4ce',
                    'cta_background' => '#e66b2f', 'cta_color' => '#ffffff', 'cta_radius' => 0,
                    'height' => 88, 'padding_x' => 50, 'nav_size' => 13, 'logo_tone' => 'light',
                ],
                'marketplace_variant' => 'stonebridge_industrial',
            ];
        }

        return [
            'type' => 'glassmorphism_header',
            'logo_text' => $name,
            'cta_label' => 'Get Started',
            'cta_url' => '/contact',
            'menu' => [],
            'overlay' => false,
        ];
    }

    /** @return array<string, mixed> */
    private function footer(string $name, string $industry): array
    {
        if ($name === 'Ember & Olive') {
            return [
                'type' => 'mega_footer',
                'brand' => $name,
                'description' => 'Fire-crafted cuisine. Genuine hospitality. Moments worth savoring.',
                'columns' => [
                    ['title' => 'Navigate', 'links' => [
                        ['label' => 'Home', 'url' => '/'], ['label' => 'About', 'url' => '/about'],
                        ['label' => 'Menu', 'url' => '/menu'], ['label' => 'Private Dining', 'url' => '/private-dining'],
                        ['label' => 'Reservations', 'url' => '/contact'],
                    ]],
                    ['title' => 'Information', 'links' => [
                        ['label' => 'Gift Cards', 'url' => '#'], ['label' => 'Careers', 'url' => '#'],
                        ['label' => 'Press', 'url' => '#'], ['label' => 'Accessibility', 'url' => '#'],
                    ]],
                    ['title' => 'Contact', 'links' => [
                        ['label' => '(555) 123-4567', 'url' => 'tel:+15551234567'],
                        ['label' => 'hello@emberandolive.com', 'url' => 'mailto:hello@emberandolive.com'],
                        ['label' => '123 Hearthwood Lane, Portland, OR 97201', 'url' => '#'],
                    ]],
                ],
                'marketplace_variant' => 'ember_editorial',
            ];
        }

        if ($name === 'Northfield Legal') {
            return [
                'type' => 'mega_footer',
                'brand' => $name,
                'description' => 'Clear, discreet legal advice for business, property, estate planning, and dispute matters.',
                'custom_shell_mode' => true,
                'custom_style' => [
                    'background_color' => '#171819', 'text_color' => '#ffffff', 'muted_color' => '#c9c0b5',
                    'accent_color' => '#b28a53', 'logo_tone' => 'light',
                ],
                'columns' => [
                    ['title' => 'Practice Areas', 'links' => [
                        ['label' => 'Business Law', 'url' => '/services'], ['label' => 'Property Law', 'url' => '/services'],
                        ['label' => 'Estate Planning', 'url' => '/services'], ['label' => 'Dispute Resolution', 'url' => '/services'],
                    ]],
                    ['title' => 'The Firm', 'links' => [
                        ['label' => 'About Northfield', 'url' => '/about'], ['label' => 'Legal FAQs', 'url' => '/faq'],
                        ['label' => 'Request a Consultation', 'url' => '/contact'],
                    ]],
                    ['title' => 'Contact', 'links' => [
                        ['label' => '(02) 5550 0196', 'url' => 'tel:+61255500196'],
                        ['label' => 'hello@northfieldlegal.example', 'url' => 'mailto:hello@northfieldlegal.example'],
                        ['label' => 'Level 8 · 65 King Street · Sydney NSW', 'url' => '/contact'],
                    ]],
                ],
                'marketplace_variant' => 'northfield_editorial',
            ];
        }

        if ($name === 'ClearFlow Plumbing') {
            return [
                'type' => 'mega_footer', 'brand' => $name,
                'description' => 'Responsive local plumbing for urgent repairs, leaks, drains, hot water, maintenance, and planned installations.',
                'custom_shell_mode' => true,
                'custom_style' => [
                    'background_color' => '#102a43', 'text_color' => '#ffffff', 'muted_color' => '#b8ced8',
                    'accent_color' => '#28b7c8', 'logo_tone' => 'light',
                ],
                'columns' => [
                    ['title' => 'Services', 'links' => [
                        ['label' => 'Emergency Repairs', 'url' => '/services'], ['label' => 'Leak Detection', 'url' => '/services'],
                        ['label' => 'Drain Clearing', 'url' => '/services'], ['label' => 'Hot Water', 'url' => '/services'],
                    ]],
                    ['title' => 'ClearFlow', 'links' => [
                        ['label' => 'About', 'url' => '/about'], ['label' => 'Plumbing FAQ', 'url' => '/faq'],
                        ['label' => 'Book a Plumber', 'url' => '/contact'],
                    ]],
                    ['title' => 'Need Help?', 'links' => [
                        ['label' => '(02) 5550 0314', 'url' => 'tel:+61255500314'],
                        ['label' => 'hello@clearflow.example', 'url' => 'mailto:hello@clearflow.example'],
                        ['label' => 'Local service · Same-day availability', 'url' => '/contact'],
                    ]],
                ],
                'marketplace_variant' => 'clearflow_service',
            ];
        }

        if ($name === 'Harbor & Key Realty') {
            $quickLinks = [
                ['label'=>'Home','url'=>'/'], ['label'=>'Properties','url'=>'/listings'], ['label'=>'Buy','url'=>'/buyers'],
                ['label'=>'Sell','url'=>'/sellers'], ['label'=>'About Us','url'=>'/about'], ['label'=>'Contact','url'=>'/contact'],
            ];
            $services = [
                ['label'=>'Residential Sales','url'=>'/listings'], ['label'=>'Luxury Properties','url'=>'/listings'],
                ['label'=>'Rentals & Leasing','url'=>'/listings'], ['label'=>'Property Management','url'=>'/contact'],
                ['label'=>'Investment Consulting','url'=>'/contact'], ['label'=>'Relocation Services','url'=>'/contact'],
            ];
            $contactLinks = [
                ['label'=>'+63 912 345 6789','url'=>'tel:+639123456789'], ['label'=>'hello@harborandkey.com','url'=>'mailto:hello@harborandkey.com'],
                ['label'=>'www.harborandkey.com','url'=>'/'], ['label'=>'Mon–Sat · 9:00 AM–6:00 PM','url'=>'/contact'],
            ];
            $officeLinks = [
                ['label'=>'Harbor & Key Realty','url'=>'/contact'], ['label'=>'8F The Waterfront Tower','url'=>'/contact'],
                ['label'=>'Lahug, Cebu City 6000','url'=>'/contact'], ['label'=>'View on Map →','url'=>'/contact'],
            ];
            return [
                'type' => 'mega_footer', 'brand' => $name, 'logo_text' => 'HARBOR & KEY REALTY',
                'description' => 'Connecting you to exceptional properties and experiences across Cebu and beyond.',
                'tagline' => 'Connecting you to exceptional properties and experiences across Cebu and beyond.',
                'copyright' => '© '.now()->year.' Harbor & Key Realty. All Rights Reserved.',
                'privacy_label' => 'Privacy Policy', 'privacy_url' => '/privacy-policy',
                'terms_label' => 'Terms of Use', 'terms_url' => '/terms-and-conditions',
                'contact' => ['phone'=>'+63 912 345 6789','email'=>'hello@harborandkey.com','address'=>'8F The Waterfront Tower · Lahug, Cebu City 6000'],
                'social_links' => [
                    ['label'=>'Facebook','url'=>'#'], ['label'=>'Instagram','url'=>'#'], ['label'=>'LinkedIn','url'=>'#'], ['label'=>'YouTube','url'=>'#'],
                ],
                'newsletter' => [
                    'title'=>'Newsletter','text'=>'Be the first to get the latest property listings and news.','placeholder'=>'Enter your email','button_label'=>'SUBSCRIBE','action_url'=>'#',
                ],
                'custom_shell_mode' => true,
                'custom_style' => [
                    'background_color' => '#071f3d', 'text_color' => '#ffffff', 'muted_color' => '#aebdd0',
                    'accent_color' => '#c6a052', 'logo_tone' => 'light',
                ],
                'mega_footer' => [
                    'enabled'=>true,'variant'=>'primary','theme'=>'primary',
                    'tagline'=>'Connecting you to exceptional properties and experiences across Cebu and beyond.',
                    'primary_label'=>'BOOK A CONSULTATION','primary_url'=>'/contact',
                    'columns'=>[
                        ['title'=>'Quick Links','items'=>$quickLinks],
                        ['title'=>'Our Services','items'=>$services],
                        ['title'=>'Contact Us','items'=>$contactLinks],
                        ['title'=>'Our Office','items'=>$officeLinks],
                    ],
                ],
                // Keep the preview-friendly legacy shape too; provisioning reads mega_footer.columns.
                'columns' => [
                    ['title'=>'Quick Links','links'=>$quickLinks], ['title'=>'Our Services','links'=>$services],
                    ['title'=>'Contact Us','links'=>$contactLinks], ['title'=>'Our Office','links'=>$officeLinks],
                ],
                'marketplace_variant' => 'harbor_coastal',
            ];
        }

        if ($name === 'PureSpace Cleaning') {
            return [
                'type' => 'mega_footer', 'brand' => $name,
                'description' => 'Thoughtful home and workplace cleaning with dependable teams, clear checklists, and a calm customer experience.',
                'custom_shell_mode' => true,
                'custom_style' => ['background_color'=>'#173f3d','text_color'=>'#ffffff','muted_color'=>'#b9d2cb','accent_color'=>'#63cbb1','logo_tone'=>'light'],
                'columns' => [
                    ['title'=>'Services','links'=>[['label'=>'Home Cleaning','url'=>'/home-cleaning'],['label'=>'Office Cleaning','url'=>'/office-cleaning'],['label'=>'Deep & Move Cleaning','url'=>'/deep-move-cleaning'],['label'=>'Our Checklist','url'=>'/checklist']]],
                    ['title'=>'PureSpace','links'=>[['label'=>'About','url'=>'/about'],['label'=>'Reviews','url'=>'/reviews'],['label'=>'FAQ','url'=>'/faq'],['label'=>'Get a Quote','url'=>'/contact']]],
                    ['title'=>'Book a Clean','links'=>[['label'=>'(02) 5550 0638','url'=>'tel:+61255500638'],['label'=>'hello@purespace.example','url'=>'mailto:hello@purespace.example'],['label'=>'Flexible home + workplace service','url'=>'/contact']]],
                ],
                'marketplace_variant' => 'purespace_fresh',
            ];
        }

        if ($name === 'Brightline Dental') {
            return [
                'type' => 'mega_footer',
                'brand' => $name,
                'description' => 'Modern dentistry with calm communication, thoughtful treatment, and a patient-first experience.',
                'custom_shell_mode' => true,
                'custom_style' => [
                    'background_color' => '#10263b', 'text_color' => '#ffffff', 'muted_color' => '#b8d0da',
                    'logo_tone' => 'light',
                ],
                'columns' => [
                    ['title' => 'Treatments', 'links' => [
                        ['label' => 'General Dentistry', 'url' => '/general-dentistry'],
                        ['label' => 'Cosmetic Dentistry', 'url' => '/cosmetic-dentistry'],
                        ['label' => 'Emergency Dental', 'url' => '/emergency-dental'],
                    ]],
                    ['title' => 'Patient Care', 'links' => [
                        ['label' => 'Patient Information', 'url' => '/patient-info'],
                        ['label' => 'Our Team', 'url' => '/team'],
                        ['label' => 'FAQ', 'url' => '/faq'],
                    ]],
                    ['title' => 'Visit Brightline', 'links' => [
                        ['label' => '(02) 5550 0226', 'url' => 'tel:+61255500226'],
                        ['label' => 'hello@brightlinedental.example', 'url' => 'mailto:hello@brightlinedental.example'],
                        ['label' => 'Suite 4 · 86 Harbour Road · Sydney NSW', 'url' => '/contact'],
                    ]],
                ],
                'marketplace_variant' => 'brightline_clinic',
            ];
        }

        if ($name === 'Stonebridge Construction') {
            return [
                'type' => 'mega_footer',
                'brand' => $name,
                'description' => 'Commercial, residential, fit-out, and project delivery built around planning, accountability, and certainty.',
                'custom_shell_mode' => true,
                'custom_style' => [
                    'background_color' => '#0f1112', 'text_color' => '#ffffff', 'muted_color' => '#aaa69f',
                    'accent_color' => '#e66b2f', 'logo_tone' => 'light',
                ],
                'columns' => [
                    ['title' => 'Capabilities', 'links' => [
                        ['label' => 'Commercial Construction', 'url' => '/commercial-construction'],
                        ['label' => 'Residential Construction', 'url' => '/residential-construction'],
                        ['label' => 'Fit-outs', 'url' => '/fit-outs'],
                        ['label' => 'Project Management', 'url' => '/project-management'],
                    ]],
                    ['title' => 'Company', 'links' => [
                        ['label' => 'Projects', 'url' => '/projects'],
                        ['label' => 'Our Team', 'url' => '/team'],
                        ['label' => 'Our Process', 'url' => '/process'],
                        ['label' => 'Safety & Quality', 'url' => '/safety-quality'],
                    ]],
                    ['title' => 'Start a Project', 'links' => [
                        ['label' => '(02) 5550 0418', 'url' => 'tel:+61255500418'],
                        ['label' => 'projects@stonebridge.example', 'url' => 'mailto:projects@stonebridge.example'],
                        ['label' => '14 Foundry Street · Alexandria NSW', 'url' => '/contact'],
                    ]],
                ],
                'marketplace_variant' => 'stonebridge_industrial',
            ];
        }

        return [
            'type' => 'mega_footer',
            'brand' => $name,
            'description' => "Professional {$industry} services with a clear, customer-first experience.",
            'columns' => [
                ['title' => 'Company', 'links' => [['label' => 'About', 'url' => '/about'], ['label' => 'Contact', 'url' => '/contact']]],
                ['title' => 'Services', 'links' => [['label' => 'Our Services', 'url' => '/services'], ['label' => 'FAQ', 'url' => '/faq']]],
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function onboardingSchema(string $industry): array
    {
        return [
            ['key' => 'business_name', 'label' => 'Business name', 'type' => 'text', 'required' => true],
            ['key' => 'location', 'label' => 'Location / service area', 'type' => 'text', 'required' => true],
            ['key' => 'services', 'label' => 'Main services', 'type' => 'textarea', 'required' => true],
            ['key' => 'business_description', 'label' => 'Tell Luna about your business', 'type' => 'textarea', 'required' => false],
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'text', 'required' => false],
            ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['key' => 'logo', 'label' => 'Logo', 'type' => 'media', 'required' => false],
            ['key' => 'additional_instructions', 'label' => 'Anything else?', 'type' => 'textarea', 'required' => false],
            ['key' => 'industry', 'type' => 'hidden', 'default' => $industry],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function templates(): array
    {
        $starterPrice = (int) config('cosmic_marketplace.plans.starter.credit_price', 500);
        $growthPrice = (int) config('cosmic_marketplace.plans.growth.credit_price', 1000);
        $proPrice = (int) config('cosmic_marketplace.plans.pro.credit_price', 2000);

        return array_merge([
            [
                'slug' => 'ledger-start',
                'name' => 'LedgerPoint Accounting',
                'industry_slug' => 'accounting',
                'industry_label' => 'Accounting',
                'style_slug' => 'clean',
                'plan' => 'starter',
                'credit_price' => $starterPrice,
                'theme_key' => 'navy',
                'source_bundle_key' => 'finance-conversion',
                'marketplace_design' => [
                    'key' => 'ledger-executive',
                    'header_mode' => 'light',
                    'page' => '#f7f6f1', 'heading' => '#102b33', 'body' => '#60747a',
                    'primary' => '#0c6670', 'primary_hover' => '#09545d', 'accent' => '#d3b166',
                    'surface' => '#ffffff', 'surface_alt' => '#eef3f1', 'border' => '#dfe7e5',
                    'on_primary' => '#ffffff', 'button_primary' => '#0c6670', 'button_text' => '#ffffff',
                    'font_heading' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                    'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                    'font_display' => 'Georgia, Times New Roman, serif',
                ],
                'sort_order' => 10,
                'summary' => 'A clear, trustworthy accounting website for bookkeeping, tax, payroll, and advisory services.',
                'description' => 'A complete Starter website designed to turn accounting enquiries into consultations while keeping services easy to understand.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1497366754035-f200968a6e72?auto=format&fit=crop&w=1400&q=84',
                'features' => ['5-page website', 'AI content setup', 'Service overview', 'Consultation CTA', 'Hosting + website care'],
                'tags' => ['accounting', 'bookkeeping', 'finance', 'professional', 'clean'],
                'service_labels' => ['Bookkeeping', 'Tax & Compliance', 'Payroll', 'Business Advisory'],
                'pages' => [
                    $this->page('Home', 'home', 'home', ['marketplace_ledger_hero', 'marketplace_ledger_services', 'marketplace_ledger_story', 'marketplace_ledger_proof', 'marketplace_ledger_cta'], 'A conversion-ready overview of the firm, core services, advisory approach, proof, and consultation CTA.'),
                    $this->page('About', 'about', 'about', ['marketplace_ledger_page_hero', 'marketplace_ledger_story', 'marketplace_ledger_proof', 'marketplace_ledger_cta'], 'Introduce the firm, experience, values, and approach.'),
                    $this->page('Services', 'services', 'services', ['marketplace_ledger_page_hero', 'marketplace_ledger_services', 'marketplace_ledger_proof', 'marketplace_ledger_faq', 'marketplace_ledger_cta'], 'Explain bookkeeping, tax, payroll, and advisory services in one clear services hub.'),
                    $this->page('FAQ', 'faq', 'faq', ['marketplace_ledger_page_hero', 'marketplace_ledger_faq', 'marketplace_ledger_cta'], 'Answer common questions about onboarding, records, fees, timelines, and ongoing support.'),
                    $this->page('Contact', 'contact', 'contact', ['marketplace_ledger_page_hero', 'marketplace_ledger_contact', 'marketplace_ledger_cta'], 'Give prospective clients a simple path to request a consultation.'),
                ],
                'navigation' => [
                    $this->nav('Home', 'home'), $this->nav('About', 'about'), $this->nav('Services', 'services'),
                    $this->nav('FAQ', 'faq'), $this->nav('Contact', 'contact', true),
                ],
            ],
            [
                'slug' => 'bistro-classic',
                'name' => 'Ember & Olive',
                'version' => 2,
                'industry_slug' => 'restaurant',
                'industry_label' => 'Restaurant',
                'style_slug' => 'editorial',
                'plan' => 'growth',
                'credit_price' => $growthPrice,
                'theme_key' => 'espresso',
                'source_bundle_key' => 'restaurant-editorial',
                'marketplace_design' => [
                    'key' => 'ember-editorial',
                    'header_mode' => 'dark',
                    'page' => '#f5f0e7', 'heading' => '#201712', 'body' => '#6b6057',
                    'primary' => '#171713', 'primary_hover' => '#28251a', 'accent' => '#d5a153',
                    'surface' => '#f5f0e7', 'surface_alt' => '#34321b', 'border' => '#d4c6b4',
                    'on_primary' => '#f5eddf', 'button_primary' => '#d5a153', 'button_text' => '#17140f',
                    'font_heading' => 'Georgia, Times New Roman, serif',
                    'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                    'components' => [
                        'button_height' => '48px', 'button_px' => '28px', 'button_radius' => '3px', 'button_weight' => '700',
                        'input_height' => '44px', 'input_px' => '16px', 'input_radius' => '2px',
                        'card_radius' => '3px', 'image_radius' => '2px',
                    ],
                ],
                'sort_order' => 20,
                'summary' => 'An image-led restaurant website with menu pages, private dining, gallery, reviews, FAQs, and reservations.',
                'description' => 'A complete Growth hospitality website with a strong menu hierarchy and enough inner pages to tell the restaurant story properly.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=1400&q=84',
                'features' => ['10-page website', 'AI content setup', 'Menu subpages', 'Private dining', 'Reservation CTA', 'Website care'],
                'tags' => ['restaurant', 'bistro', 'hospitality', 'editorial', 'menu'],
                'service_labels' => ['Seasonal Menu', 'Private Dining', 'Group Bookings', 'Events'],
                'pages' => [
                    $this->page('Home', 'home', 'home', ['marketplace_ember_hero', 'marketplace_ember_feature', 'marketplace_ember_story', 'marketplace_ember_menu', 'marketplace_ember_private_dining', 'marketplace_ember_reviews', 'marketplace_ember_gallery', 'marketplace_ember_reservation', 'marketplace_ember_location'], 'Introduce the dining experience, signature dishes, private dining, guest proof, reservations, and location details.'),
                    $this->page('About', 'about', 'about', ['marketplace_ember_about'], 'Tell the story of the restaurant, chef, sourcing, hospitality philosophy, milestones, and team.'),
                    $this->page('Menu', 'menu', 'services', ['marketplace_ember_menu_page'], 'Present a complete seasonal menu with signature dishes, sourcing notes, gallery, and reservation call to action.'),
                    $this->page('Lunch', 'lunch', 'services', ['marketplace_ember_page_hero', 'marketplace_ember_menu', 'marketplace_ember_feature', 'marketplace_ember_cta'], 'Show lunch highlights, seasonal dishes, dietary notes, and booking options.', 'menu'),
                    $this->page('Dinner', 'dinner', 'services', ['marketplace_ember_page_hero', 'marketplace_ember_menu', 'marketplace_ember_gallery', 'marketplace_ember_cta'], 'Show dinner highlights, signature plates, drinks, and reservation options.', 'menu'),
                    $this->page('Private Dining', 'private-dining', 'services', ['marketplace_ember_private_dining_page'], 'Promote private dining with event formats, amenities, planning process, gallery, testimonial, and enquiry form.'),
                    $this->page('Gallery', 'gallery', 'portfolio', ['marketplace_ember_page_hero', 'marketplace_ember_gallery', 'marketplace_ember_story', 'marketplace_ember_cta'], 'Create an image-led view of food, atmosphere, team, and venue details.'),
                    $this->page('Reviews', 'reviews', 'testimonials', ['marketplace_ember_page_hero', 'marketplace_ember_reviews', 'marketplace_ember_gallery', 'marketplace_ember_cta'], 'Build trust with guest feedback and press or award highlights.'),
                    $this->page('FAQ', 'faq', 'faq', ['marketplace_ember_page_hero', 'marketplace_ember_feature', 'marketplace_ember_reviews', 'marketplace_ember_cta'], 'Answer booking, dietary, parking, group size, and event questions.'),
                    $this->page('Contact & Reservations', 'contact', 'contact', ['marketplace_ember_contact_page'], 'Make reservations, enquiries, directions, opening hours, visit information, and common questions easy to find.'),
                ],
                'navigation' => [
                    $this->nav('Home', 'home'), $this->nav('About', 'about'),
                    $this->nav('Menu', 'menu', false, [$this->nav('Lunch', 'lunch'), $this->nav('Dinner', 'dinner')]),
                    $this->nav('Private Dining', 'private-dining'), $this->nav('Gallery', 'gallery'),
                    $this->nav('Reviews', 'reviews'), $this->nav('FAQ', 'faq'), $this->nav('Reserve a Table', 'contact', true),
                ],
            ],
            [
                'slug' => 'smilecare',
                'name' => 'Brightline Dental',
                'industry_slug' => 'dental',
                'industry_label' => 'Dental Clinic',
                'style_slug' => 'premium',
                'plan' => 'growth',
                'credit_price' => $growthPrice,
                'theme_key' => 'ocean',
                'source_bundle_key' => 'dentist-conversion',
                'marketplace_design' => [
                    'key' => 'brightline-airy-clinic',
                    'header_mode' => 'light',
                    'page' => '#fbfdfd', 'heading' => '#17304a', 'body' => '#667d88',
                    'primary' => '#17304a', 'primary_hover' => '#10263b', 'accent' => '#2f86a6',
                    'surface' => '#ffffff', 'surface_alt' => '#eef7f8', 'border' => '#d9e8eb',
                    'on_primary' => '#ffffff', 'button_primary' => '#17304a', 'button_text' => '#ffffff',
                    'font_heading' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                    'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                    'font_display' => 'Georgia, Times New Roman, serif',
                    'components' => [
                        'button_radius' => 999, 'card_radius' => 28, 'image_radius' => 34,
                        'section_spacing' => 112, 'content_width' => 1380,
                        'header_variant' => 'brightline_clinic', 'footer_variant' => 'brightline_clinic',
                        'hero_style' => 'airy_split_clinic', 'card_style' => 'soft_clinical',
                    ],
                ],
                'sort_order' => 30,
                'summary' => 'A welcoming dental clinic website with treatment pages, team trust, patient information, FAQs, and booking.',
                'description' => 'A complete Growth clinic website designed to make treatments easy to understand and appointments easy to request.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1609840114035-3c981b782dfe?auto=format&fit=crop&w=1400&q=84',
                'features' => ['10-page website', 'AI content setup', 'Treatment subpages', 'Team page', 'Patient FAQs', 'Appointment CTA'],
                'tags' => ['dental', 'dentist', 'clinic', 'healthcare', 'premium'],
                'service_labels' => ['General Dentistry', 'Cosmetic Dentistry', 'Emergency Dental', 'Preventive Care'],
                'pages' => [
                    $this->page('Home', 'home', 'home', ['marketplace_dental_hero', 'marketplace_dental_treatments', 'marketplace_dental_comfort', 'marketplace_dental_process', 'marketplace_dental_team', 'marketplace_dental_proof', 'marketplace_dental_cta'], 'Introduce the clinic, treatments, patient journey, care team, patient proof, and booking CTA.'),
                    $this->page('About', 'about', 'about', ['marketplace_dental_page_hero', 'marketplace_dental_comfort', 'marketplace_dental_team', 'marketplace_dental_process', 'marketplace_dental_cta'], 'Explain the clinic approach, technology, comfort, and patient experience.'),
                    $this->page('Treatments', 'treatments', 'services', ['marketplace_dental_page_hero', 'marketplace_dental_treatments', 'marketplace_dental_process', 'marketplace_dental_faq', 'marketplace_dental_cta'], 'A treatment hub that links patients to the right dental service.'),
                    $this->page('General Dentistry', 'general-dentistry', 'services', ['marketplace_dental_page_hero', 'marketplace_dental_treatments', 'marketplace_dental_comfort', 'marketplace_dental_process', 'marketplace_dental_faq', 'marketplace_dental_cta'], 'Explain check-ups, cleans, fillings, prevention, and ongoing care.', 'treatments'),
                    $this->page('Cosmetic Dentistry', 'cosmetic-dentistry', 'services', ['marketplace_dental_page_hero', 'marketplace_dental_treatments', 'marketplace_dental_proof', 'marketplace_dental_comfort', 'marketplace_dental_faq', 'marketplace_dental_cta'], 'Present whitening, veneers, smile improvement, and consultation options.', 'treatments'),
                    $this->page('Emergency Dental', 'emergency-dental', 'services', ['marketplace_dental_page_hero', 'marketplace_dental_info', 'marketplace_dental_contact', 'marketplace_dental_faq'], 'Help urgent patients understand what to do and how to contact the clinic.', 'treatments'),
                    $this->page('Our Team', 'team', 'team', ['marketplace_dental_page_hero', 'marketplace_dental_team', 'marketplace_dental_comfort', 'marketplace_dental_proof', 'marketplace_dental_cta'], 'Introduce dentists, clinicians, and the patient-care team.'),
                    $this->page('Patient Information', 'patient-info', 'content', ['marketplace_dental_page_hero', 'marketplace_dental_info', 'marketplace_dental_process', 'marketplace_dental_faq', 'marketplace_dental_cta'], 'Explain first visits, payment, preparation, aftercare, and accessibility.'),
                    $this->page('FAQ', 'faq', 'faq', ['marketplace_dental_page_hero', 'marketplace_dental_faq', 'marketplace_dental_proof', 'marketplace_dental_cta'], 'Answer common questions about appointments, treatment, anxiety, fees, and emergencies.'),
                    $this->page('Contact & Book', 'contact', 'contact', ['marketplace_dental_page_hero', 'marketplace_dental_contact', 'marketplace_dental_info'], 'Make appointment requests, clinic directions, and contact details clear.'),
                ],
                'navigation' => [
                    $this->nav('Home', 'home'), $this->nav('About', 'about'),
                    $this->nav('Treatments', 'treatments', false, [
                        $this->nav('General Dentistry', 'general-dentistry'),
                        $this->nav('Cosmetic Dentistry', 'cosmetic-dentistry'),
                        $this->nav('Emergency Dental', 'emergency-dental'),
                    ]),
                    $this->nav('Our Team', 'team'), $this->nav('Patient Info', 'patient-info'),
                    $this->nav('FAQ', 'faq'), $this->nav('Book Appointment', 'contact', true),
                ],
            ],
            [
                'slug' => 'buildpro',
                'name' => 'Stonebridge Construction',
                'industry_slug' => 'construction',
                'industry_label' => 'Construction',
                'style_slug' => 'bold',
                'plan' => 'pro',
                'credit_price' => $proPrice,
                'theme_key' => 'asphalt',
                'source_bundle_key' => 'construction-showcase',
                'marketplace_design' => [
                    'key' => 'stonebridge-architectural',
                    'header_mode' => 'dark',
                    'page' => '#f6f3ed', 'heading' => '#17191b', 'body' => '#74716b',
                    'primary' => '#0f1112', 'primary_hover' => '#24282b', 'accent' => '#e66b2f',
                    'surface' => '#f6f3ed', 'surface_alt' => '#efe9df', 'border' => '#cfc8bd',
                    'on_primary' => '#ffffff', 'button_primary' => '#e66b2f', 'button_text' => '#ffffff',
                    'font_heading' => 'Arial Black, Inter, ui-sans-serif, system-ui, sans-serif',
                    'font_body' => 'Inter, ui-sans-serif, system-ui, sans-serif',
                    'font_display' => 'Arial Black, Inter, ui-sans-serif, system-ui, sans-serif',
                    'components' => [
                        'button_radius' => 0, 'card_radius' => 0, 'image_radius' => 0,
                        'section_spacing' => 120, 'content_width' => 1420,
                        'header_variant' => 'stonebridge_industrial', 'footer_variant' => 'stonebridge_industrial',
                        'hero_style' => 'industrial_cinematic', 'card_style' => 'architectural_grid',
                        'accent_usage' => 'safety_orange', 'surface_language' => 'paper_sand_charcoal',
                    ],
                ],
                'sort_order' => 40,
                'summary' => 'A complete contractor website with service subpages, projects, team, process, safety, proof, FAQs, and quote capture.',
                'description' => 'A Pro construction website with deeper project storytelling, premium visual Sparks, and a full lead-generation journey.',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=1400&q=84',
                'features' => ['15-page website', 'AI content setup', 'Service subpages', 'Project gallery', 'Premium Sparks', 'Quote request flow'],
                'tags' => ['construction', 'contractor', 'builder', 'projects', 'bold', 'pro'],
                'service_labels' => ['Commercial Construction', 'Residential Construction', 'Fit-outs', 'Project Management'],
                'pages' => [
                    $this->page('Home', 'home', 'home', ['marketplace_stone_hero', 'marketplace_stone_capabilities', 'marketplace_stone_manifesto', 'marketplace_stone_metrics', 'marketplace_stone_process', 'marketplace_stone_projects', 'marketplace_stone_case_study', 'marketplace_stone_testimonial', 'marketplace_stone_cta'], 'Position the contractor around capability, delivery philosophy, measurable proof, featured project storytelling, trust, and quote conversion.'),
                    $this->page('About', 'about', 'about', ['marketplace_stone_page_hero', 'marketplace_stone_manifesto', 'marketplace_stone_metrics', 'marketplace_stone_team', 'marketplace_stone_cta'], 'Tell the company story, values, capabilities, credentials, and delivery philosophy.'),
                    $this->page('Services', 'services', 'services', ['marketplace_stone_page_hero', 'marketplace_stone_capabilities', 'marketplace_stone_manifesto', 'marketplace_stone_process', 'marketplace_stone_cta'], 'A visual service hub for construction capabilities and specialist work.'),
                    $this->page('Commercial Construction', 'commercial-construction', 'services', ['marketplace_stone_page_hero', 'marketplace_stone_capabilities', 'marketplace_stone_projects', 'marketplace_stone_metrics', 'marketplace_stone_cta'], 'Present commercial construction capability, sectors, delivery, and project proof.', 'services'),
                    $this->page('Residential Construction', 'residential-construction', 'services', ['marketplace_stone_page_hero', 'marketplace_stone_manifesto', 'marketplace_stone_projects', 'marketplace_stone_testimonial', 'marketplace_stone_cta'], 'Show residential builds, renovations, quality, and customer experience.', 'services'),
                    $this->page('Fit-outs', 'fit-outs', 'services', ['marketplace_stone_page_hero', 'marketplace_stone_capabilities', 'marketplace_stone_process', 'marketplace_stone_metrics', 'marketplace_stone_cta'], 'Explain commercial fit-outs, refurbishments, coordination, and delivery.', 'services'),
                    $this->page('Project Management', 'project-management', 'services', ['marketplace_stone_page_hero', 'marketplace_stone_process', 'marketplace_stone_manifesto', 'marketplace_stone_metrics', 'marketplace_stone_cta'], 'Explain planning, procurement, communication, risk control, and project delivery.', 'services'),
                    $this->page('Projects', 'projects', 'portfolio', ['marketplace_stone_page_hero', 'marketplace_stone_projects', 'marketplace_stone_case_study', 'marketplace_stone_metrics', 'marketplace_stone_cta'], 'Show completed work across sectors with strong visual project stories.'),
                    $this->page('Featured Project', 'featured-project', 'case-studies', ['marketplace_stone_page_hero', 'marketplace_stone_case_study', 'marketplace_stone_manifesto', 'marketplace_stone_metrics', 'marketplace_stone_testimonial', 'marketplace_stone_cta'], 'A detailed project case study with challenge, process, result, imagery, and proof.', 'projects'),
                    $this->page('Our Team', 'team', 'team', ['marketplace_stone_page_hero', 'marketplace_stone_team', 'marketplace_stone_manifesto', 'marketplace_stone_testimonial', 'marketplace_stone_cta'], 'Introduce leadership, project managers, and the people behind delivery.'),
                    $this->page('Our Process', 'process', 'process', ['marketplace_stone_page_hero', 'marketplace_stone_process', 'marketplace_stone_metrics', 'marketplace_stone_testimonial', 'marketplace_stone_cta'], 'Walk prospects through planning, pre-construction, build, handover, and support.'),
                    $this->page('Safety & Quality', 'safety-quality', 'proof', ['marketplace_stone_page_hero', 'marketplace_stone_safety', 'marketplace_stone_metrics', 'marketplace_stone_manifesto', 'marketplace_stone_cta'], 'Explain safety systems, quality assurance, certifications, and accountability.'),
                    $this->page('Testimonials', 'testimonials', 'testimonials', ['marketplace_stone_page_hero', 'marketplace_stone_testimonial', 'marketplace_stone_metrics', 'marketplace_stone_projects', 'marketplace_stone_cta'], 'Build confidence with client stories, repeat work, and measurable proof.'),
                    $this->page('FAQ', 'faq', 'faq', ['marketplace_stone_page_hero', 'marketplace_stone_faq', 'marketplace_stone_safety', 'marketplace_stone_contact', 'marketplace_stone_cta'], 'Answer questions about quoting, schedules, design, variations, safety, and project handover.'),
                    $this->page('Request a Quote', 'contact', 'contact', ['marketplace_stone_page_hero', 'marketplace_stone_contact', 'marketplace_stone_metrics'], 'Capture qualified project enquiries with clear contact and project-detail prompts.'),
                ],
                'navigation' => [
                    $this->nav('Home', 'home'), $this->nav('About', 'about'),
                    $this->nav('Services', 'services', false, [
                        $this->nav('Commercial', 'commercial-construction'), $this->nav('Residential', 'residential-construction'),
                        $this->nav('Fit-outs', 'fit-outs'), $this->nav('Project Management', 'project-management'),
                    ]),
                    $this->nav('Projects', 'projects', false, [$this->nav('Featured Project', 'featured-project')]),
                    $this->nav('Team', 'team'), $this->nav('Process', 'process'),
                    $this->nav('Safety & Quality', 'safety-quality'), $this->nav('Testimonials', 'testimonials'),
                    $this->nav('FAQ', 'faq'), $this->nav('Request a Quote', 'contact', true),
                ],
            ],
        ], $this->additionalTemplates($starterPrice, $growthPrice, $proPrice));
    }

    /** @return array<string, mixed> */
    private function page(string $name, string $slug, string $intent, array $recipe, string $summary, ?string $parent = null): array
    {
        return compact('name', 'slug', 'intent', 'recipe', 'summary', 'parent');
    }

    /** @return array<string, mixed> */
    private function nav(string $label, string $page, bool $isCta = false, array $children = []): array
    {
        return ['label' => $label, 'page' => $page, 'is_cta' => $isCta, 'children' => $children];
    }
}
