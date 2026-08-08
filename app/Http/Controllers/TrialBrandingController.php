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

    public function generateLogo(Request $request, TrialGeneration $trial, TrialCreditService $trialCredits, LogoCanvasService $canvas, ThemeColorResolver $themeColors)
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
        $industry = trim((string) ($trial->industry ?: 'business'));

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

        $bytes = $canvas->normalizePngToPrimary($bytes, $primary);

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
            'logo_width' => LogoCanvasService::WIDTH,
            'logo_height' => LogoCanvasService::HEIGHT,
            'sync_state' => 'synced',
            'sync_source' => 'generated_from_theme',
            'synced_theme' => $themeKey,
            'primary_hex' => $primary,
            'regenerations_used_today' => $regenerationsUsedToday,
            'regenerations_remaining_today' => max(0, 2 - $regenerationsUsedToday),
        ]);
    }


    public function matchLogoToTheme(Request $request, TrialGeneration $trial, LogoThemeMatchService $matcher, TrialCreditService $trialCredits, LogoCanvasService $canvas)
    {
        $this->assertTrialAvailable($trial);
        abort_if(! filled($trial->logo_url), 422, 'Add a logo before matching it to the theme.');

        $validated = $request->validate([
            'theme_name' => ['nullable', 'string', 'max:60'],
            'primary_hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_hex' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $usedToday = DB::table('trial_logo_generations')
            ->where('trial_generation_id', $trial->id)
            ->where('action', 'regenerate')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        abort_if($usedToday >= 2, 429, 'You have used your two logo regenerations for today. Try again tomorrow or create an account to keep generating.');

        $trialCredits->ensureCanSpend($trial, TrialCreditService::MATCH_LOGO_TO_THEME, 'Match Logo to Theme');

        try {
            $result = $matcher->match(
                $trial->logo_url,
                $validated['primary_hex'],
                $validated['accent_hex'] ?? '',
                $validated['theme_name'] ?? ''
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if (($result['extension'] ?? '') === 'png') {
            $result['bytes'] = $canvas->normalizePngToPrimary($result['bytes'], $validated['primary_hex']);
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
            'logo_theme_synced_theme' => (string) data_get($trial->preview_theme, 'primary', 'midnight'),
            'logo_updated_at' => now(),
        ]);

        DB::table('trial_logo_generations')->insert([
            'trial_generation_id' => $trial->id,
            'company_name' => $trial->logo_company_name ?: $trial->business_name,
            'action' => 'regenerate',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $usedToday++;
        $balance = $trialCredits->consume($trial, TrialCreditService::MATCH_LOGO_TO_THEME, 'match_logo_to_theme', ['theme' => $validated['theme_name'] ?? null]);

        return response()->json([
            'status' => 'success',
            'url' => $url,
            'source' => $trial->logo_source,
            'sync_state' => 'synced',
            'sync_source' => 'logo_to_theme',
            'synced_theme' => (string) data_get($trial->preview_theme, 'primary', 'midnight'),
            'cost' => TrialCreditService::MATCH_LOGO_TO_THEME,
            'credit_balance' => $balance,
            'regenerations_used_today' => $usedToday,
            'regenerations_remaining_today' => max(0, 2 - $usedToday),
        ]);
    }

    public function matchThemeToLogo(Request $request, TrialGeneration $trial, LogoThemeAnalysisService $analyzer, TrialCreditService $trialCredits)
    {
        $this->assertTrialAvailable($trial);
        abort_if(! filled($trial->logo_url), 422, 'Add a logo before matching the theme to it.');

        $usedToday = DB::table('trial_logo_generations')
            ->where('trial_generation_id', $trial->id)
            ->where('action', 'theme_from_logo')
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
        abort_if($usedToday >= 2, 429, 'You have used your two theme-from-logo analyses for today. Try again tomorrow or create an account to keep generating.');

        $trialCredits->ensureCanSpend($trial, TrialCreditService::MATCH_THEME_TO_LOGO, 'Match Theme to Logo');

        try {
            $result = $analyzer->analyze($trial->logo_url);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        DB::table('trial_logo_generations')->insert([
            'trial_generation_id' => $trial->id,
            'company_name' => $trial->logo_company_name ?: $trial->business_name,
            'action' => 'theme_from_logo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $themeSettings = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $customTheme = is_array($result['custom_theme'] ?? null) ? $result['custom_theme'] : null;
        if ($customTheme) {
            $customTheme['source_logo_url'] = $trial->logo_url;
            $customTheme['updated_at'] = now()->toIso8601String();
            $themeSettings['custom_brand_theme'] = $customTheme;
            $trial->update(['preview_theme' => $themeSettings, 'last_saved_at' => now()]);
            $result['custom_theme'] = $customTheme;
        }

        $balance = $trialCredits->consume($trial, TrialCreditService::MATCH_THEME_TO_LOGO, 'match_theme_to_logo', ['recommended_family' => $result['recommended_family'] ?? null, 'custom_theme' => $customTheme]);

        return response()->json([
            'status' => 'success',
            ...$result,
            'cost' => TrialCreditService::MATCH_THEME_TO_LOGO,
            'credit_balance' => $balance,
            'analyses_used_today' => $usedToday + 1,
            'analyses_remaining_today' => max(0, 1 - $usedToday),
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
}
