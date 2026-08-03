<?php

namespace App\AI\Compatibility;

use App\AI\Registries\SparkPlannerRegistry;

class SparkCompatibilityChecker
{
    private const HEROES = [
        'hero_headline',
        'hero_floating_cards',
        'hero_video_background',
        'hero_video_style',
        'hero_background_image',
        'hero_slider_fade',
        'hero_parallax',
        'hero_editorial_overlay',
        'hero_split_image',
    ];

    private const CTA = ['hero_centered_cta', 'image_cta_banner'];

    private const TERMINAL = [
        'hero_centered_cta',
        'image_cta_banner',
        'contact_form_modern',
        'contact_details',
        'location_map',
    ];

    /**
     * Validate and safely repair an AI or fallback Spark plan.
     *
     * The AI remains responsible for creative selection. PHP owns hard rules,
     * supported slugs, required page-purpose blocks, and final ordering.
     */
    public function check(array $sections, string $prompt): array
    {
        $allowed = SparkPlannerRegistry::slugs();
        $intent = $this->detectPageIntent($prompt);
        $changes = [];

        $sections = $this->supportedUnique($sections, $allowed, $changes);
        $sections = $this->removeUnrequestedEffects($sections, $prompt, $changes);
        $sections = $this->limitExclusiveGroups($sections, $changes);
        $sections = $this->ensureIntentRequirements($sections, $intent, $changes);
        $sections = $this->ensureUsefulMinimum($sections, $intent, $changes);
        // Minimum/intent repair may add a member of an already selected group.
        // Re-run exclusivity so repair can never produce two heroes or CTAs.
        $sections = $this->limitExclusiveGroups($sections, $changes);
        $sections = $this->order($sections, $changes);

        if (count($sections) > 10) {
            $sections = array_slice($sections, 0, 10);
            $changes[] = 'trimmed_to_maximum_10';
            $sections = $this->order($sections, $changes, false);
        }

        return [
            'sections' => array_values($sections),
            'intent' => $intent,
            'changed' => $changes !== [],
            'changes' => array_values(array_unique($changes)),
        ];
    }

    private function supportedUnique(array $sections, array $allowed, array &$changes): array
    {
        $clean = [];

        foreach ($sections as $section) {
            if (! is_string($section) || ! in_array($section, $allowed, true)) {
                $changes[] = 'removed_unsupported_spark';
                continue;
            }

            if (in_array($section, $clean, true)) {
                $changes[] = 'removed_duplicate_spark';
                continue;
            }

            $clean[] = $section;
        }

        return $clean;
    }

    private function removeUnrequestedEffects(array $sections, string $prompt, array &$changes): array
    {
        $wantsVideo = preg_match('/\b(video|cinematic|motion background)\b/i', $prompt) === 1;
        $wantsParallax = preg_match('/\bparallax\b/i', $prompt) === 1;

        return array_values(array_filter($sections, function (string $section) use ($wantsVideo, $wantsParallax, &$changes): bool {
            if (in_array($section, ['hero_video_background', 'hero_video_style'], true) && ! $wantsVideo) {
                $changes[] = 'removed_unrequested_video_hero';
                return false;
            }

            if ($section === 'hero_parallax' && ! $wantsParallax) {
                $changes[] = 'removed_unrequested_parallax_hero';
                return false;
            }

            return true;
        }));
    }

    private function limitExclusiveGroups(array $sections, array &$changes): array
    {
        $groups = [
            'hero' => self::HEROES,
            'services' => ['services_cards', 'services_bento'],
            'cta' => self::CTA,
            'pricing' => ['pricing_cards'],
            'team' => ['team_modern'],
            'contact_form' => ['contact_form_modern'],
            'location' => ['location_map'],
            'jobs' => ['jobs_list'],
            'events' => ['events_grid'],
        ];

        $seen = [];
        $result = [];

        foreach ($sections as $section) {
            $groupName = null;
            foreach ($groups as $name => $members) {
                if (in_array($section, $members, true)) {
                    $groupName = $name;
                    break;
                }
            }

            if ($groupName !== null && isset($seen[$groupName])) {
                $changes[] = "removed_conflicting_{$groupName}_spark";
                continue;
            }

            if ($groupName !== null) {
                $seen[$groupName] = true;
            }

            $result[] = $section;
        }

        return $result;
    }

    private function ensureIntentRequirements(array $sections, string $intent, array &$changes): array
    {
        $required = match ($intent) {
            'services' => ['services_cards', 'process_timeline'],
            'pricing' => ['pricing_cards', 'faq_accordion'],
            'team' => ['team_modern'],
            'contact' => ['contact_details', 'contact_form_modern'],
            'location' => ['location_map', 'contact_details'],
            'portfolio', 'case-studies', 'work' => ['case_studies_grid'],
            'careers', 'jobs' => ['jobs_list'],
            'events' => ['events_grid'],
            'about', 'story' => ['feature_image_left', 'team_modern'],
            default => [],
        };

        foreach ($required as $spark) {
            if (! in_array($spark, $sections, true)) {
                $sections[] = $spark;
                $changes[] = "added_required_{$spark}";
            }
        }

        return $sections;
    }

    private function ensureUsefulMinimum(array $sections, string $intent, array &$changes): array
    {
        $minimum = in_array($intent, ['contact', 'location', 'careers', 'jobs', 'events'], true) ? 3 : 5;

        $defaults = match ($intent) {
            'contact' => ['hero_headline', 'contact_details', 'contact_form_modern'],
            'location' => ['hero_headline', 'location_map', 'contact_details'],
            'pricing' => ['hero_headline', 'pricing_cards', 'feature_image_left', 'faq_accordion', 'hero_centered_cta'],
            'services' => ['hero_headline', 'services_cards', 'feature_image_left', 'process_timeline', 'hero_centered_cta'],
            'team' => ['hero_headline', 'feature_image_left', 'team_modern', 'testimonials_carousel', 'hero_centered_cta'],
            'portfolio', 'case-studies', 'work' => ['hero_headline', 'case_studies_grid', 'stats_modern', 'testimonials_carousel', 'hero_centered_cta'],
            'careers', 'jobs' => ['hero_headline', 'feature_image_left', 'jobs_list', 'hero_centered_cta'],
            'events' => ['hero_headline', 'events_grid', 'contact_form_modern'],
            default => ['hero_headline', 'services_cards', 'feature_image_left', 'stats_modern', 'testimonials_carousel', 'hero_centered_cta'],
        };

        foreach ($defaults as $spark) {
            if (count($sections) >= $minimum) {
                break;
            }

            if (! in_array($spark, $sections, true) && ! $this->conflictsWithExisting($spark, $sections)) {
                $sections[] = $spark;
                $changes[] = "added_minimum_{$spark}";
            }
        }

        return $sections;
    }


    private function conflictsWithExisting(string $spark, array $sections): bool
    {
        $groups = [
            self::HEROES,
            ['services_cards', 'services_bento'],
            self::CTA,
        ];

        foreach ($groups as $group) {
            if (! in_array($spark, $group, true)) {
                continue;
            }

            foreach ($sections as $existing) {
                if (in_array($existing, $group, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function order(array $sections, array &$changes, bool $record = true): array
    {
        $original = $sections;
        $heroes = array_values(array_filter($sections, fn (string $spark): bool => in_array($spark, self::HEROES, true)));
        $body = array_values(array_filter($sections, fn (string $spark): bool => ! in_array($spark, self::HEROES, true) && ! in_array($spark, self::TERMINAL, true)));
        $terminal = array_values(array_filter($sections, fn (string $spark): bool => in_array($spark, self::TERMINAL, true)));

        // CTA should lead into contact details/form rather than appear after them.
        usort($terminal, static function (string $a, string $b): int {
            $priority = [
                'hero_centered_cta' => 10,
                'image_cta_banner' => 10,
                'contact_details' => 20,
                'location_map' => 30,
                'contact_form_modern' => 40,
            ];

            return ($priority[$a] ?? 20) <=> ($priority[$b] ?? 20);
        });

        $ordered = array_merge($heroes, $body, $terminal);

        if ($record && $ordered !== $original) {
            $changes[] = 'reordered_for_page_flow';
        }

        return $ordered;
    }

    private function detectPageIntent(string $prompt): string
    {
        $candidates = [];

        if (preg_match('/^\s*page\s*:\s*([^\r\n]+)/mi', $prompt, $matches) === 1) {
            $candidates[] = $matches[1];
        }

        $candidates[] = $prompt;

        $intentKeywords = [
            'case-studies' => ['case studies', 'case study'],
            'portfolio' => ['portfolio', 'projects'],
            'careers' => ['careers', 'career'],
            'jobs' => ['jobs', 'job openings', 'vacancies'],
            'location' => ['location', 'find us', 'directions'],
            'contact' => ['contact', 'get in touch'],
            'pricing' => ['pricing', 'plans', 'packages'],
            'services' => ['services', 'what we do'],
            'team' => ['team', 'people', 'leadership'],
            'events' => ['events', 'workshops'],
            'about' => ['about'],
            'story' => ['our story', 'story'],
            'work' => ['our work', 'work'],
            'home' => ['home', 'homepage', 'landing page'],
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalize((string) $candidate);

            foreach ($intentKeywords as $intent => $keywords) {
                foreach ($keywords as $keyword) {
                    if (str_contains(" {$normalized} ", ' ' . $this->normalize($keyword) . ' ')) {
                        return $intent;
                    }
                }
            }
        }

        return 'home';
    }

    private function normalize(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
