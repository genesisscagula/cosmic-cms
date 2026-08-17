<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LogoThemeAnalysisService
{
    public function __construct(private readonly SvgUploadSanitizer $svgSanitizer)
    {
    }

    public function analyze(string $logoUrl, ?array $allowedFamilyKeys = null): array
    {
        [$path, $extension] = $this->resolveLogo($logoUrl);
        $bytes = Storage::disk('public')->get($path);

        $palette = $extension === 'svg'
            ? $this->analyzeSvg($bytes)
            : $this->analyzeRaster($bytes, $this->mimeFor($extension));

        $palette = $this->normalizePalette($palette);
        $family = $this->nearestThemeFamily($palette['primary'], $allowedFamilyKeys);

        return [
            'palette' => $palette,
            'custom_theme' => [
                'id' => 'my-brand',
                'name' => 'My Brand Theme',
                'category' => 'Brand',
                'description' => 'A custom Cosmic color family generated from your logo.',
                'base_family' => $family['key'],
                'palette' => [
                    'primary' => $palette['primary'],
                    'secondary' => $palette['secondary'],
                    'accent' => $palette['accent'],
                    'background' => $palette['background'],
                    'surface' => $palette['surface'],
                    'text' => $palette['text'],
                    'muted' => $palette['muted'],
                    'border' => $palette['border'],
                ],
            ],
            'recommended_family' => $family['key'],
            'recommended_family_name' => $family['name'],
            'recommended_family_palette' => $family['palette'],
            'reason' => $palette['reason'] ?? 'Cosmic AI built a custom brand theme from the logo colors.',
        ];
    }

    private function analyzeRaster(string $bytes, string $mime): array
    {
        $apiKey = (string) config('openai.api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Logo color analysis is not configured.');
        }

        $dataUri = 'data:'.$mime.';base64,'.base64_encode($bytes);
        $prompt = implode("\n", [
            'Analyze this company logo and return only a JSON object.',
            'Identify meaningful brand colors, not incidental pixels.',
            'PRIMARY EXTRACTION IS STRICT: choose one prominent meaningful brand color that visibly exists in the logo and return its exact sampled six-digit HEX value. Do not approximate, beautify, darken, lighten, mute, normalize, hue-shift, or replace it.',
            'Prefer the darkest prominent meaningful chromatic brand color when multiple real brand colors are present, but the returned primary must still be an exact color visibly present in the logo.',
            'Ignore transparent areas, white backgrounds, neutral black/gray outlines, shadows, anti-aliasing pixels, and tiny decorative colors unless they are clearly part of the brand identity.',
            'Choose secondary and accent from real meaningful logo colors where possible. Do not let either override the primary identity.',
            'MANDATORY WEBSITE RULE: set background equal to the exact primary HEX. The custom website theme must visually read as the same PRIMARY-colored brand first.',
            'READABILITY OVERRIDES DECORATIVE COLOR CHOICES: never use a foreground/text color that becomes low-contrast against its section or card background.',
            'On dark or saturated backgrounds, headings and primary text must be white or near-white; paragraphs, labels, nav text, helper text, and secondary text must use a high-contrast light neutral.',
            'On light backgrounds, headings and primary text must use a dark neutral; muted text must remain clearly readable.',
            'Never reuse the PRIMARY brand HEX as body text, muted text, navigation text, or labels if that reduces contrast.',
            'Solid primary buttons must use white text/icons. Nested cards and dashboard mockups must choose readable foregrounds against their own card/surface background, not only the parent section background.',
            'Target WCAG AA contrast where practical. Brand matching controls palette identity; accessibility/readability controls foreground colors.',
            'Build surface, text, muted, and border around that exact primary without changing the primary/background HEX itself.',
            'Return six-digit HEX values only. primary and background must be exactly identical HEX strings.',
            'Schema: {"primary":"#RRGGBB","secondary":"#RRGGBB","accent":"#RRGGBB","background":"#RRGGBB","surface":"#RRGGBB","text":"#RRGGBB","muted":"#RRGGBB","border":"#RRGGBB","reason":"short explanation"}',
        ]);

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->timeout((int) config('openai.request_timeout', 120))
            ->post(rtrim((string) (config('openai.base_uri') ?: 'https://api.openai.com/v1'), '/').'/chat/completions', [
                'model' => env('OPENAI_VISION_MODEL', 'gpt-4.1-mini'),
                'temperature' => 0.1,
                'response_format' => ['type' => 'json_object'],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri, 'detail' => 'low']],
                    ],
                ]],
            ]);

        if ($response->failed()) {
            report(new RuntimeException('Logo theme analysis failed: '.$response->body()));
            throw new RuntimeException('Cosmic AI could not analyze the logo colors right now.');
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;
        if (! is_array($decoded)) {
            throw new RuntimeException('Cosmic AI returned an invalid logo color analysis.');
        }

        return $decoded;
    }

    private function analyzeSvg(string $svg): array
    {
        // Use the same sanitizer as upload/storage so a logo accepted by the
        // Media Library cannot later fail merely because this analyzer used a
        // second, stricter SVG policy.
        $svg = $this->svgSanitizer->sanitize($svg);

        preg_match_all('/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/', $svg, $matches);
        $colors = collect($matches[0] ?? [])
            ->map(fn ($hex) => $this->expandHex($hex))
            ->filter(fn ($hex) => ! $this->isNeutralOrWhite($hex))
            ->countBy()
            ->sortDesc();

        if ($colors->isEmpty()) {
            return [
                'primary' => '#1E293B',
                'secondary' => '#475569',
                'accent' => '#10B981',
                'background' => '#1E293B',
                'surface' => '#334155',
                'text' => '#F8FAFC',
                'muted' => '#CBD5E1',
                'border' => '#475569',
                'reason' => 'The SVG did not expose usable brand fills, so a safe neutral palette was selected.',
            ];
        }

        $ranked = $colors->keys()->sortBy(fn ($hex) => $this->luminance($hex))->values();
        $primary = $ranked->first();
        $secondary = $ranked->get(1, $primary);
        $accent = $ranked->sortByDesc(fn ($hex) => $this->saturation($hex))->first() ?: $secondary;

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
            'background' => $primary,
            'surface' => $this->mixHex($primary, '#FFFFFF', 0.14),
            'text' => '#FFFFFF',
            'muted' => '#E2E8F0',
            'border' => $this->mixHex($primary, '#FFFFFF', 0.28),
            'reason' => 'Colors were extracted directly from the SVG, prioritizing the darkest meaningful non-neutral brand color.',
        ];
    }

    private function normalizePalette(array $palette): array
    {
        $primary = $this->normalizeHex((string) ($palette['primary'] ?? '#1E293B'));
        $secondary = $this->normalizeHex((string) ($palette['secondary'] ?? $primary));
        $accent = $this->normalizeHex((string) ($palette['accent'] ?? $secondary));

        // My Brand Theme must preserve the exact detected logo primary.
        // Never allow the model to silently shift the website background away
        // from the brand's primary HEX.
        $background = $primary;
        $surface = $this->normalizeHex((string) ($palette['surface'] ?? $this->mixHex($background, '#FFFFFF', 0.14)));

        // Deterministic readability engine. The model may suggest foregrounds,
        // but low-contrast values are never allowed to become the saved theme.
        $preferredText = $this->normalizeHex((string) ($palette['text'] ?? '#FFFFFF'));
        $preferredMuted = $this->normalizeHex((string) ($palette['muted'] ?? '#CBD5E1'));

        $text = $this->ensureReadableForeground(
            $background,
            $preferredText,
            '#FFFFFF',
            '#0F172A',
            4.5
        );

        $muted = $this->ensureReadableForeground(
            $background,
            $preferredMuted,
            '#CBD5E1',
            '#475569',
            3.2
        );

        $surfaceText = $this->ensureReadableForeground(
            $surface,
            $text,
            '#FFFFFF',
            '#0F172A',
            4.5
        );

        // If the surface requires the opposite foreground from the section,
        // store an explicit surface_text token for nested cards/dashboard UI.
        $border = $this->normalizeHex((string) ($palette['border'] ?? $this->mixHex($surface, $surfaceText, 0.18)));

        return [
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
            'background' => $background,
            'surface' => $surface,
            'text' => $text,
            'muted' => $muted,
            'surface_text' => $surfaceText,
            'button_text' => '#FFFFFF',
            'border' => $border,
            'reason' => trim((string) ($palette['reason'] ?? '')),
        ];
    }

    private function ensureReadableForeground(
        string $background,
        string $candidate,
        string $lightFallback,
        string $darkFallback,
        float $minimumRatio
    ): string {
        $background = $this->normalizeHex($background);
        $candidate = $this->normalizeHex($candidate);
        $lightFallback = $this->normalizeHex($lightFallback);
        $darkFallback = $this->normalizeHex($darkFallback);

        if ($this->contrastRatio($background, $candidate) >= $minimumRatio) {
            return $candidate;
        }

        $lightRatio = $this->contrastRatio($background, $lightFallback);
        $darkRatio = $this->contrastRatio($background, $darkFallback);

        return $lightRatio >= $darkRatio ? $lightFallback : $darkFallback;
    }

    private function contrastRatio(string $firstHex, string $secondHex): float
    {
        $first = $this->relativeLuminance($firstHex);
        $second = $this->relativeLuminance($secondHex);

        $lighter = max($first, $second);
        $darker = min($first, $second);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function relativeLuminance(string $hex): float
    {
        [$r, $g, $b] = $this->rgb($this->normalizeHex($hex));

        $channels = array_map(static function (int $value): float {
            $channel = $value / 255;

            return $channel <= 0.03928
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4;
        }, [$r, $g, $b]);

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }

    private function nearestThemeFamily(string $primaryHex, ?array $allowedFamilyKeys = null): array
    {
        $catalog = json_decode(file_get_contents(resource_path('theme/theme-families.json')), true);
        $families = is_array($catalog) ? ($catalog['families'] ?? []) : [];
        $compilerIds = is_array($catalog) ? ($catalog['compilerThemeIds'] ?? []) : [];
        $skip = ['white', 'stone'];

        $target = $this->rgb($primaryHex);
        $bestKey = 'midnight';
        $bestDistance = PHP_FLOAT_MAX;

        foreach ($families as $key => $family) {
            if (! in_array($key, $compilerIds, true) || in_array($key, $skip, true)) {
                continue;
            }
            if (is_array($allowedFamilyKeys) && ! in_array($key, $allowedFamilyKeys, true)) {
                continue;
            }
            $candidateHex = (string) data_get($family, 'palette.background', '');
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $candidateHex)) {
                continue;
            }
            $rgb = $this->rgb($candidateHex);
            $distance = (($target[0] - $rgb[0]) ** 2) + (($target[1] - $rgb[1]) ** 2) + (($target[2] - $rgb[2]) ** 2);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestKey = $key;
            }
        }

        $family = $families[$bestKey] ?? $families['midnight'] ?? [];
        return [
            'key' => $bestKey,
            'name' => (string) ($family['name'] ?? ucfirst($bestKey)),
            'palette' => $family['palette'] ?? [],
        ];
    }

    private function resolveLogo(string $logoUrl): array
    {
        $urlPath = parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl;
        $urlPath = '/'.ltrim($urlPath, '/');
        if (! str_starts_with($urlPath, '/storage/')) {
            throw new RuntimeException('Only Cosmic-hosted logo files can be analyzed.');
        }

        $relative = ltrim(substr($urlPath, strlen('/storage/')), '/');
        if ($relative === '' || str_contains($relative, '..') || ! Storage::disk('public')->exists($relative)) {
            throw new RuntimeException('The current logo file could not be found. Upload or regenerate it first.');
        }

        return [$relative, strtolower(pathinfo($relative, PATHINFO_EXTENSION))];
    }

    private function mimeFor(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'image/png',
        };
    }

    private function normalizeHex(string $hex): string
    {
        $hex = strtoupper(trim($hex));
        if (preg_match('/^#[0-9A-F]{3}$/', $hex)) {
            return $this->expandHex($hex);
        }
        return preg_match('/^#[0-9A-F]{6}$/', $hex) ? $hex : '#1E293B';
    }

    private function expandHex(string $hex): string
    {
        $hex = strtoupper($hex);
        if (strlen($hex) === 4) {
            return '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
        }
        return $hex;
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($this->normalizeHex($hex), '#');
        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    private function luminance(string $hex): float
    {
        [$r, $g, $b] = $this->rgb($hex);
        return (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);
    }

    private function saturation(string $hex): float
    {
        [$r, $g, $b] = $this->rgb($hex);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        return $max === 0 ? 0 : ($max - $min) / $max;
    }

    private function mixHex(string $baseHex, string $mixHex, float $mixWeight): string
    {
        [$br, $bg, $bb] = $this->rgb($baseHex);
        [$mr, $mg, $mb] = $this->rgb($mixHex);
        $w = max(0.0, min(1.0, $mixWeight));

        return sprintf(
            '#%02X%02X%02X',
            (int) round(($br * (1 - $w)) + ($mr * $w)),
            (int) round(($bg * (1 - $w)) + ($mg * $w)),
            (int) round(($bb * (1 - $w)) + ($mb * $w)),
        );
    }

    private function isNeutralOrWhite(string $hex): bool
    {
        [$r, $g, $b] = $this->rgb($hex);
        $spread = max($r, $g, $b) - min($r, $g, $b);
        return ($r > 238 && $g > 238 && $b > 238) || ($spread < 12 && max($r, $g, $b) < 60);
    }
}
