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
        private readonly ImageSlotResolver $imageSlots,
        private readonly LunaCategoryPageService $lunaCategoryPages,
    ) {
    }

    public function generateTrialPage(string $prompt, ?int $trialId = null, ?array $regenerationContext = null): array
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
                'overlay_header_on_banner' => false,
            ];
        }

        // Patch 16.3: images are an independent branch. Dispatch them immediately
        // and continue the required AI branch without waiting for downloads. Spark
        // planning -> schema-bound content stays correctly dependency ordered.
        $parallel = $this->parallelEngine->execute(
            fn () => ['status' => 'remote-preview', 'trial_id' => $trialId],
            function () use ($prompt, $regenerationContext) {
                $selectionPrompt = $prompt;

                // Trial art direction: the first impression should showcase Cosmic's
                // richer visual capabilities. Prefer media-led hero Sparks on the
                // initial /start generation (slider, video, parallax and cinematic
                // image heroes) while still letting compatibility/page intent win.
                if (! is_array($regenerationContext)) {
                    $selectionPrompt .= "\n\nINITIAL TRIAL VISUAL DIRECTIVE:"
                        ." Make the opening hero visually impressive and media-led where compatible with the business."
                        ." Strongly prefer a slider, video, parallax, cinematic gallery, image-sequence, Ken Burns, or other premium image-led hero over a plain solid-color hero."
                        ." Suitable hero examples include hero_slider_fade, hero_cinematic_slider_premium, hero_crossfade_gallery_premium, hero_split_slider_premium, hero_video_background, hero_video_premium, hero_video_cinematic_premium, hero_video_split_premium, hero_parallax, hero_parallax_layers_premium, hero_reveal_parallax_premium, hero_mouse_parallax_premium, hero_ken_burns_premium, hero_editorial_image_sequence_premium, hero_background_image, and hero_luxury_fullscreen."
                        ." Use only registered compatible Sparks and keep the rest of the page balanced; this preference applies especially to the opening hero.";
                }

                if (is_array($regenerationContext)) {
                    $previous = collect($regenerationContext['previous_sections'] ?? [])
                        ->filter(fn ($section) => is_string($section) && trim($section) !== '')
                        ->map(fn ($section) => trim($section))
                        ->values()
                        ->all();

                    $selectionPrompt .= "\n\nREGENERATION LAYOUT DIRECTIVE:"
                        ." Choose a materially different page composition and Spark/layout combination from the previous version."
                        ." For the regenerated trial, deliberately move toward a cleaner solid-color/editorial/bento composition instead of another slider, video, parallax, cinematic gallery, or image-heavy opening hero unless the user's new prompt explicitly asks for one of those effects."
                        ." Prefer solid or restrained opening heroes such as hero_headline, hero_centered_cta, hero_split_editorial, hero_bento_premium, hero_saas_dashboard, hero_ai_conversation, or another compatible non-media-heavy premium hero."
                        ." Keep supporting imagery where it improves the page, but make this version feel like a visibly cleaner alternative to the initial trial."
                        ." Do not simply rewrite content inside the same structure."
                        .($previous ? " Previous Spark types to vary away from where compatible: ".implode(', ', $previous)."." : '')
                        ." Preserve the same business intent, but vary section ordering, compatible Spark variants, and visual rhythm."
                        ." Regeneration nonce: ".($regenerationContext['nonce'] ?? uniqid('', true));
                }

                // Trial generation uses the same category-schema custom UI path as the registered Builder.
                // Existing 200+ Sparks remain a manual library and are not planner candidates here.
                $categoryPlan = $this->lunaCategoryPages->planDetailed($selectionPrompt);
                $categorySections = is_array($categoryPlan['sections']??null)?$categoryPlan['sections']:[];
                $selection = [
                    'sections' => $categorySections,
                    'image_folder' => 'default',
                    'planner' => 'luna_template_metadata',
                    'page_intent' => $categoryPlan['page_intent']??'home',
                    'composition_industry' => $categoryPlan['composition_industry']??'general',
                    'composition_roles' => $categoryPlan['composition_roles']??[],
                    'composition_pass' => (bool)($categoryPlan['composition_pass']??false),
                    'template_key' => $categoryPlan['template_key']??null,
                ];
                $placeholderFolder = 'default';
                $blocks = $this->images->withRemoteDownloadBudget(0, fn () => $this->lunaCategoryPages->generate(
                    $prompt."\n\nTRIAL DESIGN NONCE: ".($regenerationContext['nonce'] ?? uniqid('', true)),
                    $categorySections
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
        $mediaQueries = $this->prioritizeRemoteImageQueries($mediaQueries);
        $targetImageCount = min((int) config('openai.remote_preview_image_slots', 24), count($mediaQueries));
        $remoteImages = $this->trialRemoteImages->resolveForQueries($visualIntent, $mediaQueries, 'TrialRemoteImages');
        $blocks = $this->trialRemoteImages->assignToBlocks($blocks, $remoteImages);
        $mediaKeywords = collect($visualIntent['image_keywords'] ?? [])
            ->merge(collect($mediaQueries)->pluck('query'))
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn ($keyword) => trim($keyword))
            ->unique()
            ->take(6)
            ->values()
            ->all();
        $firstBlockType = strtolower((string) data_get($blocks, '0.type', ''));
        $firstBlockCategory = strtolower((string) data_get($blocks, '0.category', ''));
        $supportsOverlayHeader = $firstBlockType !== ''
            && ($firstBlockCategory === 'hero' || $firstBlockType === 'hero' || str_contains($firstBlockType, 'hero') || str_contains($firstBlockType, 'banner'));
        $explicitOverlayRequest = Str::contains(Str::lower($prompt), [
            'overlay header',
            'transparent header',
            'header over hero',
            'navigation over hero',
            'menu over hero',
        ]);
        $overlayHeaderOnBanner = $supportsOverlayHeader
            && ($explicitOverlayRequest || (bool) ($visualIntent['overlay_header_on_banner'] ?? false));

        Log::info('[TrialGenerationPipeline] Trial result ready.', [
            'placeholder_folder' => $placeholderFolder,
            'blocks' => count($blocks),
            'media_keywords' => count($mediaKeywords),
            'target_image_count' => $targetImageCount,
            'remote_image_count' => count($remoteImages),
            'overlay_header_on_banner' => $overlayHeaderOnBanner,
            'elapsed_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return [
            'sections' => $selection['sections'],
            'image_folder' => $placeholderFolder,
            'blocks' => $blocks,
            'media_keywords' => $mediaKeywords,
            'target_image_count' => $targetImageCount,
            'visual_intent' => $visualIntent,
            'overlay_header_on_banner' => $overlayHeaderOnBanner,
            'remote_images' => $remoteImages,
            'parallel' => $parallel['diagnostics'],
        ];
    }


    /**
     * Resolve registered Builder page imagery through the exact same remote
     * Unsplash flow used by /start. Local/industry image values in $blocks are
     * fallback-only and remain untouched if Unsplash is unavailable.
     *
     * @return array{blocks: array, remote_images: array, target_image_count: int, visual_intent: array, media_keywords: array}
     */
    public function applyStartPageRemoteImages(string $prompt, array $blocks, ?string $imageFolder = null): array
    {
        $resolvedImageFolder = $imageFolder ?: $this->resolveLayoutFolder($prompt);

        try {
            $visualIntent = $this->pipeline->analyzeVisualIntent($prompt);
        } catch (\Throwable $exception) {
            Log::warning('[RegisteredRemoteImages] Visual intent analysis failed; using /start deterministic fallback.', [
                'message' => $exception->getMessage(),
            ]);

            $visualIntent = [
                'business_type' => trim($prompt),
                'fallback_industry' => $resolvedImageFolder,
                'image_keywords' => [trim($prompt)],
                'visual_style' => 'professional editorial',
                'overlay_header_on_banner' => false,
            ];
        }

        // Keep target calculation identical to /start so the registered Builder
        // receives the same number/style of remote provider images.
        $mediaQueries = $this->learningQueries($prompt, $blocks, $resolvedImageFolder);
        $mediaQueries = $this->prioritizeRemoteImageQueries($mediaQueries);
        $targetImageCount = min((int) config('openai.remote_preview_image_slots', 24), count($mediaQueries));

        // Registered generation follows the same slot-aware provider strategy
        // as /start so nested/multiple image fields receive equivalent imagery.
        $remoteImages = $this->trialRemoteImages->resolveForQueries($visualIntent, $mediaQueries, 'RegisteredRemoteImages');
        $blocks = $this->trialRemoteImages->assignToBlocks($blocks, $remoteImages);

        $mediaKeywords = collect($visualIntent['image_keywords'] ?? [])
            ->merge(collect($mediaQueries)->pluck('query'))
            ->filter(fn ($keyword) => is_string($keyword) && trim($keyword) !== '')
            ->map(fn ($keyword) => trim($keyword))
            ->unique()
            ->take(6)
            ->values()
            ->all();

        $sliderRequestedSlots = collect($mediaQueries)
            ->where('block_type', 'hero_slider_fade')
            ->count();
        $sliderResolvedSlots = collect($remoteImages)
            ->where('block_type', 'hero_slider_fade')
            ->count();

        Log::info('[RegisteredRemoteImages] Applied exact /start Unsplash flow.', [
            'image_folder_fallback' => $resolvedImageFolder,
            'target_image_count' => $targetImageCount,
            'remote_image_count' => count($remoteImages),
            'media_keywords' => count($mediaKeywords),
            'slider_requested_slots' => $sliderRequestedSlots,
            'slider_resolved_slots' => $sliderResolvedSlots,
            'slider_fallback_slots' => max(0, $sliderRequestedSlots - $sliderResolvedSlots),
        ]);

        return [
            'blocks' => $blocks,
            'remote_images' => $remoteImages,
            'target_image_count' => $targetImageCount,
            'visual_intent' => $visualIntent,
            'media_keywords' => $mediaKeywords,
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
                'version' => '19.0.0-spark-content-contracts',
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

            // Testimonials use Cosmic-controlled avatars only. Do not fetch random
            // people from external providers. Cycle through the six local avatar
            // placeholders so generated reviews stay brand-safe and editable.
            if (str_contains(strtolower($type), 'testimonial')) {
                $this->applyLocalTestimonialAvatars($block);
                Log::debug('[TestimonialsAvatarGuard] Applied local avatar assets.', [
                    'block_type' => $type,
                ]);
            }

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

            // Slider Showcase keeps its images inside slides[].image_url rather
            // than on the block root. Seed those nested slots with stable local
            // fallbacks before the registered/trial Unsplash pass. This prevents
            // a personalized template from rendering blank when Luna correctly
            // returns empty image_url values or when one provider lookup fails.
            if ($type === 'hero_slider_fade') {
                $slides = is_array($block['slides'] ?? null) ? array_values($block['slides']) : [];

                while (count($slides) < 3) {
                    $slides[] = [];
                }

                $fallbacks = $this->images->localFallbacks($resolvedImageFolder, count($slides));

                foreach ($slides as $slideIndex => &$slide) {
                    if (! is_array($slide)) {
                        $slide = [];
                    }

                    $existing = trim((string) ($slide['image_url'] ?? $slide['image'] ?? $slide['background_image'] ?? ''));
                    $slide['image_url'] = $existing !== ''
                        ? $existing
                        : ($fallbacks[$slideIndex] ?? '/cosmic-images/cosmic-fallback.svg');
                }

                unset($slide);
                $block['slides'] = $slides;

                Log::debug('[SliderShowcaseImages] Nested image slots prepared.', [
                    'slide_count' => count($slides),
                    'image_slot_count' => $this->imageSlots->count($block),
                    'image_folder' => $resolvedImageFolder,
                ]);

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

        $blocks = $this->enforceGeneratedMediaPolicies($blocks);

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

    /**
     * Apply local testimonial avatars and keep generated reviews editable.
     * Avatar images are intentionally not generated by AI.
     */
    private function applyLocalTestimonialAvatars(array &$block): void
    {
        $folder = '/cosmic-images/avatar/';
        $avatars = [
            $folder . 'avatar-1.svg',
            $folder . 'avatar-2.svg',
            $folder . 'avatar-3.svg',
            $folder . 'avatar-4.svg',
            $folder . 'avatar-5.svg',
            $folder . 'avatar-6.svg',
        ];

        $items = $block['items'] ?? $block['testimonials'] ?? $block['reviews'] ?? null;

        if (is_array($items)) {
            foreach ($items as $index => &$item) {
                if (is_array($item)) {
                    $avatar = $avatars[$index % count($avatars)];
                    // Support both legacy and current testimonial schema names.
                    $item['avatar'] = $avatar;
                    $item['avatar_url'] = $avatar;
                }
            }
            unset($item);

            if (array_key_exists('items', $block)) {
                $block['items'] = $items;
            } elseif (array_key_exists('testimonials', $block)) {
                $block['testimonials'] = $items;
            } else {
                $block['reviews'] = $items;
            }
            return;
        }

        // Legacy single testimonial schemas.
        $block['avatar'] = $avatars[0];
        $block['avatar_url'] = $avatars[0];
    }

    /**
     * Keep generated media predictable across Builder and export. User edits remain
     * free to replace these values later through the shared video/image editors.
     */
    private function enforceGeneratedMediaPolicies(array $blocks): array
    {
        $videoPlaceholder = '/storage/cms-videos/hero-placeholder.mp4';

        foreach ($blocks as &$block) {
            if (! is_array($block)) {
                continue;
            }

            $type = strtolower((string) ($block['type'] ?? ''));

            if (str_contains($type, 'testimonial')) {
                $this->applyLocalTestimonialAvatars($block);
            }

            // Generated hero/testimonial video experiences start from one stable
            // local MP4. The Builder's EditableVideoSource can subsequently accept
            // MP4, YouTube or Vimeo without changing the stored block contract.
            if ((str_contains($type, 'hero_video') || $type === 'testimonials_video_premium')
                && array_key_exists('video_url', $block)) {
                $video = trim((string) ($block['video_url'] ?? ''));
                if ($video === '' || $video === '#') {
                    $block['video_url'] = $videoPlaceholder;
                }
            }
        }
        unset($block);

        return $blocks;
    }

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
                'version' => '19.0.0-spark-content-contracts',
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

        foreach ($blocks as $blockIndex => $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? 'website section');
            $baseQuery = $this->visualQueryBuilder->build($prompt, $block, $type, $industry);
            $slots = $this->imageSlots->slots($block);

            foreach ($slots as $slotIndex => $slot) {
                $role = (string) ($slot['role'] ?? $this->imageRoleForBlock($type));
                $path = (string) ($slot['path'] ?? '');
                $query = $this->slotSpecificQuery($baseQuery, $type, $path, $slotIndex);

                $queries[] = [
                    'query' => $query,
                    'role' => $role,
                    'path' => $path,
                    'block_type' => $type,
                    'block_index' => $blockIndex,
                    'slot_index' => $slotIndex,
                ];
            }
        }

        return $queries;
    }

    /**
     * Keep hero slider imagery inside the 10-slot remote preview budget even on
     * image-heavy templates. Assignment remains path-based, so reordering query
     * work here cannot shift images into the wrong block.
     *
     * @param array<int, array<string,mixed>> $queries
     * @return array<int, array<string,mixed>>
     */
    private function prioritizeRemoteImageQueries(array $queries): array
    {
        return collect($queries)
            ->sortBy(fn (array $query) => ($query['block_type'] ?? '') === 'hero_slider_fade' ? 0 : 1)
            ->values()
            ->all();
    }

    private function slotSpecificQuery(string $baseQuery, string $type, string $path, int $slotIndex): string
    {
        if ($type === 'hero_slider_fade') {
            return trim($baseQuery.' editorial hero scene '.($slotIndex + 1));
        }

        if ($type === 'hero_agency_showcase') {
            if (str_contains($path, 'before_image_url')) {
                return trim($baseQuery.' before transformation');
            }

            if (str_contains($path, 'after_image_url')) {
                return trim($baseQuery.' after transformation');
            }
        }

        if (str_contains($type, 'team') || str_contains($type, 'testimonial')) {
            return trim($baseQuery.' professional portrait '.($slotIndex + 1));
        }

        if (str_contains($type, 'case_stud')) {
            return trim($baseQuery.' project case study '.($slotIndex + 1));
        }

        $normalizedType = Str::lower($type);
        $variants = match (true) {
            Str::contains($normalizedType, ['menu', 'dish', 'food']) => [
                'signature plated dish close up',
                'chef plating in working kitchen',
                'seasonal ingredients tactile detail',
                'shared table with distinct dishes',
            ],
            Str::contains($normalizedType, ['atmosphere', 'gallery']) => [
                'wide dining room establishing view',
                'intimate table setting detail',
                'architectural interior material detail',
                'evening hospitality ambience with guests',
            ],
            Str::contains($normalizedType, ['location', 'visit', 'map']) => [
                'restaurant storefront exterior',
                'neighborhood streetscape context',
                'welcoming entrance arrival detail',
                'recognizable nearby landmark',
            ],
            Str::contains($normalizedType, ['story', 'brand', 'about']) => [
                'chef or maker at work',
                'hospitality team candid moment',
                'artisan preparation close detail',
                'guest experience documentary scene',
            ],
            Str::contains($normalizedType, ['reservation', 'contact', 'cta']) => [
                'friends dining together naturally',
                'special occasion table gathering',
                'welcoming host greeting guests',
                'evening exterior invitation',
            ],
            default => [
                'wide establishing scene',
                'authentic human interaction',
                'close material detail',
                'behind the scenes working moment',
                'finished customer experience',
            ],
        };

        $variant = $variants[$slotIndex % count($variants)];

        return trim($baseQuery.' '.$variant);
    }

    private function imageRoleForBlock(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'hero_') => 'hero',
            str_contains($type, 'team') || str_contains($type, 'testimonial') => 'people',
            str_contains($type, 'gallery') || str_contains($type, 'portfolio') || str_contains($type, 'case_stud') || str_contains($type, 'location') => 'gallery',
            str_contains($type, 'service') || str_contains($type, 'feature') || str_contains($type, 'menu') || str_contains($type, 'dish') => 'services',
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
