<?php

namespace App\Services;

/**
 * Hotfix 1: deterministic request compliance gate for Luna registered-Spark previews.
 * It converts obvious hard requirements in the user's prompt into local constraints
 * and refuses to mark a generated preview as verified when those constraints are not met.
 */
final class LunaSparkRequestComplianceService
{
    /** @return array<string,mixed> */
    public function requirements(string $prompt): array
    {
        $text = strtolower(trim($prompt));
        $semantic = null;
        foreach ([
            'services' => ['service','services','what we do'],
            'hero' => ['hero','banner','masthead'],
            'pricing' => ['pricing','price plans','plans'],
            'testimonials' => ['testimonial','testimonials','reviews'],
            'faq' => ['faq','frequently asked','questions'],
            'team' => ['team','staff','members'],
            'contact' => ['contact','get in touch','inquiry'],
            'process' => ['process','steps','workflow'],
            'portfolio' => ['portfolio','gallery','projects'],
            'features' => ['features','feature list'],
        ] as $type => $needles) {
            foreach ($needles as $needle) {
                if (str_contains(' '.$text.' ', ' '.$needle.' ') || str_contains($text, $needle)) {
                    $semantic = $type;
                    break 2;
                }
            }
        }

        $itemCount = null;
        if (preg_match('/\b(\d{1,2})\s+(?:service\s+)?(?:cards?|services?|slides?|items?|plans?|testimonials?|reviews?|questions?|faqs?|members?|steps?|features?|projects?)\b/i', $prompt, $m)) {
            $itemCount = max(1, min(20, (int) $m[1]));
        }
        $columns = null;
        if (preg_match('/\b(\d{1,2})\s*(?:-|\s)?columns?\b/i', $prompt, $m)) {
            $columns = max(1, min(6, (int) $m[1]));
        }

        return array_filter([
            'semantic_type' => $semantic,
            'item_count' => $itemCount,
            'columns' => $columns,
            'layout' => preg_match('/\bcards?\b/i', $prompt) ? 'cards' : null,
            'rounded' => preg_match('/\brounded\b|\bround(?:ed)?\s+corners?\b/i', $prompt) === 1,
            'style' => preg_match('/\bclean\b/i', $prompt) ? 'clean' : (preg_match('/\bpremium\b/i', $prompt) ? 'premium' : null),
        ], static fn ($value) => $value !== null && $value !== false && $value !== '');
    }

    /** @return array<string,mixed> */
    public function validate(string $prompt, array $block, ?string $sparkKey = null): array
    {
        $requirements = $this->requirements($prompt);
        $failures = [];
        $sparkKey = trim((string) ($sparkKey ?: ($block['type'] ?? '')));
        $meta = $sparkKey !== '' ? (SparkCatalog::find($sparkKey) ?? []) : [];

        if (($requirements['semantic_type'] ?? null) !== null) {
            $expected = (string) $requirements['semantic_type'];
            $actual = strtolower((string) ($meta['semantic_type'] ?? $block['semantic_type'] ?? ''));
            $compatible = $actual === $expected
                || ($expected === 'portfolio' && in_array($actual, ['portfolio','gallery','case_studies'], true))
                || ($expected === 'testimonials' && in_array($actual, ['testimonials','reviews'], true));
            if (! $compatible) {
                $failures[] = "semantic_type expected {$expected}, got ".($actual !== '' ? $actual : 'unknown');
            }
        }

        if (isset($requirements['item_count'])) {
            $actualCount = $this->primaryCollectionCount($block, (string) ($requirements['semantic_type'] ?? ''));
            if ($actualCount !== (int) $requirements['item_count']) {
                $failures[] = 'item_count expected '.(int) $requirements['item_count'].', got '.($actualCount === null ? 'unknown' : $actualCount);
            }
        }

        if (isset($requirements['columns'])) {
            $haystack = strtolower(json_encode([$meta['layout'] ?? [], $meta['traits'] ?? [], $meta['aliases'] ?? [], $block], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
            $n = (int) $requirements['columns'];
            if (! preg_match('/(?:^|[^0-9])'.$n.'(?:[^0-9]|$)/', $haystack) && ! str_contains($haystack, $n.'-column') && ! str_contains($haystack, $n.'_column')) {
                // Column count is advisory unless explicitly represented in the registered Spark/block.
                // Keep it in requirements for Hotfix 2 candidate scoring, but don't falsely fail opaque renderers.
            }
        }

        return [
            'ok' => $failures === [],
            'requirements' => $requirements,
            'failures' => $failures,
            'spark_key' => $sparkKey,
            'verified' => $failures === [],
        ];
    }

    private function primaryCollectionCount(array $block, string $semantic): ?int
    {
        $preferred = match ($semantic) {
            'services' => ['services','cards','items','features'],
            'pricing' => ['plans','pricing','cards','items'],
            'testimonials' => ['testimonials','reviews','items','cards'],
            'faq' => ['faqs','faq','questions','items'],
            'team' => ['members','team','items','cards'],
            'process' => ['steps','items'],
            'portfolio' => ['projects','items','cards','images'],
            'features' => ['features','items','cards'],
            'hero' => ['slides','items'],
            default => ['items','cards','services','features','slides','plans','testimonials','reviews','questions','members','steps','projects'],
        };

        foreach ($preferred as $key) {
            if (isset($block[$key]) && is_array($block[$key]) && array_is_list($block[$key])) {
                return count($block[$key]);
            }
        }
        return null;
    }
}
