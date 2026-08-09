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
            'generation_type' => ['nullable', 'string', 'in:page,section,template'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
        ]);

        $website = null;
        if (! empty($validated['website_id'])) {
            $website = Website::findOrFail($validated['website_id']);
            $this->authorize('update', $website);
        }

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
                // Registered Builder intentionally uses the exact same image
                // resolution path as /start. website_id is not consulted for
                // generated imagery; it remains authorization/context only.
                try {
                    $remoteResult = $this->pageGenerationService->applyStartPageRemoteImages(
                        $validated['prompt'],
                        $blocks,
                        $imageFolder
                    );

                    $blocks = $remoteResult['blocks'];

                    Log::info('[RegisteredRemoteImages] Builder image_url values now mirror /start.', [
                        'website_id' => $website->id,
                        'remote_image_count' => count($remoteResult['remote_images'] ?? []),
                        'target_image_count' => (int) ($remoteResult['target_image_count'] ?? 0),
                        'image_source' => 'start_page_unsplash_flow',
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
