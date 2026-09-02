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
use Intervention\Image\Facades\Image;
use App\Services\SvgLogoLightnessService; // Import ni sa taas sa imong controller
use App\Services\SvgUploadSanitizer;

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
                'size' => env('OPENAI_LOGO_SIZE', '1536x1024'),
                'quality' => env('OPENAI_LOGO_QUALITY', 'medium'),
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
        // Keep a high-DPI 3.25:1 master so the Builder cropper starts with a
        // crisp horizontal logo and can downsample cleanly for the 650x200 header.
        $bytes = $canvas->fitTransparentPngToCanvas($bytes, 1950, 600, 0.12);
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
            'source_kind' => ['nullable', 'in:ai,upload,theme_match'],
            'source_theme_key' => ['nullable', 'string', 'max:60'],
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

        // Defensive parity with the browser cropper: never allow a padded or
        // legacy client crop to exceed the 650x200 header contract. Fit first,
        // then trim again so the stored asset remains tight rather than a full
        // transparent header canvas.
        if ((int) ($dimensions['width'] ?? 0) > 650 || (int) ($dimensions['height'] ?? 0) > 200) {
            $bytes = $canvas->fitTransparentPngToCanvas($bytes, 650, 200, 0.08);
            $bytes = $canvas->trimTransparentPng($bytes, 8);
            $dimensions = $this->pngDimensions($bytes) ?: $dimensions;
        }

        $filename = 'cropped-logo-'.Str::lower(Str::random(10)).'.png';
        $path = "websites/{$website->id}/logos/{$filename}";
        Storage::disk('public')->put($path, $bytes);
        $asset = app(\App\Services\MediaLibraryRegistry::class)->registerStoredPath($website, $path, 'upload', 'logo', $request->user()?->id, $filename);
        $logoUrl = $asset ? app(\App\Services\MediaLibraryRegistry::class)->url($asset) : rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path;
        $themeSettings = is_array($website->theme_settings) ? $website->theme_settings : (json_decode((string) $website->theme_settings, true) ?: []);
        $sourceKind = (string) ($validated['source_kind'] ?? 'upload');
        $activeThemeKey = trim((string) ($validated['source_theme_key'] ?? data_get($themeSettings, 'primary', 'midnight')));
        if ($activeThemeKey === '') $activeThemeKey = 'midnight';

        if ($sourceKind === 'theme_match') {
            // Preview/apply contract: preserve the source logo and only switch the
            // active asset after the user confirms the redesigned crop.
            if (empty($themeSettings['brand_original_logo_url'])) {
                $themeSettings['brand_original_logo_url'] = data_get($website->global_header, 'logo_image_url') ?: $logoUrl;
            }
            $themeSettings['brand_active_logo_url'] = $logoUrl;
            $themeSettings['brand_favicon_url'] = $logoUrl;
            $variants = (array) ($themeSettings['brand_logo_variants'] ?? []);
            $variants[$activeThemeKey] = $logoUrl;
            $themeSettings['brand_logo_variants'] = $variants;
            $themeSettings['logo_theme_sync_state'] = 'synced';
            $themeSettings['logo_theme_sync_source'] = 'logo_to_theme';
            $themeSettings['logo_theme_synced_theme'] = $activeThemeKey;
        } else {
            $themeSettings['brand_original_logo_url'] = $logoUrl;
            $themeSettings['brand_active_logo_url'] = $logoUrl;
            $themeSettings['brand_favicon_url'] = $logoUrl;
            $themeSettings['brand_logo_variants'] = $sourceKind === 'ai' ? [$activeThemeKey => $logoUrl] : [];
            $themeSettings['logo_theme_sync_state'] = $sourceKind === 'ai' ? 'synced' : 'logo_changed';
            $themeSettings['logo_theme_sync_source'] = $sourceKind === 'ai' ? 'generated_from_theme' : 'upload';
            $themeSettings['logo_theme_synced_theme'] = $sourceKind === 'ai' ? $activeThemeKey : null;
            unset($themeSettings['brand_pre_upload_snapshot']);
        }
        $website->theme_settings = $themeSettings;

        // Save Logo is the commit point. Persist the confirmed active logo into
        // both global shell locations so refresh/preview/export remain identical.
        $header = is_array($website->global_header) ? $website->global_header : [];
        $footer = is_array($website->global_footer) ? $website->global_footer : [];
        $header['logo_image_url'] = $logoUrl;
        $header['logo_filter'] = 'none';
        unset($header['logo_theme_match_mode']);
        $header['logo_height'] = max(60, (int) ($header['logo_height'] ?? 0));
        $header['logo_max_width'] = max(300, (int) ($header['logo_max_width'] ?? 0));
        $footer['logo_image_url'] = $logoUrl;
        $footer['logo_filter'] = 'none';
        unset($footer['logo_theme_match_mode']);
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
            'sync_state' => $themeSettings['logo_theme_sync_state'] ?? null,
            'sync_source' => $themeSettings['logo_theme_sync_source'] ?? null,
            'synced_theme' => $themeSettings['logo_theme_synced_theme'] ?? null,
            'source_kind' => $sourceKind,
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
            $currentLogoUrl = trim((string) $validated['logo_url']);
            $originalLogoUrl = trim((string) ($settings['brand_original_logo_url'] ?? ''));
            $activeLogoUrl = trim((string) ($settings['brand_active_logo_url'] ?? ''));

            // Older local builds can retain a stale original URL after the logo is replaced,
            // moved into Media Library, or the XAMPP host/port changes. Try the meaningful
            // Cosmic-owned candidates in identity-first order instead of failing on one stale
            // pointer. The matcher still enforces storage ownership / SSRF safety itself.
            $logoCandidates = array_values(array_unique(array_filter([
                $originalLogoUrl,
                $currentLogoUrl,
                $activeLogoUrl,
            ], static fn ($url) => is_string($url) && trim($url) !== '')));

            if ($logoCandidates === []) {
                throw new \RuntimeException('No logo is available to match. Upload or generate a logo first.');
            }

            $result = null;
            $matchedSourceUrl = null;
            $lastLogoError = null;
            foreach ($logoCandidates as $candidateUrl) {
                try {
                    $result = $matcher->match(
                        $candidateUrl,
                        $logoPalette,
                        $themeName,
                        $brandMemory,
                        (string) ($website->name ?? ''),
                        (string) ($website->industry ?: 'business'),
                    );
                    $matchedSourceUrl = $candidateUrl;
                    break;
                } catch (\RuntimeException $candidateError) {
                    $lastLogoError = $candidateError;
                }
            }

            if (! is_array($result)) {
                throw $lastLogoError ?: new \RuntimeException('The current logo could not be imported. Replace the logo and try again.');
            }

            if ($originalLogoUrl === '' || ($matchedSourceUrl !== null && $matchedSourceUrl !== $originalLogoUrl)) {
                // Repair only the stale source pointer. The newly generated themed logo is saved
                // separately as brand_active_logo_url below, preserving the source-vs-variant model.
                $settings['brand_original_logo_url'] = $matchedSourceUrl;
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' No credits were charged.'], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Cosmic AI could not match this logo to the theme. No credits were charged.'], 500);
        }

        // Keep the AI result's transparent breathing room intact. The visual
        // cropper is the single authority for final logo geometry; trimming here
        // used to undo its safe-area contract before the user could confirm it.

        $filename = 'theme-matched-logo-'.Str::lower(Str::random(10)).'.'.$result['extension'];
        $path = "websites/{$website->id}/logos/tmp/{$filename}";
        Storage::disk('public')->put($path, $result['bytes']);
        $previewUrl = rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.$path;

        // Do not mutate the active logo here. The generated file is a preview
        // source only; Builder crop/apply is the single commit point.
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
            'url' => $previewUrl,
            'cost' => $cost,
            'balance' => $wallet->balance($user),
            'source' => $result['ai'] ? 'ai-theme-match' : 'svg-theme-match',
            'sync_state' => 'theme_changed',
            'sync_source' => 'logo_to_theme_pending_apply',
            'synced_theme' => null,
            'pending_theme' => $themeKey,
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

            // A normal Design -> Match Theme to Logo request is preview-only.
            // Do not persist even the generated palette/custom-theme metadata here;
            // Builder commits it only after the user clicks Apply Theme (then Save).
            // Legacy automatic/post-upload flows keep their existing immediate-apply
            // behavior for compatibility with callers outside the new Design CTA.
            if ($automaticUpload || $postUploadChoice) {
                $settings['custom_brand_theme'] = $customTheme;
                $settings['brand_palette'] = $customTheme['palette'] ?? ($result['palette'] ?? []);
                $settings['brand_source'] = 'logo';
                $settings['primary'] = 'my-brand';
                $settings['brand_active_logo_url'] = $validated['logo_url'];
                $settings['brand_original_logo_url'] = $settings['brand_original_logo_url'] ?? $validated['logo_url'];
                $settings['brand_favicon_url'] = $validated['logo_url'];
                $settings['logo_theme_sync_state'] = 'synced';
                $settings['logo_theme_sync_source'] = 'theme_to_logo';
                $settings['logo_theme_synced_theme'] = 'my-brand';
                unset($settings['brand_pre_upload_snapshot']);
                $website->theme_settings = $settings;
                $website->save();
            }
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
        $asset = app(\App\Services\MediaLibraryRegistry::class)->importRemoteImage(
            $website, (string) $item['url'], 'unsplash', $blockType ?: 'builder', $request->user()?->id,
            ['source_url' => $item['source_url'] ?? $item['url'], 'photographer' => $item['photographer'] ?? null]
        );
        $resolvedUrl = $asset ? app(\App\Services\MediaLibraryRegistry::class)->url($asset) : $item['url'];

        return response()->json([
            'url' => $resolvedUrl,
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

    public function uploadLogo(Request $request, SvgLogoLightnessService $svgLightness, SvgUploadSanitizer $svgSanitizer)
    {
        $request->validate([
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,svg', 'max:2048'],
        ]);

        $website = Website::findOrFail($request->integer('website_id'));
        $this->authorize('update', $website);

        $file = $request->file('image');
        $isSvg = strtolower($file->getClientOriginalExtension()) === 'svg' || $file->getMimeType() === 'image/svg+xml';
        $svgAnalysis = ['is_majority_white' => false, 'light_ratio' => 0.0, 'sample_count' => 0];

        if ($isSvg) {
            try {
                $svg = $svgSanitizer->sanitizePath($file->getRealPath());
            } catch (\RuntimeException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            $svgAnalysis = $svgLightness->analyze($svg);
        }

        // Upload is staged; the active brand remains untouched until crop/apply.
        $path = $file->store("websites/{$website->id}/logos", 'public');
        $asset = app(\App\Services\MediaLibraryRegistry::class)->registerStoredPath($website, $path, 'upload', 'logo', $request->user()?->id, $file->getClientOriginalName());
        $url = $asset ? app(\App\Services\MediaLibraryRegistry::class)->url($asset) : rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path;
        // All logo formats, including SVG, are staged until the shared cropper
        // confirms the asset. Uploading alone never replaces the active brand.

        return response()->json([
            'url' => $url,
            'is_svg' => $isSvg,
            'is_majority_white' => (bool) $svgAnalysis['is_majority_white'],
            'light_ratio' => $svgAnalysis['light_ratio'],
        ]);
    }

    // Function para sa pag-upload sa file
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,avif,heic,heif|max:8192',
            'website_id' => 'required|integer|exists:websites,id'
        ]);

        $website = Website::findOrFail($request->website_id);
        $this->authorize('update', $website);

        $websiteId = $website->id;
        $file = $request->file('image');
        $path = $file->store("websites/{$websiteId}", 'public');
        $asset = app(\App\Services\MediaLibraryRegistry::class)->registerStoredPath($website, $path, 'upload', 'image', $request->user()?->id, $file->getClientOriginalName());

        return response()->json([
            'url' => $asset ? app(\App\Services\MediaLibraryRegistry::class)->url($asset) : rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path
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
            $asset = app(\App\Services\MediaLibraryRegistry::class)->registerStoredPath($website, $path, 'upload', 'builder', $request->user()?->id, $file->getClientOriginalName());

	        return response()->json([
	            'success' => true,
	            'url' => $asset ? app(\App\Services\MediaLibraryRegistry::class)->url($asset) : rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path
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
	        'image' => 'required|file|mimes:jpeg,jpg,png,gif,webp,avif,heic,heif|max:8192'
	    ]);

	    $website = Website::findOrFail($request->website_id);
	    $this->authorize('update', $website);

	    // Upload ra gyud ni siya
	    $file = $request->file('image');
        $path = $file->store("websites/{$website->id}", 'public');
        $asset = app(\App\Services\MediaLibraryRegistry::class)->registerStoredPath($website, $path, 'upload', 'builder', $request->user()?->id, $file->getClientOriginalName());
	    $imageUrl = $asset ? app(\App\Services\MediaLibraryRegistry::class)->url($asset) : rtrim($request->getSchemeAndHttpHost(), '/') . '/storage/' . $path;

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
