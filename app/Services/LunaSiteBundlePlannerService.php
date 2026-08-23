<?php

namespace App\Services;

use App\AI\Registries\IndustryMenuRegistry;
use Illuminate\Support\Str;

final class LunaSiteBundlePlannerService
{
    public function __construct(private readonly SiteBundleCatalog $catalog)
    {
    }

    /**
     * Select one of the curated bundles, adapt its page menu to explicit prompt
     * needs, and lock a distinct registered template composition to every page.
     *
     * @return array<string, mixed>
     */
    public function plan(string $prompt, string $industry): array
    {
        $normalizedPrompt = Str::lower($prompt);
        $industryKey = $this->resolveIndustry($normalizedPrompt, $industry);
        $archetype = $this->resolveArchetype($normalizedPrompt, $industryKey);
        $bundle = $this->catalog->find($industryKey.'-'.$archetype)
            ?? collect($this->catalog->all())->first();

        if (! is_array($bundle)) {
            throw new \RuntimeException('No Cosmic site bundles are available to Luna.');
        }

        $pages = $this->adaptPages((array) $bundle['pages'], $normalizedPrompt);
        $templates = collect(PageTemplateCatalog::plannerIndex())->keyBy('key');
        $usedKeys = [];
        $usedFingerprints = [];

        $pages = collect($pages)->map(function (array $page) use ($templates, &$usedKeys, &$usedFingerprints): array {
            $choices = collect($page['candidate_template_keys'] ?? [])
                ->map(fn (string $key) => $templates->get($key))
                ->filter()
                ->sortByDesc(function (array $template) use ($page, $usedKeys, $usedFingerprints): float {
                    $text = Str::lower(implode(' ', [
                        implode(' ', $template['page_intents'] ?? []),
                        implode(' ', $template['features'] ?? []),
                        implode(' ', $template['style'] ?? []),
                        (string) ($template['description'] ?? ''),
                    ]));
                    $intent = Str::lower((string) ($page['page_intent'] ?? 'information'));
                    $score = str_contains($text, $intent) ? 18 : 0;
                    $score += ((int) ($template['quality_score'] ?? 0)) / 12;
                    $score += ((int) ($template['composition_novelty_score'] ?? 50)) / 20;
                    $score -= in_array($template['key'] ?? '', $usedKeys, true) ? 100 : 0;
                    $score -= in_array($template['diversity_fingerprint'] ?? '', $usedFingerprints, true) ? 30 : 0;
                    return $score;
                })
                ->values();

            $selected = $choices->first();
            if (! is_array($selected)) {
                $selected = $templates->first();
            }

            $key = (string) ($selected['key'] ?? '');
            $fingerprint = (string) ($selected['diversity_fingerprint'] ?? '');
            $usedKeys[] = $key;
            if ($fingerprint !== '') {
                $usedFingerprints[] = $fingerprint;
            }

            return [
                ...$page,
                'template_key' => $key,
                'template_name' => (string) ($selected['name'] ?? $key),
                'sections' => array_values($selected['sections'] ?? []),
                'composition_fingerprint' => $fingerprint,
                'build_status' => ($page['is_home'] ?? false) ? 'building' : 'queued',
            ];
        })->values()->all();

        return [
            'bundle_key' => $bundle['key'],
            'bundle_name' => $bundle['name'],
            'bundle_version' => $bundle['version'],
            'industry' => $industryKey,
            'archetype' => $archetype,
            'design_contract' => $bundle['design_contract'],
            'template_candidates' => $bundle['template_candidates'],
            'pages' => $pages,
            'page_count' => count($pages),
            'planner' => 'luna_curated_site_bundle',
            'planned_at' => now()->toIso8601String(),
        ];
    }

    private function resolveIndustry(string $prompt, string $industry): string
    {
        $normalized = IndustryMenuRegistry::normalizeIndustry($industry);
        if (array_key_exists($normalized, $this->catalog->industries())) {
            return $normalized;
        }

        $bestKey = 'technology';
        $bestScore = -1;
        foreach ($this->catalog->industries() as $key => $definition) {
            $score = collect([$key, $definition['label'], ...$definition['aliases']])
                ->sum(fn (string $term) => Str::contains($prompt, Str::lower($term)) ? strlen($term) : 0);
            if ($score > $bestScore) {
                $bestKey = $key;
                $bestScore = $score;
            }
        }

        return $bestKey;
    }

    private function resolveArchetype(string $prompt, string $industry): string
    {
        $scores = [];
        foreach ($this->catalog->archetypes() as $key => $definition) {
            $scores[$key] = collect($definition['terms'])
                ->sum(fn (string $term) => Str::contains($prompt, Str::lower($term)) ? 8 + strlen($term) : 0);
        }

        $default = match ($industry) {
            'restaurant', 'coffee', 'bakery', 'hotel', 'real-estate', 'salon', 'travel' => 'showcase',
            'lawyer', 'finance', 'medical', 'dentist', 'education' => 'authority',
            'technology' => 'growth',
            default => 'conversion',
        };
        $scores[$default] = ($scores[$default] ?? 0) + 5;
        arsort($scores);

        return (string) array_key_first($scores);
    }

    /** @return array<int, array<string, mixed>> */
    private function adaptPages(array $pages, string $prompt): array
    {
        $signals = [
            'reservation' => ['reservation', 'reserve a table', 'booking'],
            'catering' => ['catering'],
            'events' => ['events', 'private event'],
            'locations' => ['locations', 'branches', 'multiple locations'],
            'pricing' => ['pricing', 'price list'],
            'faq' => ['faq', 'frequently asked'],
            'team' => ['our team', 'staff', 'chefs'],
        ];
        $titles = [
            'reservation' => 'Reservations',
            'catering' => 'Catering',
            'events' => 'Events',
            'locations' => 'Locations',
            'pricing' => 'Pricing',
            'faq' => 'FAQ',
            'team' => 'Team',
        ];

        foreach ($signals as $key => $needles) {
            if (! Str::contains($prompt, $needles)) {
                continue;
            }

            $title = $titles[$key];
            if (collect($pages)->contains(fn (array $page) => Str::lower((string) $page['title']) === Str::lower($title))) {
                continue;
            }

            $contactIndex = collect($pages)->search(fn (array $page) => ($page['slug'] ?? '') === 'contact');
            $source = $pages[max(0, min(count($pages) - 1, ($contactIndex === false ? count($pages) : $contactIndex) - 1))] ?? $pages[0];
            $extra = [
                ...$source,
                'title' => $title,
                'slug' => Str::slug($title),
                'is_home' => false,
                'page_intent' => in_array($key, ['reservation', 'catering', 'events'], true) ? 'conversion' : $key,
            ];
            $insertAt = $contactIndex === false ? count($pages) : $contactIndex;
            array_splice($pages, $insertAt, 0, [$extra]);
        }

        if (count($pages) > 7) {
            $pages = collect($pages)
                ->reject(fn (array $page) => ! ($page['is_home'] ?? false) && ($page['slug'] ?? '') !== 'contact' && in_array($page['slug'] ?? '', ['about', 'our-story'], true))
                ->take(7)
                ->values()
                ->all();
        }

        return collect($pages)->values()->map(function (array $page, int $index): array {
            $page['sort_order'] = $index + 1;
            return $page;
        })->all();
    }
}
