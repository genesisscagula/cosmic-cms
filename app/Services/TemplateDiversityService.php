<?php

namespace App\Services;

final class TemplateDiversityService
{
    /** @return array<string,array<string,mixed>> */
    public function profiles(array $templates): array
    {
        $templateCount = max(1, count($templates));
        $sparkFrequency = [];
        $exactFrequency = [];
        $semanticFrequency = [];

        foreach ($templates as $template) {
            $sections = $this->sections($template);
            foreach (array_unique($sections) as $spark) {
                $sparkFrequency[$spark] = ($sparkFrequency[$spark] ?? 0) + 1;
            }
            $exact = implode('|', $sections);
            $semantic = implode('|', array_map($this->role(...), $sections));
            $exactFrequency[$exact] = ($exactFrequency[$exact] ?? 0) + 1;
            $semanticFrequency[$semantic] = ($semanticFrequency[$semantic] ?? 0) + 1;
        }

        $profiles = [];
        foreach ($templates as $template) {
            $key = (string) ($template['key'] ?? '');
            $sections = $this->sections($template);
            $roles = array_map($this->role(...), $sections);
            $exactCount = $exactFrequency[implode('|', $sections)] ?? 1;
            $semanticCount = $semanticFrequency[implode('|', $roles)] ?? 1;
            $sparkNovelty = $sections === [] ? 0.0 : array_sum(array_map(
                static function (string $spark) use ($sparkFrequency, $templateCount): float {
                    $share = ($sparkFrequency[$spark] ?? 0) / $templateCount;
                    return max(0.0, min(100.0, ((0.15 - $share) / 0.15) * 100));
                },
                $sections
            )) / count($sections);
            $semanticNovelty = max(0.0, min(100.0, (1 - (($semanticCount - 1) / max(1, $templateCount * .08))) * 100));
            $score = (int) round(($sparkNovelty * .72) + ($semanticNovelty * .28) - (max(0, $exactCount - 1) * 12));
            $score = max(0, min(100, $score));

            $profiles[$key] = [
                'sections' => $sections,
                'semantic_roles' => $roles,
                'semantic_signature' => implode(' > ', $roles),
                'composition_novelty_score' => $score,
                'composition_novelty_level' => $score >= 72 ? 'standout' : ($score >= 48 ? 'balanced' : 'familiar'),
                'exact_composition_uses' => $exactCount,
                'semantic_composition_uses' => $semanticCount,
            ];
        }

        return $profiles;
    }

    /** @return array<string,mixed> */
    public function report(array $templates, array $registeredSparks = []): array
    {
        $profiles = $this->profiles($templates);
        $sparkUses = [];
        $exactGroups = [];
        $semanticGroups = [];
        foreach ($profiles as $key => $profile) {
            foreach (array_unique($profile['sections']) as $spark) {
                $sparkUses[$spark] = ($sparkUses[$spark] ?? 0) + 1;
            }
            $exactGroups[implode('|', $profile['sections'])][] = $key;
            $semanticGroups[$profile['semantic_signature']][] = $key;
        }
        arsort($sparkUses);
        uasort($semanticGroups, static fn (array $a, array $b): int => count($b) <=> count($a));
        $duplicates = array_filter($exactGroups, static fn (array $keys): bool => count($keys) > 1);
        $scores = array_column($profiles, 'composition_novelty_score');
        $used = array_keys($sparkUses);

        return [
            'templates' => count($templates),
            'unique_sparks_used' => count($used),
            'registered_sparks' => count($registeredSparks),
            'unused_registered_sparks' => array_values(array_diff($registeredSparks, $used)),
            'unique_exact_compositions' => count($exactGroups),
            'exact_duplicate_groups' => count($duplicates),
            'templates_in_exact_duplicate_groups' => array_sum(array_map('count', $duplicates)),
            'unique_semantic_compositions' => count($semanticGroups),
            'average_novelty_score' => $scores === [] ? 0 : round(array_sum($scores) / count($scores), 1),
            'familiar_templates' => count(array_filter($profiles, static fn (array $p): bool => $p['composition_novelty_level'] === 'familiar')),
            'top_reused_sparks' => array_slice($sparkUses, 0, 20, true),
            'largest_semantic_groups' => array_slice(array_map(
                static fn (string $signature, array $keys): array => [
                    'signature' => $signature,
                    'count' => count($keys),
                    'templates' => array_slice($keys, 0, 12),
                ],
                array_keys($semanticGroups),
                array_values($semanticGroups)
            ), 0, 12),
        ];
    }

    /** @return list<string> */
    private function sections(array $template): array
    {
        return array_values(array_filter($template['sections'] ?? [], 'is_string'));
    }

    private function role(string $spark): string
    {
        return match (true) {
            str_starts_with($spark, 'hero_') => 'hero',
            str_starts_with($spark, 'services_'), str_starts_with($spark, 'features_') => 'offer',
            str_starts_with($spark, 'content_'), str_starts_with($spark, 'feature_image_'), str_starts_with($spark, 'about_') => 'story',
            str_starts_with($spark, 'process_'), str_starts_with($spark, 'how_') => 'process',
            str_starts_with($spark, 'stats_'), str_starts_with($spark, 'proof_'), str_starts_with($spark, 'testimonials_'), str_starts_with($spark, 'case_stud'), str_starts_with($spark, 'portfolio_') => 'proof',
            str_starts_with($spark, 'gallery_'), str_contains($spark, '_gallery') => 'gallery',
            str_starts_with($spark, 'pricing_') => 'pricing',
            str_starts_with($spark, 'faq_') => 'faq',
            str_starts_with($spark, 'team_') => 'team',
            str_starts_with($spark, 'location_'), str_starts_with($spark, 'contact_map') => 'location',
            str_starts_with($spark, 'menu_'), str_starts_with($spark, 'product_') => 'catalog',
            str_starts_with($spark, 'events_') => 'events',
            str_starts_with($spark, 'cta_'), str_starts_with($spark, 'contact_'), str_starts_with($spark, 'lead_'), $spark === 'image_cta_banner' => 'conversion',
            default => explode('_', $spark, 2)[0],
        };
    }
}
