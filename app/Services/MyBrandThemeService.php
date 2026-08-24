<?php

namespace App\Services;

final class MyBrandThemeService
{
    public function __construct(private readonly ThemeColorResolver $colors)
    {
    }

    public function seedFromFamily(string $familyKey): array
    {
        $resolved = $this->colors->resolve($familyKey, [], 'midnight');
        $familyKey = $resolved['family'] === 'custom-hex' ? 'midnight' : $resolved['family'];

        return [
            'id' => 'my-brand',
            'name' => 'My Brand Theme',
            'category' => 'Brand',
            'description' => 'Your reusable brand theme. Match it to your logo anytime.',
            'base_family' => $familyKey,
            'seed_source' => 'initial_theme',
            'source_logo_url' => null,
            // Preserve the legacy family fields while storing the complete
            // semantic contract so every renderer can resolve the same roles.
            'palette' => [
                ...$resolved,
                'primary' => $resolved['primary'],
                'secondary' => $resolved['brand_surface'],
                'tertiary' => $resolved['accent'],
                'accent' => $resolved['accent'],
                'background' => $resolved['primary'],
                'surface' => $resolved['brand_surface'],
                'content_surface' => $resolved['surface'],
                'text' => $resolved['on_primary'],
                'surface_text' => $resolved['body'],
                'button_text' => $resolved['button_text'],
            ],
        ];
    }

    public function ensureInSettings(array $settings, string $fallbackFamily = 'midnight'): array
    {
        if (is_array($settings['custom_brand_theme'] ?? null)) return $settings;

        $familyKey = trim((string) ($settings['primary'] ?? $fallbackFamily));
        $customTheme = $this->seedFromFamily($familyKey);
        $settings['custom_brand_theme'] = $customTheme;
        $settings['brand_palette'] = $customTheme['palette'];
        $settings['brand_source'] = 'initial_theme';

        return $settings;
    }
}
