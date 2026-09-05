<?php

namespace App\Support;

/**
 * Canonical website-shell variant contract.
 *
 * Header/footer variants are the single source of truth. Legacy overlay and
 * mega-footer toggles are normalized into the selected variant so Builder,
 * preview, export, API bridge and dynamic storefronts cannot disagree.
 *
 * Luna also reads this contract through the deterministic detector helpers.
 * Keep the labels/aliases aligned with the Builder variant cards so natural
 * language commands and manual selection resolve to the exact same IDs.
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

    public const HEADER_VARIANT_META = [
        'classic_header' => [
            'name' => 'White + Primary CTA',
            'description' => 'White header, original/colored logo and a primary CTA.',
        ],
        'primary_header' => [
            'name' => 'Primary Contrast',
            'description' => 'Primary background, light logo/navigation and a white CTA.',
        ],
        'split_navigation_header' => [
            'name' => 'Center Logo + CTA',
            'description' => 'Centered logo with navigation split left/right and a CTA.',
        ],
        'centered_header' => [
            'name' => 'Center Logo',
            'description' => 'Centered logo with navigation split left/right and no CTA.',
        ],
        'overlay_hero_header' => [
            'name' => 'Overlay Hero',
            'description' => 'Transparent overlay with a light logo and navigation over the hero.',
        ],
        'overlay_centered_header' => [
            'name' => 'Overlay Center Logo',
            'description' => 'Transparent hero overlay with centered light logo and split navigation.',
        ],
        'secondary_header' => [
            'name' => 'Secondary Surface',
            'description' => 'Theme-matched secondary shell, light or dark depending on the color family.',
        ],
    ];

    public const FOOTER_VARIANT_META = [
        'classic' => [
            'name' => 'White Mega',
            'description' => 'Clean white mega footer with brand, CTA and multi-column navigation.',
        ],
        'primary' => [
            'name' => 'Primary Mega',
            'description' => 'Primary color footer with light branding and a white conversion CTA.',
        ],
        'centered_cta' => [
            'name' => 'Centered + CTA',
            'description' => 'Centered brand statement and CTA with navigation columns below.',
        ],
        'centered' => [
            'name' => 'Centered Minimal',
            'description' => 'Centered brand and navigation without a footer CTA.',
        ],
        'split' => [
            'name' => 'Split Editorial',
            'description' => 'Editorial split between brand/contact details and navigation.',
        ],
        'brand' => [
            'name' => 'Primary Brand',
            'description' => 'Large brand-led composition on the primary color family.',
        ],
        'secondary' => [
            'name' => 'Secondary Surface',
            'description' => 'Theme-matched secondary background that can resolve light or dark.',
        ],
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

    /**
     * Deterministic Luna vocabulary for the seven header cards.
     * Returns null for generic/negative requests that should be handled by a
     * different bounded action (for example "turn off overlay header").
     */
    public static function detectHeaderVariant(string $prompt): ?string
    {
        $text = trim($prompt);
        if ($text === '') return null;

        // Negative overlay commands belong to overlay_header, not variant choice.
        if (preg_match('/\b(?:disable|remove|turn\s+off|switch\s+off|no)\b.{0,24}\b(?:overlay|transparent)\b|\b(?:overlay|transparent)\b.{0,24}\b(?:off|disabled|removed)\b/i', $text)) {
            return null;
        }

        // Most specific phrases first.
        if (preg_match('/\b(?:overlay|transparent)\b.{0,32}\b(?:center(?:ed)?|centre(?:d)?|center\s+logo)\b|\b(?:center(?:ed)?|centre(?:d)?|center\s+logo)\b.{0,32}\b(?:overlay|transparent)\b/i', $text)) {
            return 'overlay_centered_header';
        }
        if (preg_match('/\b(?:overlay(?:\s+hero)?|transparent)\s+header\b|\bheader\b.{0,24}\b(?:over|overlay|transparent)\b.{0,20}\b(?:hero|banner)?\b|\bheader\s+(?:over|on)\s+(?:the\s+)?(?:hero|banner)\b/i', $text)) {
            return 'overlay_hero_header';
        }
        if (preg_match('/\b(?:center(?:ed)?|centre(?:d)?)\s+(?:logo\s+)?(?:header\s+)?(?:with|\+)\s+(?:a\s+)?cta\b|\bsplit\s+(?:navigation|nav)\b.{0,24}\bcta\b/i', $text)) {
            return 'split_navigation_header';
        }
        if (preg_match('/\bsplit\s+(?:navigation|nav)(?:\s+header)?\b/i', $text)) {
            return 'split_navigation_header';
        }
        if (preg_match('/\b(?:center(?:ed)?|centre(?:d)?)\s+(?:logo\s+)?header\b.{0,24}\b(?:without|no)\s+cta\b|\bcenter\s+logo\b.{0,24}\b(?:without|no)\s+cta\b/i', $text)) {
            return 'centered_header';
        }
        if (preg_match('/\b(?:center(?:ed)?|centre(?:d)?)\s+(?:logo\s+)?header\b|\bcenter\s+logo\b/i', $text)
            && ! preg_match('/\bcta\b/i', $text)) {
            return 'centered_header';
        }
        if (preg_match('/\bprimary(?:\s+(?:contrast|color|colour|background))?\s+header\b|\bheader\b.{0,20}\bprimary\b/i', $text)) {
            return 'primary_header';
        }
        if (preg_match('/\bsecondary(?:\s+(?:surface|background))?\s+header\b|\bheader\b.{0,20}\bsecondary\b/i', $text)) {
            return 'secondary_header';
        }
        if (preg_match('/\b(?:classic|white|default)\s+header\b|\bheader\b.{0,20}\b(?:classic|white|default)\b/i', $text)) {
            return 'classic_header';
        }

        return null;
    }

    /** Deterministic Luna vocabulary for the seven footer cards. */
    public static function detectFooterVariant(string $prompt): ?string
    {
        $text = trim($prompt);
        if ($text === '') return null;

        if (preg_match('/\b(?:center(?:ed)?|centre(?:d)?)(?:\s+(?:mega\s+)?footer)?\b.{0,24}\b(?:without|no)\s+cta\b|\b(?:mega\s+)?footer\b.{0,24}\b(?:center(?:ed)?|centre(?:d)?)\b.{0,18}\b(?:without|no)\s+cta\b/i', $text)) {
            return 'centered';
        }
        if (preg_match('/\b(?:center(?:ed)?|centre(?:d)?)(?:\s+(?:mega\s+)?footer)?\b.{0,28}\b(?:with\s+|\+\s*)?cta\b|\bfooter\b.{0,28}\b(?:center(?:ed)?|centre(?:d)?)\b.{0,20}\bcta\b|\bcentered[_ +\-]?cta\b/i', $text)) {
            return 'centered_cta';
        }
        if (preg_match('/\b(?:center(?:ed)?|centre(?:d)?)(?:\s+minimal)?\s+(?:mega\s+)?footer\b|\b(?:mega\s+)?footer\b.{0,24}\b(?:center(?:ed)?|centre(?:d)?|minimal)\b/i', $text)
            && ! preg_match('/\bcta\b/i', $text)) {
            return 'centered';
        }
        if (preg_match('/\b(?:split|editorial)\s+(?:mega\s+)?footer\b|\b(?:mega\s+)?footer\b.{0,20}\b(?:split|editorial)\b/i', $text)) {
            return 'split';
        }
        if (preg_match('/\b(?:primary\s+brand|brand(?:-led)?)\s+(?:mega\s+)?footer\b|\b(?:mega\s+)?footer\b.{0,20}\bbrand\b/i', $text)) {
            return 'brand';
        }
        if (preg_match('/\bsecondary(?:\s+(?:surface|background))?\s+(?:mega\s+)?footer\b|\b(?:mega\s+)?footer\b.{0,20}\bsecondary\b/i', $text)) {
            return 'secondary';
        }
        if (preg_match('/\bprimary\s+(?:mega\s+)?footer\b|\b(?:mega\s+)?footer\b.{0,20}\bprimary\b/i', $text)) {
            return 'primary';
        }
        if (preg_match('/\b(?:classic|white|default)\s+(?:mega\s+)?footer\b|\b(?:mega\s+)?footer\b.{0,20}\b(?:classic|white|default)\b/i', $text)) {
            return 'classic';
        }

        return null;
    }

    public static function headerVariantName(string $variant): string
    {
        return (string) (self::HEADER_VARIANT_META[$variant]['name'] ?? $variant);
    }

    public static function footerVariantName(string $variant): string
    {
        return (string) (self::FOOTER_VARIANT_META[$variant]['name'] ?? $variant);
    }
}
