<?php

namespace App\Services;

use App\Models\TrialGeneration;
use App\Models\Website;
use App\Support\HeaderFooterVariantContract;
use Illuminate\Support\Str;

/**
 * Choose the initial Trial website shell from the same seven Header/Footer
 * cards exposed by Builder and understood by Luna.
 *
 * Selection is deterministic and design-aware: the generated Home hero/media,
 * visual-intent hint, active color family, industry, navigation/CTA shape and
 * explicit user wording all contribute. No extra AI/provider call is required.
 */
final class LunaTrialShellSelectionService
{
    public function __construct(private readonly ThemeColorResolver $themeColors)
    {
    }

    /**
     * @param array<string,mixed> $theme
     * @param array<int,array<string,mixed>> $menu
     * @param array<int,array<string,mixed>> $homeBlocks
     * @param array<string,mixed> $visualIntent
     * @param array<string,mixed> $currentHeader
     * @param array<string,mixed> $currentFooter
     * @return array{header:array,footer:array,meta:array}
     */
    public function select(
        TrialGeneration $trial,
        array $theme,
        array $menu,
        array $homeBlocks = [],
        array $visualIntent = [],
        array $currentHeader = [],
        array $currentFooter = [],
    ): array {
        $prompt = trim((string) ($trial->latest_user_prompt ?: $trial->brand_prompt ?: $trial->prompt ?: $trial->business_description));
        $industry = Str::lower(trim((string) $trial->industry));
        $themeKey = (string) data_get($theme, 'primary', 'midnight');
        $palette = $this->themeColors->resolve($themeKey, $theme, 'midnight');
        $first = is_array($homeBlocks[0] ?? null) ? $homeBlocks[0] : [];
        $heroType = Str::lower((string) ($first['type'] ?? ''));
        $heroCategory = Str::lower((string) ($first['category'] ?? ''));
        $supportsHero = $heroType !== '' && (
            $heroCategory === 'hero'
            || $heroType === 'hero'
            || Str::contains($heroType, ['hero', 'banner', 'masthead'])
        );
        $mediaLed = $supportsHero && Str::contains($heroType, [
            'slider', 'video', 'parallax', 'background_image', 'background-image',
            'cinematic', 'gallery', 'image_sequence', 'image-sequence', 'ken_burns',
            'crossfade', 'reveal', 'fullscreen', 'luxury', 'media',
        ]);
        $visualOverlay = $supportsHero && (bool) ($visualIntent['overlay_header_on_banner'] ?? false);
        $visualStyle = Str::lower((string) ($visualIntent['visual_style'] ?? ''));
        $navCount = count($menu);
        $cta = $this->ctaTarget($menu);
        $hasCta = $cta !== null;
        $promptLower = Str::lower($prompt);
        $isLuxury = Str::contains($promptLower.' '.$visualStyle, ['luxury', 'premium', 'elegant', 'exclusive', 'cinematic', 'refined', 'high-end', 'high end']);
        $isMinimal = Str::contains($promptLower.' '.$visualStyle, ['minimal', 'clean', 'simple', 'editorial', 'quiet', 'restrained']);
        $immersiveIndustry = Str::contains($industry, ['hotel', 'resort', 'travel', 'tour', 'real estate', 'property', 'restaurant', 'hospitality', 'salon', 'beauty', 'fitness', 'automotive', 'marine', 'yacht']);
        $professionalIndustry = Str::contains($industry, ['law', 'legal', 'finance', 'account', 'medical', 'clinic', 'dental', 'education', 'consult']);
        $boldIndustry = Str::contains($industry, ['technology', 'software', 'saas', 'construction', 'roof', 'plumb', 'electric', 'automotive', 'industrial']);
        $organicIndustry = Str::contains($industry, ['landscap', 'cleaning', 'bakery', 'coffee', 'wellness', 'organic', 'eco']);

        $headerVariant = HeaderFooterVariantContract::detectHeaderVariant($prompt);
        $headerReason = $headerVariant ? 'explicit user shell request' : null;

        if ($headerVariant === null && $supportsHero && ($visualOverlay || ($mediaLed && ($immersiveIndustry || $isLuxury)))) {
            $centerFriendly = $navCount <= 6 && ($isLuxury || Str::contains($heroType, ['center', 'fullscreen', 'luxury', 'cinematic']));
            $headerVariant = $centerFriendly ? 'overlay_centered_header' : 'overlay_hero_header';
            $headerReason = $visualOverlay ? 'visual-intent overlay hint on Home hero' : 'media-led immersive Home hero';
        }

        if ($headerVariant === null && $isMinimal && $navCount <= 5) {
            $headerVariant = $hasCta ? 'split_navigation_header' : 'centered_header';
            $headerReason = 'minimal/editorial direction with compact navigation';
        }

        if ($headerVariant === null && $professionalIndustry) {
            $headerVariant = $navCount <= 6 && $hasCta ? 'split_navigation_header' : 'classic_header';
            $headerReason = 'professional/trust-led industry';
        }

        if ($headerVariant === null && $boldIndustry) {
            $headerVariant = 'primary_header';
            $headerReason = 'bold/technical industry direction';
        }

        if ($headerVariant === null && ($organicIndustry || ($palette['shell_secondary_tone'] ?? 'dark') === 'light')) {
            $headerVariant = 'secondary_header';
            $headerReason = 'theme-matched secondary shell';
        }

        if ($headerVariant === null && $isLuxury && $navCount <= 6) {
            $headerVariant = $hasCta ? 'split_navigation_header' : 'centered_header';
            $headerReason = 'premium centered composition';
        }

        if ($headerVariant === null) {
            // Stable diversity for ordinary businesses. This is a tie-breaker,
            // not randomness: the same business/theme always receives the same shell.
            $bucket = abs(crc32(($trial->business_name ?: 'trial').'|'.$industry.'|'.$themeKey)) % 4;
            $headerVariant = match ($bucket) {
                1 => 'primary_header',
                2 => $hasCta && $navCount <= 7 ? 'split_navigation_header' : 'classic_header',
                3 => 'secondary_header',
                default => 'classic_header',
            };
            $headerReason = 'stable design tie-break for general business';
        }

        $footerVariant = HeaderFooterVariantContract::detectFooterVariant($prompt);
        $footerReason = $footerVariant ? 'explicit user shell request' : null;

        if ($footerVariant === null && in_array($headerVariant, ['overlay_hero_header', 'overlay_centered_header'], true) && ($isLuxury || $immersiveIndustry)) {
            $footerVariant = 'brand';
            $footerReason = 'brand-led close for immersive/luxury Home';
        }
        if ($footerVariant === null && $professionalIndustry) {
            $footerVariant = 'split';
            $footerReason = 'editorial information hierarchy for professional industry';
        }
        if ($footerVariant === null && $headerVariant === 'primary_header') {
            $footerVariant = $navCount >= 6 ? 'primary' : 'centered_cta';
            $footerReason = 'conversion-focused companion to primary header';
        }
        if ($footerVariant === null && $headerVariant === 'secondary_header') {
            $footerVariant = 'secondary';
            $footerReason = 'theme-matched secondary shell continuity';
        }
        if ($footerVariant === null && $navCount <= 4) {
            $footerVariant = $hasCta ? 'centered_cta' : 'centered';
            $footerReason = 'compact navigation footprint';
        }
        if ($footerVariant === null) {
            $footerBucket = abs(crc32($themeKey.'|'.($trial->business_name ?: 'trial').'|footer')) % 4;
            $footerVariant = match ($footerBucket) {
                1 => 'primary',
                2 => 'split',
                3 => 'secondary',
                default => 'classic',
            };
            $footerReason = 'stable design tie-break for general business';
        }

        $header = array_replace([
            'type' => 'classic_header',
            'logo_text' => (string) ($trial->business_name ?: 'Your Logo'),
            'logo_image_url' => $trial->logo_url ?: '/storage/branding/your-logo.png',
            'logo_height' => 60,
            'logo_max_width' => 300,
            'logo_filter_key' => $themeKey,
            'allow_light_logo_filter' => true,
            'cta_label' => $cta['label'] ?? 'Get Started',
            'cta_url' => $cta['url'] ?? 'contact',
            'menu' => $menu,
        ], $currentHeader, [
            'type' => $headerVariant,
            'logo_text' => (string) ($trial->business_name ?: ($currentHeader['logo_text'] ?? 'Your Logo')),
            'logo_image_url' => $trial->logo_url ?: ($currentHeader['logo_image_url'] ?? '/storage/branding/your-logo.png'),
            'logo_filter_key' => $themeKey,
            'allow_light_logo_filter' => (bool) ($currentHeader['allow_light_logo_filter'] ?? true),
            'cta_label' => $cta['label'] ?? ($currentHeader['cta_label'] ?? 'Get Started'),
            'cta_url' => $cta['url'] ?? ($currentHeader['cta_url'] ?? 'contact'),
            'menu' => $menu,
        ]);
        $header = HeaderFooterVariantContract::normalizeHeader($header) ?? $header;

        $footer = array_replace([
            'type' => 'minimal_footer',
            'theme' => 'white',
            'logo_text' => (string) ($trial->business_name ?: 'Your Logo'),
            'logo_image_url' => $trial->logo_url ?: '/storage/branding/your-logo.png',
            'logo_filter_key' => $themeKey,
            'allow_light_logo_filter' => true,
            'copyright' => '© '.now()->year.' '.($trial->business_name ?: 'Your business').'. All rights reserved.',
            'mega_footer' => ['enabled' => true, 'variant' => 'classic', 'theme' => 'white'],
        ], $currentFooter);
        $mega = is_array($footer['mega_footer'] ?? null) ? $footer['mega_footer'] : [];
        $mega['variant'] = $footerVariant;
        $mega['enabled'] = true;
        $footer['mega_footer'] = $mega;
        $footer['logo_text'] = (string) ($trial->business_name ?: ($footer['logo_text'] ?? 'Your Logo'));
        $footer['logo_image_url'] = $trial->logo_url ?: ($footer['logo_image_url'] ?? '/storage/branding/your-logo.png');
        $footer['logo_filter_key'] = $themeKey;
        $footer['allow_light_logo_filter'] = (bool) ($footer['allow_light_logo_filter'] ?? true);
        $footer = HeaderFooterVariantContract::normalizeFooter($footer) ?? $footer;

        return [
            'header' => $header,
            'footer' => $footer,
            'meta' => [
                'source' => 'luna_trial_shell_selector_v1',
                'header_variant' => $headerVariant,
                'header_name' => HeaderFooterVariantContract::headerVariantName($headerVariant),
                'header_reason' => $headerReason,
                'footer_variant' => $footerVariant,
                'footer_name' => HeaderFooterVariantContract::footerVariantName($footerVariant),
                'footer_reason' => $footerReason,
                'hero_type' => $heroType ?: null,
                'hero_media_led' => $mediaLed,
                'visual_overlay_hint' => $visualOverlay,
                'visual_style' => $visualStyle ?: null,
                'theme' => $themeKey,
                'secondary_tone' => (string) ($palette['shell_secondary_tone'] ?? 'dark'),
                'navigation_count' => $navCount,
                'cta_target' => $cta,
                'selected_at' => now()->toIso8601String(),
            ],
        ];
    }

    /**
     * Persist one Luna shell decision onto the Trial's isolated website.
     *
     * @param array<int,array<string,mixed>> $homeBlocks
     * @param array<string,mixed> $visualIntent
     * @return array{header:array,footer:array,meta:array}
     */
    public function apply(TrialGeneration $trial, Website $website, array $homeBlocks = [], array $visualIntent = []): array
    {
        $theme = is_array($trial->preview_theme) ? $trial->preview_theme : (is_array($website->theme_settings) ? $website->theme_settings : []);
        $menu = collect($trial->menu_structure ?? [])
            ->filter(fn ($page) => is_array($page))
            ->map(fn (array $page) => [
                'label' => (string) ($page['title'] ?? 'Page'),
                'url' => (bool) ($page['is_home'] ?? false) ? 'home' : (string) ($page['slug'] ?? '#'),
            ])
            ->values()
            ->all();
        if ($menu === []) {
            $menu = is_array(data_get($website->global_header, 'menu')) ? data_get($website->global_header, 'menu') : [];
        }

        $selection = $this->select(
            $trial,
            $theme,
            $menu,
            $homeBlocks,
            $visualIntent,
            is_array($website->global_header) ? $website->global_header : [],
            is_array($website->global_footer) ? $website->global_footer : [],
        );

        $settings = is_array($website->settings) ? $website->settings : [];
        data_set($settings, 'trial_shell_selection', $selection['meta']);
        $website->forceFill([
            'global_header' => $selection['header'],
            'global_footer' => $selection['footer'],
            'settings' => $settings,
        ])->save();

        // Keep the old visual-intent boolean in sync for old screens only. The
        // canonical Header variant remains authoritative everywhere else.
        $previewTheme = is_array($trial->preview_theme) ? $trial->preview_theme : [];
        $previewTheme['overlay_header_on_banner'] = (bool) ($selection['header']['overlay_header_on_banner'] ?? false);
        $previewTheme['trial_header_variant'] = (string) $selection['meta']['header_variant'];
        $previewTheme['trial_footer_variant'] = (string) $selection['meta']['footer_variant'];
        $trial->forceFill(['preview_theme' => $previewTheme])->save();

        return $selection;
    }

    /** @param array<int,array<string,mixed>> $menu */
    private function ctaTarget(array $menu): ?array
    {
        $priorities = [
            ['needles' => ['reservation', 'reserve'], 'label' => 'Reserve'],
            ['needles' => ['booking', 'book'], 'label' => 'Book Now'],
            ['needles' => ['quote', 'estimate'], 'label' => 'Get a Quote'],
            ['needles' => ['contact', 'enquiry', 'inquiry'], 'label' => 'Contact'],
            ['needles' => ['pricing', 'plans'], 'label' => 'View Pricing'],
            ['needles' => ['shop', 'order'], 'label' => 'Shop Now'],
            ['needles' => ['menu'], 'label' => 'View Menu'],
        ];

        foreach ($priorities as $priority) {
            foreach ($menu as $item) {
                if (! is_array($item)) continue;
                $label = trim((string) ($item['label'] ?? $item['title'] ?? ''));
                $url = trim((string) ($item['url'] ?? $item['slug'] ?? ''));
                $haystack = Str::lower($label.' '.$url);
                if ($label !== '' && $url !== '' && Str::contains($haystack, $priority['needles'])) {
                    return ['label' => $priority['label'], 'url' => $url];
                }
            }
        }

        return null;
    }
}
