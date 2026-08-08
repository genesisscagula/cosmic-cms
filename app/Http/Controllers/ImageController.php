<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use App\Models\Website; // Siguroha nga sakto ang namespace sa imong Model
use App\Services\TrialRemoteImageService;
use App\Services\CreditWalletService;
use App\Services\LogoThemeMatchService;
use App\Services\LogoThemeAnalysisService;
use App\Services\ThemePlanAccessService;
use App\Services\LogoCanvasService;
use App\Services\ThemeColorResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image; // Import ni sa taas sa imong controller

class ImageController extends Controller
{


    public function generateLogo(Request $request, Website $website, CreditWalletService $wallet, LogoCanvasService $canvas, ThemeColorResolver $themeColors)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:80'],
            'primary' => ['nullable', 'string', 'max:32'],
            'primary_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $user = $request->user();
        $cost = 50;
        if (! $wallet->canAfford($user, $cost)) {
            return response()->json([
                'message' => "Not enough Cosmic Credits. Logo generation costs {$cost} credits.",
                'required_credits' => $cost,
                'available_credits' => $wallet->balance($user),
            ], 422);
        }

        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'AI logo generation is not configured.');

        $company = trim($validated['company_name']);
        $themeKey = trim((string) ($validated['primary'] ?? '')) ?: (string) data_get($website->settings, 'theme.primary', 'midnight');
        $primary = isset($validated['primary_hex'])
            ? strtoupper((string) $validated['primary_hex'])
            : ($themeKey === 'my-brand'
                ? (string) data_get($website->theme_settings, 'custom_brand_theme.palette.background', '#243447')
                : $themeColors->primaryHex($themeKey));
        $industry = trim((string) ($website->industry ?: 'business'));

        $prompt = implode("\n", [
            "Create a clean professional horizontal brand logo for {$company}.",
            "Industry: {$industry}.",
            "Current website theme: {$themeKey}.",
            "EXACT primary brand HEX: {$primary}.",
            'Use the exact supplied primary HEX as the dominant chromatic brand color. Do not reinterpret it, lighten it, darken it, desaturate it, shift its hue, or substitute a similar color.',
            'Use a simple icon plus readable company wordmark. Keep the composition compact and suitable for a website header.',
            'Design for a wide 650 by 150 pixel website-header canvas. Keep the icon and wordmark horizontally balanced with generous transparent breathing room.',
            'Transparent background. No mockup, no card, no scene, no watermark, no slogan unless it is part of the company name.',
        ]);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('openai.request_timeout', 180))
            ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/images/generations', [
                'model' => env('OPENAI_LOGO_MODEL', 'gpt-image-1'),
                'prompt' => $prompt,
                'size' => '1024x1024',
                'quality' => env('OPENAI_LOGO_QUALITY', 'low'),
                'background' => 'transparent',
                'n' => 1,
            ]);

        if ($response->failed()) {
            report(new \RuntimeException('OpenAI registered logo generation failed: '.$response->body()));
            return response()->json(['message' => 'Cosmic AI could not generate the logo right now. No credits were charged.'], 502);
        }

        $encoded = data_get($response->json(), 'data.0.b64_json');
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($bytes === false || strlen($bytes) < 100) {
            return response()->json(['message' => 'Cosmic AI returned an invalid logo image. No credits were charged.'], 502);
        }

        $bytes = $canvas->normalizePngToPrimary($bytes, $primary);

        $filename = 'luna-logo-'.Str::lower(Str::random(10)).'.png';
        $path = "websites/{$website->id}/logos/{$filename}";
        Storage::disk('public')->put($path, $bytes);

        $wallet->debit(
            $user,
            $cost,
            'AI logo generation',
            'ai_generation',
            $website,
            'logo-generation:'.Str::uuid(),
            ['company_name' => $company]
        );

        return response()->json([
            'status' => 'success',
            'url' => rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path,
            'company_name' => $company,
            'cost' => $cost,
            'balance' => $wallet->balance($user),
            'logo_width' => LogoCanvasService::WIDTH,
            'logo_height' => LogoCanvasService::HEIGHT,
            'sync_state' => 'synced',
            'sync_source' => 'generated_from_theme',
            'synced_theme' => $themeKey,
            'primary_hex' => $primary,
        ]);
    }


    public function matchLogoToTheme(Request $request, Website $website, CreditWalletService $wallet, LogoThemeMatchService $matcher, LogoCanvasService $canvas)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'logo_url' => ['required', 'string', 'max:2048'],
            'theme_name' => ['nullable', 'string', 'max:60'],
            'primary_hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $user = $request->user();
        $cost = 50;
        if (! $wallet->canAfford($user, $cost)) {
            return response()->json([
                'message' => "Not enough Cosmic Credits. Logo theme matching costs {$cost} credits.",
                'required_credits' => $cost,
                'available_credits' => $wallet->balance($user),
            ], 422);
        }

        try {
            $result = $matcher->match(
                $validated['logo_url'],
                $validated['primary_hex'],
                $validated['accent_hex'] ?? '',
                $validated['theme_name'] ?? ''
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' No credits were charged.'], 422);
        }

        if (($result['extension'] ?? '') === 'png') {
            $result['bytes'] = $canvas->normalizePngToPrimary($result['bytes'], $validated['primary_hex']);
        }

        $filename = 'theme-matched-logo-'.Str::lower(Str::random(10)).'.'.$result['extension'];
        $path = "websites/{$website->id}/logos/{$filename}";
        Storage::disk('public')->put($path, $result['bytes']);

        $wallet->debit(
            $user,
            $cost,
            'Match logo to theme',
            'ai_generation',
            $website,
            'logo-theme-match:'.Str::uuid(),
            ['theme' => $validated['theme_name'] ?? null, 'primary_hex' => $validated['primary_hex']]
        );

        return response()->json([
            'status' => 'success',
            'url' => rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path,
            'cost' => $cost,
            'balance' => $wallet->balance($user),
            'source' => $result['ai'] ? 'ai-theme-match' : 'svg-theme-match',
            'sync_state' => 'synced',
            'sync_source' => 'logo_to_theme',
            'synced_theme' => $validated['theme_name'] ?? null,
        ]);
    }


    public function matchThemeToLogo(Request $request, Website $website, CreditWalletService $wallet, LogoThemeAnalysisService $analyzer)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'logo_url' => ['required', 'string', 'max:2048'],
        ]);

        $user = $request->user();
        $cost = 50;
        if (! $wallet->canAfford($user, $cost)) {
            return response()->json([
                'message' => "Not enough Cosmic Credits. Theme-from-logo analysis costs {$cost} credits.",
                'required_credits' => $cost,
                'available_credits' => $wallet->balance($user),
            ], 422);
        }

        try {
            $themeAccess = app(ThemePlanAccessService::class);
            $allowedThemes = $themeAccess->allowedThemeKeysForWebsite($user->effectivePlanKey(), $website);
            $result = $analyzer->analyze($validated['logo_url'], $allowedThemes);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' No credits were charged.'], 422);
        }

        $settings = is_array($website->theme_settings) ? $website->theme_settings : (json_decode((string) $website->theme_settings, true) ?: []);
        $customTheme = is_array($result['custom_theme'] ?? null) ? $result['custom_theme'] : null;
        if ($customTheme) {
            $customTheme['source_logo_url'] = $validated['logo_url'];
            $customTheme['updated_at'] = now()->toIso8601String();
            $settings['custom_brand_theme'] = $customTheme;
            $website->theme_settings = $settings;
            $website->save();
            $result['custom_theme'] = $customTheme;
        }

        $wallet->debit(
            $user,
            $cost,
            'Match theme to logo',
            'ai_generation',
            $website,
            'theme-from-logo:'.Str::uuid(),
            ['recommended_family' => $result['recommended_family'], 'palette' => $result['palette'], 'custom_theme' => $customTheme]
        );

        return response()->json([
            'status' => 'success',
            ...$result,
            'cost' => $cost,
            'balance' => $wallet->balance($user),
        ]);
    }


    /**
     * Return one provider-hosted image URL for registered Builder image replacement.
     * No provider bytes are downloaded or written to Cosmic storage.
     */
    public function remoteImage(Request $request, Website $website, TrialRemoteImageService $remoteImages)
    {
        $this->authorize('update', $website);

        $data = $request->validate([
            'query' => ['nullable', 'string', 'max:180'],
            'block_type' => ['nullable', 'string', 'max:80'],
        ]);

        $query = trim((string) ($data['query'] ?? ''));
        $blockType = trim((string) ($data['block_type'] ?? ''));

        $keywords = collect([
            $query !== '' ? $query : null,
            $blockType !== '' ? str_replace('_', ' ', $blockType) : null,
            $website->business_description,
        ])->filter(fn ($value) => is_string($value) && trim($value) !== '')
          ->map(fn ($value) => trim($value))
          ->take(3)
          ->values()
          ->all();

        $pool = $remoteImages->resolveForRegistered([
            'image_keywords' => $keywords,
            'business_type' => $website->industry ?: $website->name,
            'visual_style' => data_get($website->settings, 'visual_style', 'editorial'),
        ], 8);

        if ($pool === []) {
            return response()->json([
                'message' => 'No Unsplash image is available right now. Your current image was kept.',
            ], 503);
        }

        $item = $pool[array_rand($pool)];

        return response()->json([
            'url' => $item['url'],
            'provider' => $item['provider'] ?? 'unsplash',
            'source_url' => $item['source_url'] ?? null,
            'photographer' => $item['photographer'] ?? null,
            'remote' => true,
        ]);
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,svg', 'max:2048'],
        ]);

        $website = Website::findOrFail($request->integer('website_id'));
        $this->authorize('update', $website);

        $file = $request->file('image');

        if (strtolower($file->getClientOriginalExtension()) === 'svg') {
            $svg = file_get_contents($file->getRealPath());

            if ($svg === false || preg_match('/<\s*(?:script|iframe|object|embed|foreignObject)\b|\son\w+\s*=|(?:href|xlink:href)\s*=\s*[\'\"]\s*(?:https?:|javascript:|data:)/i', $svg)) {
                return response()->json([
                    'message' => 'The SVG contains unsupported active or external content.',
                ], 422);
            }
        }

        $path = $file->store("websites/{$website->id}/logos", 'public');

        return response()->json([
            'url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path,
        ]);
    }

    // Function para sa pag-upload sa file
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp,avif|max:4096',
            'website_id' => 'required|integer|exists:websites,id'
        ]);

        $website = Website::findOrFail($request->website_id);
        $this->authorize('update', $website);

        $websiteId = $website->id;
        $path = $request->file('image')->store("websites/{$websiteId}", 'public');

        return response()->json([
            'url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path
        ]);
    }

    public function update(Request $request)
	{
	    try {

	        $request->validate([
	            'website_id' => 'required|integer|exists:websites,id',
	            'block_index' => 'required|integer',

	            // Allow all common image formats including AVIF
	            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,avif|max:4096',
	        ]);

	        $website = Website::findOrFail($request->website_id);
	        $this->authorize('update', $website);

	        if (!$request->hasFile('image')) {
	            return response()->json([
	                'message' => 'No image uploaded.'
	            ], 422);
	        }

	        $file = $request->file('image');

	        if (!$file->isValid()) {
	            return response()->json([
	                'message' => 'Uploaded file is invalid.'
	            ], 422);
	        }

	        $path = $file->store(
	            "websites/{$website->id}",
	            'public'
	        );

	        return response()->json([
	            'success' => true,
	            'url' => rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path
	        ]);

	    } catch (AuthorizationException $e) {

	        throw $e;

	    } catch (\Illuminate\Validation\ValidationException $e) {

	        return response()->json([
	            'message' => 'Validation failed.',
	            'errors' => $e->errors()
	        ], 422);

	    } catch (\Throwable $e) {

	        return response()->json([
	            'message' => $e->getMessage(),
	            'line' => $e->getLine(),
	            'file' => basename($e->getFile())
	        ], 500);

	    }
	}

	public function uploadBlockImage(Request $request)
	{
	    $request->validate([
	        'website_id' => 'required|integer|exists:websites,id',
	        'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp,avif|max:4096'
	    ]);

	    $website = Website::findOrFail($request->website_id);
	    $this->authorize('update', $website);

	    // Upload ra gyud ni siya
	    $path = $request->file('image')->store("websites/{$website->id}", 'public');
	    $imageUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path;

	    // I-return lang ang URL
	    return response()->json(['url' => $imageUrl]);
	}
}
