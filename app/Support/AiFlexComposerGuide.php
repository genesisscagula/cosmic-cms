<?php

namespace App\Support;

/**
 * Intent-to-primitives guide for Luna AI Flex.
 *
 * This deliberately never inspects the registered Spark catalog. It turns a
 * user's request into a small trusted-component composition brief so Luna can
 * focus on hierarchy/content/styling while Cosmic owns component mechanics.
 */
final class AiFlexComposerGuide
{
    public const VERSION = 3;
    public const PROFILE = 'trusted_primitives_v1';

    /** @return array<string,mixed> */
    public static function forRequest(string $request): array
    {
        $q = strtolower($request);
        $has = static function (array $terms) use ($q): bool {
            foreach ($terms as $term) {
                if ($term !== '' && str_contains($q, strtolower((string) $term))) return true;
            }
            return false;
        };

        $layoutRecipe = AiFlexLayoutRecipeRegistry::detect($request);
        $slider = $has(['slider', 'carousel', 'slideshow', 'slides']);
        $backgroundVideo = $has(['background video', 'video background', 'video hero', 'video banner']);
        $backgroundImage = $has(['background image', 'image background', 'photo background', 'full bleed image', 'full-bleed image']);
        $gallery = $has(['gallery', 'media grid', 'image grid', 'photo grid', 'masonry', 'paired images', 'paired media']);
        $hero = $has(['hero', 'banner', 'masthead', 'above the fold', 'above-the-fold']);
        $services = $has(['services', 'features', 'benefits', 'capabilities', 'offerings']);
        $pricing = $has(['pricing', 'plans', 'packages', 'tiers']);
        $testimonials = $has(['testimonial', 'testimonials', 'reviews', 'customer stories']);
        $faq = $has(['faq', 'frequently asked', 'questions and answers']);
        $split = $has(['split', 'two column', 'two-column', '2 column', '2-column', 'image left', 'image right']) || in_array((string) ($layoutRecipe['key'] ?? ''), ['two_equal','two_40_60','two_60_40','split_media_left','split_media_right','sidebar_left','sidebar_right'], true);
        $multipleCtas = $has(['two buttons', '2 buttons', 'two ctas', '2 ctas', 'two cta buttons', '2 cta buttons', 'primary and secondary', 'button group']);
        $requestedSlideCount = null;
        if ($slider && preg_match('/\b([2-6])\s*(?:slides?|panels?)\b/i', $request, $m)) {
            $requestedSlideCount = (int) $m[1];
        }
        $sliderControls = $slider ? [
            'show_arrows' => $has(['arrows', 'previous and next', 'prev and next', 'navigation arrows']),
            'show_dots' => $has(['dots', 'pagination dots', 'indicators']),
            'show_counter' => $has(['counter', 'slide counter', '01 / 03']),
        ] : [];

        $required = [];
        $preferred = ['row', 'column', 'stack', 'heading', 'text'];
        $avoid = ['loose duplicated CTA buttons', 'decorative primitives with no visual purpose', 'deep nesting that does not improve composition'];
        $recipe = 'row → column → stack → content';
        $semantic = 'content';

        if ($slider) {
            $required[] = 'slider';
            $required[] = 'slide';
            $recipe = 'row → column → slider → slide → content container';
            $semantic = $hero ? 'hero' : 'content';
            $preferred = array_merge($preferred, ['slider', 'slide']);
            if ($backgroundVideo) {
                $required[] = 'background_video';
                $required[] = 'overlay';
                $recipe = 'row → column → slider → slide → background_video → overlay → stack → content';
            } elseif ($backgroundImage || $hero) {
                $required[] = 'background_image';
                $required[] = 'overlay';
                $recipe = 'row → column → slider → slide → background_image → overlay → stack → content';
            }
        } elseif ($backgroundVideo) {
            $required = ['background_video', 'overlay'];
            $recipe = 'row → column → background_video → overlay → stack → content';
            $semantic = $hero ? 'hero' : 'content';
            $preferred = array_merge($preferred, ['background_video', 'overlay']);
        } elseif ($backgroundImage) {
            $required = ['background_image', 'overlay'];
            $recipe = 'row → column → background_image → overlay → stack → content';
            $semantic = $hero ? 'hero' : 'content';
            $preferred = array_merge($preferred, ['background_image', 'overlay']);
        } elseif ($gallery) {
            $required = ['media_group'];
            $recipe = 'row → column → stack(copy) + media_group(image/video)';
            $preferred = array_merge($preferred, ['media_group', 'image', 'video']);
        } elseif ($pricing || $services || $testimonials) {
            $recipe = 'row → column → stack(intro) + grid → card → content';
            $preferred = array_merge($preferred, ['grid', 'card']);
            $semantic = $pricing ? 'pricing' : ($services ? 'services' : 'testimonials');
        } elseif ($faq) {
            $recipe = 'row → column → stack → heading/text/list';
            $preferred = array_merge($preferred, ['list', 'divider']);
            $semantic = 'faq';
        } elseif ($hero || $split) {
            $recipe = $split
                ? 'row → two columns (copy stack + media)'
                : 'row → column(s) → stack → content/media';
            $semantic = $hero ? 'hero' : 'content';
            $preferred = array_merge($preferred, ['image']);
        }

        if ($multipleCtas || $hero || $pricing) {
            $preferred[] = 'button_group';
        }
        if ($multipleCtas) $required[] = 'button_group';

        if (is_array($layoutRecipe)) {
            $recipeKey = (string) ($layoutRecipe['key'] ?? '');
            $recipe = 'LAYOUT LEGO '.$recipeKey.': '.(string) ($layoutRecipe['description'] ?? $recipe).'; '.$recipe;
            if (($layoutRecipe['kind'] ?? '') === 'grid') $preferred[] = 'grid';
            if (str_starts_with($recipeKey, 'bento_')) $preferred = array_merge($preferred, ['grid','card']);
        }

        $required = array_values(array_unique(array_filter($required, [AiFlexComponentRegistry::class, 'isRendererReady'])));
        $preferred = array_values(array_unique(array_filter($preferred, [AiFlexComponentRegistry::class, 'isRendererReady'])));

        return [
            'version' => self::VERSION,
            'profile' => self::PROFILE,
            'semantic_hint' => $semantic,
            'layout_recipe' => $layoutRecipe,
            'layout_recipe_contract' => AiFlexLayoutRecipeRegistry::CONTRACT,
            'requested_slide_count' => $requestedSlideCount,
            'slider_controls' => $sliderControls,
            'recipe' => $recipe,
            'required_components' => $required,
            'preferred_components' => $preferred,
            'avoid' => $avoid,
            'rules' => [
                'Use the smallest primitive tree that faithfully satisfies the request.',
                'Use registered components for mechanics; never recreate slider/background/group behavior with ad-hoc structure.',
                'Use button_group for paired/grouped CTAs and media_group for deliberate image/video clusters.',
                'Keep text/content editable as leaf primitives rather than baking copy into container labels.',
                'Make desktop composition intentional and supply safe tablet/mobile geometry.',
                'When a slider count or controls are explicitly requested, honor them exactly.',
                'For slide-wide background media requests, put the requested background media and overlay inside every slide rather than only the first slide.',
            ],
        ];
    }

    /** @param array<string,mixed> $plan */
    public static function prompt(array $plan): string
    {
        return 'COMPOSER PLAN (authoritative intent guide; not a fixed template): '.json_encode($plan, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
            .'. Select and arrange renderer-ready primitives to satisfy this plan. Required components must appear when listed. '
            .'You may vary hierarchy/details when useful, but do not replace trusted component mechanics with improvised equivalents. '.
            'When layout_recipe is present, treat its geometry as authoritative Lego geometry: use the stated desktop widths/columns and safe tablet/mobile collapse; fill it with content/components rather than inventing competing geometry.';
    }
}
