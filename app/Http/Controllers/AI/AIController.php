<?php

namespace App\Http\Controllers\AI;

use App\Cosmic\Pricing\ActionPricing;
use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Website;
use App\Services\MediaPackImageService;
use App\Services\MediaPackOwnershipService;
use App\Services\MediaAssetLifecycleService;
use App\Services\TrialRemoteImageService;
use App\Services\AiPageGenerationService;
use App\Services\CreditService;
use App\Services\LunaCategoryPageService;
use App\Services\LunaPexelsVideoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Exceptions\TransporterException;

class AIController extends Controller
{
    public function __construct(
        private readonly AiPageGenerationService $pageGenerationService,
        private readonly CreditService $credits,
        private readonly MediaPackImageService $mediaPackImages,
        private readonly MediaPackOwnershipService $mediaPackOwnership,
        private readonly MediaAssetLifecycleService $mediaAssets,
        private readonly TrialRemoteImageService $remoteImages,
        private readonly LunaCategoryPageService $lunaCategoryPages,
        private readonly LunaPexelsVideoService $lunaVideos,
    ) {
    }

    public function generateContent(Request $request)
    {
        set_time_limit(240);

        $validated = $request->validate([
            'prompt' => ['required', 'string'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'string'],
            'image_folder' => ['nullable', 'string'],
            'generation_type' => ['nullable', 'string', 'in:page,section,template'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'header_overlay_enabled' => ['nullable', 'boolean'],
            'design_seed' => ['nullable', 'string', 'max:100'],
        ]);

        $website = null;
        if (! empty($validated['website_id'])) {
            $website = Website::findOrFail($validated['website_id']);
            $this->authorize('update', $website);
        }

        $headerOverlayEnabled = (bool) ($validated['header_overlay_enabled'] ?? false);
        $generationPrompt = $this->withHeaderOverlayContext($validated['prompt'], $headerOverlayEnabled);
        $generationPrompt = $this->withLunaCreativeContext(
            $generationPrompt,
            (string) ($validated['design_seed'] ?? ''),
        );

        $generationType = $validated['generation_type'] ?? (count($validated['sections']) > 1 ? 'page' : 'section');
        // Luna chooses the website color family once: on the first real page build.
        // Existing websites keep their saved theme unless the user explicitly asks
        // for a color/theme change through the dedicated theme controls/Luna flow.
        $firstBuildTheme = null;
        if ($website && $generationType === 'page' && $this->isFirstWebsiteBuild($website)) {
            $firstBuildTheme = $this->themeForFirstBuildPrompt($validated['prompt']);
            $settings = (array) ($website->theme_settings ?? []);
            $settings['primary'] = $firstBuildTheme;
            $settings['secondary'] = $settings['secondary'] ?? 'white';
            $settings['tertiary'] = $settings['tertiary'] ?? 'stone';
            $settings['auto'] = true;
            $website->forceFill(['theme_settings' => $settings])->save();
            $website->setAttribute('theme_settings', $settings);

            Log::info('[LunaTheme] First-build website theme selected.', [
                'website_id' => $website->id,
                'theme' => $firstBuildTheme,
            ]);
        }
        $cost = match ($generationType) {
            'page' => ActionPricing::GENERATE_PAGE,
            'template' => ActionPricing::TEMPLATE_AI_PERSONALIZE,
            default => ActionPricing::SPARK_AI_PERSONALIZE,
        };
        $reference = 'ai-' . Str::uuid();

        $this->credits->consume(
            $request->user(),
            $cost,
            $generationType === 'page' ? 'Generate page with Cosmic AI' : ($generationType === 'template' ? 'Personalize page template with Cosmic AI' : 'Generate section with Cosmic AI'),
            $website,
            $reference,
            [
                'generation_type' => $generationType,
                'sections' => $validated['sections'],
                'estimated_cost' => $cost,
                'pricing_rule' => $generationType === 'section' ? 'spark_ai_personalize' : ($generationType === 'template' ? 'template_ai_personalize' : 'generate_page'),
            ],
        );

        // Registered Builder generation follows the same visual model as /start:
        // remote Unsplash URLs are primary, while the resolved industry library
        // is fallback-only. The content pass is forbidden from downloading remote
        // provider images into Cosmic storage.
        $imageFolder = $validated['image_folder']
            ?? $this->pageGenerationService->resolveLayoutFolder($validated['prompt']);

        try {
            $usesLunaCategorySchema = $generationType === 'page'
                && collect($validated['sections'])->every(fn ($section) => is_string($section) && str_starts_with($section, 'luna:'));

            if ($usesLunaCategorySchema) {
                $blocks = $this->lunaCategoryPages->generate($generationPrompt, $validated['sections']);

                if ($website) {
                    try {
                        $remoteResult = $this->pageGenerationService->applyStartPageRemoteImages(
                            $generationPrompt,
                            $blocks,
                            $imageFolder
                        );
                        $blocks = $remoteResult['blocks'];
                    } catch (\Throwable $mediaException) {
                        Log::warning('[LunaCustomSections] Remote image pass failed; keeping safe fallbacks.', [
                            'website_id' => $website->id,
                            'message' => $mediaException->getMessage(),
                        ]);
                    }
                }

                $blocks = $this->lunaVideos->apply($generationPrompt, $blocks);

                return response()->json([
                    'blocks' => $blocks,
                    'generation_meta' => ['mode' => 'luna_category_schema', 'custom_ui' => true],
                    'credits_spent' => $cost,
                    'credit_balance' => $this->credits->balance($request->user()),
                    'website_theme' => $firstBuildTheme,
                    'theme_selected_on_first_build' => $firstBuildTheme !== null,
                    'builder_protection' => [
                        'global_shell' => 'preserved',
                        'navigation' => 'preserved',
                        'uploaded_media' => 'client_merge_protected',
                    ],
                ]);
            }

            // Generate the content/schema first. For authenticated Builder requests,
            // image_url values are subsequently replaced by the same remote Unsplash
            // search pipeline used by /start. website_id is authorization/context only;
            // it is not an image source.
            $generation = $website
                ? $this->pageGenerationService->imagesWithoutRemoteDownloads(fn () =>
                    $this->pageGenerationService->generateBlocksDetailed(
                        $generationPrompt,
                        $validated['sections'],
                        $imageFolder
                    )
                )
                : $this->pageGenerationService->generateBlocksDetailed(
                    $generationPrompt,
                    $validated['sections'],
                    $imageFolder
                );

            $blocks = $generation['blocks'];

            if ($website) {
                // Registered Builder intentionally uses the exact same image
                // resolution path as /start. website_id is not consulted for
                // generated imagery; it remains authorization/context only.
                try {
                    $remoteResult = $this->pageGenerationService->applyStartPageRemoteImages(
                        $generationPrompt,
                        $blocks,
                        $imageFolder
                    );

                    $blocks = $remoteResult['blocks'];

                    Log::info('[RegisteredRemoteImages] Builder image_url values now mirror /start.', [
                        'website_id' => $website->id,
                        'remote_image_count' => count($remoteResult['remote_images'] ?? []),
                        'target_image_count' => (int) ($remoteResult['target_image_count'] ?? 0),
                        'image_source' => 'start_page_unsplash_flow',
                        'header_overlay_enabled' => $headerOverlayEnabled,
                    ]);
                } catch (\Throwable $mediaException) {
                    Log::warning('[RegisteredRemoteImages] /start Unsplash flow failed; returning generated fallback images.', [
                        'website_id' => $website->id,
                        'message' => $mediaException->getMessage(),
                    ]);
                }
            }

            $blocks = $this->lunaVideos->apply($generationPrompt, $blocks);

            return response()->json([
                'blocks' => $blocks,
                'generation_meta' => $generation['diagnostics'],
                'credits_spent' => $cost,
                'credit_balance' => $this->credits->balance($request->user()),
                'website_theme' => $firstBuildTheme,
                'theme_selected_on_first_build' => $firstBuildTheme !== null,
                'builder_protection' => [
                    'global_shell' => 'preserved',
                    'navigation' => 'preserved',
                    'uploaded_media' => 'client_merge_protected',
                ],
                'builder_context' => [
                    'header_overlay_enabled' => $headerOverlayEnabled,
                    'header_surface_rule' => $headerOverlayEnabled ? 'overlay_allowed' : 'solid_separate',
                ],
            ]);
        } catch (TransporterException $exception) {
            $this->refundFailedGeneration($request, $cost, $website, $reference, $validated['sections']);

            Log::warning('OpenAI content generation request failed', [
                'message' => $exception->getMessage(),
                'sections' => $validated['sections'],
                'image_folder' => $imageFolder,
            ]);

            return response()->json([
                'message' => 'Cosmic AI is temporarily unavailable. Your credits were refunded.',
            ], 503);
        } catch (\Throwable $exception) {
            $this->refundFailedGeneration($request, $cost, $website, $reference, $validated['sections']);

            Log::error('AI content generation failed unexpectedly', [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'sections' => $validated['sections'],
                'image_folder' => $imageFolder,
            ]);

            return response()->json([
                'message' => 'Page generation failed unexpectedly. Your credits were refunded.',
            ], 500);
        }
    }

    /**
     * Give Luna the Builder's current header-overlay state as a hard design
     * constraint. The setting is UI state, not something the model should infer
     * from the user's prose. This keeps regenerated pages/Sparks aligned with
     * the header treatment the user has explicitly selected.
     */
    private function withHeaderOverlayContext(string $prompt, bool $enabled): string
    {
        $directive = $enabled
            ? <<<'TEXT'
HEADER OVERLAY STATE: ENABLED.
The global header is intentionally allowed to overlay the first hero/banner. You may choose hero/banner treatments with a top fade, transparency-safe image composition, gradients, or other visual blending that supports readable overlaid navigation. Do not change the global header setting itself. The header runtime is contrast-aware: it may use a light or original logo/navigation treatment and will prefer the website primary CTA when readable, falling back to a light surface CTA only when the primary would merge into the hero. Do not hard-code the hero specifically to require a white CTA or white logo.
TEXT
            : <<<'TEXT'
HEADER OVERLAY STATE: DISABLED.
The global header must remain a solid, visually separate surface above the first hero/banner. Design the first section for a non-overlay header: do not create a top fade whose purpose is to blend into navigation, do not reserve transparent-header space, and do not rely on header-over-image contrast. Avoid transparent/glass/gradient-to-transparent header assumptions. The hero may still use normal image overlays for content readability, but its top edge must read as a clean section boundary below the solid header. Do not change the global header setting itself.
TEXT;

        return trim($prompt)."\n\nCOSMIC BUILDER SHELL CONTEXT\n".$directive;
    }


    /**
     * Give every full-page Luna pass its own art-direction identity while keeping
     * the audited Spark renderer/export contract intact. Existing library Sparks
     * remain manual choices; generated pages are encouraged to compose a fresh
     * combination and visual rhythm on every run.
     */
    private function withLunaCreativeContext(string $prompt, string $seed = '', array $avoidSections = []): string
    {
        $seed = trim($seed) !== '' ? trim($seed) : Str::uuid()->toString();
        $brandRule = 'Preserve the current brand identity/color-family intent, but redesign the page composition, section pacing, image treatment, and layout rhythm so it does not feel like a duplicate generation.';

        $avoid = collect($avoidSections)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn ($value) => trim($value))
            ->unique()
            ->take(30)
            ->values()
            ->all();

        $avoidRule = $avoid === []
            ? 'There is no previous generated Spark list to avoid.'
            : 'Previous generated Spark types: '.implode(', ', $avoid).'. Prefer different compatible Spark types and ordering when good alternatives exist; reuse only when page intent genuinely requires it.';

        return trim($prompt)."\n\nLUNA UNIQUE PAGE ART DIRECTION\n"
            ."Design seed: {$seed}\n"
            ."This generation must feel custom to this website rather than like a repeated template.\n"
            ."\nCOLOR SYSTEM RULE: Choose one primary solid brand color family first. Generate only lighter secondary and tertiary variations from that same family. Do not create unrelated section colors. Use accent colors sparingly for highlights and calls to action. {$brandRule}\n"
            ."{$avoidRule}\n"
            ."HERO EXPERIENCE RULE: Select the most suitable hero presentation for the business: static editorial image, image slider, parallax, cinematic video, fullscreen, split hero, or showcase. Prefer video/parallax/slider only when it supports the brand and has a fallback.\n"
            ."LUNA ART DIRECTOR RULE: Design the complete visitor experience, not a collection of sections. Do not turn every piece of information into cards. Decide whether content deserves editorial layouts, cinematic imagery, split compositions, galleries, timelines, interactive showcases, bento arrangements, or minimal content blocks. Use cards only when they improve usability.\n"
            ."Avoid repetitive patterns such as heading followed by cards repeated throughout the page. Do not use the same layout pattern for consecutive sections. Create visual rhythm with changing compositions, whitespace, typography hierarchy, imagery, and storytelling flow.\n"
            ."Content quantity does not determine layout. Ten services do not automatically become ten cards. Choose the most elegant presentation for the brand, audience, and industry. Think like a senior award-winning web designer and art director.\n"
            ."LUNA INDUSTRY ASSET INTELLIGENCE RULE: Identify the business industry before selecting imagery or visual direction. All generated media must match the business context, audience, and brand story. Never reuse unrelated assets from previous generations. Build visual keywords and avoid keywords before choosing images. For hospitality use destinations, rooms, dining, guests, and atmosphere. For construction use architecture, developments, materials, and projects. For restaurants use food, chefs, interiors, and dining experiences. Avoid unrelated subjects such as construction imagery for hotels or food imagery for professional services.\n"
            ."Use the registered Spark schemas as safe responsive/exportable building primitives, not as a reason to repeat the same page recipe. Vary hierarchy, visual pacing, media density, asymmetry, section transitions, and storytelling order. Existing manual Sparks remain available to the user separately.\n"
            ."LUNA VIDEO EXPERIENCE RULE: Decide when the website benefits from cinematic video, testimonial video, product demo, project walkthrough, slider, or parallax experiences. Use video intentionally based on industry and conversion goals. For video-capable Sparks, Cosmic will retrieve relevant landscape stock footage from Pexels after your content pass. Choose video Sparks only when motion materially improves the page; imagery continues through the Unsplash pipeline. Never invent remote video URLs.\n"
            ."LUNA BRAND SYSTEM: Act as the brand designer. Define a coherent color family using design tokens (primary, secondary, accent, background, surface, text, muted). Apply color theory based on industry and brand personality. Do not invent random colors per section; keep a unified brand system while allowing different section moods.";
    }

    private function refundFailedGeneration(Request $request, int $cost, ?Website $website, string $reference, array $sections): void
    {
        try {
            $this->credits->refund(
                $request->user(),
                $cost,
                'Refund for failed Cosmic AI generation',
                $website,
                $reference . '-refund',
                ['sections' => $sections],
            );
        } catch (\Throwable $refundException) {
            Log::critical('Cosmic Credit refund failed', [
                'message' => $refundException->getMessage(),
                'reference' => $reference,
                'user_id' => $request->user()?->id,
            ]);
        }
    }

    public function selectSections(Request $request)
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string'],
            'header_overlay_enabled' => ['nullable', 'boolean'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'design_seed' => ['nullable', 'string', 'max:100'],
            'avoid_sections' => ['nullable', 'array', 'max:30'],
            'avoid_sections.*' => ['string', 'max:120'],
        ]);

        $prompt = $this->withHeaderOverlayContext(
            $validated['prompt'],
            (bool) ($validated['header_overlay_enabled'] ?? false),
        );

        $prompt = $this->withLunaCreativeContext(
            $prompt,
            (string) ($validated['design_seed'] ?? ''),
            is_array($validated['avoid_sections'] ?? null) ? $validated['avoid_sections'] : [],
        );

        if (! empty($validated['design_seed'])) {
            $plan = $this->lunaCategoryPages->planDetailed($prompt);

            return response()->json([
                'sections' => $plan['sections'],
                'image_folder' => $this->pageGenerationService->resolveLayoutFolder($prompt),
                'planner' => $plan['planner'],
                'template_key' => $plan['template_key'],
                'template_name' => $plan['template_name'],
                'metadata_candidates' => $plan['metadata_candidates'],
            ]);
        }

        // Registered Builder: the first successful full-page generation for a
        // website gets Cosmic's media-led "wow" preference. After that Luna is
        // fully unrestricted. We derive success from the existing credit ledger
        // (failed generations are paired with a refund), avoiding browser-only
        // state and avoiding any database migration.
        if (! empty($validated['website_id']) && $request->user()) {
            $website = Website::findOrFail($validated['website_id']);
            $this->authorize('update', $website);

            $pageDebits = CreditTransaction::query()
                ->where('user_id', $request->user()->id)
                ->where('website_id', $website->id)
                ->where('type', 'debit')
                ->where('description', 'Generate page with Cosmic AI')
                ->count();

            $failedRefunds = CreditTransaction::query()
                ->where('user_id', $request->user()->id)
                ->where('website_id', $website->id)
                ->where('type', 'refund')
                ->where('description', 'Refund for failed Cosmic AI generation')
                ->count();

            $isInitialRegisteredGeneration = $pageDebits <= $failedRefunds;

            if ($isInitialRegisteredGeneration) {
                $prompt .= "\n\nINITIAL REGISTERED GENERATION VISUAL DIRECTIVE:"
                    ." Make the opening hero visually impressive and media-led where compatible with the business."
                    ." Strongly prefer slider, video, parallax, cinematic gallery, image-sequence, Ken Burns, or another premium image-led hero over a plain solid-color opening hero."
                    ." Keep industry, page intent, Spark registration, and compatibility rules authoritative.";
            }
        }

        $plan = $this->lunaCategoryPages->planDetailed($prompt);

        return response()->json([
            'sections' => $plan['sections'],
            'image_folder' => $this->pageGenerationService->resolveLayoutFolder($prompt),
            'planner' => $plan['planner'],
            'template_key' => $plan['template_key'],
            'template_name' => $plan['template_name'],
            'metadata_candidates' => $plan['metadata_candidates'],
        ]);
    }

    public function selectSection(Request $request)
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'in:hero,services,feature,pricing,testimonials,process,stats,team,cta,contact'],
            'prompt' => ['required', 'string'],
        ]);

        return response()->json(
            $this->pageGenerationService->selectSection(
                $validated['category'],
                $validated['prompt']
            )
        );
    }
    /**
     * A newly-created website may already contain a blank Home page. Treat it as
     * "first build" until any standard page has actual Spark blocks.
     */
    private function isFirstWebsiteBuild(Website $website): bool
    {
        return ! $website->pages()
            ->get(['blocks'])
            ->contains(fn ($page) => is_array($page->blocks) && count($page->blocks) > 0);
    }

    /**
     * Deterministic prompt-aware first-build palette. Luna selects from Cosmic's
     * export-safe theme families; she does not invent arbitrary colors here.
     */
    private function themeForFirstBuildPrompt(string $prompt): string
    {
        $p = Str::lower(trim($prompt));

        // First-build theme selection is intent-first. Midnight is never used as
        // a generic fallback; it is selected only when the user explicitly asks
        // for a midnight/night-style direction.
        if (Str::contains($p, ['midnight', 'midnight blue', 'night theme', 'night-time', 'nighttime'])) {
            return 'midnight';
        }

        $intentPools = [
            // Industry beats generic mood on a fresh build. A request such as
            // "premium automotive" must not be swallowed by the generic
            // luxury pool and repeatedly resolve to the same dark palette.
            [
                'terms' => ['automotive', 'auto service', 'car service', 'vehicle service', 'mechanic', 'car repair', 'auto repair', 'detailing', 'tire shop', 'tyre shop'],
                'themes' => ['teal', 'indigo', 'emerald', 'asphalt', 'ocean', 'navy'],
            ],
            [
                'terms' => ['luxury', 'exclusive', 'high-end', 'high end', 'cinematic', 'dramatic', 'black luxury'],
                'themes' => ['obsidian', 'charcoal', 'asphalt'],
            ],
            [
                'terms' => ['architecture', 'architect', 'construction', 'builder', 'engineering', 'industrial', 'property developer'],
                'themes' => ['asphalt', 'navy', 'terracotta', 'charcoal'],
            ],
            [
                'terms' => ['restaurant', 'dining', 'food', 'hospitality', 'chef', 'bar', 'bistro'],
                'themes' => ['terracotta', 'espresso', 'coffee', 'rose'],
            ],
            [
                'terms' => ['coffee', 'cafe', 'bakery', 'chocolate', 'roastery'],
                'themes' => ['coffee', 'espresso', 'terracotta'],
            ],
            [
                'terms' => ['finance', 'financial', 'law', 'legal', 'corporate', 'investment', 'accounting', 'insurance'],
                'themes' => ['navy', 'indigo', 'teal', 'asphalt'],
            ],
            [
                'terms' => ['technology', 'tech', 'saas', 'software', 'cyber', 'artificial intelligence', 'ai-powered', 'ai powered', 'startup'],
                'themes' => ['indigo', 'violet', 'navy', 'teal'],
            ],
            [
                'terms' => ['medical', 'healthcare', 'health care', 'clinic', 'dental', 'dentist', 'wellness'],
                'themes' => ['teal', 'emerald', 'ocean', 'navy'],
            ],
            [
                'terms' => ['eco', 'sustainable', 'organic', 'farm', 'nature', 'environment', 'landscape', 'garden', 'outdoor'],
                'themes' => ['emerald', 'forest', 'teal', 'terracotta'],
            ],
            [
                'terms' => ['travel', 'resort', 'hotel', 'coastal', 'beach', 'marine', 'island', 'tourism'],
                'themes' => ['ocean', 'teal', 'terracotta', 'navy'],
            ],
            [
                'terms' => ['beauty', 'salon', 'wedding', 'floral', 'fashion', 'skincare', 'spa'],
                'themes' => ['rose', 'terracotta', 'violet', 'espresso'],
            ],
            [
                'terms' => ['creative', 'studio', 'agency', 'portfolio', 'experimental', 'futuristic', 'artist'],
                'themes' => ['violet', 'indigo', 'terracotta', 'teal'],
            ],
            [
                'terms' => ['earthy', 'warm clay', 'mediterranean', 'artisan', 'handmade', 'craft'],
                'themes' => ['terracotta', 'coffee', 'espresso', 'forest'],
            ],
        ];

        foreach ($intentPools as $intent) {
            if (! Str::contains($p, $intent['terms'])) {
                continue;
            }

            $themes = $intent['themes'];
            return $themes[abs(crc32($p.'|'.implode('|', $themes))) % count($themes)];
        }

        // Explicit color-family language remains deterministic.
        $explicit = match (true) {
            Str::contains($p, ['terracotta', 'clay']) => 'terracotta',
            Str::contains($p, ['violet', 'purple']) => 'violet',
            Str::contains($p, ['emerald']) => 'emerald',
            Str::contains($p, ['forest']) => 'forest',
            Str::contains($p, ['ocean']) => 'ocean',
            Str::contains($p, ['espresso']) => 'espresso',
            Str::contains($p, ['coffee']) => 'coffee',
            Str::contains($p, ['rose', 'pink']) => 'rose',
            Str::contains($p, ['navy']) => 'navy',
            Str::contains($p, ['indigo']) => 'indigo',
            Str::contains($p, ['teal']) => 'teal',
            Str::contains($p, ['charcoal']) => 'charcoal',
            Str::contains($p, ['asphalt']) => 'asphalt',
            Str::contains($p, ['obsidian']) => 'obsidian',
            default => null,
        };
        if ($explicit !== null) {
            return $explicit;
        }

        // Neutral/ambiguous first prompts deliberately diversify. Midnight is
        // excluded so an unspecified first build cannot silently bias dark/navy.
        $fallback = [
            'emerald', 'navy', 'indigo', 'terracotta', 'violet', 'ocean',
            'teal', 'forest', 'coffee', 'rose', 'asphalt', 'espresso',
        ];

        return $fallback[abs(crc32($p !== '' ? $p : 'cosmic-first-build')) % count($fallback)];
    }

}