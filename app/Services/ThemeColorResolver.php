<?php

namespace App\Services;

class ThemeColorResolver
{
    private ?array $families = null;

    public function primaryHex(?string $themeOrHex, string $fallbackTheme = 'midnight'): string
    {
        $value = trim((string) $themeOrHex);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return strtoupper($value);
        }

        $families = $this->families();
        $themeKey = $value !== '' ? $value : $fallbackTheme;
        $hex = data_get($families, $themeKey.'.palette.background');

        if (! is_string($hex) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            $hex = data_get($families, $fallbackTheme.'.palette.background', '#243447');
        }

        return strtoupper((string) $hex);
    }

    public function palette(?string $themeKey, string $fallbackTheme = 'midnight'): array
    {
        $families = $this->families();
        $key = trim((string) $themeKey) ?: $fallbackTheme;
        $palette = data_get($families, $key.'.palette');
        if (! is_array($palette)) {
            $palette = data_get($families, $fallbackTheme.'.palette', []);
        }

        return [
            'primary' => strtoupper((string) ($palette['background'] ?? '#243447')),
            'accent' => strtoupper((string) ($palette['accent'] ?? '#60A5FA')),
            'surface' => strtoupper((string) ($palette['surface'] ?? '#30475E')),
            'text' => strtoupper((string) ($palette['text'] ?? '#F8FAFC')),
        ];
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
}
