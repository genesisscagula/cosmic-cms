<?php

namespace App\Http\Controllers\AI;

use App\Cosmic\Pricing\ActionPricing;
use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Http\Controllers\Controller;
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
            'generation_type' => ['nullable', 'string', 'in:page,section'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
        ]);

        $website = null;
        if (! empty($validated['website_id'])) {
            $website = Website::findOrFail($validated['website_id']);
            $this->authorize('update', $website);
        }

        $generationType = $validated['generation_type'] ?? (count($validated['sections']) > 1 ? 'page' : 'section');
        $cost = $generationType === 'page'
            ? ActionPricing::GENERATE_PAGE
            : ActionPricing::SPARK_AI_PERSONALIZE;
        $reference = 'ai-' . Str::uuid();

        $this->credits->consume(
            $request->user(),
            $cost,
            $generationType === 'page' ? 'Generate page with Cosmic AI' : 'Generate section with Cosmic AI',
            $website,
            $reference,
            [
                'generation_type' => $generationType,
                'sections' => $validated['sections'],
                'estimated_cost' => $cost,
                'pricing_rule' => $generationType === 'section' ? 'spark_ai_personalize' : 'generate_page',
            ],
        );

        // Logged-in generation must use the website-owned media pack as the
        // primary source. Curated industry/default libraries are fallback only.
        $imageFolder = $website ? 'default' : (
            $validated['image_folder']
                ?? $this->pageGenerationService->resolveLayoutFolder($validated['prompt'])
        );

        try {
            $generation = $website
                ? $this->pageGenerationService->imagesWithoutRemoteDownloads(fn () =>
                    $this->pageGenerationService->generateBlocksDetailed(
                        $validated['prompt'],
                        $validated['sections'],
                        $imageFolder
                    )
                )
                : $this->pageGenerationService->generateBlocksDetailed(
                    $validated['prompt'],
                    $validated['sections'],
                    $imageFolder
                );

            $blocks = $generation['blocks'];

            if ($website) {
                $target = $this->mediaPackImages->imageSlotCount($blocks);
                $remoteImages = $this->remoteImages->resolve([
                    'business_type' => $validated['prompt'],
                    'image_keywords' => [$validated['prompt']],
                    'visual_style' => 'professional editorial website photography',
                ], $target);

                $blocks = $this->remoteImages->assignToBlocks($blocks, $remoteImages);

                $pack = $this->mediaPackOwnership->ensureForWebsite(
                    $website,
                    [$validated['prompt']],
                    0
                );

                $this->mediaAssets->syncRemoteManifest(
                    $pack,
                    $blocks,
                    'remote_logged_in_preview',
                    $remoteImages
                );

                Log::info('[MediaAssets] Logged-in Builder is using remote preview images; local download deferred until publish.', [
                    'website_id' => $website->id,
                    'media_pack_id' => $pack->id,
                    'remote_image_count' => count($remoteImages),
                    'target_image_count' => $target,
                ]);
            }

            return response()->json([
                'blocks' => $blocks,
                'generation_meta' => $generation['diagnostics'],
                'credits_spent' => $cost,
                'credit_balance' => $this->credits->balance($request->user()),
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
        ]);

        return response()->json(
            $this->pageGenerationService->selectSections($validated['prompt'])
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
