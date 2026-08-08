<?php

namespace App\Services;

class MyBrandThemeService
{
    private ?array $families = null;

    public function seedFromFamily(string $familyKey): array
    {
        $families = $this->families();
        $familyKey = isset($families[$familyKey]) ? $familyKey : 'midnight';
        $family = $families[$familyKey] ?? [];
        $palette = is_array($family['palette'] ?? null) ? $family['palette'] : [];

        $background = $this->hex($palette['background'] ?? '#243447', '#243447');
        $surface = $this->hex($palette['surface'] ?? $background, $background);
        $accent = $this->hex($palette['accent'] ?? $surface, $surface);
        $text = $this->hex($palette['text'] ?? '#F8FAFC', '#F8FAFC');

        // The seed is a separate editable copy of the selected preset.
        // Keep all family HEX values exact and derive only fields the family
        // catalog does not explicitly carry.
        $muted = $this->mixHex($text, $background, 0.30);
        $border = $this->mixHex($surface, $text, 0.18);

        return [
            'id' => 'my-brand',
            'name' => 'My Brand Theme',
            'category' => 'Brand',
            'description' => 'Your reusable brand theme. Match it to your logo anytime.',
            'base_family' => $familyKey,
            'seed_source' => 'initial_theme',
            'source_logo_url' => null,
            'palette' => [
                'primary' => $background,
                'secondary' => $surface,
                'tertiary' => $accent,
                'accent' => $accent,
                'background' => $background,
                'surface' => $surface,
                'text' => $text,
                'muted' => $muted,
                'surface_text' => $text,
                'button_text' => '#FFFFFF',
                'border' => $border,
            ],
        ];
    }

    public function ensureInSettings(array $settings, string $fallbackFamily = 'midnight'): array
    {
        if (is_array($settings['custom_brand_theme'] ?? null)) {
            return $settings;
        }

        $familyKey = trim((string) ($settings['primary'] ?? $fallbackFamily));
        $customTheme = $this->seedFromFamily($familyKey);

        $settings['custom_brand_theme'] = $customTheme;
        $settings['brand_palette'] = $customTheme['palette'];
        $settings['brand_source'] = 'initial_theme';

        return $settings;
    }

    private function families(): array
    {
        if ($this->families !== null) {
            return $this->families;
        }

        $path = resource_path('theme/theme-families.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $this->families = is_array($decoded) && is_array($decoded['families'] ?? null)
            ? $decoded['families']
            : [];

        return $this->families;
    }

    private function hex(mixed $value, string $fallback): string
    {
        $value = strtoupper(trim((string) $value));

        return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : strtoupper($fallback);
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

    private function rgb(string $hex): array
    {
        $hex = ltrim($this->hex($hex, '#243447'), '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
