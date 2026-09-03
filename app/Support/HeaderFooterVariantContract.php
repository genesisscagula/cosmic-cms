<?php

namespace App\Support;

/**
 * Canonical website-shell variant contract.
 *
 * Header/footer variants are the single source of truth. Legacy overlay and
 * mega-footer toggles are normalized into the selected variant so Builder,
 * preview, export, API bridge and dynamic storefronts cannot disagree.
 */
final class HeaderFooterVariantContract
{
    public const HEADER_VARIANTS = [
        'classic_header',
        'primary_header',
        'split_navigation_header',
        'centered_header',
        'overlay_hero_header',
        'overlay_centered_header',
        'secondary_header',
    ];

    public const FOOTER_VARIANTS = [
        'classic',
        'primary',
        'centered_cta',
        'centered',
        'split',
        'brand',
        'secondary',
    ];

    public static function normalizeHeader(?array $header): ?array
    {
        if (! is_array($header)) {
            return $header;
        }

        $requested = trim((string) ($header['type'] ?? 'classic_header'));
        $legacyOverlay = (bool) ($header['overlay_header_on_banner'] ?? false);

        // Only legacy aliases inherit the old overlay toggle. Once a website has
        // one of the seven canonical variants, that selected variant wins.
        $aliases = [
            'glassmorphism_header' => $legacyOverlay ? 'overlay_hero_header' : 'classic_header',
            'dark_cyan_header' => $legacyOverlay ? 'overlay_hero_header' : 'classic_header',
            'floating_glass_header' => $legacyOverlay ? 'overlay_hero_header' : 'classic_header',
            'minimal_header' => $legacyOverlay ? 'overlay_hero_header' : 'classic_header',
        ];

        $type = $aliases[$requested] ?? $requested;
        if (! in_array($type, self::HEADER_VARIANTS, true)) {
            $type = 'classic_header';
        }

        $header['type'] = $type;
        $header['overlay_header_on_banner'] = in_array($type, ['overlay_hero_header', 'overlay_centered_header'], true);
        $header['allow_light_logo_filter'] = (bool) ($header['allow_light_logo_filter'] ?? true);

        return $header;
    }

    public static function normalizeFooter(?array $footer): ?array
    {
        if (! is_array($footer)) {
            return $footer;
        }

        $mega = is_array($footer['mega_footer'] ?? null) ? $footer['mega_footer'] : [];
        $requested = trim((string) ($mega['variant'] ?? 'classic'));
        $aliases = [
            'contact' => 'centered_cta',
            'newsletter' => 'centered_cta',
            'cta' => 'centered_cta',
            'detailed' => 'classic',
        ];
        $variant = $aliases[$requested] ?? $requested;
        if (! in_array($variant, self::FOOTER_VARIANTS, true)) {
            $variant = 'classic';
        }

        $background = match ($variant) {
            'primary', 'brand' => 'primary',
            'split' => 'surface',
            'secondary' => 'secondary',
            default => 'white',
        };

        $footer['type'] = 'minimal_footer';
        $footer['mega_enabled'] = true;
        $footer['allow_light_logo_filter'] = (bool) ($footer['allow_light_logo_filter'] ?? true);
        $footer['mega_footer'] = array_merge($mega, [
            'enabled' => true,
            'variant' => $variant,
            // Background/tone belongs to the variant contract. Keeping a stale
            // independent theme here was the main Builder -> Live parity leak.
            'theme' => $background,
        ]);

        return $footer;
    }
}
