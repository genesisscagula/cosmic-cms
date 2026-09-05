<?php

namespace App\Services;

final class ThemeColorResolver
{
    private const LEGACY_PALETTES = [
        'light' => ['background' => '#FEFEFD', 'surface' => '#F8F8F7', 'accent' => '#4F46E5', 'text' => '#0F172A'],
        'soft' => ['background' => '#F7F7F5', 'surface' => '#FFFFFF', 'accent' => '#78716C', 'text' => '#0F172A'],
        'sky' => ['background' => '#E0F2FE', 'surface' => '#FFFFFF', 'accent' => '#0284C7', 'text' => '#0F172A'],
        'cream' => ['background' => '#FFF7ED', 'surface' => '#FFFFFF', 'accent' => '#C2410C', 'text' => '#0F172A'],
        'slate-light' => ['background' => '#475569', 'surface' => '#64748B', 'accent' => '#CBD5E1', 'text' => '#FFFFFF'],
        'slate-950' => ['background' => '#0F172A', 'surface' => '#1E293B', 'accent' => '#94A3B8', 'text' => '#FFFFFF'],
    ];

    private ?array $families = null;

    public function __construct(private readonly ColorContrastGuard $contrast)
    {
    }

    /**
     * Resolve a named family, editable my-brand palette, or custom HEX into the
     * semantic contract shared by Luna, Builder, preview, export and live.
     * Explicit identity colors stay exact; only absent or unsafe roles derive.
     */
    public function resolve(?string $themeOrHex, array $settings = [], string $fallbackTheme = 'midnight'): array
    {
        $requested = trim((string) $themeOrHex);
        $familyKey = $requested !== '' ? $requested : $fallbackTheme;
        $customHex = $this->contrast->normalizeHex($requested);
        $family = [];
        $source = [];

        if ($customHex !== null) {
            $source = ['primary' => $customHex, 'background' => $customHex];
            $familyKey = 'custom-hex';
        } elseif ($familyKey === 'my-brand') {
            $customTheme = is_array($settings['custom_brand_theme'] ?? null) ? $settings['custom_brand_theme'] : [];
            $source = is_array($settings['brand_palette'] ?? null)
                ? $settings['brand_palette']
                : (is_array($customTheme['palette'] ?? null) ? $customTheme['palette'] : []);
            $baseFamily = trim((string) ($customTheme['base_family'] ?? $settings['base_family'] ?? $fallbackTheme));
            $family = $this->family($baseFamily, $fallbackTheme);
        } else {
            $family = $this->family($familyKey, $fallbackTheme);
            $source = is_array($family['palette'] ?? null) ? $family['palette'] : (self::LEGACY_PALETTES[$familyKey] ?? []);
        }

        $fallbackFamily = $this->family($fallbackTheme, 'midnight');
        $fallbackPalette = is_array($fallbackFamily['palette'] ?? null)
            ? $fallbackFamily['palette']
            : ['background' => '#243447', 'surface' => '#30475E', 'accent' => '#60A5FA', 'text' => '#F8FAFC'];
        $basePalette = is_array($family['palette'] ?? null) ? $family['palette'] : $fallbackPalette;
        // A raw HEX is its own color-family seed. Inheriting the fallback
        // family's secondary/accent/surfaces here made every custom family
        // reuse the same highlighted-card color instead of deriving its own.
        $raw = $customHex !== null ? $source : array_replace($basePalette, $source);
        $rawGradient = is_array($source['gradient'] ?? null)
            ? $source['gradient']
            : (is_array($family['gradient'] ?? null) ? $family['gradient'] : []);

        $primary = $this->hex($raw, ['primary', 'background', 'sourceColor', 'source_color'], '#243447');
        $brandSurface = $this->hex($raw, ['secondary', 'brand_surface', 'brandSurface', 'surface'], $this->contrast->mix($primary, '#FFFFFF', 0.82));
        $secondary = $this->hex($raw, ['secondary'], $brandSurface);
        $accent = $this->hex($raw, ['accent', 'tertiary'], $this->hex($rawGradient, ['glow'], $secondary));
        // Keep the proven color-family contract simple: PRIMARY is the exact
        // brand color and WHITE is always literal white. Only the two light
        // supporting surfaces are derived from PRIMARY so every family gets
        // a matching tint without changing the old primary/white/surface flow.
        $white = '#FFFFFF';
        $contentSurface = $this->contrast->mix($primary, $white, 0.06);
        $surfaceAlt = $this->contrast->mix($primary, $white, 0.12);
        $page = $white;
        $dark = $this->hex($raw, ['dark'], '#0F172A');

        $onPrimary = $this->contrast->readableForeground($primary, $this->nullableHex($raw, ['on_primary', 'onPrimary', 'primary_text', 'primaryText', 'background_text', 'backgroundText', 'button_text', 'buttonText']));
        $onSecondary = $this->contrast->readableForeground($secondary, $this->nullableHex($raw, ['on_secondary', 'onSecondary', 'secondary_text', 'secondaryText']));
        $onAccent = $this->contrast->readableForeground($accent, $this->nullableHex($raw, ['on_accent', 'onAccent', 'accent_text', 'accentText']));
        $onSurface = $this->contrast->readableForeground($contentSurface, $this->nullableHex($raw, ['surface_text', 'surfaceText', 'body', 'text']));
        $heading = $this->contrast->ensureContrast($this->hex($raw, ['heading'], $primary), $contentSurface);
        $body = $this->contrast->ensureContrast($this->hex($raw, ['body', 'surface_text', 'surfaceText'], $onSurface), $contentSurface);
        $muted = $this->contrast->ensureContrast(
            $this->hex($raw, ['muted'], $this->contrast->mix($body, $contentSurface, 0.68)),
            $contentSurface,
        );
        $border = $this->hex($raw, ['border'], $this->contrast->mix($body, $contentSurface, 0.18));
        $borderStrong = $this->hex($raw, ['border_strong', 'borderStrong'], $this->contrast->mix($body, $contentSurface, 0.32));
        $primaryHover = $this->hex($raw, ['primary_hover', 'primaryHover', 'buttonHover'], $this->contrast->mix('#000000', $primary, 0.12));
        $primarySoft = $this->hex($raw, ['primary_soft', 'primarySoft'], $this->contrast->mix($primary, $contentSurface, 0.12));
        $buttonPrimary = $this->hex($raw, ['button_primary', 'buttonPrimary'], $primary);
        $buttonPrimaryText = $this->contrast->readableForeground($buttonPrimary, $this->nullableHex($raw, ['button_text', 'buttonText', 'primary_text', 'primaryText', 'on_primary', 'onPrimary']));
        $buttonSecondary = $this->hex($raw, ['button_secondary', 'buttonSecondary'], $surfaceAlt);
        $buttonSecondaryText = $this->contrast->readableForeground($buttonSecondary, $this->nullableHex($raw, ['button_secondary_text', 'buttonSecondaryText', 'heading']));
        $shellSecondary = $this->hex($raw, ['headerFooterSecondary', 'header_footer_secondary', 'shellSecondary', 'shell_secondary'],
            $this->contrast->luminance($brandSurface) >= 0.72 ? $this->contrast->mix($primary, $white, 0.10) : $brandSurface);
        $shellSecondaryText = $this->contrast->readableForeground($shellSecondary, $this->nullableHex($raw, ['headerFooterSecondaryText', 'header_footer_secondary_text', 'shellSecondaryText', 'shell_secondary_text']));
        $shellSecondaryMuted = $this->contrast->readableMutedForeground($shellSecondary, $this->nullableHex($raw, ['headerFooterSecondaryMuted', 'header_footer_secondary_muted', 'shellSecondaryMuted', 'shell_secondary_muted']) ?? $this->contrast->mix($shellSecondaryText, $shellSecondary, 0.68));
        $shellSecondaryBorder = $this->hex($raw, ['headerFooterSecondaryBorder', 'header_footer_secondary_border', 'shellSecondaryBorder', 'shell_secondary_border'], $this->contrast->mix($shellSecondaryText, $shellSecondary, 0.18));
        // Tone describes the actual shell surface, so derive it from the resolved
        // background rather than trusting stale family metadata.
        $shellSecondaryTone = $this->contrast->luminance($shellSecondary) >= 0.58 ? 'light' : 'dark';

        $gradient = [
            'from' => $this->hex($rawGradient, ['from'], $this->contrast->mix('#000000', $primary, 0.58)),
            'via' => $this->hex($rawGradient, ['via'], $this->contrast->mix('#000000', $primary, 0.40)),
            'to' => $this->hex($rawGradient, ['to'], $this->contrast->mix($accent, $primary, 0.28)),
            'glow' => $this->hex($rawGradient, ['glow'], $accent),
            'angle' => max(0, min(360, (int) ($rawGradient['angle'] ?? 120))),
        ];

        $palette = [
            'family' => $familyKey,
            'source_color' => $this->hex($raw, ['source_color', 'sourceColor'], $primary),
            'primary' => $primary,
            'primary_hover' => $primaryHover,
            'primary_soft' => $primarySoft,
            'secondary' => $secondary,
            'accent' => $accent,
            // Legacy family fields remain exact for existing Spark renderers.
            'background' => $primary,
            'brand_surface' => $brandSurface,
            // Semantic light/surface states are separate from brand surfaces.
            'page' => $page,
            'surface' => $contentSurface,
            'surface_alt' => $surfaceAlt,
            'white' => $white,
            'dark' => $dark,
            'heading' => $heading,
            'body' => $body,
            'text' => $body,
            'muted' => $muted,
            'border' => $border,
            'border_strong' => $borderStrong,
            'on_primary' => $onPrimary,
            'on_secondary' => $onSecondary,
            'on_accent' => $onAccent,
            'on_surface' => $onSurface,
            'on_dark' => $this->contrast->readableForeground($dark, $this->nullableHex($raw, ['on_dark', 'onDark'])),
            'button_primary' => $buttonPrimary,
            'button_text' => $buttonPrimaryText,
            'button_secondary' => $buttonSecondary,
            'button_secondary_text' => $buttonSecondaryText,
            'shell_secondary' => $shellSecondary,
            'shell_secondary_text' => $shellSecondaryText,
            'shell_secondary_muted' => $shellSecondaryMuted,
            'shell_secondary_border' => $shellSecondaryBorder,
            'shell_secondary_tone' => $shellSecondaryTone,
            'success' => $this->hex($raw, ['success'], '#237A57'),
            'warning' => $this->hex($raw, ['warning'], '#A86D22'),
            'error' => $this->hex($raw, ['error'], '#B44949'),
            'gradient' => $gradient,
        ];

        return $this->withCamelAliases($palette);
    }

    public function fromCustomHex(string $hex, array $overrides = [], string $baseFamily = 'midnight'): array
    {
        $resolved = $this->resolve($hex, ['base_family' => $baseFamily], $baseFamily);
        if ($overrides === []) return $resolved;

        return $this->resolve('my-brand', [
            'base_family' => $baseFamily,
            'brand_palette' => array_replace($resolved, $overrides, [
                'primary' => $resolved['primary'],
                'background' => $resolved['primary'],
                'source_color' => $resolved['primary'],
                'sourceColor' => $resolved['primary'],
                'button_primary' => $resolved['primary'],
                'buttonPrimary' => $resolved['primary'],
            ]),
        ], $baseFamily);
    }

    public function primaryHex(?string $themeOrHex, string $fallbackTheme = 'midnight'): string
    {
        return $this->resolve($themeOrHex, [], $fallbackTheme)['primary'];
    }

    public function palette(?string $themeKey, string $fallbackTheme = 'midnight'): array
    {
        return $this->resolve($themeKey, [], $fallbackTheme);
    }

    private function family(string $key, string $fallback): array
    {
        $families = $this->families();
        return is_array($families[$key] ?? null)
            ? $families[$key]
            : (is_array($families[$fallback] ?? null) ? $families[$fallback] : []);
    }

    private function hex(array $source, array $keys, string $fallback): string
    {
        return $this->nullableHex($source, $keys)
            ?? $this->contrast->normalizeHex($fallback, '#243447')
            ?? '#243447';
    }

    private function nullableHex(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            $resolved = $this->contrast->normalizeHex($source[$key] ?? null);
            if ($resolved !== null) return $resolved;
        }
        return null;
    }

    private function withCamelAliases(array $palette): array
    {
        foreach ([
            'sourceColor' => 'source_color', 'primaryHover' => 'primary_hover', 'primarySoft' => 'primary_soft',
            'brandSurface' => 'brand_surface', 'surfaceMuted' => 'surface_alt', 'surfaceText' => 'body',
            'borderStrong' => 'border_strong', 'primaryText' => 'on_primary', 'secondaryText' => 'on_secondary',
            'accentText' => 'on_accent', 'backgroundText' => 'on_primary', 'onPrimary' => 'on_primary', 'onSecondary' => 'on_secondary',
            'onAccent' => 'on_accent', 'onSurface' => 'on_surface', 'onDark' => 'on_dark',
            'buttonPrimary' => 'button_primary', 'buttonText' => 'button_text',
            'buttonSecondary' => 'button_secondary', 'buttonSecondaryText' => 'button_secondary_text',
            'shellSecondary' => 'shell_secondary', 'shellSecondaryText' => 'shell_secondary_text',
            'shellSecondaryMuted' => 'shell_secondary_muted', 'shellSecondaryBorder' => 'shell_secondary_border',
            'shellSecondaryTone' => 'shell_secondary_tone',
        ] as $alias => $source) {
            $palette[$alias] = $palette[$source];
        }
        return $palette;
    }

    private function families(): array
    {
        if ($this->families !== null) return $this->families;
        $path = resource_path('theme/theme-families.json');
        $decoded = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $this->families = is_array($decoded) && is_array($decoded['families'] ?? null) ? $decoded['families'] : [];
        return $this->families;
    }
}
