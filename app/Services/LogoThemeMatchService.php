<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class LogoThemeMatchService
{
    public function match(string $logoUrl, string $primaryHex, string $accentHex = '', string $themeName = ''): array
    {
        $primaryHex = $this->normalizeHex($primaryHex);
        $accentHex = $accentHex !== '' ? $this->normalizeHex($accentHex) : $primaryHex;
        $path = $this->publicDiskPathFromUrl($logoUrl);

        if (! Storage::disk('public')->exists($path)) {
            throw new RuntimeException('The current logo file could not be found. Upload or regenerate the logo first.');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension === 'svg') {
            $source = Storage::disk('public')->get($path);
            $result = $this->recolorSvg($source, $primaryHex, $accentHex);
            return ['bytes' => $result, 'extension' => 'svg', 'mime' => 'image/svg+xml', 'ai' => false];
        }

        $mime = match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
        $bytes = Storage::disk('public')->get($path);
        $edited = $this->editRasterWithLuna($bytes, $mime, $extension ?: 'png', $primaryHex, $accentHex, $themeName);

        return ['bytes' => $edited, 'extension' => 'png', 'mime' => 'image/png', 'ai' => true];
    }

    private function editRasterWithLuna(string $bytes, string $mime, string $extension, string $primaryHex, string $accentHex, string $themeName): string
    {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('AI logo matching is not configured.');
        }

        $prompt = implode("\n", [
            'Edit this existing logo to match the supplied website theme while preserving the brand identity.',
            'Do NOT redesign the logo. Preserve the same symbol, wordmark wording, typography feel, proportions, layout, spacing, and recognizable identity.',
            "Theme: ".($themeName !== '' ? $themeName : 'current website theme').'.',
            "EXACT primary brand HEX: {$primaryHex}.",
            "Accent color: {$accentHex}.",
            'The supplied primary HEX must be reproduced exactly as the dominant chromatic color. Do not lighten, darken, desaturate, hue-shift, or replace it with an approximate color.',
            'Adapt only the logo colors and minor contrast treatment needed to harmonize with the theme.',
            'Keep a transparent background. Do not add a card, mockup, scene, slogan, watermark, border, or new graphic elements.',
            'Keep the result clean and suitable for a compact website header on a wide 650 by 150 pixel canvas.',
        ]);

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

    private function recolorSvg(string $svg, string $primaryHex, string $accentHex): string
    {
        if (preg_match('/<\s*(?:script|iframe|object|embed|foreignObject)\b|\son\w+\s*=|(?:href|xlink:href)\s*=\s*[\'\"]\s*(?:https?:|javascript:|data:)/i', $svg)) {
            throw new RuntimeException('This SVG contains unsupported active or external content.');
        }

        $index = 0;
        $replace = function (array $match) use ($primaryHex, $accentHex, &$index): string {
            $value = strtoupper($match[2]);
            if (in_array($value, ['#FFFFFF', '#FFF', '#000000', '#000'], true)) {
                return $match[0];
            }
            $new = ($index++ % 3 === 2) ? $accentHex : $primaryHex;
            return $match[1].$new.$match[3];
        };

        $svg = preg_replace_callback('/((?:fill|stroke)\s*=\s*[\'\"])(#[0-9a-fA-F]{3,6})([\'\"])/i', $replace, $svg) ?? $svg;
        $svg = preg_replace_callback('/((?:fill|stroke)\s*:\s*)(#[0-9a-fA-F]{3,6})(\s*(?:;|[\'\"]))/i', $replace, $svg) ?? $svg;
        return $svg;
    }
}
