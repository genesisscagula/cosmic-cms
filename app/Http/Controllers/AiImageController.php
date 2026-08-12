<?php

namespace App\Http\Controllers;

use App\Models\TrialGeneration;
use App\Models\Website;
use App\Services\CreditWalletService;
use App\Services\TrialCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AiImageController extends Controller
{
    public const COST = 50;

    public function generate(Request $request, Website $website, CreditWalletService $wallet)
    {
        $this->authorize('update', $website);

        $user = $request->user();
        abort_unless($user, 401);

        if (! $wallet->canAfford($user, self::COST)) {
            return response()->json([
                'message' => 'Not enough Cosmic Credits. Luna image generation costs '.self::COST.' credits.',
                'required_credits' => self::COST,
                'available_credits' => $wallet->balance($user),
            ], 422);
        }

        $result = $this->generateAndStore($request, $website);

        // Charge only after a valid image has been generated and persisted.
        $wallet->debit(
            $user,
            self::COST,
            'Luna AI image generation',
            'ai_generation',
            $website,
            'luna-image-generation:'.Str::uuid(),
            [
                'prompt' => Str::limit((string) $result['prompt'], 240, ''),
                'size' => $result['size'],
                'block_type' => (string) $request->input('block_type', ''),
            ]
        );

        return response()->json([
            'status' => 'success',
            'url' => $result['url'],
            'cost' => self::COST,
            'balance' => $wallet->balance($user),
            'size' => $result['size'],
        ]);
    }

    public function generateTrial(Request $request, TrialGeneration $trial, TrialCreditService $credits)
    {
        abort_if($trial->claimed_at, 410, 'This trial has already been claimed.');

        $page = $trial->page()->with('website')->first();
        $website = $page?->website;
        abort_unless($website, 404, 'The trial website could not be found.');

        $credits->ensureCanSpend($trial, self::COST, 'Luna image generation');

        $result = $this->generateAndStore($request, $website);

        // Same rule as registered wallets: a failed API call never consumes credits.
        $balance = $credits->consume($trial, self::COST, 'generate_image', [
            'website_id' => $website->id,
            'page_id' => $page?->id,
            'prompt' => Str::limit((string) $result['prompt'], 240, ''),
            'size' => $result['size'],
            'block_type' => (string) $request->input('block_type', ''),
        ]);

        return response()->json([
            'status' => 'success',
            'url' => $result['url'],
            'cost' => self::COST,
            'credit_balance' => $balance,
            'balance' => $balance,
            'size' => $result['size'],
        ]);
    }

    private function generateAndStore(Request $request, Website $website): array
    {
        $validated = $request->validate([
            'prompt' => ['required', 'string', 'min:3', 'max:800'],
            'image_query' => ['nullable', 'string', 'max:300'],
            'block_type' => ['nullable', 'string', 'max:100'],
            'target_width' => ['nullable', 'integer', 'min:1', 'max:8000'],
            'target_height' => ['nullable', 'integer', 'min:1', 'max:8000'],
        ]);

        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'Luna image generation is not configured.');

        $width = max(1, (int) ($validated['target_width'] ?? 1024));
        $height = max(1, (int) ($validated['target_height'] ?? 1024));
        $ratio = $width / $height;
        $size = $ratio > 1.2 ? '1536x1024' : ($ratio < 0.83 ? '1024x1536' : '1024x1024');
        $orientation = $ratio > 1.2 ? 'landscape' : ($ratio < 0.83 ? 'portrait' : 'square');

        $userPrompt = trim((string) $validated['prompt']);
        $context = trim((string) ($validated['image_query'] ?? ''));
        $blockType = trim((string) ($validated['block_type'] ?? ''));
        $industry = trim((string) ($website->industry ?: 'business'));
        $business = trim((string) ($website->name ?: 'this website'));

        $prompt = implode("\n", array_filter([
            'Create a premium website image for '.$business.'.',
            'Industry/context: '.$industry.'.',
            $blockType !== '' ? 'Website section type: '.$blockType.'.' : null,
            $context !== '' ? 'Existing section context: '.$context.'.' : null,
            'User request: '.$userPrompt,
            'Composition: '.$orientation.' image, designed to crop cleanly inside a responsive website image slot.',
            'Use polished, realistic commercial art direction with clear subject separation and useful negative space.',
            'Do not add text, typography, logos, watermarks, UI labels, borders, or mock captions unless the user explicitly asks for them.',
        ]));

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout((int) config('openai.request_timeout', 180))
                ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/images/generations', [
                    'model' => env('OPENAI_IMAGE_MODEL', 'gpt-image-1'),
                    'prompt' => $prompt,
                    'size' => $size,
                    'quality' => env('OPENAI_IMAGE_QUALITY', 'low'),
                    'n' => 1,
                ]);
        } catch (Throwable $exception) {
            report($exception);
            return abort(502, 'Luna could not generate an image right now. No credits were charged.');
        }

        if ($response->failed()) {
            report(new \RuntimeException('Luna image generation failed: '.$response->body()));
            abort(502, 'Luna could not generate an image right now. No credits were charged.');
        }

        $encoded = data_get($response->json(), 'data.0.b64_json');
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($bytes === false || strlen($bytes) < 100) {
            abort(502, 'Luna returned an invalid image. No credits were charged.');
        }

        $filename = 'luna-image-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.png';
        $path = "websites/{$website->id}/ai-images/{$filename}";
        $stored = Storage::disk('public')->put($path, $bytes);
        if (! $stored) {
            abort(502, 'Luna generated the image but it could not be saved. No credits were charged.');
        }

        return [
            // Persist a host-agnostic public path. Preview resolves it on the CMS host, while
            // CmsHtmlCompiler converts /storage assets to COSMIC_ASSET_BASE_URL for static export.
            'url' => '/storage/'.$path,
            'size' => $size,
            'prompt' => $userPrompt,
        ];
    }
}
