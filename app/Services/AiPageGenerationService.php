<?php

namespace App\Services;

use App\AI\Compatibility\SparkCompatibilityChecker;
use App\AI\Generators\ContentGenerator;
use App\AI\Layouts\LayoutEngine;
use App\AI\Planners\SparkPlanner;
use App\AI\Images\VisualQueryBuilder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiPageGenerationService
{
    public function __construct(
        private readonly SmartImageService $images,
        private readonly SparkPlanner $sparkPlanner,
        private readonly SparkCompatibilityChecker $compatibilityChecker,
        private readonly AiGenerationAnalytics $analytics,
        private readonly VisualQueryBuilder $visualQueryBuilder,
    ) {
    }

    public function generatePage(string $prompt): array
    {
        $selection = $this->selectSections($prompt);

        return [
            'sections' => $selection['sections'],
            'image_folder' => $selection['image_folder'],
            'blocks' => $this->generateBlocks(
                $prompt,
                $selection['sections'],
                $selection['image_folder']
            ),
        ];
    }

    public function generateBlocks(string $prompt, array $sections, ?string $imageFolder = null): array
    {
        return $this->generateBlocksDetailed($prompt, $sections, $imageFolder)['blocks'];
    }

    public function generateBlocksDetailed(string $prompt, array $sections, ?string $imageFolder = null): array
    {
        $this->analytics->increment('content_requests');

        $resolvedImageFolder = $imageFolder ?: $this->resolveLayoutFolder($prompt);
        $cacheKey = $this->contentCacheKey($prompt, $sections);
        $cacheEnabled = (bool) config('openai.content_cache_enabled', true);
        $cached = $cacheEnabled ? Cache::get($cacheKey) : null;

        if (is_array($cached) && is_array($cached['blocks'] ?? null)) {
            $content = $cached;
            $cacheStatus = 'hit';
            $this->analytics->increment('content_cache_hits');
        } else {
            $this->analytics->increment('content_cache_misses');

            try {
                $content = (new ContentGenerator())->generate($prompt, $sections);
                $this->analytics->increment('content_successes');
            } catch (\Throwable $exception) {
                $this->analytics->increment('content_failures');
                throw $exception;
            }

            $cacheStatus = $cacheEnabled ? 'miss' : 'disabled';

            if ($cacheEnabled) {
                Cache::put(
                    $cacheKey,
                    $content,
                    max(60, (int) config('openai.content_cache_ttl', 3600))
                );
            }
        }

        $blocks = $content['blocks'] ?? [];

        Log::debug('Selected Spark schemas loaded for content generation', array_merge(
            $content['schema_diagnostics'] ?? [],
            ['content_cache' => $cacheStatus]
        ));

        foreach ($blocks as &$block) {
            $type = (string) ($block['type'] ?? 'website section');
            $query = $this->visualQueryBuilder->build(
                $prompt,
                $block,
                $type,
                $resolvedImageFolder
            );

            Log::debug('[SmartVisualQuery] Built provider search query.', [
                'block_type' => $type,
                'image_folder' => $resolvedImageFolder,
                'query' => $query,
            ]);

            if ($type === 'hero_video_background') {
                $block['poster_image_url'] = $this->images->find($query, $resolvedImageFolder);
                continue;
            }

            if ($type === 'hero_agency_showcase') {
                $images = $this->images->localFallbacks($resolvedImageFolder, 2);
                $block['before_image_url'] = $images[0] ?? $this->images->find($query.' before redesign', $resolvedImageFolder);
                $block['after_image_url'] = $images[1] ?? $this->images->find($query.' after redesign', $resolvedImageFolder);
                continue;
            }

            if ($type === 'case_studies_grid' && is_array($block['studies'] ?? null)) {
                $studyImages = $this->images->localFallbacks(
                    $resolvedImageFolder,
                    count($block['studies'])
                );

                foreach ($block['studies'] as $studyIndex => &$study) {
                    if (! is_array($study)) {
                        continue;
                    }

                    $study['image_url'] = $studyImages[$studyIndex]
                        ?? '/cosmic-images/cosmic-fallback.svg';
                }

                unset($study);
                continue;
            }

            if (! array_key_exists('image_url', $block)) {
                continue;
            }

            $block['image_url'] = $this->images->find($query, $resolvedImageFolder);
        }

        unset($block);

        return [
            'blocks' => $blocks,
            'diagnostics' => [
                'cache' => $cacheStatus,
                'cache_key' => substr(hash('sha256', $cacheKey), 0, 12),
                'content_model' => config('openai.content_model'),
                'schema' => $content['schema_diagnostics'] ?? [],
                'analytics' => $this->analytics->snapshot(),
            ],
        ];
    }

    public function selectSections(string $prompt): array
    {
        $this->analytics->increment('planner_requests');

        $imageFolder = $this->resolveLayoutFolder($prompt);
        $cacheKey = $this->plannerCacheKey($prompt);
        $cacheEnabled = (bool) config('openai.planner_cache_enabled', true);
        $cached = $cacheEnabled ? Cache::get($cacheKey) : null;

        if (is_array($cached) && is_array($cached['sections'] ?? null)) {
            $result = $cached;
            $result['cache'] = 'hit';
            $this->analytics->increment('planner_cache_hits');
            $result['analytics'] = $this->analytics->snapshot();

            return $result;
        }

        $this->analytics->increment('planner_cache_misses');

        try {
            $sections = $this->sparkPlanner->plan($prompt);
            $planner = 'ai';
            $this->analytics->increment('planner_ai_successes');
        } catch (\Throwable $exception) {
            Log::warning('Spark Planner failed; using deterministic layout fallback', [
                'message' => $exception->getMessage(),
                'image_folder' => $imageFolder,
            ]);

            $sections = LayoutEngine::random($imageFolder, $prompt);
            $planner = 'fallback';
            $this->analytics->increment('planner_fallbacks');
        }

        $compatibility = $this->compatibilityChecker->check($sections, $prompt);

        if ($compatibility['changed']) {
            $this->analytics->increment('compatibility_repairs');
        }

        $result = [
            'sections' => $compatibility['sections'],
            'image_folder' => $imageFolder,
            'planner' => $planner,
            'page_intent' => $compatibility['intent'],
            'compatibility' => [
                'changed' => $compatibility['changed'],
                'changes' => $compatibility['changes'],
            ],
            'cache' => $cacheEnabled ? 'miss' : 'disabled',
        ];

        if ($cacheEnabled) {
            Cache::put(
                $cacheKey,
                $result,
                max(60, (int) config('openai.planner_cache_ttl', 86400))
            );
        }

        $result['analytics'] = $this->analytics->snapshot();

        return $result;
    }

    public function selectSection(string $category, string $prompt): array
    {
        return [
            'section' => LayoutEngine::randomSection($category, $prompt),
            'image_folder' => $this->resolveLayoutFolder($prompt),
        ];
    }

    public function resolveLayoutFolder(string $prompt): string
    {
        $normalizedPrompt = trim($prompt);
        $normalizedPrompt = function_exists('mb_strtolower')
            ? mb_strtolower($normalizedPrompt, 'UTF-8')
            : strtolower($normalizedPrompt);

        $industryKeywords = $this->industryKeywords();

        if (preg_match('/^industry:\s*([^\r\n.]+)/mi', $prompt, $matches) === 1) {
            $profileIndustry = trim($matches[1]);
            $profileIndustry = function_exists('mb_strtolower')
                ? mb_strtolower($profileIndustry, 'UTF-8')
                : strtolower($profileIndustry);

            foreach ($industryKeywords as $folder => $keywords) {
                if ($profileIndustry === $folder || in_array($profileIndustry, $keywords, true)) {
                    return $folder;
                }
            }
        }

        foreach ($industryKeywords as $folder => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($normalizedPrompt, $keyword)) {
                    return $folder;
                }
            }
        }

        return 'default';
    }

    private function plannerCacheKey(string $prompt): string
    {
        $registry = \App\AI\Registries\SparkPlannerRegistry::slugs();

        return 'cosmic:ai:planner:' . hash('sha256', json_encode([
            'prompt' => $this->normalizeCachePrompt($prompt),
            'model' => config('openai.planner_model'),
            'registry' => $registry,
            'version' => '4.1.0.6',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function contentCacheKey(string $prompt, array $sections): string
    {
        return 'cosmic:ai:content:' . hash('sha256', json_encode([
            'prompt' => $this->normalizeCachePrompt($prompt),
            'sections' => array_values($sections),
            'model' => config('openai.content_model'),
            'version' => '4.1.0.6',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function normalizeCachePrompt(string $prompt): string
    {
        $prompt = preg_replace('/\s+/', ' ', trim($prompt)) ?? trim($prompt);

        return function_exists('mb_strtolower')
            ? mb_strtolower($prompt, 'UTF-8')
            : strtolower($prompt);
    }

    private function industryKeywords(): array
    {
        return [
            'bakery' => ['bakery', 'pastry', 'pastries', 'bread', 'cake', 'cakes', 'dessert', 'desserts'],
            'coffee' => ['coffee', 'coffee shop', 'cafe', 'café', 'espresso', 'roastery'],
            'hotel' => ['hotel', 'resort', 'accommodation', 'lodging', 'boutique hotel'],
            'travel' => ['travel', 'tour', 'tourism', 'vacation', 'holiday', 'destination', 'yacht', 'yachting', 'superyacht', 'charter', 'yacht charter', 'cruise', 'cruises', 'sailing', 'sailboat', 'catamaran', 'mediterranean'],
            'restaurant' => ['restaurant', 'dining', 'food', 'pizza', 'pasta', 'catering', 'bistro'],
            'dentist' => ['dentist', 'dental', 'orthodontist', 'orthodontic', 'teeth whitening'],
            'medical' => ['medical', 'healthcare', 'health care', 'clinic', 'doctor', 'physician', 'wellness center'],
            'fitness' => ['fitness', 'gym', 'personal trainer', 'workout', 'crossfit', 'yoga studio'],
            'cleaning' => ['cleaning', 'house cleaning', 'commercial cleaning', 'janitorial', 'maid service'],
            'landscaping' => ['landscaping', 'landscape', 'lawn care', 'garden design', 'tree service'],
            'lawyer' => ['lawyer', 'law firm', 'attorney', 'legal services', 'legal counsel'],
            'finance' => ['finance', 'financial advisor', 'accounting', 'accountant', 'bookkeeping', 'wealth management'],
            'real-estate' => ['real estate', 'realtor', 'property listing', 'property management', 'realty'],
            'technology' => ['technology', 'software', 'saas', 'tech startup', 'it services', 'web development'],
            'education' => ['education', 'school', 'academy', 'tutoring', 'training center', 'online course'],
            'salon' => ['salon', 'beauty', 'hair stylist', 'barber', 'spa', 'nail studio'],
            'roofing' => ['roofing', 'roofer', 'roof repair', 'roof replacement'],
            'electrician' => ['electrician', 'electrical', 'wiring', 'electric service'],
            'plumbing' => ['plumbing', 'plumber', 'drain cleaning', 'water heater', 'pipe repair'],
            'construction' => ['construction', 'contractor', 'home builder', 'renovation', 'remodeling', 'remodelling'],
            'automotive' => ['automotive', 'car dealership', 'car dealer', 'auto repair', 'mechanic', 'garage', 'car service', 'vehicle', 'car wash', 'detailing', 'tire shop'],
        ];
    }
}
