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

        $path = $file->store("trials/{$trial->id}/branding", 'public');
        $url = '/storage/'.$path;

        $trial->update([
            'logo_url' => $url,
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

    public function generateLogo(Request $request, TrialGeneration $trial, TrialCreditService $trialCredits, LogoCanvasService $canvas, ThemeColorResolver $themeColors, ThemeLogoPaletteService $logoPalettes)
    {
        $this->assertTrialAvailable($trial);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:80'],
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
        $themeKey = (string) data_get($trial->preview_theme, 'primary', 'midnight');
        $primary = $themeKey === 'my-brand'
            ? (string) data_get($trial->preview_theme, 'custom_brand_theme.palette.background', '#243447')
            : $themeColors->primaryHex($themeKey);
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
        $industry = trim((string) ($trial->industry ?: 'business'));

        $prompt = implode("\n", [
            "Create a clean professional horizontal brand logo for {$company}.",
            "Industry: {$industry}.",
            "Current website theme: {$themeKey}.",
            "Logo palette — use these exact colors intelligently:",
            "Primary (dominant): {$logoPalette['primary']}.",
            "Secondary (supporting): {$logoPalette['secondary']}.",
            "Tertiary (small accents): {$logoPalette['tertiary']}.",
            'EXACT PRIMARY COLOR CONTRACT: the supplied PRIMARY HEX must be used exactly at 100% opacity on the dominant brand elements.',
            'Never lower PRIMARY opacity. Never lighten, darken, tint, shade, mute, desaturate, blend, recolor, or substitute PRIMARY with a near-match. Exact HEX wins over artistic styling.',
            'PRIMARY must remain the unmistakable dominant brand color. SECONDARY is optional supporting design color. TERTIARY is optional and only for small accents/details.',
            'SECONDARY and TERTIARY may add contrast and personality but must never change the appearance of PRIMARY or visually overpower it.',
            'Design the logo specifically for a 650 × 150 pixel horizontal website-header crop box (13:3 aspect ratio).',
            'The complete visible logo artwork must FIT INSIDE that 650 × 150 safe frame: horizontal symbol + wordmark preferred, centered, with comfortable transparent padding on every side.',
            'Do not make a tall, square, stacked, poster-like, or oversized composition. Do not require cropping or zooming to fit the 650 × 150 header frame.',
            'Keep the entire icon, wordmark, every letter, and any intentional detail inside the safe frame. Nothing may touch or cross an edge.',
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

        $bytes = $canvas->normalizePngToPalette($bytes, $logoPalette);
        $logoDimensions = @getimagesizefromstring($bytes);

        $filename = 'luna-logo-'.Str::lower(Str::random(10)).'.png';
        $path = "trials/{$trial->id}/branding/{$filename}";
        Storage::disk('public')->put($path, $bytes);
        $url = '/storage/'.$path;

        $trial->update([
            'logo_url' => $url,
            'logo_company_name' => $company,
            'logo_source' => 'ai',
            'logo_theme_sync_state' => 'synced',
            'logo_theme_sync_source' => 'generated_from_theme',
            'logo_theme_synced_theme' => $themeKey,
            'logo_updated_at' => now(),
        ]);

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
            'logo_palette' => $logoPalette,
            'regenerations_used_today' => $regenerationsUsedToday,
            'regenerations_remaining_today' => max(0, 2 - $regenerationsUsedToday),
        ]);
    }


    public function cropLogo(Request $request, TrialGeneration $trial)
    {
        $this->assertTrialAvailable($trial);

        $validated = $request->validate([
            'image_data' => ['required', 'string', 'max:8000000'],
            'company_name' => ['nullable', 'string', 'max:80'],
        ]);

        if (! preg_match('/^data:image\/png;base64,([A-Za-z0-9+\/=\r\n]+)$/', $validated['image_data'], $matches)) {
            return response()->json(['message' => 'The cropped logo data is invalid.'], 422);
        }

        $bytes = base64_decode(preg_replace('/\s+/', '', $matches[1]), true);
        $dimensions = $bytes !== false ? $this->pngDimensions($bytes) : null;
        if ($bytes === false || strlen($bytes) < 100 || $dimensions === null) {
            return response()->json(['message' => 'The cropped logo could not be processed.'], 422);
        }

        $filename = 'cropped-logo-'.Str::lower(Str::random(10)).'.png';
        $path = "trials/{$trial->id}/branding/{$filename}";
        Storage::disk('public')->put($path, $bytes);
        $url = '/storage/'.$path;

        $trial->update([
            'logo_url' => $url,
            'logo_company_name' => trim((string) ($validated['company_name'] ?? $trial->logo_company_name ?: $trial->business_name)),
            'logo_updated_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'url' => $url,
            'logo_width' => (int) ($dimensions['width'] ?? 650),
            'logo_height' => (int) ($dimensions['height'] ?? 150),
        ]);
    }

    public function matchLogoToTheme(Request $request, TrialGeneration $trial, LogoThemeMatchService $matcher, TrialCreditService $trialCredits, LogoCanvasService $canvas, ThemeLogoPaletteService $logoPalettes)
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

            $customPalette = $themeKey === 'my-brand'
                ? (array) data_get($trial->preview_theme, 'custom_brand_theme.palette', [])
                : [];

            $logoPalette = $logoPalettes->forPrimary($themeKey, $validated['primary_hex']);
            $logoPalette['secondary'] = strtoupper((string) ($validated['secondary_hex'] ?? ($customPalette['secondary'] ?? $customPalette['surface'] ?? $logoPalette['secondary'])));
            $logoPalette['tertiary'] = strtoupper((string) ($validated['tertiary_hex'] ?? ($customPalette['tertiary'] ?? $customPalette['accent'] ?? $logoPalette['tertiary'])));

            $sourceLogoUrl = trim((string) ($trial->logo_url ?: ($validated['logo_url'] ?? '/storage/branding/your-logo.png')));
            $result = $matcher->match(
                $sourceLogoUrl,
                $logoPalette,
                $themeName
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['message' => 'Cosmic AI could not match this logo to the theme. No credits were used.'], 500);
        }

        if (($result['extension'] ?? '') === 'png') {
            $result['bytes'] = $canvas->normalizePngToPalette($result['bytes'], $logoPalette);
        }

        $filename = 'theme-matched-logo-'.Str::lower(Str::random(10)).'.'.$result['extension'];
        $path = "trials/{$trial->id}/branding/{$filename}";
        Storage::disk('public')->put($path, $result['bytes']);
        $url = '/storage/'.$path;

        $trial->update([
            'logo_url' => $url,
            'logo_source' => $result['ai'] ? 'ai-theme-match' : 'svg-theme-match',
            'logo_theme_sync_state' => 'synced',
            'logo_theme_sync_source' => 'logo_to_theme',
            'logo_theme_synced_theme' => $themeKey,
            'logo_updated_at' => now(),
        ]);

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
            'source' => $trial->logo_source,
            'sync_state' => 'synced',
            'sync_source' => 'logo_to_theme',
            'synced_theme' => $themeKey,
            'logo_palette' => $logoPalette,
            'cost' => TrialCreditService::MATCH_LOGO_TO_THEME,
            'credit_balance' => $balance,
        ]);
    }

    public function matchThemeToLogo(Request $request, TrialGeneration $trial, LogoThemeAnalysisService $analyzer, TrialCreditService $trialCredits)
    {
        $this->assertTrialAvailable($trial);
        $sourceLogoUrl = trim((string) ($trial->logo_url ?: '/storage/branding/your-logo.png'));

        $trialCredits->ensureCanSpend($trial, TrialCreditService::MATCH_THEME_TO_LOGO, 'Match Theme to Logo');

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

        $balance = $trialCredits->consume(
            $trial,
            TrialCreditService::MATCH_THEME_TO_LOGO,
            'match_theme_to_logo',
            ['recommended_family' => $result['recommended_family'] ?? null, 'custom_theme' => $customTheme]
        );

        return response()->json([
            'status' => 'success',
            ...$result,
            'cost' => TrialCreditService::MATCH_THEME_TO_LOGO,
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

        // H7 browser canvas contract: final crop is exactly 650 × 150.
        if ($width !== 650 || $height !== 150) {
            return null;
        }

        return [
            'width' => $width,
            'height' => $height,
        ];
    }

}
