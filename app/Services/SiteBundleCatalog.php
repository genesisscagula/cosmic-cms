<?php

namespace App\Services;

use App\AI\Registries\IndustryMenuRegistry;
use Illuminate\Support\Str;

/**
 * Central registry for whole-site recipes.
 *
 * Bundles reference the audited page-template library; they never copy template
 * definitions. This keeps 220 site recipes maintainable while every visual
 * composition still has one canonical source in PageTemplateCatalog.
 */
final class SiteBundleCatalog
{
    public const EXPECTED_BUNDLE_COUNT = 220;
    public const TEMPLATE_CANDIDATES_PER_BUNDLE = 18;

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        static $bundles;

        if (is_array($bundles)) {
            return $bundles;
        }

        // Normalizing the searchable metadata once is important: this catalog is
        // used during the public trial flow, so repeating string preparation for
        // every industry/archetype pair made the old 100-bundle catalog slow on
        // a cold request. The prepared fields are stripped before returning.
        $templates = array_map(
            fn (array $template): array => $this->prepareTemplate($template),
            PageTemplateCatalog::plannerIndex(),
        );
        $bundles = [];
        $usedCandidateSignatures = [];

        foreach ($this->industries() as $industryKey => $industry) {
            foreach ($this->archetypes() as $archetypeKey => $archetype) {
                $selectionVariant = 0;
                do {
                    $candidateTemplates = $this->candidateTemplates(
                        $templates,
                        $industryKey,
                        $industry,
                        $archetypeKey,
                        $archetype,
                        $selectionVariant,
                    );
                    $candidateKeys = array_column($candidateTemplates, 'key');
                    $candidateSignature = $this->candidateSignature($candidateKeys);
                    $selectionVariant++;
                } while (isset($usedCandidateSignatures[$candidateSignature]) && $selectionVariant < 25);

                if (isset($usedCandidateSignatures[$candidateSignature])) {
                    throw new \RuntimeException("Unable to create a unique candidate composition for [{$industryKey}-{$archetypeKey}].");
                }
                $usedCandidateSignatures[$candidateSignature] = true;

                $pages = $this->pages($industryKey, $archetypeKey, $candidateTemplates);

                $pageSignature = sha1(implode('|', array_map(
                    fn (array $page): string => ($page['slug'] ?? '').':'.($page['page_intent'] ?? ''),
                    $pages,
                )));

                $bundles[] = [
                    'key' => $industryKey.'-'.$archetypeKey,
                    'name' => $industry['label'].' · '.$archetype['label'],
                    'description' => $archetype['description'].' for '.$industry['label'].'.',
                    'industry' => $industryKey,
                    'industry_label' => $industry['label'],
                    'industry_aliases' => $industry['aliases'],
                    'archetype' => $archetypeKey,
                    'archetype_label' => $archetype['label'],
                    'intent_terms' => $archetype['terms'],
                    'design_contract' => [
                        'tone' => $archetype['tone'],
                        'goal' => $archetype['goal'],
                        'shared_theme' => true,
                        'shared_typography' => true,
                        'shared_spacing' => true,
                        'distinct_page_compositions' => true,
                        'customizable_section_slots' => true,
                        'custom_slot_policy' => 'registered_first_flex_fallback',
                        'visual_direction' => $archetype['visual_direction'],
                    ],
                    'pages' => $pages,
                    'page_count' => count($pages),
                    'template_candidates' => $candidateKeys,
                    'template_count' => count($candidateTemplates),
                    'candidate_signature' => $candidateSignature,
                    'composition_signature' => sha1($candidateSignature.'|'.$pageSignature),
                    'selection_variant' => $selectionVariant - 1,
                    'version' => 2,
                ];
            }
        }

        return $bundles;
    }

    public function find(string $key): ?array
    {
        return collect($this->all())->firstWhere('key', $key);
    }

    /** @return array<string, array{label:string,aliases:array<int,string>}> */
    public function industries(): array
    {
        return [
            'automotive' => ['label' => 'Automotive', 'aliases' => ['automotive', 'car', 'vehicle', 'garage', 'dealership']],
            'bakery' => ['label' => 'Bakery', 'aliases' => ['bakery', 'baker', 'pastry', 'bread', 'cake']],
            'cleaning' => ['label' => 'Cleaning', 'aliases' => ['cleaning', 'cleaner', 'janitorial', 'housekeeping']],
            'coffee' => ['label' => 'Coffee Shop', 'aliases' => ['coffee', 'cafe', 'café', 'roastery']],
            'construction' => ['label' => 'Construction', 'aliases' => ['construction', 'contractor', 'builder', 'renovation']],
            'dentist' => ['label' => 'Dental Clinic', 'aliases' => ['dental', 'dentist', 'dentistry', 'orthodontic']],
            'education' => ['label' => 'Education', 'aliases' => ['education', 'school', 'academy', 'course', 'training']],
            'electrician' => ['label' => 'Electrician', 'aliases' => ['electrician', 'electrical', 'wiring', 'power']],
            'finance' => ['label' => 'Finance', 'aliases' => ['finance', 'financial', 'accounting', 'insurance', 'investment']],
            'fitness' => ['label' => 'Fitness', 'aliases' => ['fitness', 'gym', 'workout', 'personal training']],
            'hotel' => ['label' => 'Hotel & Resort', 'aliases' => ['hotel', 'resort', 'hospitality', 'accommodation']],
            'landscaping' => ['label' => 'Landscaping', 'aliases' => ['landscaping', 'garden', 'lawn', 'outdoor']],
            'lawyer' => ['label' => 'Law Firm', 'aliases' => ['law', 'lawyer', 'attorney', 'legal']],
            'medical' => ['label' => 'Medical Clinic', 'aliases' => ['medical', 'clinic', 'healthcare', 'doctor']],
            'plumbing' => ['label' => 'Plumbing', 'aliases' => ['plumbing', 'plumber', 'drain', 'pipe']],
            'real-estate' => ['label' => 'Real Estate', 'aliases' => ['real estate', 'property', 'realtor', 'homes']],
            'restaurant' => ['label' => 'Restaurant', 'aliases' => ['restaurant', 'dining', 'food', 'bistro', 'eatery']],
            'roofing' => ['label' => 'Roofing', 'aliases' => ['roofing', 'roofer', 'roof', 'gutter']],
            'salon' => ['label' => 'Salon & Beauty', 'aliases' => ['salon', 'beauty', 'spa', 'hair', 'skincare']],
            'technology' => ['label' => 'Technology', 'aliases' => ['technology', 'software', 'saas', 'ai', 'startup']],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function archetypes(): array
    {
        return [
            'conversion' => [
                'label' => 'Conversion',
                'description' => 'A direct journey built around enquiries, bookings and action',
                'terms' => ['book', 'reserve', 'appointment', 'lead', 'contact', 'quote', 'conversion', 'sales'],
                'style' => ['conversion', 'focused', 'clear', 'service'],
                'section_terms' => ['contact', 'cta', 'pricing', 'faq', 'form', 'services'],
                'visual_direction' => 'direct hierarchy, concise proof and frequent action points',
                'tone' => 'clear, confident and action-oriented',
                'goal' => 'convert qualified visitors',
            ],
            'editorial' => [
                'label' => 'Editorial Story',
                'description' => 'A spacious brand story with human, image-led pacing',
                'terms' => ['editorial', 'story', 'minimal', 'brand', 'heritage', 'craft', 'journal'],
                'style' => ['editorial', 'minimal', 'story', 'image-led'],
                'section_terms' => ['story', 'editorial', 'image', 'quote', 'timeline', 'values'],
                'visual_direction' => 'generous whitespace, narrative pacing and selective imagery',
                'tone' => 'editorial, warm and considered',
                'goal' => 'build brand affinity through story',
            ],
            'showcase' => [
                'label' => 'Visual Showcase',
                'description' => 'A media-forward experience for products, places and portfolios',
                'terms' => ['gallery', 'portfolio', 'visual', 'photos', 'luxury', 'cinematic', 'showcase'],
                'style' => ['visual', 'cinematic', 'gallery', 'premium'],
                'section_terms' => ['gallery', 'slider', 'portfolio', 'image', 'showcase', 'project'],
                'visual_direction' => 'cinematic hero, immersive media and gallery-led discovery',
                'tone' => 'visual, immersive and premium',
                'goal' => 'showcase the strongest work and imagery',
            ],
            'authority' => [
                'label' => 'Authority',
                'description' => 'A credibility-led structure for expertise, proof and trust',
                'terms' => ['trust', 'expert', 'professional', 'corporate', 'authority', 'proof', 'team'],
                'style' => ['authority', 'professional', 'structured', 'proof'],
                'section_terms' => ['stats', 'team', 'testimonial', 'proof', 'case', 'credential'],
                'visual_direction' => 'structured expertise, measurable proof and reassuring clarity',
                'tone' => 'credible, polished and reassuring',
                'goal' => 'establish expertise and reduce buyer risk',
            ],
            'growth' => [
                'label' => 'Comprehensive Growth',
                'description' => 'A complete content-rich site for discovery and long-term growth',
                'terms' => ['complete', 'full website', 'growth', 'seo', 'resources', 'pricing', 'faq'],
                'style' => ['comprehensive', 'balanced', 'content-rich', 'modern'],
                'section_terms' => ['services', 'features', 'process', 'faq', 'resources', 'cta'],
                'visual_direction' => 'balanced content depth with clear discovery paths',
                'tone' => 'comprehensive, modern and easy to explore',
                'goal' => 'support discovery, evaluation and conversion',
            ],
            'boutique' => [
                'label' => 'Boutique Premium',
                'description' => 'A high-touch signature experience with refined visual restraint',
                'terms' => ['boutique', 'premium', 'luxury', 'bespoke', 'high-end', 'exclusive', 'signature', 'concierge', 'refined'],
                'style' => ['premium', 'luxury', 'signature', 'polished', 'immersive'],
                'section_terms' => ['hero', 'image', 'story', 'gallery', 'testimonial', 'cta'],
                'visual_direction' => 'luxury framing, signature moments and restrained conversion',
                'tone' => 'refined, high-touch and quietly confident',
                'goal' => 'position the business as a distinctive premium choice',
            ],
            'local' => [
                'label' => 'Local Favorite',
                'description' => 'A welcoming local-first journey grounded in proximity and trust',
                'terms' => ['local', 'near me', 'service area', 'neighborhood', 'neighbourhood', 'nearby', 'local business'],
                'style' => ['local', 'friendly', 'welcoming', 'trust-led', 'clear'],
                'section_terms' => ['review', 'location', 'map', 'contact', 'services', 'faq'],
                'visual_direction' => 'approachable service details, local proof and fast contact paths',
                'tone' => 'friendly, familiar and dependable',
                'goal' => 'turn nearby visitors into confident local customers',
            ],
            'product' => [
                'label' => 'Offerings Explorer',
                'description' => 'A structured product and service journey made for comparison',
                'terms' => ['product-led', 'product catalog', 'catalog', 'compare plans', 'comparison', 'packages', 'pricing plans', 'membership plans'],
                'style' => ['product-led', 'interactive', 'structured', 'clear', 'modern'],
                'section_terms' => ['feature', 'comparison', 'pricing', 'tabs', 'faq', 'product'],
                'visual_direction' => 'scannable offerings, interactive comparison and decision support',
                'tone' => 'clear, useful and product-confident',
                'goal' => 'help visitors compare offerings and choose the right fit',
            ],
            'community' => [
                'label' => 'Community Story',
                'description' => 'A people-led experience centered on belonging, values and participation',
                'terms' => ['community', 'our people', 'our values', 'mission-led', 'inclusive', 'member stories', 'community events'],
                'style' => ['community-led', 'personal', 'warm', 'story-led', 'friendly'],
                'section_terms' => ['team', 'testimonial', 'story', 'event', 'logo', 'review'],
                'visual_direction' => 'human stories, shared values and welcoming participation',
                'tone' => 'human, inclusive and optimistic',
                'goal' => 'build belonging and motivate participation',
            ],
            'launch' => [
                'label' => 'Launch Campaign',
                'description' => 'A bold campaign journey for launches, promotions and timely offers',
                'terms' => ['launch', 'campaign', 'promotion', 'grand opening', 'coming soon', 'waitlist', 'limited-time', 'special offer'],
                'style' => ['bold', 'energetic', 'dynamic', 'motion', 'conversion-focused'],
                'section_terms' => ['hero', 'countdown', 'cta', 'pricing', 'stats', 'faq'],
                'visual_direction' => 'bold momentum, focused messaging and time-sensitive action',
                'tone' => 'energetic, concise and action-forward',
                'goal' => 'concentrate attention around a timely campaign action',
            ],
            'signature-system' => [
                'label' => 'Signature System',
                'description' => 'A distinctive low-image journey built from editorial chapters, connected offers, process maps and evidence',
                'terms' => ['signature system', 'low image', 'editorial system', 'connected offer', 'service map', 'evidence ledger', 'distinctive'],
                'style' => ['signature-system', 'low-image', 'graphic', 'editorial', 'structured'],
                'section_terms' => ['chapter_index', 'orbit_map', 'constellation', 'staircase', 'evidence_ledger', 'decision_tree', 'ticket', 'availability_board'],
                'visual_direction' => 'one selective visual opening followed by graphic, low-image information systems and varied section silhouettes',
                'tone' => 'distinctive, clear and quietly confident',
                'goal' => 'make a complete offer memorable without relying on image-heavy repetition',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function prepareTemplate(array $template): array
    {
        $industryText = Str::lower(implode(' ', $template['industries'] ?? []));
        $intentText = Str::lower(implode(' ', $template['page_intents'] ?? []));
        $styleText = Str::lower(implode(' ', $template['style'] ?? []));
        $featureText = Str::lower(implode(' ', $template['features'] ?? []));
        $sectionText = Str::lower(implode(' ', $template['sections'] ?? []));

        return [
            ...$template,
            '_bundle_industry_text' => $industryText,
            '_bundle_intent_text' => $intentText,
            '_bundle_style_feature_text' => $styleText.' '.$featureText,
            '_bundle_section_text' => $sectionText,
            '_bundle_all_text' => implode(' ', [
                $industryText,
                $intentText,
                $styleText,
                $featureText,
                $sectionText,
                Str::lower((string) ($template['description'] ?? '')),
            ]),
            '_bundle_section_set' => array_fill_keys(array_values(array_unique($template['sections'] ?? [])), true),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function candidateTemplates(
        array $templates,
        string $industryKey,
        array $industry,
        string $archetypeKey,
        array $archetype,
        int $selectionVariant = 0,
    ): array {
        $industryTerms = array_map('strtolower', [$industryKey, $industry['label'], ...$industry['aliases']]);
        $archetypeTerms = array_map('strtolower', [$archetypeKey, ...$archetype['terms'], ...$archetype['style']]);
        $sectionTerms = array_map('strtolower', $archetype['section_terms'] ?? []);

        $ranked = collect($templates)
            ->map(function (array $template) use ($industryTerms, $archetypeTerms, $sectionTerms, $industryKey, $archetypeKey): array {
                $industryText = (string) ($template['_bundle_industry_text'] ?? '');
                $intentText = (string) ($template['_bundle_intent_text'] ?? '');
                $styleFeatureText = (string) ($template['_bundle_style_feature_text'] ?? '');
                $sectionText = (string) ($template['_bundle_section_text'] ?? '');
                $allText = (string) ($template['_bundle_all_text'] ?? '');
                $score = 0.0;

                foreach ($industryTerms as $term) {
                    if ($term !== '' && str_contains($industryText, $term)) {
                        $score += 16;
                    } elseif ($term !== '' && str_contains($allText, $term)) {
                        $score += 6;
                    }
                }
                foreach ($archetypeTerms as $term) {
                    if ($term !== '' && str_contains($intentText, $term)) {
                        $score += 8;
                    } elseif ($term !== '' && str_contains($styleFeatureText, $term)) {
                        $score += 5;
                    } elseif ($term !== '' && str_contains($allText, $term)) {
                        $score += 2;
                    }
                }
                foreach ($sectionTerms as $term) {
                    if ($term !== '' && str_contains($sectionText, $term)) {
                        $score += 3;
                    }
                }

                // A named bundle direction must materially influence selection.
                // Without this guard, high-volume legacy industry templates can
                // outrank every purpose-built low-image Signature System even
                // when the prompt explicitly asks for that composition family.
                if ($archetypeKey === 'signature-system') {
                    $score += str_contains($styleFeatureText, 'signature-system') ? 56 : 0;
                    $score += collect($sectionTerms)
                        ->filter(fn (string $term): bool => $term !== '' && str_contains($sectionText, $term))
                        ->count() * 4;
                }

                $score += ((int) ($template['quality_score'] ?? 0)) / 12;
                $score += ((int) ($template['visual_score'] ?? 0)) / 25;
                $score += ((int) ($template['composition_novelty_score'] ?? 50)) / 22;
                $score += ($template['quality_status'] ?? '') === 'excellent' ? 5 : 0;
                $score -= ($template['quality_status'] ?? '') === 'invalid' ? 100 : 0;
                // A small stable tiebreaker keeps thin-industry bundles from
                // collapsing to the same top 18 without overpowering relevance.
                $hash = hexdec(substr(sha1($industryKey.'|'.$archetypeKey.'|'.($template['key'] ?? '')), 0, 6));
                $score += ($hash % 1000) / 1000;

                return [...$template, '_bundle_score' => $score];
            })
            ->sortByDesc('_bundle_score')
            ->values()
            ->all();

        // Only used when two semantic directions naturally resolve to the same
        // top 18. Removing one high-ranked candidate lets MMR admit the next-best
        // composition while preserving the rest of the relevance ordering.
        if ($selectionVariant > 0 && count($ranked) > self::TEMPLATE_CANDIDATES_PER_BUNDLE) {
            $dropIndex = ($selectionVariant - 1) % min(24, count($ranked));
            array_splice($ranked, $dropIndex, 1);
        }

        return $this->diverseSelection($ranked, self::TEMPLATE_CANDIDATES_PER_BUNDLE);
    }

    /** @return array<int, array<string, mixed>> */
    private function diverseSelection(array $ranked, int $limit): array
    {
        $selected = [];
        $remaining = array_map(
            fn (array $template): array => [...$template, '_bundle_max_overlap' => 0.0],
            array_values($ranked),
        );

        while ($remaining !== [] && count($selected) < $limit) {
            $bestIndex = 0;
            $bestScore = -INF;

            foreach ($remaining as $index => $candidate) {
                $score = (float) ($candidate['_bundle_score'] ?? 0)
                    - ((float) ($candidate['_bundle_max_overlap'] ?? 0) * 11)
                    + (((int) ($candidate['composition_novelty_score'] ?? 50)) / 28);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $index;
                }
            }

            $chosen = $remaining[$bestIndex];
            $selected[] = $chosen;
            array_splice($remaining, $bestIndex, 1);

            // Incremental maximum overlap changes an O(limit² × catalog)
            // selection into O(limit × catalog) without changing the MMR rule.
            foreach ($remaining as &$candidate) {
                $candidate['_bundle_max_overlap'] = max(
                    (float) ($candidate['_bundle_max_overlap'] ?? 0),
                    $this->sectionSimilaritySets(
                        (array) ($candidate['_bundle_section_set'] ?? []),
                        (array) ($chosen['_bundle_section_set'] ?? []),
                    ),
                );
            }
            unset($candidate);
        }

        return array_map(function (array $template): array {
            foreach (array_keys($template) as $key) {
                if (str_starts_with((string) $key, '_bundle_')) {
                    unset($template[$key]);
                }
            }
            return $template;
        }, $selected);
    }

    /** @return array<int, array<string, mixed>> */
    private function pages(string $industry, string $archetype, array $templates): array
    {
        $titles = collect(IndustryMenuRegistry::for($industry))->pluck('title')->all();
        $contactIndex = array_search('Contact', $titles, true);

        $extra = match ($archetype) {
            'editorial' => 'Our Story',
            'showcase' => $this->showcasePage($industry),
            'authority' => $this->authorityPage($industry),
            'growth' => 'FAQ',
            'boutique' => 'Signature Experience',
            'local' => $this->localPage($industry),
            'product' => $this->offeringsPage($industry),
            'community' => 'Community',
            'launch' => 'Special Offer',
            'signature-system' => 'How It Works',
            default => null,
        };

        if ($extra && ! collect($titles)->contains(fn (string $title) => Str::lower($title) === Str::lower($extra))) {
            $insertAt = $contactIndex === false ? count($titles) : $contactIndex;
            array_splice($titles, $insertAt, 0, [$extra]);
        }

        $titles = array_slice(array_values(array_unique($titles)), 0, 7);
        $candidateCount = count($templates);

        return collect($titles)->map(function (string $title, int $index) use ($templates, $candidateCount): array {
            $isHome = $index === 0;
            $slug = $isHome ? 'home' : (Str::slug($title) ?: 'page-'.($index + 1));
            $pageIntent = $this->pageIntent($title, $isHome);
            $candidateKeys = [];

            $candidateKeys = collect($templates)
                ->map(function (array $template, int $templateIndex) use ($pageIntent, $index, $candidateCount): array {
                    $haystack = Str::lower(implode(' ', [
                        implode(' ', $template['page_intents'] ?? []),
                        implode(' ', $template['features'] ?? []),
                        implode(' ', $template['sections'] ?? []),
                    ]));
                    $score = $this->pageCandidateScore($haystack, $pageIntent);
                    // Rotate equal matches per page so pages do not all inherit
                    // the same five choices from a bundle.
                    $score += (($templateIndex + ($index * 7)) % max(1, $candidateCount)) / 1000;
                    return ['key' => (string) $template['key'], 'score' => $score];
                })
                ->sortByDesc('score')
                ->take(min(5, $candidateCount))
                ->pluck('key')
                ->values()
                ->all();

            return [
                'title' => $title,
                'slug' => $slug,
                'is_home' => $isHome,
                'sort_order' => $index + 1,
                'page_type' => 'standard',
                'page_intent' => $pageIntent,
                'candidate_template_keys' => array_values(array_unique($candidateKeys)),
            ];
        })->all();
    }

    private function pageIntent(string $title, bool $isHome): string
    {
        if ($isHome) {
            return 'home';
        }

        $slug = Str::slug($title);
        return match (true) {
            Str::contains($slug, ['contact', 'appointment', 'reservation', 'book']) => 'conversion',
            Str::contains($slug, ['special-offer', 'launch', 'waitlist']) => 'conversion',
            Str::contains($slug, ['gallery', 'project', 'property', 'destination', 'inventory']) => 'showcase',
            Str::contains($slug, ['testimonial', 'team', 'doctor', 'trainer', 'agent']) => 'proof',
            Str::contains($slug, ['menu', 'service', 'product', 'room', 'course', 'program', 'package', 'pricing', 'membership', 'treatment', 'order-online']) => 'offerings',
            Str::contains($slug, ['about', 'story', 'signature-experience']) => 'story',
            Str::contains($slug, ['community', 'event']) => 'proof',
            Str::contains($slug, ['location', 'service-area']) => 'local',
            Str::contains($slug, ['faq', 'resource']) => 'education',
            Str::contains($slug, ['how-it-works', 'approach', 'process']) => 'process',
            default => 'information',
        };
    }

    private function showcasePage(string $industry): string
    {
        return match ($industry) {
            'construction', 'electrician', 'landscaping', 'plumbing', 'roofing' => 'Projects',
            'real-estate' => 'Featured Properties',
            'technology' => 'Case Studies',
            default => 'Gallery',
        };
    }

    private function authorityPage(string $industry): string
    {
        return match ($industry) {
            'dentist', 'medical' => 'Doctors',
            'fitness' => 'Trainers',
            'lawyer', 'finance', 'technology' => 'Team',
            default => 'Testimonials',
        };
    }

    private function localPage(string $industry): string
    {
        return match ($industry) {
            'automotive', 'cleaning', 'construction', 'electrician', 'landscaping', 'plumbing', 'roofing' => 'Service Areas',
            default => 'Locations',
        };
    }

    private function offeringsPage(string $industry): string
    {
        return match ($industry) {
            'automotive' => 'Inventory',
            'bakery', 'coffee', 'restaurant' => 'Order Online',
            'education' => 'Programs',
            'fitness' => 'Memberships',
            'hotel' => 'Rooms & Suites',
            'real-estate' => 'Properties',
            'salon' => 'Treatments',
            'technology' => 'Product',
            default => 'Services & Pricing',
        };
    }

    private function pageCandidateScore(string $haystack, string $pageIntent): float
    {
        $terms = match ($pageIntent) {
            'conversion' => ['contact', 'cta', 'booking', 'enquiry', 'form', 'pricing'],
            'showcase' => ['gallery', 'portfolio', 'project', 'case stud', 'slider', 'image'],
            'proof' => ['testimonial', 'review', 'team', 'stats', 'proof', 'logo'],
            'offerings' => ['service', 'product', 'feature', 'pricing', 'comparison', 'menu'],
            'story' => ['story', 'about', 'timeline', 'values', 'editorial'],
            'education' => ['faq', 'resource', 'process', 'how'],
            'local' => ['location', 'map', 'contact', 'service', 'review'],
            default => ['feature', 'story', 'services'],
        };

        return collect($terms)->sum(fn (string $term): int => str_contains($haystack, $term) ? 6 : 0);
    }

    /** @param array<int, string> $keys */
    private function candidateSignature(array $keys): string
    {
        sort($keys, SORT_STRING);
        return sha1(implode('|', $keys));
    }

    /** @param array<string, bool> $left @param array<string, bool> $right */
    private function sectionSimilaritySets(array $left, array $right): float
    {
        $unionCount = count($left + $right);

        return $unionCount === 0 ? 1.0 : count(array_intersect_key($left, $right)) / $unionCount;
    }
}
