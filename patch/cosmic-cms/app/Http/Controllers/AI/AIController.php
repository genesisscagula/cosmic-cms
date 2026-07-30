<?php

namespace App\Http\Controllers\AI;

use App\Cosmic\Pricing\ActionPricing;
use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Http\Controllers\Controller;
use App\Models\Website;
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
            : BlockPricingRegistry::estimate($validated['sections']);
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
            ],
        );

        $imageFolder = $validated['image_folder']
            ?? $this->pageGenerationService->resolveLayoutFolder($validated['prompt']);

        try {
            $blocks = $this->pageGenerationService->generateBlocks(
                $validated['prompt'],
                $validated['sections'],
                $imageFolder
            );

            return response()->json([
                'blocks' => $blocks,
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
