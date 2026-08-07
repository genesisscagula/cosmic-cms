<?php

namespace App\Services;

use App\AI\Cache\AiCacheManager;
use App\AI\Compatibility\SparkCompatibilityChecker;
use App\AI\Generators\ContentGenerator;
use App\AI\Layouts\LayoutEngine;
use App\AI\Pipeline\AiPipelineOrchestrator;
use App\AI\Pipeline\ParallelProcessingEngine;
use App\AI\Images\VisualQueryBuilder;
use App\Models\MediaPack;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiPageGenerationService
{
    /**
     * Global website shell blocks are never valid AI-generated page Sparks.
     * Keeping this deny-list here is a second-line guard in case a future
     * model/schema accidentally emits branding/navigation structures.
     *
     * @var array<int, string>
     */
    private const PROTECTED_SHELL_TYPES = [
        'glassmorphism_header',
        'dark_cyan_header',
        'minimal_footer',
        'detailed_footer',
        'header',
        'footer',
        'navigation',
        'navbar',
    ];

    public function __construct(
        private readonly SmartImageService $images,
        private readonly AiPipelineOrchestrator $pipeline,
        private readonly ParallelProcessingEngine $parallelEngine,
        private readonly SparkCompatibilityChecker $compatibilityChecker,
        private readonly AiGenerationAnalytics $analytics,
        private readonly VisualQueryBuilder $visualQueryBuilder,
        private readonly IndustryResolver $industryResolver,
        private readonly AiCacheManager $cache,
        private readonly TrialRemoteImageService $trialRemoteImages,
    ) {
    }

    public function generateTrialPage(string $prompt, ?int $trialId = null): array
    {
        $startedAt = microtime(true);

        // Stage 1 is deliberately tiny: understand the business and produce
        // image-search intent before the heavier Spark/content stages. Patch 16.2
        // will use this boundary to dispatch the media queue immediately.
        try {
            $visualIntent = $this->pipeline->analyzeVisualIntent($prompt);
        } catch (\Throwable $exception) {
            Log::warning('[TrialGenerationPipeline] Visual intent analysis failed; using deterministic fallback.', [
                'message' => $exception->getMessage(),
            ]);

            $visualIntent = [
                'business_type' => trim($prompt),
                'fallback_industry' => $this->resolveLayoutFolder($prompt),
                'image_keywords' => [trim($prompt)],
                'visual_style' => 'professional editorial',
            ];
        }

        // Patch 16.3: images are an independent branch. Dispatch them immediately
        // and continue the required AI branch without waiting for downloads. Spark
        // planning -> schema-bound content stays correctly dependency ordered.
        $parallel = $this->parallelEngine->execute(
            fn () => ['status' => 'remote-preview', 'trial_id' => $trialId],
            function () use ($prompt) {
                $selection = $this->selectSections($prompt);
                $placeholderFolder = 'default';
                $blocks = $this->images->withRemoteDownloadBudget(0, fn () => $this->generateBlocks(
                    $prompt,
                    $selection['sections'],
                    $placeholderFolder
                ));

                return [
                    'selection' => $selection,
                    'placeholder_folder' => $placeholderFolder,
                    'blocks' => $blocks,
                ];
            }
        );

        $selection = $parallel['result']['selection'];
        $placeholderFolder = $parallel['result']['placeholder_folder'];
        $blocks = $parallel['result']['blocks'];

        Log::info('[TrialGenerationPipeline] Parallel branches merged.', [
            'placeholder_folder' => $placeholderFolder,
            'parallel' => $parallel['diagnostics'],
            'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        // Patch A: trial previews hotlink a small Unsplash pool instead of
        // downloading media into Cosmic storage. The generated local/curated
        // image values remain as a safe fallback if Unsplash is unavailable.
        $mediaQueries = $this->learningQueries($prompt, $blocks, $placeholderFolder);
        $targetImageCount = min(10, count($mediaQueries));
        $remoteImages = $this->trialRemoteImages->resolve($visualIntent, $targetImageCount);
        $blocks = $this->trialRemoteImages->assignToBlocks($blocks, $remoteImages);
        $mediaKeywords = collect($visualIntent['image_keywords'] ?? [])
            ->merge(collect($mediaQueries)->pluck('query'))
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn ($keyword) => trim($keyword))
            ->unique()
            ->take(6)
            ->values()
            ->all();
        Log::info('[TrialGenerationPipeline] Trial result ready.', [
            'placeholder_folder' => $placeholderFolder,
            'blocks' => count($blocks),
            'media_keywords' => count($mediaKeywords),
            'target_image_count' => $targetImageCount,
            'remote_image_count' => count($remoteImages),
            'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return [
            'sections' => $selection['sections'],
            'image_folder' => $placeholderFolder,
            'blocks' => $blocks,
            'media_keywords' => $mediaKeywords,
            'target_image_count' => $targetImageCount,
            'visual_intent' => $visualIntent,
            'remote_images' => $remoteImages,
            'parallel' => $parallel['diagnostics'],
        ];
    }


    public function imagesWithoutRemoteDownloads(callable $callback): mixed
    {
        return $this->images->withRemoteDownloadBudget(0, $callback);
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
        $cacheEnabled = (bool) config('openai.content_cache_enabled', false);
        $cached = $this->cache->remember(
            'content',
            [
                'prompt' => $this->cache->normalizePrompt($prompt),
                'sections' => array_values($sections),
                'model' => config('openai.content_model'),
                'version' => '16.4.0',
            ],
            (int) config('openai.content_cache_ttl', 3600),
            function () use ($prompt, $sections) {
                try {
                    $content = $this->pipeline->generateContent($prompt, $sections);
                    $this->analytics->increment('content_successes');
                    return $content;
                } catch (\Throwable $exception) {
                    $this->analytics->increment('content_failures');
                    throw $exception;
                }
            },
            $cacheEnabled,
        );

        $content = is_array($cached['value']) ? $cached['value'] : [];
        $cacheStatus = $cached['cache'];
        $cacheKey = $cached['key'];

        if ($cacheStatus === 'hit') {
            $this->analytics->increment('content_cache_hits');
        } else {
            $this->analytics->increment('content_cache_misses');
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
                $block['poster_image_url'] = $this->images->find($query, $resolvedImageFolder, 'hero');
                continue;
            }

            if ($type === 'hero_agency_showcase') {
                $images = $this->images->localFallbacks($resolvedImageFolder, 2);
                $block['before_image_url'] = $images[0] ?? $this->images->find($query.' before redesign', $resolvedImageFolder, 'case-study');
                $block['after_image_url'] = $images[1] ?? $this->images->find($query.' after redesign', $resolvedImageFolder, 'case-study');
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

            $block['image_url'] = $this->images->find($query, $resolvedImageFolder, $this->imageRoleForBlock($type));
        }

        unset($block);

        $beforeProtection = count($blocks);
        $blocks = $this->protectBrandingShell($blocks);
        if (count($blocks) !== $beforeProtection) {
            Log::warning('[AiBrandingProtection] Protected global shell block removed from AI output.', [
                'removed_count' => $beforeProtection - count($blocks),
            ]);
        }

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

    /** @param array<int, mixed> $blocks
     *  @return array<int, mixed>
     */
    private function protectBrandingShell(array $blocks): array
    {
        return collect($blocks)
            ->reject(function ($block): bool {
                if (! is_array($block)) {
                    return false;
                }

                $type = strtolower(trim((string) ($block['type'] ?? '')));

                return in_array($type, self::PROTECTED_SHELL_TYPES, true);
            })
            ->values()
            ->all();
    }

    public function selectSections(string $prompt): array
    {
        $this->analytics->increment('planner_requests');

        $imageFolder = $this->resolveLayoutFolder($prompt);
        $cacheEnabled = (bool) config('openai.planner_cache_enabled', true);
        $cached = $this->cache->remember(
            'planner',
            [
                'prompt' => $this->cache->normalizePrompt($prompt),
                'model' => config('openai.planner_model'),
                'registry' => \App\AI\Registries\SparkPlannerRegistry::slugs(),
                'version' => '16.4.0',
            ],
            (int) config('openai.planner_cache_ttl', 86400),
            function () use ($prompt, $imageFolder) {
                try {
                    $sections = $this->pipeline->selectSparks($prompt);
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

                return [
                    'sections' => $compatibility['sections'],
                    'image_folder' => $imageFolder,
                    'planner' => $planner,
                    'page_intent' => $compatibility['intent'],
                    'compatibility' => [
                        'changed' => $compatibility['changed'],
                        'changes' => $compatibility['changes'],
                    ],
                ];
            },
            $cacheEnabled,
        );

        $result = is_array($cached['value']) ? $cached['value'] : [];
        $result['cache'] = $cached['cache'];

        if ($cached['cache'] === 'hit') {
            $this->analytics->increment('planner_cache_hits');
        } else {
            $this->analytics->increment('planner_cache_misses');
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
        return $this->industryResolver->resolve($prompt, 'default');
    }

    private function learningQueries(string $prompt, array $blocks, string $industry): array
    {
        $queries = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? 'website section');
            $baseQuery = $this->visualQueryBuilder->build($prompt, $block, $type, $industry);
            $role = $this->imageRoleForBlock($type);
            $slotCount = 0;

            if ($type === 'hero_video_background') {
                $slotCount = 1;
            } elseif ($type === 'hero_agency_showcase') {
                $slotCount = 2;
            } elseif ($type === 'case_studies_grid' && is_array($block['studies'] ?? null)) {
                $slotCount = count($block['studies']);
            } elseif (array_key_exists('image_url', $block)) {
                $slotCount = 1;
            }

            for ($slot = 0; $slot < $slotCount; $slot++) {
                $queries[] = [
                    'query' => $baseQuery,
                    'role' => $role,
                ];
            }
        }

        return $queries;
    }

    private function imageRoleForBlock(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'hero_') => 'hero',
            str_contains($type, 'team') || str_contains($type, 'testimonial') => 'people',
            str_contains($type, 'gallery') || str_contains($type, 'portfolio') || str_contains($type, 'case_stud') => 'gallery',
            str_contains($type, 'service') || str_contains($type, 'feature') => 'services',
            default => 'general',
        };
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
