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
use App\Services\ThemeLogoPaletteService;
use App\Services\SmartLogoPromptService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image; // Import ni sa taas sa imong controller

class ImageController extends Controller
{


    public function generateLogo(Request $request, Website $website, CreditWalletService $wallet, LogoCanvasService $canvas, ThemeColorResolver $themeColors, ThemeLogoPaletteService $logoPalettes, SmartLogoPromptService $logoPrompt)
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
        $customPalette = $themeKey === 'my-brand'
            ? [
                'primary' => data_get($website->theme_settings, 'brand_palette.primary'),
                'secondary' => data_get($website->theme_settings, 'brand_palette.secondary'),
                'tertiary' => data_get($website->theme_settings, 'brand_palette.accent'),
            ]
            : null;
        $logoPalette = $themeKey === 'my-brand'
            ? $logoPalettes->randomFor($themeKey, $customPalette)
            : $logoPalettes->forPrimary($themeKey, $primary);
        // The requested/active theme primary is authoritative for logo generation.
        $logoPalette['primary'] = strtoupper($primary);
        $resolvedThemePalette = $themeColors->palette($themeKey);
        $accent = strtoupper((string) ($themeKey === 'my-brand'
            ? ($logoPalette['secondary'] ?? $primary)
            : ($resolvedThemePalette['accent'] ?? $primary)));
        $surface = strtoupper((string) ($resolvedThemePalette['surface'] ?? $primary));
        $headerBackground = '#FFFFFF';

        $industry = trim((string) ($website->industry ?: 'business'));

        $brandMemory = (array) data_get($website->settings, 'brand_memory', []);
        $prompt = $logoPrompt->build(
            company: $company,
            industry: $industry,
            primary: $primary,
            themeKey: $themeKey,
            accent: $accent,
            headerBackground: $headerBackground,
            surface: $surface,
            brandPrompt: (string) ($brandMemory['brand_prompt'] ?? $website->business_description ?? ''),
            brandContext: (array) ($brandMemory['brand_context'] ?? []),
            latestUserPrompt: (string) ($brandMemory['latest_user_prompt'] ?? ''),
        );

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

        $bytes = $canvas->trimTransparentPng($bytes, 6);
        $logoDimensions = @getimagesizefromstring($bytes);

        $filename = 'luna-logo-'.Str::lower(Str::random(10)).'.png';
        // AI output is staged as a temporary local upload so the frontend can
        // pass it through the exact same cropper path as a computer-uploaded raster.
        $path = "websites/{$website->id}/logos/tmp/{$filename}";
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

        $logoUrl = rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path;
        // Do NOT make the Luna file active yet. It is only a temporary raster
        // source. The user's confirmed crop is the first point where branding
        // is committed, matching the normal upload-logo behavior.

        return response()->json([
            'status' => 'success',
            'url' => $logoUrl,
            'company_name' => $company,
            'cost' => $cost,
            'balance' => $wallet->balance($user),
            'logo_width' => (int) ($logoDimensions[0] ?? 0),
            'logo_height' => (int) ($logoDimensions[1] ?? 0),
            'sync_state' => 'synced',
            'sync_source' => 'generated_from_theme',
            'synced_theme' => $themeKey,
            'primary_hex' => $logoPalette['primary'],
            'logo_palette' => ['primary' => strtoupper($primary), 'secondary' => 'ai-selected', 'tertiary' => 'ai-selected'],
        ]);
    }


    public function cropLogo(Request $request, Website $website, LogoCanvasService $canvas)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'image_data' => ['required', 'string', 'max:8000000'],
            'source_url' => ['nullable', 'string', 'max:2048'],
        ]);

        if (! preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=\r\n]+)$/', $validated['image_data'], $matches)) {
            return response()->json(['message' => 'The cropped logo data is invalid.'], 422);
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);
        $dimensions = $bytes !== false ? $this->pngDimensions($bytes) : null;
        if ($bytes === false || strlen($bytes) < 100 || $dimensions === null) {
            return response()->json(['message' => 'The cropped logo could not be processed.'], 422);
        }

        // 650x200 is a cropper working frame, not the permanent logo canvas.
        // Save tight artwork bounds so transparent safety padding does not make
        // the visible logo microscopic in the website header.
        $bytes = $canvas->trimTransparentPng($bytes, 8);
        $dimensions = $this->pngDimensions($bytes) ?: $dimensions;

        $filename = 'cropped-logo-'.Str::lower(Str::random(10)).'.png';
        $path = "websites/{$website->id}/logos/{$filename}";
        Storage::disk('public')->put($path, $bytes);
        $logoUrl = rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path;
        $themeSettings = is_array($website->theme_settings) ? $website->theme_settings : (json_decode((string) $website->theme_settings, true) ?: []);
        $themeSettings['brand_original_logo_url'] = $logoUrl;
        $themeSettings['brand_active_logo_url'] = $logoUrl;
        $themeSettings['brand_favicon_url'] = $logoUrl;
        $themeSettings['brand_logo_variants'] = [];
        $website->theme_settings = $themeSettings;

        // Registered Builder parity with the trial flow: Save Logo is the commit
        // point. Persist the active logo into both global shell locations now so
        // a refresh/preview/export immediately uses the confirmed crop instead of
        // waiting for a separate Save Draft action.
        $header = is_array($website->global_header) ? $website->global_header : [];
        $footer = is_array($website->global_footer) ? $website->global_footer : [];
        $header['logo_image_url'] = $logoUrl;
        $header['logo_filter'] = 'none';
        $header['logo_height'] = max(60, (int) ($header['logo_height'] ?? 0));
        $header['logo_max_width'] = max(300, (int) ($header['logo_max_width'] ?? 0));
        $footer['logo_image_url'] = $logoUrl;
        $footer['logo_filter'] = 'none';
        if (! empty($header['logo_text'])) {
            $footer['logo_text'] = $footer['logo_text'] ?? $header['logo_text'];
        }
        $website->global_header = $header;
        $website->global_footer = $footer;
        $website->save();

        // Successful crop commits the logo; now the staged Luna source can be removed.
        $sourceUrl = trim((string) ($validated['source_url'] ?? ''));
        $tmpPrefix = '/storage/websites/'.$website->id.'/logos/tmp/';
        if ($sourceUrl !== '' && str_contains($sourceUrl, $tmpPrefix)) {
            $relative = ltrim((string) parse_url($sourceUrl, PHP_URL_PATH), '/');
            if (str_starts_with($relative, 'storage/')) $relative = substr($relative, 8);
            Storage::disk('public')->delete($relative);
        }

        Log::info('[BuilderLogo] Cropped logo committed.', [
            'website_id' => $website->id,
            'logo_url' => $logoUrl,
            'logo_width' => (int) ($dimensions['width'] ?? 650),
            'logo_height' => (int) ($dimensions['height'] ?? 200),
        ]);

        return response()->json([
            'status' => 'success',
            'url' => $logoUrl,
            'logo_width' => (int) ($dimensions['width'] ?? 650),
            'logo_height' => (int) ($dimensions['height'] ?? 200),
        ]);
    }

    public function matchLogoToTheme(Request $request, Website $website, CreditWalletService $wallet, LogoThemeMatchService $matcher, LogoCanvasService $canvas, ThemeLogoPaletteService $logoPalettes)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'logo_url' => ['required', 'string', 'max:2048'],
            'theme_key' => ['nullable', 'string', 'max:60'],
            'theme_name' => ['nullable', 'string', 'max:80'],
            'primary_hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tertiary_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
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
            $themeKey = (string) ($validated['theme_key'] ?? 'midnight');
            $themeName = (string) ($validated['theme_name'] ?? $themeKey);
            $settings = is_array($website->theme_settings) ? $website->theme_settings : (json_decode((string) $website->theme_settings, true) ?: []);
            $customPalette = $themeKey === 'my-brand'
                ? (array) data_get($settings, 'custom_brand_theme.palette', [])
                : [];

            // Match Logo to Theme must consume the FINAL website palette exactly.
            // Do not call ThemeLogoPaletteService::forPrimary() here: that service
            // intentionally selects a curated/random logo variation for fresh logo
            // generation, which can introduce unrelated supporting hues.
            //
            // Builder payload semantics:
            // - primary_hex   = theme background / dominant brand anchor
            // - accent_hex    = theme accent
            // - secondary_hex = theme surface / supporting neutral
            $primaryHex = strtoupper((string) $validated['primary_hex']);
            $accentHex = strtoupper((string) ($validated['accent_hex']
                ?? $customPalette['accent']
                ?? $validated['tertiary_hex']
                ?? $validated['secondary_hex']
                ?? $primaryHex));
            $surfaceHex = strtoupper((string) ($validated['secondary_hex']
                ?? $customPalette['surface']
                ?? $customPalette['secondary']
                ?? $validated['tertiary_hex']
                ?? $primaryHex));

            $logoPalette = [
                'primary' => $primaryHex,
                'secondary' => $accentHex,
                'tertiary' => $surfaceHex,
                'accent' => $accentHex,
                'surface' => $surfaceHex,
                'header_background' => '#FFFFFF',
            ];

            $brandMemory = (array) data_get($website->settings, 'brand_memory', []);
            $sourceLogoUrl = trim((string) ($settings['brand_original_logo_url'] ?? $validated['logo_url']));
            if ($sourceLogoUrl === '') $sourceLogoUrl = $validated['logo_url'];
            if (empty($settings['brand_original_logo_url'])) {
                $settings['brand_original_logo_url'] = $validated['logo_url'];
            }
            $result = $matcher->match(
                $sourceLogoUrl,
                $logoPalette,
                $themeName,
                $brandMemory,
                (string) ($website->name ?? ''),
                (string) ($website->industry ?: 'business'),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' No credits were charged.'], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Cosmic AI could not match this logo to the theme. No credits were charged.'], 500);
        }

        if (($result['extension'] ?? '') === 'png') {
            // Normalize every AI result back to the same navbar-safe crop flow used by Generate Logo.
            $result['bytes'] = $canvas->trimTransparentPng($result['bytes'], 6);
        }

        $filename = 'theme-matched-logo-'.Str::lower(Str::random(10)).'.'.$result['extension'];
        $path = "websites/{$website->id}/logos/{$filename}";
        Storage::disk('public')->put($path, $result['bytes']);

        $settings['brand_active_logo_url'] = rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path;
        $settings['brand_favicon_url'] = $settings['brand_active_logo_url'];
        $variants = (array) ($settings['brand_logo_variants'] ?? []);
        $variants[$themeKey] = $settings['brand_active_logo_url'];
        $settings['brand_logo_variants'] = $variants;
        $website->forceFill(['theme_settings' => $settings])->save();

        $wallet->debit(
            $user,
            $cost,
            'Match logo to theme',
            'ai_generation',
            $website,
            'logo-theme-match:'.Str::uuid(),
            ['theme' => $themeKey, 'theme_name' => $themeName, 'primary_hex' => $validated['primary_hex'], 'palette' => $logoPalette]
        );

        return response()->json([
            'status' => 'success',
            'url' => rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path,
            'cost' => $cost,
            'balance' => $wallet->balance($user),
            'source' => $result['ai'] ? 'ai-theme-match' : 'svg-theme-match',
            'sync_state' => 'synced',
            'sync_source' => 'logo_to_theme',
            'synced_theme' => $themeKey,
            'logo_palette' => $logoPalette,
        ]);
    }


    public function matchThemeToLogo(Request $request, Website $website, CreditWalletService $wallet, LogoThemeAnalysisService $analyzer)
    {
        $this->authorize('update', $website);

        $validated = $request->validate([
            'logo_url' => ['required', 'string', 'max:2048'],
            'automatic_upload' => ['nullable', 'boolean'],
            'post_upload_choice' => ['nullable', 'boolean'],
        ]);

        $user = $request->user();
        $automaticUpload = (bool) ($validated['automatic_upload'] ?? false);
        $postUploadChoice = (bool) ($validated['post_upload_choice'] ?? false);

        if ($automaticUpload || $postUploadChoice) {
            $logoPath = (string) parse_url($validated['logo_url'], PHP_URL_PATH);
            $expectedPrefix = '/storage/websites/'.$website->id.'/logos/';
            if (! str_starts_with($logoPath, $expectedPrefix)) {
                return response()->json(['message' => 'Automatic brand matching is only available for a logo uploaded to this website.'], 422);
            }

            $settings = is_array($website->theme_settings)
                ? $website->theme_settings
                : (json_decode((string) $website->theme_settings, true) ?: []);
            $existingCustomTheme = (array) data_get($settings, 'custom_brand_theme', []);
            if ($existingCustomTheme !== []
                && hash_equals((string) ($existingCustomTheme['source_logo_url'] ?? ''), (string) $validated['logo_url'])) {
                return response()->json([
                    'status' => 'success',
                    'custom_theme' => $existingCustomTheme,
                    'palette' => $existingCustomTheme['palette'] ?? [],
                    'recommended_family' => 'my-brand',
                    'reason' => 'Reused the existing brand theme for this uploaded logo.',
                    'cost' => 0,
                    'automatic_upload' => true,
                    'balance' => $wallet->balance($user),
                ]);
            }
        }

        $cost = $automaticUpload ? 0 : ($postUploadChoice ? 20 : 50);
        if ($cost > 0 && ! $wallet->canAfford($user, $cost)) {
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

            $settings = is_array($website->theme_settings)
                ? $website->theme_settings
                : (json_decode((string) $website->theme_settings, true) ?: []);
            $customTheme = is_array($result['custom_theme'] ?? null) ? $result['custom_theme'] : null;

            if (! $customTheme) {
                return response()->json(['message' => 'Cosmic AI did not return a usable My Brand Theme. No credits were charged.'], 422);
            }

            $customTheme['source_logo_url'] = $validated['logo_url'];
            $customTheme['updated_at'] = now()->toIso8601String();
            $settings['custom_brand_theme'] = $customTheme;
            $settings['brand_palette'] = $customTheme['palette'] ?? ($result['palette'] ?? []);
            $settings['brand_source'] = 'logo';
            // Upload -> crop -> automatic analysis should behave exactly like the
            // trial Builder: My Brand Theme becomes active as soon as analysis
            // succeeds. Manual Match Theme to Logo still waits for its preview/apply
            // flow because automatic_upload is false in that path.
            if ($automaticUpload || $postUploadChoice) {
                $settings['primary'] = 'my-brand';
                $settings['brand_active_logo_url'] = $validated['logo_url'];
                $settings['brand_original_logo_url'] = $settings['brand_original_logo_url'] ?? $validated['logo_url'];
                $settings['brand_favicon_url'] = $validated['logo_url'];
                $settings['logo_theme_sync_state'] = 'synced';
                $settings['logo_theme_sync_source'] = 'theme_to_logo';
                $settings['logo_theme_synced_theme'] = 'my-brand';
            }
            unset($settings['brand_pre_upload_snapshot']);
            $website->theme_settings = $settings;
            $website->save();
            $result['custom_theme'] = $customTheme;
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' No credits were charged.'], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Cosmic AI could not match My Brand Theme to this logo. No credits were charged.'], 500);
        }

        if ($cost > 0) {
            $wallet->debit(
                $user,
                $cost,
                'Match theme to logo',
                'ai_generation',
                $website,
                'theme-from-logo:'.Str::uuid(),
                ['recommended_family' => $result['recommended_family'], 'palette' => $result['palette'], 'custom_theme' => $customTheme]
            );
        }

        return response()->json([
            'status' => 'success',
            ...$result,
            'cost' => $cost,
            'automatic_upload' => $automaticUpload,
            'post_upload_choice' => $postUploadChoice,
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

    public function rollbackUploadedLogo(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        $settings = is_array($website->theme_settings) ? $website->theme_settings : (json_decode((string) $website->theme_settings, true) ?: []);
        $snapshot = is_array($settings['brand_pre_upload_snapshot'] ?? null)
            ? $settings['brand_pre_upload_snapshot']
            : null;

        if (! $snapshot) {
            return response()->json(['message' => 'No previous logo is available to restore.'], 422);
        }

        foreach (['brand_original_logo_url', 'brand_active_logo_url', 'brand_favicon_url', 'brand_logo_variants', 'logo_theme_sync_state', 'logo_theme_sync_source', 'logo_theme_synced_theme'] as $key) {
            if (array_key_exists($key, $snapshot) && $snapshot[$key] !== null) {
                $settings[$key] = $snapshot[$key];
            } else {
                unset($settings[$key]);
            }
        }
        unset($settings['brand_pre_upload_snapshot']);

        $website->theme_settings = $settings;
        $website->save();

        $restoreUrl = trim((string) ($settings['brand_active_logo_url'] ?? $settings['brand_original_logo_url'] ?? ''));
        if ($restoreUrl === '') {
            $restoreUrl = '/storage/branding/your-logo.png';
        }

        return response()->json([
            'status' => 'success',
            'url' => $restoreUrl,
            'sync_state' => $settings['logo_theme_sync_state'] ?? null,
            'sync_source' => $settings['logo_theme_sync_source'] ?? null,
            'synced_theme' => $settings['logo_theme_synced_theme'] ?? null,
        ]);
    }

    public function restoreOriginalLogo(Request $request, Website $website)
    {
        $this->authorize('update', $website);
        $settings = is_array($website->theme_settings) ? $website->theme_settings : (json_decode((string) $website->theme_settings, true) ?: []);
        $original = trim((string) ($settings['brand_original_logo_url'] ?? ''));
        if ($original === '') return response()->json(['message' => 'No original logo is available to restore.'], 422);
        $settings['brand_active_logo_url'] = $original;
        $settings['brand_favicon_url'] = $original;
        $website->theme_settings = $settings;
        $website->save();
        return response()->json(['status' => 'success', 'url' => $original]);
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

        // Preserve the currently active brand until automatic theme analysis
        // succeeds. This lets a throttled upload roll back cleanly.
        $themeSettings = is_array($website->theme_settings) ? $website->theme_settings : (json_decode((string) $website->theme_settings, true) ?: []);
        if (! isset($themeSettings['brand_pre_upload_snapshot'])) {
            $themeSettings['brand_pre_upload_snapshot'] = [
                'brand_original_logo_url' => $themeSettings['brand_original_logo_url'] ?? null,
                'brand_active_logo_url' => $themeSettings['brand_active_logo_url'] ?? null,
                'brand_favicon_url' => $themeSettings['brand_favicon_url'] ?? null,
                'brand_logo_variants' => $themeSettings['brand_logo_variants'] ?? [],
                'logo_theme_sync_state' => $themeSettings['logo_theme_sync_state'] ?? null,
                'logo_theme_sync_source' => $themeSettings['logo_theme_sync_source'] ?? null,
                'logo_theme_synced_theme' => $themeSettings['logo_theme_synced_theme'] ?? null,
            ];
            $website->theme_settings = $themeSettings;
            $website->save();
        }

        $path = $file->store("websites/{$website->id}/logos", 'public');
        $url = rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path;
        if (strtolower($file->getClientOriginalExtension()) === 'svg') {
            $themeSettings['brand_original_logo_url'] = $url;
            $themeSettings['brand_active_logo_url'] = $url;
            $themeSettings['brand_favicon_url'] = $url;
            $themeSettings['brand_logo_variants'] = [];
            $website->theme_settings = $themeSettings;

            // SVGs skip the raster cropper, so upload itself is their Save Logo
            // commit point. Keep global header/footer in sync immediately.
            $header = is_array($website->global_header) ? $website->global_header : [];
            $footer = is_array($website->global_footer) ? $website->global_footer : [];
            $header['logo_image_url'] = $url;
            $header['logo_filter'] = 'none';
            $header['logo_height'] = max(60, (int) ($header['logo_height'] ?? 0));
            $header['logo_max_width'] = max(300, (int) ($header['logo_max_width'] ?? 0));
            $footer['logo_image_url'] = $url;
            $footer['logo_filter'] = 'none';
            if (! empty($header['logo_text'])) {
                $footer['logo_text'] = $footer['logo_text'] ?? $header['logo_text'];
            }
            $website->global_header = $header;
            $website->global_footer = $footer;
            $website->save();
        }

        return response()->json(['url' => $url]);
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

    /**
     * Validate a PNG and read dimensions without requiring the GD extension.
     * PNG signature is 8 bytes; IHDR begins immediately after and stores
     * width/height as unsigned big-endian 32-bit integers.
     */
    private function pngDimensions(string $bytes): ?array
    {
        if (strlen($bytes) < 24 || substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        if (substr($bytes, 12, 4) !== 'IHDR') {
            return null;
        }

        $width = unpack('N', substr($bytes, 16, 4))[1] ?? 0;
        $height = unpack('N', substr($bytes, 20, 4))[1] ?? 0;

        if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
            return null;
        }

        // The browser may alpha-trim the 650 × 200 working frame before upload.
        // Keep registered Builder parity with the trial crop path: accept either
        // the full frame or any tighter PNG produced from that frame. Reject only
        // images that exceed the crop contract bounds.
        if ($width > 650 || $height > 200) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

}
