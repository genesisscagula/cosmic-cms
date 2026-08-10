<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LogoThemeMatchService
{
    public function match(
        string $logoUrl,
        array $palette,
        string $themeName = '',
        array $brandMemory = [],
        string $companyName = '',
        string $industry = 'business',
    ): array {
        $primaryHex = $this->normalizeHex((string) ($palette['primary'] ?? ''));
        $secondaryHex = $this->normalizeHex((string) ($palette['secondary'] ?? $primaryHex));
        $tertiaryHex = $this->normalizeHex((string) ($palette['tertiary'] ?? $secondaryHex));
        $path = $this->publicDiskPathFromUrl($logoUrl);

        if (! Storage::disk('public')->exists($path)) {
            throw new RuntimeException('The current logo file could not be found. Upload or regenerate the logo first.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            // SVG stays vector-safe. We preserve its geometry and improve the
            // palette without rasterizing the user's original vector artwork.
            $source = Storage::disk('public')->get($path);
            $result = $this->recolorSvg($source, $primaryHex, $secondaryHex, $tertiaryHex);
            return ['bytes' => $result, 'extension' => 'svg', 'mime' => 'image/svg+xml', 'ai' => false];
        }

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        $bytes = Storage::disk('public')->get($path);
        $edited = $this->editRasterWithLuna(
            bytes: $bytes,
            mime: $mime,
            extension: $extension ?: 'png',
            primaryHex: $primaryHex,
            secondaryHex: $secondaryHex,
            tertiaryHex: $tertiaryHex,
            themeName: $themeName,
            brandMemory: $brandMemory,
            companyName: $companyName,
            industry: $industry,
        );

        return ['bytes' => $edited, 'extension' => 'png', 'mime' => 'image/png', 'ai' => true];
    }

    private function editRasterWithLuna(
        string $bytes,
        string $mime,
        string $extension,
        string $primaryHex,
        string $secondaryHex,
        string $tertiaryHex,
        string $themeName,
        array $brandMemory,
        string $companyName,
        string $industry,
    ): string {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('AI logo matching is not configured.');
        }

        $brandPrompt = $this->clean((string) ($brandMemory['brand_prompt'] ?? ''), 1000);
        $latestPrompt = $this->clean((string) ($brandMemory['latest_user_prompt'] ?? ''), 500);
        $brandContext = $this->contextSummary((array) ($brandMemory['brand_context'] ?? []));
        $companyName = $this->clean($companyName, 100);
        $industry = $this->clean($industry, 120) ?: 'business';

        $prompt = implode("\n", array_filter([
            'BRAND-IDENTITY REFINEMENT of the supplied existing logo. Match it to the website theme in BOTH COLOR AND VISUAL DESIGN — not recolor-only.',
            $companyName !== '' ? "Company/brand name: {$companyName}." : null,
            "Industry: {$industry}.",
            'Theme: '.($themeName !== '' ? $themeName : 'current website theme').'.',
            "FINAL THEME PRIMARY — EXACT AND MANDATORY: {$primaryHex}.",
            "FINAL THEME ACCENT — EXACT AND MANDATORY: {$secondaryHex}.",
            "FINAL THEME SURFACE/NEUTRAL — EXACT AND MANDATORY: {$tertiaryHex}.",
            'COLOR MATCH IS MANDATORY: derive the visible logo palette from those exact final website colors. Do not invent unrelated blue, teal, purple, orange, gray, or generic SaaS colors unless that exact hue is present in the supplied final palette.',
            'All visible icon and wordmark pixels must be fully opaque and high-contrast on a white/light website header. Do not fade the company name or use washed-out low-opacity lettering.', 
            $brandPrompt !== '' ? "Original website/brand brief: {$brandPrompt}" : null,
            $brandContext !== '' ? "Saved brand context: {$brandContext}" : null,
            $latestPrompt !== '' && $latestPrompt !== $brandPrompt ? "Recent brand direction, only if relevant: {$latestPrompt}" : null,
            'IDENTITY RULE: preserve the exact company/wordmark text and keep the logo recognizably the same brand. Do not invent a different business name, slogan, mascot, or unrelated symbol.',
            'DESIGN FREEDOM: you MAY professionally refine the symbol treatment, symbol-to-wordmark relationship, spacing, balance, proportions, typography treatment, and overall horizontal composition when doing so produces a stronger brand mark. This is intentionally more than a color swap.',
            'Choose art direction from the brand context and theme. A refined flat treatment is allowed, but do NOT default to generic flat SaaS styling. When suitable, introduce premium dimensional/3D depth, layered forms, tasteful gradients, metallic or glass-like material cues, soft highlights, controlled shadows, depth separation, or a more custom wordmark treatment.',
            'If a 3D/dimensional treatment fits the brand, keep it logo-like and restrained: clean silhouette, crisp edges, no scene, no mockup, no wall sign, no floating product render, and no photographic background.',
            "Use {$primaryHex} as the dominant palette anchor and {$secondaryHex} as the intentional supporting accent. {$tertiaryHex} may be used only as a restrained surface/neutral support. Tonal variants are allowed only when they remain visibly within this final theme color family.",
            'Keep the result visually compatible with the website while maintaining strong contrast and wordmark legibility at small header size.',
            'Prefer a premium horizontal logo composition suitable for a website navbar. The symbol may remain left of the wordmark, integrate into it, or be proportionally rebalanced, but the brand must remain immediately recognizable.',
            'HEADER CROPPER TARGET IS MANDATORY: refine the complete visible logo to fit safely inside a 650 × 200 pixel website-header frame (3.25:1). Preserve every existing wordmark line, icon, tagline, shadow, glow, highlight, and intentional decorative element.',
            'SAFE AREA: keep approximately 10–15% transparent padding on all sides. Nothing may touch the top or bottom safe-area edges. If the supplied logo is tall or square, scale the ENTIRE composition down proportionally and add transparent padding rather than cropping any part.',
            'CONTAIN, NEVER COVER: do not enlarge the logo merely to fill the frame, do not trim away required transparent safety padding, and never stretch, distort, crop, or reposition separate pieces in a way that changes the identity. The complete mark must be visible at the cropper default zoom/position.',
            'Transparent background only. No background rectangle/card, no watermark, no decorative scene, no new tagline unless already present in the supplied logo.',
            'Final target: SAME BRAND IDENTITY, BETTER ART DIRECTION, THEME-MATCHED COLOR FAMILY, and a more commissioned/premium finish — including dimensional/3D styling when appropriate.',
        ]));

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('openai.request_timeout', 180))
            ->attach('image[]', $bytes, 'source-logo.'.($extension ?: 'png'), ['Content-Type' => $mime])
            ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/images/edits', [
                'model' => env('OPENAI_LOGO_MODEL', 'gpt-image-1'),
                'prompt' => $prompt,
                'size' => '1024x1024',
                'quality' => env('OPENAI_LOGO_QUALITY', 'low'),
                'background' => 'transparent',
                'n' => 1,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Cosmic AI could not match the logo to the theme right now.');
        }

        $encoded = data_get($response->json(), 'data.0.b64_json');
        $result = is_string($encoded) ? base64_decode($encoded, true) : false;
        if ($result === false || strlen($result) < 100) {
            throw new RuntimeException('Cosmic AI returned an invalid matched logo.');
        }

        return $result;
    }

    private function contextSummary(array $context): string
    {
        $allowed = ['industry', 'audience', 'personality', 'visual_direction', 'logo_direction', 'avoid', 'keywords', 'business_description'];
        $parts = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $context)) {
                continue;
            }
            $value = $context[$key];
            if (is_array($value)) {
                $value = implode(', ', array_slice(array_values(array_filter(array_map('strval', $value))), 0, 12));
            }
            if (! is_scalar($value)) {
                continue;
            }
            $value = $this->clean((string) $value, 450);
            if ($value !== '') {
                $parts[] = str_replace('_', ' ', $key).': '.$value;
            }
        }
        return $this->clean(implode(' | ', $parts), 1600);
    }

    private function clean(?string $value, int $max): string
    {
        $value = preg_replace('/\s+/u', ' ', trim((string) $value)) ?? '';
        return mb_substr($value, 0, $max);
    }

    private function publicDiskPathFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $path = '/'.ltrim($path, '/');
        if (! str_starts_with($path, '/storage/')) {
            throw new RuntimeException('Only Cosmic-hosted logo files can be theme-matched.');
        }

        $relative = ltrim(substr($path, strlen('/storage/')), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            throw new RuntimeException('The current logo path is invalid.');
        }
        return $relative;
    }

    private function normalizeHex(string $hex): string
    {
        $hex = strtoupper(trim($hex));
        if (! preg_match('/^#[0-9A-F]{6}$/', $hex)) {
            throw new RuntimeException('The selected theme does not contain a valid primary color.');
        }
        return $hex;
    }

    private function recolorSvg(string $svg, string $primaryHex, string $secondaryHex, string $tertiaryHex): string
    {
        if (preg_match('/<\s*(?:script|iframe|object|embed|foreignObject)\b|\son\w+\s*=|(?:href|xlink:href)\s*=\s*[\'\"]\s*(?:https?:|javascript:|data:)/i', $svg)) {
            throw new RuntimeException('This SVG contains unsupported active or external content.');
        }

        $index = 0;
        $replace = function (array $match) use ($primaryHex, $secondaryHex, $tertiaryHex, &$index): string {
            $value = strtoupper($match[2]);
            if (in_array($value, ['#FFFFFF', '#FFF', '#000000', '#000'], true)) {
                return $match[0];
            }
            $colors = [$primaryHex, $secondaryHex, $tertiaryHex];
            $new = $colors[$index++ % count($colors)];
            return $match[1].$new.$match[3];
        };

        $svg = preg_replace_callback('/((?:fill|stroke)\s*=\s*[\'\"])(#[0-9a-fA-F]{3,6})([\'\"])/i', $replace, $svg) ?? $svg;
        $svg = preg_replace_callback('/((?:fill|stroke)\s*:\s*)(#[0-9a-fA-F]{3,6})(\s*(?:;|[\'\"]))/i', $replace, $svg) ?? $svg;
        return $svg;
    }
}
