<?php

namespace App\AI\Layouts;

class LayoutEngine
{
    public static function random(string $folder, ?string $prompt = null): array
    {
        $layoutFiles = [
            'restaurant' => 'RestaurantLayouts.php',
            'coffee' => 'CoffeeLayouts.php',
            'bakery' => 'BakeryLayouts.php',
            'hotel' => 'HotelLayouts.php',
            'travel' => 'TravelLayouts.php',
            'automotive' => 'AutomotiveLayouts.php',
            'construction' => 'ConstructionLayouts.php',
            'electrician' => 'ElectricianLayouts.php',
            'plumbing' => 'PlumbingLayouts.php',
            'roofing' => 'RoofingLayouts.php',
            'dentist' => 'DentistLayouts.php',
            'medical' => 'MedicalLayouts.php',
            'fitness' => 'FitnessLayouts.php',
            'cleaning' => 'CleaningLayouts.php',
            'landscaping' => 'LandscapingLayouts.php',
            'lawyer' => 'LawyerLayouts.php',
            'finance' => 'FinanceLayouts.php',
            'real-estate' => 'RealEstateLayouts.php',
            'technology' => 'TechnologyLayouts.php',
            'education' => 'EducationLayouts.php',
            'salon' => 'SalonLayouts.php',
        ];

        $layoutFile = $layoutFiles[$folder] ?? 'DefaultLayouts.php';
        $layouts = self::normalizeLegacyBlockTypes(require __DIR__.'/'.$layoutFile);

        if (isset($layouts['focus'], $layouts['default']) && is_array($layouts['focus']) && is_array($layouts['default'])) {
            $layouts = self::layoutsForPrompt($layouts, $prompt);
        }

        // Video is an explicit visual request. Prefer only layout variations
        // that already include the dedicated video hero, so PHP keeps control
        // of the complete block order and does not inject a surprise section.
        if (self::wantsVideo($prompt)) {
            $videoLayouts = array_values(array_filter(
                $layouts,
                static fn (array $layout): bool => in_array('hero_video_background', $layout, true)
            ));

            if ($videoLayouts !== []) {
                return $videoLayouts[array_rand($videoLayouts)];
            }
        }

        return $layouts[array_rand($layouts)];
    }

    /**
     * Resolve a page-intent group before choosing a random visual variation.
     * Only layout files that opt into the `focus` / `default` shape use this;
     * existing industry layout files remain fully backward compatible.
     */
    private static function layoutsForPrompt(array $definition, ?string $prompt): array
    {
        if ($prompt === null || trim($prompt) === '') {
            return $definition['default'];
        }

        // The Builder always includes "Page: {title}" in its profile context.
        // That explicit page title wins over incidental words in old page copy.
        $pageTitle = '';
        if (preg_match('/^\s*page\s*:\s*([^\r\n]+)/mi', $prompt, $matches) === 1) {
            $pageTitle = trim($matches[1]);
        }

        foreach ([$pageTitle, $prompt] as $candidate) {
            $normalized = self::normalizeLayoutText($candidate);

            if ($normalized === '') {
                continue;
            }

            foreach ($definition['focus'] as $focus) {
                foreach ($focus['keywords'] ?? [] as $keyword) {
                    if (self::containsLayoutKeyword($normalized, $keyword)) {
                        return $focus['layouts'];
                    }
                }
            }
        }

        return $definition['default'];
    }

    /**
     * Normalize human page titles and URL-style slugs into the same form.
     * For example, "Our Story", "our-story", and "our_story" all match
     * a configured "story" focus keyword.
     */
    private static function normalizeLayoutText(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private static function containsLayoutKeyword(string $normalizedCandidate, string $keyword): bool
    {
        $normalizedKeyword = self::normalizeLayoutText($keyword);

        if ($normalizedKeyword === '') {
            return false;
        }

        return str_contains(" {$normalizedCandidate} ", " {$normalizedKeyword} ");
    }

    /**
     * Keep the first-generation industry layout files compatible with the
     * current Builder registry. These aliases were used before the reusable
     * Team, Contact, Map, and Blog blocks were finalized. Normalizing here
     * keeps PHP layout selection deterministic while preventing an unknown
     * section type from reaching the AI schema or React renderer.
     */
    private static function normalizeLegacyBlockTypes(array $definition): array
    {
        $aliases = [
            'team_grid' => 'team_modern',
            'contact_form' => 'contact_form_modern',
            'map_embed' => 'location_map',
            'posts_grid' => 'blog_hub',
            'featured_posts' => 'blog_hub',
            'newsletter_signup' => 'newsletter_cta',
        ];

        $normalizeLayout = static function (array $layout) use ($aliases): array {
            $types = array_map(
                static fn ($type) => is_string($type) ? ($aliases[$type] ?? $type) : $type,
                $layout
            );

            // The old featured-posts + posts-grid pairing is now one cohesive
            // Blog Hub block. Retain order while avoiding duplicate instances.
            return array_values(array_unique($types, SORT_REGULAR));
        };

        if (isset($definition['focus'], $definition['default'])) {
            foreach ($definition['focus'] as $key => $focus) {
                if (isset($focus['layouts']) && is_array($focus['layouts'])) {
                    $definition['focus'][$key]['layouts'] = array_map($normalizeLayout, $focus['layouts']);
                }
            }

            if (is_array($definition['default'])) {
                $definition['default'] = array_map($normalizeLayout, $definition['default']);
            }

            return $definition;
        }

        return array_map($normalizeLayout, $definition);
    }

    /**
     * Video is an explicit visual request. Keep the detection intentionally
     * narrow so ordinary mentions of images or media do not override the
     * normal randomized hero selection.
     */
    private static function wantsVideo(?string $prompt): bool
    {
        return $prompt !== null
            && preg_match('/\bvideo\b/i', $prompt) === 1;
    }

    /**
     * Pick a supported visual variation for one user-selected section purpose.
     * Structure remains deterministic in PHP; AI only fills the chosen schema.
     */
    public static function randomSection(string $category, ?string $prompt = null): string
    {
        // A video request is an explicit visual requirement, not a random variation.
        // Keep the rest of the selection deterministic and lightweight in PHP.
        if ($category === 'hero' && self::wantsVideo($prompt)) {
            return 'hero_video_background';
        }

        $sections = [
            'hero' => [
                'hero_headline',
                'hero_background_image',
                'hero_editorial_overlay',
                'hero_split_image',
                'hero_video_background',
                'hero_floating_cards',
            ],
            'services' => ['services_cards', 'services_bento'],
            'feature' => ['feature_image_left', 'feature_image_right'],
            'pricing' => ['pricing_cards'],
            'testimonials' => ['testimonials_carousel'],
            'process' => ['process_timeline'],
            'stats' => ['stats_modern'],
            'team' => ['team_modern'],
            'cta' => ['hero_centered_cta', 'image_cta_banner'],
            'faq' => ['faq_accordion'],
            'contact' => ['contact_form_modern', 'contact_details', 'location_map'],
            'location' => ['location_map'],
            'case_studies' => ['case_studies_grid'],
            'portfolio' => ['case_studies_grid'],
            'careers' => ['jobs_list'],
            'jobs' => ['jobs_list'],
            'events' => ['events_grid'],
        ];

        $candidates = $sections[$category] ?? [];

        if ($candidates === []) {
            throw new \InvalidArgumentException('Unsupported section category.');
        }

        return $candidates[array_rand($candidates)];
    }
}
