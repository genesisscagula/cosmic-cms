<?php

namespace App\Services;

use App\AI\Schemas\SchemaManager;
use Illuminate\Support\Str;

/**
 * Central catalog guard for visual rhythm.
 *
 * Older generated template batches contain valid Sparks but frequently place
 * several image-rich Sparks back-to-back. This service preserves each template
 * as a curated recipe while swapping only the conflicting section for a
 * registered, role-compatible, text/data-led alternative.
 */
final class TemplateCompositionBalancer
{
    public function __construct(private readonly TemplateQualityAuditor $auditor)
    {
    }

    /** @param array<int, array<string, mixed>> $templates */
    public function balanceAll(array $templates): array
    {
        return array_map(fn (array $template): array => $this->balance($template), $templates);
    }

    /** @param array<string, mixed> $template */
    public function balance(array $template): array
    {
        $sections = array_values(array_filter($template['sections'] ?? [], 'is_string'));
        $original = $sections;
        $replacements = [];

        if (count($sections) < 2) {
            return $this->withMetadata($template, $original, $sections, $replacements);
        }

        // Two passes handle sequences such as image/image/image without making
        // the outcome depend on whichever conflict happened to be visited first.
        for ($pass = 0; $pass < 2; $pass++) {
            for ($index = 1; $index < count($sections); $index++) {
                if (! $this->auditor->isImageHeavySection($sections[$index - 1])
                    || ! $this->auditor->isImageHeavySection($sections[$index])) {
                    continue;
                }

                $target = $this->replacementTarget($sections, $index);
                $this->replaceAt($template, $sections, $target, 'consecutive_image_sections', $replacements);
            }
        }

        // Alternation normally guarantees this already. The ratio pass also
        // covers unusually short or custom recipes and visual-first batches.
        while (($this->auditor->imageProfile($sections)['ratio'] ?? 0) > 0.60) {
            $target = $this->ratioReplacementTarget($sections);
            if ($target === null || ! $this->replaceAt($template, $sections, $target, 'image_density_over_60_percent', $replacements)) {
                break;
            }
        }

        return $this->withMetadata($template, $original, $sections, $replacements);
    }

    /** @param array<int, string> $sections */
    private function replacementTarget(array $sections, int $current): int
    {
        $currentRole = $this->roleFor($sections[$current]);
        $previousRole = $this->roleFor($sections[$current - 1]);

        // Preserve a gallery/media moment when the preceding image-rich section
        // has a safe semantic alternative. Heroes are never replaced here.
        if (in_array($currentRole, ['media', 'gallery'], true)
            && $previousRole !== 'hero'
            && $this->hasAlternative($previousRole, $sections)) {
            return $current - 1;
        }

        return $current;
    }

    /** @param array<int, string> $sections */
    private function ratioReplacementTarget(array $sections): ?int
    {
        foreach ($sections as $index => $section) {
            if ($index === 0 || ! $this->auditor->isImageHeavySection($section)) {
                continue;
            }

            if ($this->hasAlternative($this->roleFor($section), $sections)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $template
     * @param array<int, string> $sections
     * @param array<int, array<string, mixed>> $replacements
     */
    private function replaceAt(array $template, array &$sections, int $index, string $reason, array &$replacements): bool
    {
        $from = $sections[$index] ?? '';
        if ($from === '' || $this->roleFor($from) === 'hero') {
            return false;
        }

        $role = $this->roleFor($from);
        $to = $this->alternative($role, $template, $index, $from, $sections);
        if ($to === null) {
            return false;
        }

        $sections[$index] = $to;
        $replacements[] = [
            'index' => $index,
            'from' => $from,
            'to' => $to,
            'role' => $role,
            'reason' => $reason,
        ];

        return true;
    }

    /** @param array<int, string> $sections */
    private function alternative(string $role, array $template, int $index, string $from, array $sections): ?string
    {
        $candidates = $this->candidatePools()[$role] ?? $this->candidatePools()['information'];
        $registered = $this->registeredSections();
        $candidates = array_values(array_filter($candidates, fn (string $candidate): bool =>
            isset($registered[$candidate])
            && ! in_array($candidate, $sections, true)
            && ! $this->auditor->isImageHeavySection($candidate)
        ));

        if ($candidates === []) {
            return null;
        }

        $seed = (string) ($template['key'] ?? 'template').'|'.$index.'|'.$from;
        $offset = (int) ((float) sprintf('%u', crc32($seed)) % count($candidates));

        return $candidates[$offset];
    }

    /** @param array<int, string> $sections */
    private function hasAlternative(string $role, array $sections): bool
    {
        $registered = $this->registeredSections();

        foreach ($this->candidatePools()[$role] ?? $this->candidatePools()['information'] as $candidate) {
            if (isset($registered[$candidate])
                && ! in_array($candidate, $sections, true)
                && ! $this->auditor->isImageHeavySection($candidate)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, array<int, string>> */
    private function candidatePools(): array
    {
        return [
            'story' => [
                'about_mission_grid', 'about_timeline_story', 'about_brand_journey',
                'about_interactive_stats', 'content_editorial_statement_premium',
            ],
            'services' => [
                'services_editorial_premium', 'services_minimal_luxury', 'services_interactive_tabs',
                'services_feature_comparison', 'services_cards', 'features_tabs_premium',
            ],
            'proof' => [
                'stats_modern', 'stats_animated_counters_premium', 'testimonials_carousel',
                'testimonials_trust_dashboard', 'proof_metrics_premium', 'trust_metrics_premium',
            ],
            'conversion' => [
                'cta_gradient_premium', 'cta_glass_premium', 'cta_book_demo_premium',
                'contact_form_modern', 'contact_details', 'contact_split_premium',
            ],
            'contact' => [
                'contact_details', 'contact_form_modern', 'contact_split_premium',
                'cta_gradient_premium', 'cta_book_demo_premium',
            ],
            'location' => [
                'contact_details', 'contact_split_premium', 'contact_form_modern',
            ],
            'process' => ['process_timeline', 'process_steps_premium', 'about_timeline_story'],
            'faq' => ['faq_accordion', 'faq_split_premium', 'faq_search_premium'],
            'team' => ['team_org_chart_premium', 'team_timeline_premium', 'about_mission_grid'],
            'pricing' => ['pricing_cards', 'pricing_toggle_premium', 'pricing_comparison_premium'],
            'media' => [
                'about_mission_grid', 'about_brand_journey', 'about_interactive_stats',
                'services_minimal_luxury', 'process_timeline', 'stats_modern',
            ],
            'gallery' => [
                'about_mission_grid', 'about_timeline_story', 'about_interactive_stats',
                'services_minimal_luxury', 'process_timeline', 'stats_modern',
            ],
            'information' => [
                'about_mission_grid', 'features_tabs_premium', 'services_cards',
                'process_timeline', 'faq_accordion', 'stats_modern',
            ],
        ];
    }

    private function roleFor(string $section): string
    {
        $section = Str::lower($section);

        return match (true) {
            Str::startsWith($section, ['hero_', 'mini_hero_']) => 'hero',
            Str::contains($section, ['contact_', 'lead_', 'cta_', 'reservation_cta', 'booking']) => 'conversion',
            Str::contains($section, ['testimonial', 'review', 'proof_', 'stats_', 'trust_', 'client_logo']) => 'proof',
            Str::contains($section, ['pricing', 'package']) => 'pricing',
            Str::contains($section, ['faq']) => 'faq',
            Str::contains($section, ['team', 'leadership', 'founder']) => 'team',
            Str::contains($section, ['process', 'timeline', 'workflow', 'step']) => 'process',
            Str::contains($section, ['location', 'map', 'city_spotlight']) => 'location',
            Str::contains($section, ['gallery']) => 'gallery',
            Str::contains($section, ['portfolio', 'showcase', 'case_stud']) => 'media',
            Str::contains($section, ['service', 'feature', 'menu', 'dish', 'room', 'treatment', 'program']) => 'services',
            Str::contains($section, ['about', 'story', 'mission', 'value', 'content_', 'brand_']) => 'story',
            default => 'information',
        };
    }

    /** @return array<string, bool> */
    private function registeredSections(): array
    {
        static $registered = null;
        $registered ??= array_fill_keys(array_keys(SchemaManager::map()), true);

        return $registered;
    }

    /**
     * @param array<string, mixed> $template
     * @param array<int, string> $original
     * @param array<int, string> $sections
     * @param array<int, array<string, mixed>> $replacements
     */
    private function withMetadata(array $template, array $original, array $sections, array $replacements): array
    {
        $template['sections'] = $sections;
        $template['auto_balanced'] = $replacements !== [];
        $template['balanced_replacements'] = $replacements;

        if ($replacements !== []) {
            $template['original_sections'] = $original;
        }

        return $template;
    }
}
