<?php

namespace App\Services;

use App\Cosmic\Pricing\BlockPricingRegistry;

class SparkCatalog
{
    public static function all(): array
    {
        $overrides = (array) config('cosmic-sparks.overrides', []);

        return collect(BlockPricingRegistry::all())
            ->map(function ($item, $type) use ($overrides) {
                if (! is_array($item) || ! is_string($type) || blank($type)) {
                    return null;
                }

                $collection = (string) ($item['category'] ?? 'growth');
                $override = (array) ($overrides[$type] ?? []);

                return [
                    'key' => $type,
                    'name' => self::displayName($type, $item['label'] ?? null),
                    'description' => self::description($type),
                    'category' => self::category($type),
                    'aliases' => self::metadata($type, $override)['aliases'],
                    'media' => self::metadata($type, $override)['media'],
                    'layout' => self::metadata($type, $override)['layout'],
                    'style' => self::metadata($type, $override)['style'],
                    'intent' => self::metadata($type, $override)['intent'],
                    'industry_fit' => self::metadata($type, $override)['industry_fit'],
                    'position_fit' => self::metadata($type, $override)['position_fit'],
                    'capabilities' => self::metadata($type, $override)['capabilities'],
                    'collection' => $collection,
                    'collection_label' => self::collectionLabel($collection),
                    'access_level' => (string) ($override['access_level'] ?? self::collectionAccessLevel($collection)),
                    'catalog_index' => 0,
                    'credits' => (int) ($item['credits'] ?? 20),
                    'featured' => in_array($type, [
                        'hero_floating_cards', 'hero_video_background', 'services_bento',
                        'pricing_cards', 'testimonials_carousel', 'case_studies_grid',
                    ], true),
                ];
            })
            ->filter()
            ->values()
            ->map(function (array $spark, int $index) {
                $spark['catalog_index'] = $index;

                return $spark;
            })
            ->all();
    }

    public static function find(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
    }

    public static function accessLevels(): array
    {
        return (array) config('cosmic-sparks.access_levels', []);
    }

    public static function collections(): array
    {
        return (array) config('cosmic-sparks.collections', []);
    }

    /**
     * Frontend-safe registry metadata. No ownership or user-specific state.
     */
    public static function forClient(): array
    {
        return [
            'access_levels' => self::accessLevels(),
            'collections' => self::collections(),
        ];
    }

    /**
     * Validate registry references without crashing production requests.
     *
     * @return array<int, string>
     */
    public static function validationErrors(): array
    {
        $levels = self::accessLevels();
        $errors = [];

        foreach (self::collections() as $key => $collection) {
            $level = (string) ($collection['access_level'] ?? '');
            if ($level === '' || ! array_key_exists($level, $levels)) {
                $errors[] = "Spark collection [{$key}] references an unknown access level [{$level}].";
            }
        }

        foreach (self::all() as $spark) {
            $level = (string) ($spark['access_level'] ?? '');
            if ($level === '' || ! array_key_exists($level, $levels)) {
                $errors[] = "Spark [{$spark['key']}] references an unknown access level [{$level}].";
            }
        }

        return array_values(array_unique($errors));
    }

    private static function collectionAccessLevel(string $collection): string
    {
        return (string) config("cosmic-sparks.collections.{$collection}.access_level", 'growth');
    }

    private static function collectionLabel(string $collection): string
    {
        return (string) config("cosmic-sparks.collections.{$collection}.label", str($collection)->headline()->toString());
    }

    private static function displayName(string $type, ?string $fallback): string
    {
        return $fallback ?: str($type)->replace('_', ' ')->title()->toString();
    }

    private static function category(string $type): string
    {
        return match (true) {
            str_starts_with($type, 'mini_hero_') => 'Mini Heroes',
            str_starts_with($type, 'hero_'), $type === 'image_cta_banner' => 'Hero',
            str_starts_with($type, 'services_') => 'Services',
            str_starts_with($type, 'about_') => 'About',
            str_starts_with($type, 'feature_') => 'Features',
            str_starts_with($type, 'cta_') => 'CTA',
            str_contains($type, 'pricing') => 'Pricing',
            str_contains($type, 'testimonial') => 'Testimonials',
            str_contains($type, 'team') => 'Team',
            str_starts_with($type, 'contact_'), str_contains($type, 'location') => 'Contact',
            str_contains($type, 'faq') => 'FAQ',
            str_starts_with($type, 'lead_') => 'Lead Generation',
            str_starts_with($type, 'sales_') => 'Sales',
            str_starts_with($type, 'agency_') => 'Agency',
            str_starts_with($type, 'ai_') => 'AI',
            str_contains($type, 'case_stud') => 'Case Studies',
            str_contains($type, 'job') => 'Careers',
            str_starts_with($type, 'content_') => 'Posts / Updates',
            str_contains($type, 'event') => 'Events',
            str_contains($type, 'blog'), str_contains($type, 'newsletter'), str_contains($type, 'resource') => 'Blog',
            str_starts_with($type, 'footer_') => 'Footer',
            str_starts_with($type, 'commerce_') => 'Commerce',
            str_starts_with($type, 'stats_') => 'Statistics',
            str_contains($type, 'process') => 'Proof',
            default => 'Other',
        };
    }

    private static function metadata(string $type, array $override = []): array
    {
        $words = collect(preg_split('/[_\-]+/', strtolower($type)) ?: [])
            ->filter()->values()->all();
        $text = ' '.implode(' ', $words).' ';

        $aliases = $words;
        $aliasGroups = [
            'slider' => ['slider','carousel','slideshow','rotating banner','rotating images','image rotator'],
            'carousel' => ['carousel','slider','slideshow','rotating content'],
            'video' => ['video','motion','background video','video banner','video section'],
            'gallery' => ['gallery','portfolio','showcase','image grid','project gallery'],
            'testimonial' => ['testimonial','reviews','customer stories','social proof','client quotes'],
            'pricing' => ['pricing','plans','packages','price comparison'],
            'faq' => ['faq','questions','accordion','frequently asked questions'],
            'services' => ['services','offerings','solutions','what we do'],
            'case' => ['case studies','portfolio','work','projects','selected work'],
            'team' => ['team','people','staff','leadership'],
            'process' => ['process','steps','workflow','how it works'],
            'stats' => ['stats','metrics','numbers','proof','results'],
            'contact' => ['contact','enquiry','inquiry','lead form','get in touch'],
            'hero' => ['hero','banner','masthead','opening section','page intro'],
            'bento' => ['bento','masonry','editorial grid'],
            'split' => ['split','two column','side by side'],
        ];
        foreach ($aliasGroups as $needle => $values) {
            if (str_contains($text, " {$needle} ")) $aliases = array_merge($aliases, $values);
        }

        $media = match (true) {
            str_contains($text, ' video ') => 'video',
            str_contains($text, ' slider '), str_contains($text, ' carousel ') => 'slider',
            str_contains($text, ' gallery '), str_contains($text, ' portfolio ') => 'gallery',
            str_contains($text, ' image '), str_contains($text, ' photo ') => 'image',
            str_contains($text, ' map ') => 'map',
            default => 'mixed',
        };

        $layout = array_values(array_filter([
            str_contains($text, ' split ') ? 'split' : null,
            str_contains($text, ' bento ') ? 'bento' : null,
            str_contains($text, ' grid ') ? 'grid' : null,
            str_contains($text, ' fullscreen ') ? 'fullscreen' : null,
            str_contains($text, ' centered ') ? 'centered' : null,
            str_contains($text, ' editorial ') ? 'editorial' : null,
            str_contains($text, ' horizontal ') ? 'horizontal' : null,
            str_contains($text, ' timeline ') ? 'timeline' : null,
            str_contains($text, ' comparison ') ? 'comparison' : null,
        ]));
        if (!$layout) $layout = ['standard'];

        $style = array_values(array_filter([
            str_contains($text, ' premium ') ? 'premium' : null,
            str_contains($text, ' luxury ') ? 'luxury' : null,
            str_contains($text, ' minimal ') ? 'minimal' : null,
            str_contains($text, ' bold ') ? 'bold' : null,
            str_contains($text, ' editorial ') ? 'editorial' : null,
            str_contains($text, ' glass ') ? 'glass' : null,
            str_contains($text, ' modern ') ? 'modern' : null,
        ]));
        if (!$style) $style = ['balanced'];

        $category = strtolower(self::category($type));
        $intent = array_values(array_unique(array_filter([
            in_array($category, ['cta','lead generation','contact','pricing','sales'], true) ? 'conversion' : null,
            in_array($category, ['testimonials','case studies','statistics','proof'], true) ? 'proof' : null,
            in_array($category, ['about','team','blog','posts / updates'], true) ? 'storytelling' : null,
            in_array($category, ['services','features','commerce'], true) ? 'showcase' : null,
            str_contains($text, ' portfolio ') || str_contains($text, ' gallery ') ? 'showcase' : null,
            str_contains($text, ' slider ') || str_contains($text, ' carousel ') ? 'visual storytelling' : null,
        ])));
        if (!$intent) $intent = ['general'];

        $industry = array_values(array_filter([
            preg_match('/hotel|travel|resort|hospitality/', $text) ? 'hospitality' : null,
            preg_match('/real estate|property|apartment/', $text) ? 'real-estate' : null,
            preg_match('/construction|builder|architect/', $text) ? 'construction' : null,
            preg_match('/agency|studio|creative/', $text) ? 'agency' : null,
            preg_match('/saas|software|technology|ai/', $text) ? 'technology' : null,
            preg_match('/shop|product|commerce|store/', $text) ? 'ecommerce' : null,
            preg_match('/restaurant|food|dining|cafe/', $text) ? 'restaurant' : null,
        ]));
        if (!$industry) $industry = ['universal'];

        $position = [];
        if (str_starts_with($type, 'hero_') || str_starts_with($type, 'mini_hero_')) $position[] = 'top';
        if (str_starts_with($type, 'footer_')) $position[] = 'bottom';
        if (!$position) $position = ['mid-page','flexible'];

        $capabilities = array_values(array_unique(array_filter([
            $media === 'slider' ? 'supports-slider' : null,
            $media === 'video' ? 'supports-video' : null,
            in_array($media, ['image','gallery','slider','mixed'], true) ? 'supports-images' : null,
            str_contains($text, ' parallax ') ? 'supports-parallax' : null,
            str_contains($text, ' form ') || in_array($category, ['contact','lead generation'], true) ? 'supports-form' : null,
            str_contains($text, ' carousel ') || str_contains($text, ' slider ') ? 'supports-multiple-items' : null,
            (str_starts_with($type, 'hero_') || str_starts_with($type, 'mini_hero_')) ? 'opening-section-safe' : null,
            'supports-universal-background-image',
            'supports-smart-background-overlay',
            'supports-luna-design-overrides',
            'supports-luna-responsive-guardrails',
            'supports-luna-relative-editing',
            'supports-luna-reference-editing',
            'supports-luna-self-qa',
            'supports-luna-verified-execution',
            'supports-luna-site-memory',
            'supports-luna-multi-operation-planning',
            'supports-luna-page-art-direction',
            'supports-luna-cross-page-reference',
        ])));

        $derived = compact('aliases','media','layout','style','intent','industry','position','capabilities');
        $derived['industry_fit'] = $derived['industry']; unset($derived['industry']);
        $derived['position_fit'] = $derived['position']; unset($derived['position']);

        foreach (['aliases','layout','style','intent','industry_fit','position_fit','capabilities'] as $key) {
            if (isset($override[$key]) && is_array($override[$key])) {
                $derived[$key] = array_values(array_unique(array_merge($derived[$key], $override[$key])));
            }
        }
        if (isset($override['media']) && is_string($override['media']) && $override['media'] !== '') {
            $derived['media'] = $override['media'];
        }

        return $derived;
    }

    private static function description(string $type): string
    {
        return match (self::category($type)) {
            'Mini Heroes' => 'A compact page introduction for shops, blogs, archives, services, and inner pages.',
            'Hero' => 'A polished opening section designed to create a strong first impression.',
            'Services' => 'Present your services clearly with a reusable, conversion-friendly layout.',
            'Agency' => 'Premium agency-focused sections for client experience, white label, management, and operational showcase.',
            'AI' => 'Premium AI-focused sections for prompts, workflows, assistants, timelines, and generation experiences.',
            'About' => 'Tell the company story, purpose, people, and proof through a premium editorial layout.',
            'Features' => 'Explain an important benefit with balanced content and imagery.',
            'CTA' => 'Drive one clear next action with a focused, premium conversion section.',
            'Pricing' => 'Show packages and pricing in a clear, easy-to-compare format.',
            'Testimonials' => 'Build trust with customer stories and social proof.',
            'Team' => 'Introduce the people behind the business with a professional team layout.',
            'FAQ' => 'Answer common questions in a clean, easy-to-scan section.',
            'Contact' => 'Give visitors a clear path to contact, visit, or enquire.',
            'Case Studies' => 'Show selected work, outcomes, and proof of capability.',
            'Careers' => 'Share open roles and invite people to join your team.',
            'Posts / Updates' => 'Bind Blog, Events, Projects, or custom content types to reusable dynamic content layouts.',
            'Events' => 'Promote upcoming events, sessions, and important dates.',
            'Blog' => 'Add editorial content, resources, or newsletter promotion.',
            'Footer' => 'Finish the page with premium navigation, trust, and conversion details.',
            'Commerce' => 'Connect live catalog data to a reusable storefront section.',
            'Statistics' => 'Present verified metrics, trends, milestones, and measurable proof in premium layouts.',
            'Proof' => 'Highlight your process, results, experience, and measurable proof.',
            default => 'A reusable premium section for your Cosmic CMS pages.',
        };
    }
}
