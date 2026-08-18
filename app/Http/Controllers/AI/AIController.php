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
        ]);

        $website = null;
        if (! empty($validated['website_id'])) {
            $website = Website::findOrFail($validated['website_id']);
            $this->authorize('update', $website);
        }

        $headerOverlayEnabled = (bool) ($validated['header_overlay_enabled'] ?? false);
        $generationPrompt = $this->withHeaderOverlayContext($validated['prompt'], $headerOverlayEnabled);

        $generationType = $validated['generation_type'] ?? (count($validated['sections']) > 1 ? 'page' : 'section');
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

            return response()->json([
                'blocks' => $blocks,
                'generation_meta' => $generation['diagnostics'],
                'credits_spent' => $cost,
                'credit_balance' => $this->credits->balance($request->user()),
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
        ]);

        $prompt = $this->withHeaderOverlayContext(
            $validated['prompt'],
            (bool) ($validated['header_overlay_enabled'] ?? false),
        );

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

        return response()->json(
            $this->pageGenerationService->selectSections($prompt)
        );
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
}
