<?php

namespace App\Services;

use App\Models\TrialGeneration;
use Illuminate\Support\Str;

class TrialBrandContextService
{
    /**
     * Build a small, durable brand-memory packet without spending another AI call.
     * L2/L3 can pass this packet plus brand_prompt to Luna for logo art direction.
     */
    public function build(array $profile, string $prompt): array
    {
        return [
            'business_name' => trim((string) ($profile['business_name'] ?? '')),
            'industry' => trim((string) ($profile['industry'] ?? '')),
            'location' => trim((string) ($profile['location'] ?? '')),
            'business_description' => trim((string) ($profile['business_description'] ?? $prompt)),
            'brand_prompt' => trim($prompt),
            'keywords' => $this->keywords($prompt),
            'source' => 'trial_generation',
            'updated_at' => now()->toIso8601String(),
        ];
    }

    public function appendPromptHistory(?array $history, string $prompt, string $source): array
    {
        $items = collect($history ?? [])
            ->filter(fn ($item) => is_array($item) && trim((string) ($item['prompt'] ?? '')) !== '')
            ->values();

        $items->push([
            'prompt' => trim($prompt),
            'source' => $source,
            'created_at' => now()->toIso8601String(),
        ]);

        // Keep enough history for context while preventing an unbounded JSON field.
        return $items->take(-12)->values()->all();
    }

    public function refreshForRegeneration(TrialGeneration $trial, string $prompt): array
    {
        $context = is_array($trial->brand_context) ? $trial->brand_context : [];
        $context['business_name'] = $trial->business_name;
        $context['industry'] = $trial->industry;
        $context['location'] = $trial->location;
        $context['business_description'] = $trial->business_description;
        $context['brand_prompt'] = $trial->brand_prompt ?: $trial->prompt ?: $prompt;
        $context['latest_user_prompt'] = trim($prompt);
        $context['latest_keywords'] = $this->keywords($prompt);
        $context['updated_at'] = now()->toIso8601String();

        return $context;
    }

    private function keywords(string $prompt): array
    {
        $stop = [
            'about','after','again','also','and','are','build','business','create','for','from','have','into','make',
            'page','site','that','the','their','this','use','website','with','your','you','our','but','can','should',
        ];

        return collect(preg_split('/[^a-z0-9]+/i', Str::lower($prompt)) ?: [])
            ->map(fn ($word) => trim($word))
            ->filter(fn ($word) => strlen($word) >= 4 && ! in_array($word, $stop, true))
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take(12)
            ->values()
            ->all();
    }
}
