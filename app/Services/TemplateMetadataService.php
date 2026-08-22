<?php

namespace App\Services;

use Illuminate\Support\Str;

final class TemplateMetadataService
{
    public function enrich(array $template): array
    {
        $sections = array_values(array_filter($template['sections'] ?? [], 'is_string'));
        $styles = array_values(array_filter($template['style'] ?? [], 'is_string'));
        $features = array_values(array_filter($template['features'] ?? [], 'is_string'));
        $industries = array_values(array_filter($template['industry'] ?? [], 'is_string'));
        $intents = array_values(array_filter($template['intent'] ?? [], 'is_string'));
        $tags = array_values(array_filter($template['tags'] ?? [], 'is_string'));

        $hero = collect($sections)->first(fn ($section) => str_starts_with($section, 'hero_'));
        $mediaHeavyTokens = ['video', 'slider', 'gallery', 'image_sequence', 'parallax', 'ken_burns', 'carousel', 'before_after'];
        $interactiveTokens = ['interactive', 'mouse', 'cursor', 'tilt', 'morph', 'reveal', 'floating', 'particle', 'motion', 'marquee'];
        $denseTokens = ['pricing', 'comparison', 'faq', 'cards', 'grid', 'bento'];

        $mediaHits = $this->countTokenHits($sections, $mediaHeavyTokens);
        $interactiveHits = $this->countTokenHits($sections, $interactiveTokens);
        $denseHits = $this->countTokenHits($sections, $denseTokens);
        $imageSections = collect($sections)->filter(fn ($section) => Str::contains($section, ['image', 'gallery', 'portfolio', 'case_stud', 'team', 'testimonial']))->count();

        $mediaMode = match (true) {
            $hero && Str::contains($hero, 'video') => 'video-led',
            $mediaHits >= 3 => 'cinematic',
            $imageSections >= 3 => 'image-led',
            $imageSections >= 1 => 'mixed-media',
            default => 'text-led',
        };

        $explicitHeroMediaMode = strtolower(trim((string) ($template['hero_media_mode'] ?? '')));
        $heroMediaMode = in_array($explicitHeroMediaMode, ['image', 'slider', 'video'], true)
            ? $explicitHeroMediaMode
            : match (true) {
                $hero && Str::contains($hero, 'video') => 'video',
                $hero && Str::contains($hero, ['slider', 'carousel', 'crossfade']) => 'slider',
                $hero && Str::contains($hero, ['background_image', 'editorial_overlay', 'ken_burns', 'parallax', 'image_sequence', 'mask_reveal']) => 'image',
                default => 'none',
            };

        $overlayHeaderRecommended = array_key_exists('overlay_header_recommended', $template)
            ? (bool) $template['overlay_header_recommended']
            : in_array($heroMediaMode, ['image', 'slider', 'video'], true);

        $overlayHeaderDefault = array_key_exists('overlay_header_default', $template)
            ? (bool) $template['overlay_header_default']
            : $overlayHeaderRecommended;

        $layoutStyle = $this->layoutStyle($hero, $styles, $sections);
        $textDensity = $denseHits >= 4 ? 'dense' : ($denseHits >= 2 ? 'balanced' : 'airy');
        $visualScore = min(100, 38 + ($mediaHits * 10) + ($interactiveHits * 8) + ($imageSections * 4) + (($template['featured'] ?? false) ? 6 : 0));

        $category = $this->category($industries, $tags, $features);
        $quality = app(TemplateQualityAuditor::class)->audit($template);
        $rhythm = $this->visualRhythm($sections);

        $premiumLevel = isset($template['price_credits']) && (int) $template['price_credits'] >= 240
            ? 'signature'
            : (($template['featured'] ?? false) ? 'premium' : 'standard');

        return array_merge($template, [
            'category' => $category,
            'industries' => $industries,
            'page_intents' => $intents,
            'layout_style' => $layoutStyle,
            'media_mode' => $mediaMode,
            'hero_media_mode' => $heroMediaMode,
            'overlay_header_recommended' => $overlayHeaderRecommended,
            'overlay_header_default' => $overlayHeaderDefault,
            'text_density' => $textDensity,
            'visual_score' => $visualScore,
            'premium_level' => $premiumLevel,
            'hero_type' => $hero ?: null,
            'section_count' => count($sections),
            'recommended_position' => 'full-page',
            'visual_rhythm' => $rhythm,
            'quality_score' => $quality['quality_score'],
            'quality_status' => $quality['quality_status'],
            'proof_count' => $quality['proof_count'],
            'conversion_ready' => $quality['has_conversion_close'],
            'diversity_fingerprint' => substr(hash('sha256', implode('|', [$layoutStyle, $mediaMode, $textDensity, $rhythm, implode(',', $sections)])), 0, 16),
        ]);
    }

    public function plannerPayload(array $template): array
    {
        $template = $this->enrich($template);

        return [
            'key' => $template['key'],
            'name' => $template['name'],
            'description' => $template['description'] ?? '',
            'aliases' => $template['aliases'] ?? [],
            'category' => $template['category'],
            'industries' => $template['industries'],
            'page_intents' => $template['page_intents'],
            'audience' => $template['audience'] ?? [],
            'style' => $template['style'] ?? [],
            'features' => $template['features'] ?? [],
            'layout_style' => $template['layout_style'],
            'media_mode' => $template['media_mode'],
            'hero_media_mode' => $template['hero_media_mode'],
            'overlay_header_recommended' => $template['overlay_header_recommended'],
            'overlay_header_default' => $template['overlay_header_default'],
            'text_density' => $template['text_density'],
            'visual_score' => $template['visual_score'],
            'quality_score' => $template['quality_score'],
            'quality_status' => $template['quality_status'],
            'visual_rhythm' => $template['visual_rhythm'],
            'proof_count' => $template['proof_count'],
            'conversion_ready' => $template['conversion_ready'],
            'premium_level' => $template['premium_level'],
            'section_count' => $template['section_count'],
            'hero_type' => $template['hero_type'],
            'diversity_fingerprint' => $template['diversity_fingerprint'],
        ];
    }


    private function visualRhythm(array $sections): string
    {
        $dense = collect($sections)->filter(fn ($s) => Str::contains($s, ['cards', 'grid', 'comparison', 'pricing', 'faq']))->count();
        $visual = collect($sections)->filter(fn ($s) => Str::contains($s, ['image', 'video', 'gallery', 'portfolio', 'slider', 'parallax', 'story']))->count();
        $interactive = collect($sections)->filter(fn ($s) => Str::contains($s, ['interactive', 'motion', 'morph', 'reveal', 'carousel', 'marquee']))->count();

        if ($interactive >= 2) return 'dynamic';
        if ($visual >= 3 && $dense <= 2) return 'editorial';
        if ($dense >= 4) return 'structured';
        return 'balanced';
    }

    private function countTokenHits(array $sections, array $tokens): int
    {
        return collect($sections)->sum(function ($section) use ($tokens) {
            return collect($tokens)->contains(fn ($token) => str_contains($section, $token)) ? 1 : 0;
        });
    }

    private function layoutStyle(?string $hero, array $styles, array $sections): string
    {
        $haystack = strtolower(implode(' ', array_merge([$hero ?? ''], $styles, $sections)));
        foreach (['bento', 'editorial', 'split', 'fullscreen', 'parallax', 'slider', 'carousel', 'stacked', 'masonry', 'timeline', 'corporate', 'minimal'] as $style) {
            if (str_contains($haystack, $style)) {
                return $style;
            }
        }

        return 'balanced';
    }

    private function category(array $industries, array $tags, array $features): string
    {
        $haystack = strtolower(implode(' ', array_merge($industries, $tags, $features)));
        $map = [
            'technology' => ['saas', 'software', 'technology', 'artificial intelligence', 'ai', 'automation', 'fintech'],
            'hospitality' => ['hotel', 'hospitality', 'travel', 'restaurant', 'coffee', 'bakery', 'resort', 'food'],
            'property' => ['real estate', 'property', 'architecture', 'residential', 'interior'],
            'health' => ['medical', 'health', 'clinic', 'dental', 'wellness', 'fitness', 'veterinary'],
            'professional-services' => ['law', 'consulting', 'professional services', 'finance', 'accounting', 'business services'],
            'creative' => ['agency', 'creative', 'branding', 'design', 'portfolio', 'fashion'],
            'local-services' => ['construction', 'plumbing', 'electrician', 'cleaning', 'landscaping', 'automotive'],
        ];

        foreach ($map as $category => $needles) {
            if (collect($needles)->contains(fn ($needle) => str_contains($haystack, $needle))) {
                return $category;
            }
        }

        return 'business';
    }
}
