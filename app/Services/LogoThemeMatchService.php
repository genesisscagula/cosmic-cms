<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\MediaAsset;
use RuntimeException;
use Illuminate\Support\Str;

class LogoThemeMatchService
{
    public function __construct(private readonly SvgUploadSanitizer $svgSanitizer)
    {
    }

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
            'BRAND-SAFE LUNA REDESIGN of the supplied existing logo for the website theme. Preserve the same unmistakable brand identity while making a polished, restrained redesign that feels intentionally created for this color system.',
            $companyName !== '' ? "Company/brand name: {$companyName}." : null,
            "Industry: {$industry}.",
            'Theme: '.($themeName !== '' ? $themeName : 'current website theme').'.',
            "FINAL THEME DOMINANT COLOR — EXACT: {$primaryHex}.",
            "FINAL THEME ACCENT COLOR — EXACT: {$secondaryHex}.",
            "FINAL THEME SURFACE/NEUTRAL — EXACT: {$tertiaryHex}.",
            'STRICT COLOR RULE: use only the supplied final theme colors above, plus transparent pixels. Do not introduce unrelated blue, teal, purple, orange, red, green, gray, white highlights, metallic tints, or industry-default colors unless that exact color is one of the supplied palette values.',
            'Do not reinterpret the palette from the industry. For example, a dental logo must not become blue merely because blue is common in dentistry.',
            $brandPrompt !== '' ? "Original website/brand brief: {$brandPrompt}" : null,
            $brandContext !== '' ? "Saved brand context: {$brandContext}" : null,
            $latestPrompt !== '' && $latestPrompt !== $brandPrompt ? "Recent brand direction, only if relevant: {$latestPrompt}" : null,
            'IDENTITY FIRST: preserve the exact company/wordmark text, symbol concept, recognizable silhouette, symbol-to-wordmark relationship, and overall identity. Do not invent a different business name, slogan, mascot, icon, or unrelated symbol.',
            'REDESIGN LIMIT: you may refine spacing, balance, line weight, proportions, typography treatment, and internal color hierarchy, but the original mark must remain recognizable at first glance. Do not replace the core symbol, rename the brand, or invent a different visual identity.',
            'STYLE DISCIPLINE: keep the redesign clean and logo-like. Do not add mockup scenes, photographic backgrounds, unrelated mascots, or excessive 3D/decorative effects. Any stylistic polish must serve the existing identity and the active theme.',
            'If the source logo already contains gradients, shadows, highlights, or depth, preserve the character of those effects but remap them strictly within the supplied theme palette. Any interpolation must stay between the supplied palette colors only.',
            "PALETTE BALANCE: keep {$primaryHex} as the dominant family anchor (roughly 60–80% of visible colored area), but intentionally use {$secondaryHex} and/or {$tertiaryHex} for up to roughly 20–40% combined when the source logo has multiple logical parts and the extra color improves hierarchy, legibility, or polish. Suitable uses include icon sub-parts, selected lettering, dividers, small highlights, or secondary marks. Stay inside this exact color family; never invent a fourth hue.",
            'Do not force a multicolor result when it would make the logo worse. If the source is intentionally minimal or one-color, a one-color adaptation is acceptable. If the source clearly contains multiple components, prefer a tasteful 2–3 color treatment rather than flattening every component into the dominant color.',
            "Use {$primaryHex} as the dominant anchor, {$secondaryHex} as the intentional accent, and {$tertiaryHex} as a restrained supporting/surface color. Preserve semantic roles unless contrast or source-logo structure requires a small adjustment.",
            'All visible icon and wordmark pixels must remain crisp, fully opaque where the original artwork is opaque, and high-contrast on a white/light website header. Do not wash out the company name or reduce wordmark opacity.',
            "Prefer the source logo's existing horizontal arrangement. Rebalance only enough to make the complete mark comfortable in a website navbar.",
            'HEADER CROPPER TARGET IS MANDATORY: fit the complete visible logo safely inside a 650 × 200 pixel website-header frame (3.25:1). Preserve every existing wordmark line, icon, tagline, shadow, glow, highlight, and intentional decorative element.',
            'SAFE AREA: keep approximately 10–15% transparent padding on all sides. Nothing may touch the top or bottom safe-area edges. If the supplied logo is tall or square, scale the ENTIRE composition down proportionally and add transparent padding rather than cropping any part.',
            'CONTAIN, NEVER COVER: do not enlarge the logo merely to fill the frame. Never stretch, distort, crop, or separate/reposition pieces in a way that changes the identity. The complete mark must be visible at the cropper default zoom/position.',
            'Transparent background only. No background rectangle/card, no watermark, no decorative scene, and no new tagline.',
            'Final target: SAME BRAND IDENTITY, THOUGHTFULLY REDESIGNED FOR THE EXACT WEBSITE COLOR FAMILY, WITH A PREMIUM HEADER FIT. It should feel like a professional evolution of the original logo, not a different company.',
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
        $path = rawurldecode((string) $path);
        $path = '/'.ltrim($path, '/');

        // Normal public-disk URL.
        if (str_starts_with($path, '/storage/')) {
            $relative = ltrim(substr($path, strlen('/storage/')), '/');
            if ($relative === '' || str_contains($relative, '..')) {
                throw new RuntimeException('The current logo path is invalid.');
            }
            return $relative;
        }

        // Media Library deliberately serves protected assets through a route
        // instead of /storage. Resolve that route back to its Cosmic-owned
        // public-disk file so Match Logo to Theme works with the new single
        // Replace Logo -> Media Library flow.
        // Current Media Library delivery URLs use /media-library/files/{uuid}.
        // Keep the older /assets/{uuid} shape as a compatibility fallback because
        // saved theme/logo settings can outlive route refactors. Resolve either URL
        // directly back to the website-owned MediaAsset instead of attempting an
        // HTTP import (which correctly rejects localhost/private hosts).
        if (preg_match('#^/websites/(\d+)/media-library/(?:files|assets)/([0-9a-fA-F-]{36})(?:/|$)#', $path, $matches)) {
            $asset = MediaAsset::query()
                ->where('website_id', (int) $matches[1])
                ->where('uuid', $matches[2])
                ->first();

            if ($asset && $asset->disk === 'public' && $asset->path) {
                $assetPath = ltrim((string) $asset->path, '/');
                if ($assetPath !== '' && ! str_contains($assetPath, '..') && Storage::disk('public')->exists($assetPath)) {
                    return $assetPath;
                }
            }
        }

        // Legacy/external logo: import a safe public image into Cosmic storage
        // first. This keeps storage details invisible to the user and avoids the
        // old "Only Cosmic-hosted" dead end. Private/local network targets are
        // rejected to prevent this convenience import becoming an SSRF path.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new RuntimeException('The current logo could not be imported. Replace the logo and try again.');
        }
        $ips = @gethostbynamel($host) ?: [];
        if (! $ips || collect($ips)->contains(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            throw new RuntimeException('The current logo could not be imported safely. Replace the logo and try again.');
        }

        $response = Http::timeout(20)->withOptions(['allow_redirects' => ['max' => 3]])->get($url);
        if ($response->failed() || strlen($response->body()) < 100 || strlen($response->body()) > 5 * 1024 * 1024) {
            throw new RuntimeException('The current logo could not be imported. Replace the logo and try again.');
        }
        $mime = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));
        $extension = match ($mime) {
            'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg',
            default => null,
        };
        if (! $extension) {
            throw new RuntimeException('The current logo is not a supported image. Replace the logo and try again.');
        }
        $relative = 'logos/imported/'.Str::lower(Str::random(24)).'.'.$extension;
        Storage::disk('public')->put($relative, $response->body());
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
        // Keep logo matching in lockstep with Media Library/logo uploads.
        // Valid static SVGs are sanitized once, while executable/external
        // content remains blocked by the shared sanitizer.
        $svg = $this->svgSanitizer->sanitize($svg);

        $index = 0;
        $replace = function (array $match) use ($primaryHex, $secondaryHex, $tertiaryHex, &$index): string {
            $value = strtoupper($match[2]);

            // White is commonly a deliberate knockout/highlight in vector logos,
            // so keep it intact. Black / near-black is different: most uploaded
            // wordmarks and icons use black as the editable brand ink. The old
            // matcher preserved black verbatim, which made every newly-added
            // theme appear as a black logo even though the selected palette was
            // correct. Map dark ink to the active theme primary instead.
            if (in_array($value, ['#FFFFFF', '#FFF'], true)) {
                return $match[0];
            }

            if (in_array($value, ['#000000', '#000', '#111111', '#111', '#0F0F0F', '#101010', '#1A1A1A'], true)) {
                return $match[1].$primaryHex.$match[3];
            }

            // Existing colored SVG parts are distributed through the selected
            // theme family only. This keeps vector output crisp while allowing
            // tasteful 2–3 color treatment without inventing unrelated hues.
            $colors = [$primaryHex, $secondaryHex, $tertiaryHex];
            $new = $colors[$index++ % count($colors)];
            return $match[1].$new.$match[3];
        };

        $svg = preg_replace_callback('/((?:fill|stroke)\s*=\s*[\'\"])(#[0-9a-fA-F]{3,6})([\'\"])/i', $replace, $svg) ?? $svg;
        $svg = preg_replace_callback('/((?:fill|stroke)\s*:\s*)(#[0-9a-fA-F]{3,6})(\s*(?:;|[\'\"]))/i', $replace, $svg) ?? $svg;
        return $svg;
    }
}
