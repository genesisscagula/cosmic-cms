<?php

namespace App\Http\Controllers;

use App\Models\TrialGeneration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\LogoThemeMatchService;
use App\Services\LogoThemeAnalysisService;
use App\Services\TrialCreditService;
use App\Services\LogoCanvasService;
use App\Services\ThemeColorResolver;
use App\Services\ThemeLogoPaletteService;
use App\Services\SmartLogoPromptService;

class TrialBrandingController extends Controller
{
    public function uploadLogo(Request $request, TrialGeneration $trial)
    {
        $this->assertTrialAvailable($trial);

        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,svg', 'max:2048'],
        ]);

        $file = $validated['image'];
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'svg') {
            $svg = file_get_contents($file->getRealPath());
            if ($svg === false || preg_match('/<\s*(?:script|iframe|object|embed|foreignObject)\b|\son\w+\s*=|(?:href|xlink:href)\s*=\s*[\'\"]\s*(?:https?:|javascript:|data:)/i', $svg)) {
                return response()->json(['message' => 'The SVG contains unsupported active or external content.'], 422);
            }
        }

        // Snapshot the brand that was active BEFORE this upload. The upload +
        // automatic theme analysis behaves transactionally: if the analysis is
        // throttled ("Too many attempts") the Builder can restore this exact
        // logo instead of leaving the newly uploaded asset half-applied.
        $previewTheme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        if (! isset($previewTheme['brand_pre_upload_snapshot'])) {
            $previewTheme['brand_pre_upload_snapshot'] = [
                'logo_url' => $trial->logo_url,
                'logo_source' => $trial->logo_source,
                'logo_theme_sync_state' => $trial->logo_theme_sync_state,
                'logo_theme_sync_source' => $trial->logo_theme_sync_source,
                'logo_theme_synced_theme' => $trial->logo_theme_synced_theme,
                'brand_original_logo_url' => $previewTheme['brand_original_logo_url'] ?? $trial->logo_url,
                'brand_active_logo_url' => $previewTheme['brand_active_logo_url'] ?? $trial->logo_url,
                'brand_favicon_url' => $previewTheme['brand_favicon_url'] ?? $trial->logo_url,
                'brand_logo_variants' => $previewTheme['brand_logo_variants'] ?? [],
            ];
        }

        $path = $file->store("trials/{$trial->id}/branding", 'public');
        $url = '/storage/'.$path;

        if ($extension === 'svg') {
            $previewTheme['brand_original_logo_url'] = $url;
            $previewTheme['brand_active_logo_url'] = $url;
            $previewTheme['brand_favicon_url'] = $url;
            $previewTheme['brand_logo_variants'] = [];
        }
        $trial->update([
            'logo_url' => $url,
            'preview_theme' => $previewTheme,
            'logo_company_name' => $trial->business_name,
            'logo_source' => 'upload',
            'logo_theme_sync_state' => 'logo_changed',
            'logo_theme_sync_source' => 'upload',
            'logo_theme_synced_theme' => null,
            'logo_updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'url' => $url,
            'source' => 'upload',
        ]);
    }

    public function generateLogo(Request $request, TrialGeneration $trial, TrialCreditService $trialCredits, LogoCanvasService $canvas, ThemeColorResolver $themeColors, ThemeLogoPaletteService $logoPalettes, SmartLogoPromptService $logoPrompt)
    {
        $this->assertTrialAvailable($trial);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:80'],
            // Trial theme changes can still be unsaved in the Builder. Accept the
            // live Builder state so logo generation never falls back to the
            // stale theme stored on TrialGeneration.
            'theme_key' => ['nullable', 'string', 'max:40'],
            'primary_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $isRegeneration = filled($trial->logo_url);
        if ($isRegeneration) {
            $usedToday = DB::table('trial_logo_generations')
                ->where('trial_generation_id', $trial->id)
                ->where('action', 'regenerate')
                ->where('created_at', '>=', now()->startOfDay())
                ->count();
            abort_if($usedToday >= 2, 429, 'You have used your two logo regenerations for today. Try again tomorrow or create an account to keep generating.');
        }

        $trialCredits->ensureCanSpend($trial, TrialCreditService::GENERATE_LOGO, 'AI logo generation');

        $apiKey = (string) config('openai.api_key');
        abort_if($apiKey === '', 503, 'AI logo generation is not configured.');

        $company = trim($validated['company_name']);
        $themeKey = trim((string) ($validated['theme_key'] ?? data_get($trial->preview_theme, 'primary', 'midnight')));
        if ($themeKey === '') {
            $themeKey = 'midnight';
        }

        // primary_hex from the request is the source of truth for trial logo
        // generation because it represents the theme currently visible in the
        // Builder (Emerald, Midnight, etc.), even before Save Theme is clicked.
        $primary = strtoupper((string) ($validated['primary_hex'] ?? ''));
        if (! preg_match('/^#[0-9A-F]{6}$/', $primary)) {
            $primary = $themeKey === 'my-brand'
                ? strtoupper((string) data_get($trial->preview_theme, 'custom_brand_theme.palette.background', '#243447'))
                : strtoupper($themeColors->primaryHex($themeKey));
        }
        $customPalette = $themeKey === 'my-brand'
            ? [
                'primary' => data_get($trial->preview_theme, 'brand_palette.primary'),
                'secondary' => data_get($trial->preview_theme, 'brand_palette.secondary'),
                'tertiary' => data_get($trial->preview_theme, 'brand_palette.accent'),
            ]
            : null;
        $logoPalette = $themeKey === 'my-brand'
            ? $logoPalettes->randomFor($themeKey, $customPalette)
            : $logoPalettes->forPrimary($themeKey, $primary);
        $resolvedThemePalette = $themeColors->palette($themeKey);
        $accent = strtoupper((string) ($themeKey === 'my-brand'
            ? ($logoPalette['secondary'] ?? $primary)
            : ($resolvedThemePalette['accent'] ?? $primary)));
        $surface = strtoupper((string) ($resolvedThemePalette['surface'] ?? $primary));
        $headerBackground = '#FFFFFF';
        $industry = trim((string) ($trial->industry ?: 'business'));

        $prompt = $logoPrompt->build(
            company: $company,
            industry: $industry,
            primary: $primary,
            themeKey: $themeKey,
            accent: $accent,
            headerBackground: $headerBackground,
            surface: $surface,
            brandPrompt: $trial->brand_prompt ?: $trial->prompt,
            brandContext: is_array($trial->brand_context) ? $trial->brand_context : [],
            latestUserPrompt: $trial->latest_user_prompt,
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
            report(new \RuntimeException('OpenAI logo generation failed: '.$response->body()));
            return response()->json(['message' => 'Cosmic AI could not generate the logo right now. Please try again.'], 502);
        }

        $encoded = data_get($response->json(), 'data.0.b64_json');
        if (! is_string($encoded) || $encoded === '') {
            return response()->json(['message' => 'Cosmic AI returned an empty logo. Please try again.'], 502);
        }

        $bytes = base64_decode($encoded, true);
        if ($bytes === false || strlen($bytes) < 100) {
            return response()->json(['message' => 'Cosmic AI returned an invalid logo image. Please try again.'], 502);
        }

        $bytes = $canvas->trimTransparentPng($bytes, 6);
        $logoDimensions = @getimagesizefromstring($bytes);

        $filename = 'luna-logo-'.Str::lower(Str::random(10)).'.png';
        // Stage Luna output as a temporary local raster. The cropper will treat
        // this file exactly like a computer upload and only commit after Save Crop.
        $path = "trials/{$trial->id}/branding/tmp/{$filename}";
        Storage::disk('public')->put($path, $bytes);
        $url = '/storage/'.$path;

        // Keep the currently active logo untouched while the crop modal is open.
        // This mirrors upload-logo behavior and also makes Cancel/failure safe.

        DB::table('trial_logo_generations')->insert([
            'trial_generation_id' => $trial->id,
            'company_name' => $company,
            'action' => $isRegeneration ? 'regenerate' : 'generate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $regenerationsUsedToday = DB::table('trial_logo_generations')
            ->where('trial_generation_id', $trial->id)
            ->where('action', 'regenerate')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        $balance = $trialCredits->consume($trial, TrialCreditService::GENERATE_LOGO, $isRegeneration ? 'regenerate_logo' : 'generate_logo', ['company_name' => $company]);

        return response()->json([
            'cost' => TrialCreditService::GENERATE_LOGO,
            'credit_balance' => $balance,
            'status' => 'success',
            'url' => $url,
            'company_name' => $company,
            'source' => 'ai',
            'logo_width' => (int) ($logoDimensions[0] ?? 0),
            'logo_height' => (int) ($logoDimensions[1] ?? 0),
            'sync_state' => 'synced',
            'sync_source' => 'generated_from_theme',
            'synced_theme' => $themeKey,
            'primary_hex' => $logoPalette['primary'],
            'logo_palette' => ['primary' => strtoupper($primary), 'secondary' => 'ai-selected', 'tertiary' => 'ai-selected'],
            'regenerations_used_today' => $regenerationsUsedToday,
            'regenerations_remaining_today' => max(0, 2 - $regenerationsUsedToday),
        ]);
    }


    public function cropLogo(Request $request, TrialGeneration $trial, LogoCanvasService $canvas)
    {
        $this->assertTrialAvailable($trial);

        $validated = $request->validate([
            'image_data' => ['required', 'string', 'max:8000000'],
            'company_name' => ['nullable', 'string', 'max:80'],
            'source_url' => ['nullable', 'string', 'max:2048'],
            'source_kind' => ['nullable', 'in:ai,upload,theme_match'],
        ]);

        if (! preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=\r\n]+)$/', $validated['image_data'], $matches)) {
            return response()->json(['message' => 'The cropped logo data is invalid.'], 422);
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);
        $dimensions = $bytes !== false ? $this->pngDimensions($bytes) : null;
        if ($bytes === false || strlen($bytes) < 100 || $dimensions === null) {
            return response()->json(['message' => 'The cropped logo could not be processed.'], 422);
        }

        // The 650x200 canvas is only the cropper/header-safe working frame.
        // Never persist that whole transparent canvas: otherwise the browser
        // sizes the empty pixels together with the artwork and the visible logo
        // looks tiny. Trim transparency only AFTER the user confirms the crop,
        // preserving every visible pixel plus a small anti-alias safety margin.
        $bytes = $canvas->trimTransparentPng($bytes, 8);
        $dimensions = $this->pngDimensions($bytes) ?: $dimensions;

        $filename = 'cropped-logo-'.Str::lower(Str::random(10)).'.png';
        $path = "trials/{$trial->id}/branding/{$filename}";
        Storage::disk('public')->put($path, $bytes);
        $url = '/storage/'.$path;

        $previewTheme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $sourceKind = (string) ($validated['source_kind'] ?? 'upload');
        $activeThemeKey = (string) data_get($previewTheme, 'primary', 'midnight');

        if ($sourceKind === 'theme_match') {
            // Preserve the original brand source; only the active theme variant
            // changes after the user explicitly confirms the crop.
            if (empty($previewTheme['brand_original_logo_url'])) {
                $previewTheme['brand_original_logo_url'] = $trial->logo_url ?: $url;
            }
            $previewTheme['brand_active_logo_url'] = $url;
            $previewTheme['brand_favicon_url'] = $url;
            $variants = (array) ($previewTheme['brand_logo_variants'] ?? []);
            $variants[$activeThemeKey] = $url;
            $previewTheme['brand_logo_variants'] = $variants;
        } else {
            $previewTheme['brand_original_logo_url'] = $url;
            $previewTheme['brand_active_logo_url'] = $url;
            $previewTheme['brand_favicon_url'] = $url;
            $previewTheme['brand_logo_variants'] = [];
        }

        $previewTheme['brand_logo_crop_confirmed'] = true;
        $previewTheme['brand_logo_crop_dismissed'] = false;
        // Keep display geometry stable after refresh; intrinsic PNG dimensions
        // are not header CSS dimensions.
        $previewTheme['logo_height'] = max(60, (int) ($previewTheme['logo_height'] ?? 60));
        $previewTheme['logo_max_width'] = max(300, (int) ($previewTheme['logo_max_width'] ?? 300));
        $isThemeMatch = $sourceKind === 'theme_match';
        $isGeneratedFromTheme = $sourceKind === 'ai';
        $trial->update([
            'logo_url' => $url,
            'preview_theme' => $previewTheme,
            'logo_company_name' => trim((string) ($validated['company_name'] ?? $trial->logo_company_name ?: $trial->business_name)),
            'logo_source' => $isThemeMatch ? 'ai-theme-match' : ($isGeneratedFromTheme ? 'ai' : ($trial->logo_source ?: 'upload')),
            'logo_theme_sync_state' => ($isThemeMatch || $isGeneratedFromTheme) ? 'synced' : 'logo_changed',
            'logo_theme_sync_source' => $isThemeMatch ? 'logo_to_theme' : ($isGeneratedFromTheme ? 'generated_from_theme' : 'upload'),
            'logo_theme_synced_theme' => ($isThemeMatch || $isGeneratedFromTheme) ? $activeThemeKey : null,
            'logo_updated_at' => now(),
        ]);

        $sourceUrl = trim((string) ($validated['source_url'] ?? ''));
        $tmpPrefix = '/storage/trials/'.$trial->id.'/branding/tmp/';
        if ($sourceUrl !== '' && str_contains($sourceUrl, $tmpPrefix)) {
            $relative = ltrim((string) parse_url($sourceUrl, PHP_URL_PATH), '/');
            if (str_starts_with($relative, 'storage/')) $relative = substr($relative, 8);
            Storage::disk('public')->delete($relative);
        }

        return response()->json([
            'status' => 'success',
            'url' => $url,
            'logo_width' => (int) ($dimensions['width'] ?? 650),
            'logo_height' => (int) ($dimensions['height'] ?? 200),
            'sync_state' => ($isThemeMatch || $isGeneratedFromTheme) ? 'synced' : 'logo_changed',
            'sync_source' => $isThemeMatch ? 'logo_to_theme' : ($isGeneratedFromTheme ? 'generated_from_theme' : 'upload'),
            'synced_theme' => ($isThemeMatch || $isGeneratedFromTheme) ? $activeThemeKey : null,
            'source_kind' => $sourceKind,
        ]);
    }

    public function dismissLogoCrop(Request $request, TrialGeneration $trial)
    {
        $this->assertTrialAvailable($trial);

        $previewTheme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $previewTheme['brand_logo_crop_confirmed'] = true;
        $previewTheme['brand_logo_crop_dismissed'] = true;
        $trial->update(['preview_theme' => $previewTheme]);

        return response()->json([
            'status' => 'success',
            'dismissed' => true,
        ]);
    }

    public function matchLogoToTheme(Request $request, TrialGeneration $trial, LogoThemeMatchService $matcher, TrialCreditService $trialCredits, LogoCanvasService $canvas, ThemeColorResolver $themeColors)
    {
        $this->assertTrialAvailable($trial);

        $validated = $request->validate([
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'theme_key' => ['nullable', 'string', 'max:60'],
            'theme_name' => ['nullable', 'string', 'max:80'],
            'primary_hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'tertiary_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $trialCredits->ensureCanSpend($trial, TrialCreditService::MATCH_LOGO_TO_THEME, 'Match Logo to Theme');

        try {
            $themeKey = (string) ($validated['theme_key'] ?? data_get($trial->preview_theme, 'primary', 'midnight'));
            $themeName = (string) ($validated['theme_name'] ?? $themeKey);

            // Theme matching must use the exact FINAL website palette, just like
            // initial trial-logo generation. Never introduce a random curated
            // logo palette here: that was the cause of unrelated blue/teal/orange
            // variants after a manual theme change.
            $resolvedThemePalette = $themeColors->palette($themeKey);
            $logoPalette = [
                'primary' => strtoupper((string) $validated['primary_hex']),
                'secondary' => strtoupper((string) ($validated['accent_hex'] ?? $resolvedThemePalette['accent'])),
                'tertiary' => strtoupper((string) ($validated['secondary_hex'] ?? $resolvedThemePalette['surface'])),
                'accent' => strtoupper((string) ($validated['accent_hex'] ?? $resolvedThemePalette['accent'])),
                'surface' => strtoupper((string) ($validated['secondary_hex'] ?? $resolvedThemePalette['surface'])),
                'header_background' => '#FFFFFF',
            ];

            $currentLogoUrl = trim((string) ($trial->logo_url ?: ($validated['logo_url'] ?? '/storage/branding/your-logo.png')));
            $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
            $sourceLogoUrl = trim((string) ($themeSettings['brand_original_logo_url'] ?? $currentLogoUrl));
            if ($sourceLogoUrl === '') $sourceLogoUrl = $currentLogoUrl;
            if (empty($themeSettings['brand_original_logo_url'])) {
                $themeSettings['brand_original_logo_url'] = $currentLogoUrl;
            }
            $result = $matcher->match(
                $sourceLogoUrl,
                $logoPalette,
                $themeName,
                [
                    'brand_prompt' => (string) ($trial->brand_prompt ?? $trial->prompt ?? ''),
                    'latest_user_prompt' => (string) ($trial->latest_user_prompt ?? ''),
                    'brand_context' => (array) ($trial->brand_context ?? []),
                ],
                (string) ($trial->logo_company_name ?: $trial->business_name),
                (string) ($trial->industry ?: data_get($trial->brand_context, 'industry', 'business')),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Cosmic AI could not match this logo to the theme. No credits were used.'], 500);
        }

        if (($result['extension'] ?? '') === 'png') {
            $result['bytes'] = $canvas->trimTransparentPng($result['bytes'], 6);
        }

        // Stage the matched result exactly like a generated/uploaded raster.
        // It must NOT become the active logo until the user confirms the same
        // 650x200 cropper used by the trial-start logo flow.
        $filename = 'theme-matched-logo-'.Str::lower(Str::random(10)).'.'.$result['extension'];
        $path = "trials/{$trial->id}/branding/tmp/{$filename}";
        Storage::disk('public')->put($path, $result['bytes']);
        $url = '/storage/'.$path;

        DB::table('trial_logo_generations')->insert([
            'trial_generation_id' => $trial->id,
            'company_name' => $trial->logo_company_name ?: $trial->business_name,
            'action' => 'regenerate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $balance = $trialCredits->consume($trial, TrialCreditService::MATCH_LOGO_TO_THEME, 'match_logo_to_theme', [
            'theme' => $themeKey,
            'theme_name' => $themeName,
            'palette' => $logoPalette,
        ]);

        return response()->json([
            'status' => 'success',
            'url' => $url,
            'source' => $result['ai'] ? 'ai-theme-match' : 'svg-theme-match',
            'sync_state' => 'theme_changed',
            'sync_source' => 'logo_to_theme_pending_crop',
            'synced_theme' => null,
            'pending_theme' => $themeKey,
            'logo_palette' => $logoPalette,
            'cost' => TrialCreditService::MATCH_LOGO_TO_THEME,
            'credit_balance' => $balance,
        ]);
    }

    public function rollbackUploadedLogo(Request $request, TrialGeneration $trial)
    {
        $this->assertTrialAvailable($trial);
        $theme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $snapshot = is_array($theme['brand_pre_upload_snapshot'] ?? null)
            ? $theme['brand_pre_upload_snapshot']
            : null;

        if (! $snapshot) {
            return response()->json(['message' => 'No previous logo is available to restore.'], 422);
        }

        $restoreUrl = trim((string) ($snapshot['logo_url'] ?? $snapshot['brand_active_logo_url'] ?? ''));
        if ($restoreUrl === '') {
            $restoreUrl = '/storage/branding/your-logo.png';
        }

        foreach (['brand_original_logo_url', 'brand_active_logo_url', 'brand_favicon_url', 'brand_logo_variants'] as $key) {
            if (array_key_exists($key, $snapshot)) {
                $theme[$key] = $snapshot[$key];
            } else {
                unset($theme[$key]);
            }
        }
        unset($theme['brand_pre_upload_snapshot']);

        $trial->update([
            'logo_url' => $restoreUrl,
            'preview_theme' => $theme,
            'logo_source' => $snapshot['logo_source'] ?? null,
            'logo_theme_sync_state' => $snapshot['logo_theme_sync_state'] ?? null,
            'logo_theme_sync_source' => $snapshot['logo_theme_sync_source'] ?? null,
            'logo_theme_synced_theme' => $snapshot['logo_theme_synced_theme'] ?? null,
            'logo_updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'url' => $restoreUrl,
            'sync_state' => $trial->fresh()->logo_theme_sync_state,
            'sync_source' => $trial->fresh()->logo_theme_sync_source,
            'synced_theme' => $trial->fresh()->logo_theme_synced_theme,
        ]);
    }

    public function restoreOriginalLogo(Request $request, TrialGeneration $trial)
    {
        $this->assertTrialAvailable($trial);
        $theme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $original = trim((string) ($theme['brand_original_logo_url'] ?? ''));
        if ($original === '') return response()->json(['message' => 'No original logo is available to restore.'], 422);
        $theme['brand_active_logo_url'] = $original;
        $theme['brand_favicon_url'] = $original;
        $trial->update([
            'logo_url' => $original,
            'preview_theme' => $theme,
            'logo_source' => 'original',
            'logo_theme_sync_state' => 'logo_changed',
            'logo_theme_sync_source' => 'restore_original',
            'logo_theme_synced_theme' => null,
            'logo_updated_at' => now(),
        ]);
        return response()->json(['status' => 'success', 'url' => $original]);
    }

    public function matchThemeToLogo(Request $request, TrialGeneration $trial, LogoThemeAnalysisService $analyzer, TrialCreditService $trialCredits)
    {
        $this->assertTrialAvailable($trial);
        $validated = $request->validate([
            'logo_url' => ['nullable', 'string', 'max:2048'],
            'automatic_upload' => ['nullable', 'boolean'],
        ]);
        $sourceLogoUrl = trim((string) ($validated['logo_url'] ?? $trial->logo_url ?: '/storage/branding/your-logo.png'));
        $automaticUpload = (bool) ($validated['automatic_upload'] ?? false);
        $freeUploadAnalysis = $automaticUpload
            && $trial->logo_source === 'upload'
            && hash_equals((string) $trial->logo_url, $sourceLogoUrl);

        if ($automaticUpload && ! $freeUploadAnalysis) {
            return response()->json(['message' => 'Automatic brand matching is only available immediately from the uploaded trial logo.'], 422);
        }

        $existingCustomTheme = (array) data_get($trial->preview_theme, 'custom_brand_theme', []);
        if ($freeUploadAnalysis
            && $existingCustomTheme !== []
            && hash_equals((string) ($existingCustomTheme['source_logo_url'] ?? ''), $sourceLogoUrl)) {
            return response()->json([
                'status' => 'success',
                'custom_theme' => $existingCustomTheme,
                'palette' => $existingCustomTheme['palette'] ?? [],
                'recommended_family' => 'my-brand',
                'reason' => 'Reused the existing brand theme for this uploaded logo.',
                'cost' => 0,
                'automatic_upload' => true,
                'credit_balance' => $trialCredits->balance($trial),
            ]);
        }

        if (! $freeUploadAnalysis) {
            $trialCredits->ensureCanSpend($trial, TrialCreditService::MATCH_THEME_TO_LOGO, 'Match Theme to Logo');
        }

        try {
            $result = $analyzer->analyze($sourceLogoUrl);

            $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
            $customTheme = is_array($result['custom_theme'] ?? null) ? $result['custom_theme'] : null;

            if (! $customTheme) {
                return response()->json(['message' => 'Cosmic AI did not return a usable My Brand Theme. No credits were used.'], 422);
            }

            $customTheme['source_logo_url'] = $sourceLogoUrl;
            $customTheme['updated_at'] = now()->toIso8601String();
            $themeSettings['custom_brand_theme'] = $customTheme;
            $themeSettings['brand_palette'] = $customTheme['palette'] ?? ($result['palette'] ?? []);
            $themeSettings['brand_source'] = 'logo';
            // Brand analysis succeeded, so the uploaded logo is now committed.
            unset($themeSettings['brand_pre_upload_snapshot']);

            $trial->update([
                'preview_theme' => $themeSettings,
                'last_saved_at' => now(),
            ]);

            $result['custom_theme'] = $customTheme;

            // Audit logging should never be allowed to break the actual brand match.
            try {
                DB::table('trial_logo_generations')->insert([
                    'trial_generation_id' => $trial->id,
                    'company_name' => $trial->logo_company_name ?: $trial->business_name,
                    'action' => 'theme_from_logo',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $auditError) {
                report($auditError);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage().' No credits were used.'], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Cosmic AI could not match My Brand Theme to this logo. No credits were used.'], 500);
        }

        $balance = $freeUploadAnalysis
            ? $trialCredits->balance($trial)
            : $trialCredits->consume(
                $trial,
                TrialCreditService::MATCH_THEME_TO_LOGO,
                'match_theme_to_logo',
                ['recommended_family' => $result['recommended_family'] ?? null, 'custom_theme' => $customTheme]
            );

        return response()->json([
            'status' => 'success',
            ...$result,
            'cost' => $freeUploadAnalysis ? 0 : TrialCreditService::MATCH_THEME_TO_LOGO,
            'automatic_upload' => $freeUploadAnalysis,
            'credit_balance' => $balance,
        ]);
    }

    private function assertTrialAvailable(TrialGeneration $trial): void
    {
        abort_if($trial->claimed_at, 410, 'This trial has already been claimed.');
        $expiresAt = filled($trial->email)
            ? $trial->created_at->copy()->addDays(30)
            : $trial->created_at->copy()->addHours(24);
        abort_if($expiresAt->isPast(), 410, 'This trial link has expired.');
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
        // Accept either the full crop frame or any tighter PNG produced from it.
        // Reject only images that exceed the crop contract bounds.
        if ($width > 650 || $height > 200) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

}
