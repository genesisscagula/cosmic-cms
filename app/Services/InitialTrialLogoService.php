<?php

namespace App\Services;

use App\Models\TrialGeneration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InitialTrialLogoService
{
    public function __construct(
        private readonly LogoCanvasService $canvas,
        private readonly ThemeColorResolver $themeColors,
        private readonly SmartLogoPromptService $prompts,
    ) {}

    /** Generate the complimentary first-trial brand mark. Never charges guest credits. */
    public function generate(TrialGeneration $trial): ?string
    {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') return null;

        $themeKey = (string) data_get($trial->preview_theme, 'primary', 'midnight');
        $primary = strtoupper($this->themeColors->primaryHex($themeKey));
        $themePalette = $this->themeColors->palette($themeKey);
        $accent = strtoupper((string) ($themePalette['accent'] ?? $primary));
        $surface = strtoupper((string) ($themePalette['surface'] ?? $primary));
        $headerBackground = '#FFFFFF';
        $generic = $this->isGenericName($trial->business_name);

        $prompt = $generic
            ? $this->anonymousPrompt($trial, $themeKey, $primary, $accent, $headerBackground)
            : $this->prompts->build(
                company: $trial->business_name,
                industry: $trial->industry ?: 'business',
                primary: $primary,
                themeKey: $themeKey,
                accent: $accent,
                headerBackground: $headerBackground,
                surface: $surface,
                brandPrompt: $trial->brand_prompt ?: $trial->prompt,
                brandContext: is_array($trial->brand_context) ? $trial->brand_context : [],
                latestUserPrompt: $trial->latest_user_prompt,
            );

        try {
            $response = Http::withToken($apiKey)->acceptJson()
                ->timeout((int) config('openai.request_timeout', 180))
                ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/images/generations', [
                    'model' => env('OPENAI_LOGO_MODEL', 'gpt-image-1'),
                    'prompt' => $prompt,
                    'size' => '1024x1024',
                    'quality' => env('OPENAI_LOGO_QUALITY', 'low'),
                    'background' => 'transparent',
                    'n' => 1,
                ]);

            if ($response->failed()) throw new \RuntimeException($response->body());
            $encoded = data_get($response->json(), 'data.0.b64_json');
            $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
            if ($bytes === false || strlen($bytes) < 100) throw new \RuntimeException('Empty/invalid image response.');

            $bytes = $this->canvas->trimTransparentPng($bytes, 6);
            $path = 'trials/'.$trial->id.'/branding/initial-logo-'.Str::lower(Str::random(10)).'.png';
            Storage::disk('public')->put($path, $bytes);
            $url = '/storage/'.$path;

            $previewTheme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
            $previewTheme['brand_original_logo_url'] = $url;
            $previewTheme['brand_active_logo_url'] = $url;
            $previewTheme['brand_favicon_url'] = $url;
            $previewTheme['brand_logo_variants'] = [];
            $previewTheme['brand_logo_crop_confirmed'] = false;
            $previewTheme['brand_logo_crop_dismissed'] = false;

            $trial->update([
                'logo_url' => $url,
                'preview_theme' => $previewTheme,
                'logo_company_name' => $generic ? null : $trial->business_name,
                'logo_source' => $generic ? 'ai-neutral' : 'ai',
                'logo_theme_sync_state' => 'synced',
                'logo_theme_sync_source' => 'initial_trial_generation',
                'logo_theme_synced_theme' => $themeKey,
                'logo_updated_at' => now(),
            ]);

            return $url;
        } catch (\Throwable $e) {
            // Website generation must still succeed if the optional logo provider fails.
            Log::warning('[TrialBrand] Initial logo generation failed; using safe fallback.', [
                'trial' => $trial->id, 'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function isGenericName(?string $name): bool
    {
        $name = Str::lower(trim((string) $name));
        return $name === '' || in_array($name, ['your new website', 'landing page', 'website', 'your website'], true);
    }

    private function anonymousPrompt(TrialGeneration $trial, string $themeKey, string $primary, string $accent, string $headerBackground): string
    {
        return "Create a premium SYMBOL-ONLY temporary brand mark for a {$trial->industry} website. "
            ."The user did not provide a company name, so DO NOT invent, render, spell, or imply any business name, initials, slogan, or text. "
            ."Infer a tasteful visual direction from this brief: ".mb_substr((string) ($trial->brand_prompt ?: $trial->prompt), 0, 900).". "
            ."Match the FINAL {$themeKey} website theme exactly: use {$primary} as the dominant visible color and {$accent} as the supporting accent. Do not invent unrelated hues. The header background is {$headerBackground}; keep every visible mark fully opaque and high-contrast against it. "
            ."Distinctive professional icon, transparent background, no watermark, no mockup, no scene. "
            ."Compose it for the 650 × 200 (3.25:1) Cosmic header frame using contain, never cover. Keep roughly 6–10% transparent safety padding on every side. Make the symbol substantial at header size—roughly 74–86% of the usable frame height—and do not place a tiny mark in a mostly empty canvas. Nothing may touch or cross the safe-area edges.";
    }
}
