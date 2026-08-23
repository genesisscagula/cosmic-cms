<?php

namespace App\Services;

use App\AI\Registries\IndustryMenuRegistry;
use Illuminate\Support\Str;

/**
 * Central registry for whole-site recipes.
 *
 * Bundles reference the audited page-template library; they never copy template
 * definitions. This keeps 100 site recipes maintainable while every visual
 * composition still has one canonical source in PageTemplateCatalog.
 */
final class SiteBundleCatalog
{
    public const EXPECTED_BUNDLE_COUNT = 100;
    public const TEMPLATE_CANDIDATES_PER_BUNDLE = 18;

    /** @return array<int, array<string, mixed>> */
    public function all(): array
    {
        static $bundles;

        if (is_array($bundles)) {
            return $bundles;
        }

        $templates = PageTemplateCatalog::plannerIndex();
        $bundles = [];

        foreach ($this->industries() as $industryKey => $industry) {
            foreach ($this->archetypes() as $archetypeKey => $archetype) {
                $candidateTemplates = $this->candidateTemplates(
                    $templates,
                    $industryKey,
                    $industry,
                    $archetypeKey,
                    $archetype,
                );
                $pages = $this->pages($industryKey, $archetypeKey, $candidateTemplates);

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
                    ],
                    'pages' => $pages,
                    'page_count' => count($pages),
                    'template_candidates' => array_column($candidateTemplates, 'key'),
                    'template_count' => count($candidateTemplates),
                    'version' => 1,
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
                'tone' => 'clear, confident and action-oriented',
                'goal' => 'convert qualified visitors',
            ],
            'editorial' => [
                'label' => 'Editorial Story',
                'description' => 'A spacious brand story with human, image-led pacing',
                'terms' => ['editorial', 'story', 'minimal', 'brand', 'heritage', 'craft', 'journal'],
                'style' => ['editorial', 'minimal', 'story', 'image-led'],
                'tone' => 'editorial, warm and considered',
                'goal' => 'build brand affinity through story',
            ],
            'showcase' => [
                'label' => 'Visual Showcase',
                'description' => 'A media-forward experience for products, places and portfolios',
                'terms' => ['gallery', 'portfolio', 'visual', 'photos', 'luxury', 'cinematic', 'showcase'],
                'style' => ['visual', 'cinematic', 'gallery', 'premium'],
                'tone' => 'visual, immersive and premium',
                'goal' => 'showcase the strongest work and imagery',
            ],
            'authority' => [
                'label' => 'Authority',
                'description' => 'A credibility-led structure for expertise, proof and trust',
                'terms' => ['trust', 'expert', 'professional', 'corporate', 'authority', 'proof', 'team'],
                'style' => ['authority', 'professional', 'structured', 'proof'],
                'tone' => 'credible, polished and reassuring',
                'goal' => 'establish expertise and reduce buyer risk',
            ],
            'growth' => [
                'label' => 'Comprehensive Growth',
                'description' => 'A complete content-rich site for discovery and long-term growth',
                'terms' => ['complete', 'full website', 'growth', 'seo', 'resources', 'pricing', 'faq'],
                'style' => ['comprehensive', 'balanced', 'content-rich', 'modern'],
                'tone' => 'comprehensive, modern and easy to explore',
                'goal' => 'support discovery, evaluation and conversion',
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function candidateTemplates(
        array $templates,
        string $industryKey,
        array $industry,
        string $archetypeKey,
        array $archetype,
    ): array {
        $industryTerms = array_map('strtolower', [$industryKey, $industry['label'], ...$industry['aliases']]);
        $archetypeTerms = array_map('strtolower', [$archetypeKey, ...$archetype['terms'], ...$archetype['style']]);

        $ranked = collect($templates)
            ->map(function (array $template) use ($industryTerms, $archetypeTerms): array {
                $industryText = Str::lower(implode(' ', $template['industries'] ?? []));
                $intentText = Str::lower(implode(' ', $template['page_intents'] ?? []));
                $styleText = Str::lower(implode(' ', $template['style'] ?? []));
                $featureText = Str::lower(implode(' ', $template['features'] ?? []));
                $allText = implode(' ', [$industryText, $intentText, $styleText, $featureText, Str::lower((string) ($template['description'] ?? ''))]);
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
                    } elseif ($term !== '' && str_contains($styleText.' '.$featureText, $term)) {
                        $score += 5;
                    } elseif ($term !== '' && str_contains($allText, $term)) {
                        $score += 2;
                    }
                }

                $score += ((int) ($template['quality_score'] ?? 0)) / 12;
                $score += ((int) ($template['visual_score'] ?? 0)) / 25;
                $score += ((int) ($template['composition_novelty_score'] ?? 50)) / 22;
                $score += ($template['quality_status'] ?? '') === 'excellent' ? 5 : 0;
                $score -= ($template['quality_status'] ?? '') === 'invalid' ? 100 : 0;

                return [...$template, '_bundle_score' => $score];
            })
            ->sortByDesc('_bundle_score')
            ->values()
            ->all();

        return $this->diverseSelection($ranked, self::TEMPLATE_CANDIDATES_PER_BUNDLE);
    }

    /** @return array<int, array<string, mixed>> */
    private function diverseSelection(array $ranked, int $limit): array
    {
        $selected = [];
        $remaining = array_values($ranked);

        while ($remaining !== [] && count($selected) < $limit) {
            $bestIndex = 0;
            $bestScore = -INF;

            foreach ($remaining as $index => $candidate) {
                $maximumOverlap = 0.0;
                foreach ($selected as $chosen) {
                    $maximumOverlap = max($maximumOverlap, $this->sectionSimilarity(
                        (array) ($candidate['sections'] ?? []),
                        (array) ($chosen['sections'] ?? []),
                    ));
                }

                $score = (float) ($candidate['_bundle_score'] ?? 0)
                    - ($maximumOverlap * 11)
                    + (((int) ($candidate['composition_novelty_score'] ?? 50)) / 28);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestIndex = $index;
                }
            }

            $selected[] = $remaining[$bestIndex];
            array_splice($remaining, $bestIndex, 1);
        }

        return array_map(function (array $template): array {
            unset($template['_bundle_score']);
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

            for ($offset = 0; $offset < min(5, $candidateCount); $offset++) {
                $candidateKeys[] = (string) $templates[($index * 3 + $offset) % $candidateCount]['key'];
            }

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
            Str::contains($slug, ['gallery', 'project', 'property', 'destination', 'inventory']) => 'showcase',
            Str::contains($slug, ['testimonial', 'team', 'doctor', 'trainer', 'agent']) => 'proof',
            Str::contains($slug, ['menu', 'service', 'product', 'room', 'course', 'program', 'package', 'pricing']) => 'offerings',
            Str::contains($slug, ['about', 'story']) => 'story',
            Str::contains($slug, ['faq', 'resource']) => 'education',
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

    private function sectionSimilarity(array $left, array $right): float
    {
        $left = array_values(array_unique(array_filter($left, 'is_string')));
        $right = array_values(array_unique(array_filter($right, 'is_string')));
        $union = array_unique(array_merge($left, $right));

        return $union === [] ? 1.0 : count(array_intersect($left, $right)) / count($union);
    }
}
