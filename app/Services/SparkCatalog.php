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
                if (($item['retired'] ?? false) === true) {
                    return null;
                }

                $collection = (string) ($item['category'] ?? 'growth');
                $override = (array) ($overrides[$type] ?? []);

                $metadata = self::metadata($type, $override);

                return [
                    'key' => $type,
                    'name' => self::displayName($type, $item['label'] ?? null),
                    'description' => self::description($type),
                    'category' => self::category($type),
                    'ai_metadata_version' => 2,
                    'semantic_type' => $metadata['semantic_type'],
                    'aliases' => $metadata['aliases'],
                    'traits' => $metadata['traits'],
                    'use_cases' => $metadata['use_cases'],
                    'media' => $metadata['media'],
                    'layout' => $metadata['layout'],
                    'style' => $metadata['style'],
                    'style_traits' => $metadata['style_traits'],
                    'visual_traits' => $metadata['visual_traits'],
                    'intent' => $metadata['intent'],
                    'industry_fit' => $metadata['industry_fit'],
                    'position_fit' => $metadata['position_fit'],
                    'capabilities' => $metadata['capabilities'],
                    'search_terms' => $metadata['search_terms'],
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
            str_starts_with($type, 'services_'), str_starts_with($type, 'service_') => 'Services',
            str_starts_with($type, 'about_') => 'About',
            str_starts_with($type, 'feature_'), str_starts_with($type, 'features_') => 'Features',
            str_starts_with($type, 'cta_'), str_contains($type, '_cta') => 'CTA',
            str_contains($type, 'pricing') => 'Pricing',
            str_contains($type, 'testimonial'), str_contains($type, 'review') => 'Testimonials',
            str_contains($type, 'team') => 'Team',
            str_starts_with($type, 'contact_'), str_contains($type, 'location'), str_contains($type, 'inquiry') => 'Contact',
            str_contains($type, 'faq') => 'FAQ',
            str_starts_with($type, 'lead_') => 'Lead Generation',
            str_starts_with($type, 'sales_') => 'Sales',
            str_starts_with($type, 'agency_') => 'Agency',
            str_starts_with($type, 'ai_') => 'AI',
            str_contains($type, 'case_stud') => 'Case Studies',
            str_starts_with($type, 'portfolio_'), str_starts_with($type, 'gallery_') => 'Portfolio',
            str_starts_with($type, 'proof_'), str_starts_with($type, 'trust_'), str_starts_with($type, 'stats_') => 'Proof',
            str_starts_with($type, 'process_') => 'Process',
            str_starts_with($type, 'brand_') => 'Brand',
            str_contains($type, 'job') => 'Careers',
            str_starts_with($type, 'content_') => 'Posts / Updates',
            str_contains($type, 'event') => 'Events',
            str_contains($type, 'blog'), str_contains($type, 'newsletter'), str_contains($type, 'resource') => 'Blog',
            str_starts_with($type, 'footer_') => 'Footer',
            str_starts_with($type, 'commerce_') => 'Commerce',
            default => 'Other',
        };
    }

    /**
     * AI matching metadata v2. Everything here is deterministic and safe to build
     * for the whole Spark registry before Luna ever sees a shortlist.
     */
    private static function metadata(string $type, array $override = []): array
    {
        $words = collect(preg_split('/[_\-]+/', strtolower($type)) ?: [])->filter()->values()->all();
        $text = ' '.implode(' ', $words).' ';
        $category = strtolower(self::category($type));
        $semanticType = self::semanticType($type, $text, $category);

        $aliasGroups = [
            'hero' => ['hero','banner','masthead','opening section','page intro','above the fold'],
            'slider' => ['slider','carousel','slideshow','rotating banner','rotating images','image rotator'],
            'carousel' => ['carousel','slider','slideshow','rotating content'],
            'video' => ['video','motion','background video','video banner','cinematic video'],
            'gallery' => ['gallery','portfolio','showcase','image grid','project gallery','visual showcase'],
            'portfolio' => ['portfolio','selected work','projects','work showcase','case study showcase'],
            'testimonial' => ['testimonial','reviews','customer stories','social proof','client quotes'],
            'pricing' => ['pricing','plans','packages','price comparison','membership plans'],
            'faq' => ['faq','questions','accordion','frequently asked questions','help questions'],
            'services' => ['services','offerings','solutions','what we do','service list'],
            'features' => ['features','benefits','capabilities','product features','feature list'],
            'team' => ['team','people','staff','leadership','our people'],
            'process' => ['process','steps','workflow','how it works','method'],
            'stats' => ['stats','metrics','numbers','proof','results','key figures'],
            'proof' => ['proof','trust','evidence','credibility','results','social proof'],
            'contact' => ['contact','enquiry','inquiry','lead form','get in touch','contact form'],
            'bento' => ['bento','masonry','editorial grid','asymmetric grid'],
            'split' => ['split','two column','two columns','side by side','split layout'],
            'grid' => ['grid','cards','card grid','multi column'],
            'cta' => ['cta','call to action','conversion banner','action section'],
            'about' => ['about','company story','our story','brand story','mission'],
        ];

        $aliases = array_merge($words, [str_replace('_', ' ', strtolower($type)), strtolower(self::displayName($type, null))]);
        foreach ($aliasGroups as $needle => $values) {
            if (str_contains($text, " {$needle} ") || $semanticType === $needle) {
                $aliases = array_merge($aliases, $values);
            }
        }

        $media = match (true) {
            str_contains($text, ' video ') => 'video',
            str_contains($text, ' slider '), str_contains($text, ' carousel ') => 'slider',
            str_contains($text, ' gallery '), str_contains($text, ' portfolio ') => 'gallery',
            str_contains($text, ' image '), str_contains($text, ' photo '), str_contains($text, ' visual '), str_contains($text, ' media ') => 'image',
            str_contains($text, ' map ') => 'map',
            default => 'mixed',
        };

        $layout = array_values(array_filter([
            str_contains($text, ' split ') ? 'split' : null,
            str_contains($text, ' bento ') ? 'bento' : null,
            str_contains($text, ' mosaic ') ? 'mosaic' : null,
            str_contains($text, ' grid ') ? 'grid' : null,
            str_contains($text, ' cards ') ? 'cards' : null,
            str_contains($text, ' fullbleed ') || str_contains($text, ' fullscreen ') ? 'full-bleed' : null,
            str_contains($text, ' centered ') ? 'centered' : null,
            str_contains($text, ' editorial ') ? 'editorial' : null,
            str_contains($text, ' horizontal ') || str_contains($text, ' rail ') ? 'horizontal' : null,
            str_contains($text, ' timeline ') ? 'timeline' : null,
            str_contains($text, ' comparison ') ? 'comparison' : null,
            str_contains($text, ' accordion ') ? 'accordion' : null,
            str_contains($text, ' stack ') ? 'stack' : null,
            str_contains($text, ' staggered ') ? 'staggered' : null,
        ]));
        if ($layout === []) $layout = ['standard'];

        $style = array_values(array_filter([
            str_contains($text, ' premium ') ? 'premium' : null,
            str_contains($text, ' luxury ') ? 'luxury' : null,
            str_contains($text, ' minimal ') ? 'minimal' : null,
            str_contains($text, ' bold ') ? 'bold' : null,
            str_contains($text, ' editorial ') ? 'editorial' : null,
            str_contains($text, ' glass ') ? 'glass' : null,
            str_contains($text, ' modern ') ? 'modern' : null,
            str_contains($text, ' cinematic ') ? 'cinematic' : null,
            str_contains($text, ' visual ') ? 'visual' : null,
        ]));
        if ($style === []) $style = ['balanced'];

        $intent = array_values(array_unique(array_filter([
            in_array($semanticType, ['cta','lead','contact','pricing','sales'], true) ? 'conversion' : null,
            in_array($semanticType, ['testimonials','proof','case-studies'], true) ? 'proof' : null,
            in_array($semanticType, ['about','team','content','brand'], true) ? 'storytelling' : null,
            in_array($semanticType, ['services','features','commerce','portfolio','gallery'], true) ? 'showcase' : null,
            in_array($media, ['slider','gallery'], true) ? 'visual storytelling' : null,
            $semanticType === 'hero' ? 'first impression' : null,
        ])));
        if ($intent === []) $intent = ['general'];

        $industry = array_values(array_filter([
            preg_match('/ hotel | travel | resort | hospitality | room | destination /', $text) ? 'hospitality' : null,
            preg_match('/ realestate | real estate | property | apartment | listing | neighborhood /', $text) ? 'real-estate' : null,
            preg_match('/ construction | builder | architect | site progress /', $text) ? 'construction' : null,
            preg_match('/ agency | studio | creative /', $text) ? 'agency' : null,
            preg_match('/ saas | software | technology | ai /', $text) ? 'technology' : null,
            preg_match('/ shop | product | commerce | store /', $text) ? 'ecommerce' : null,
            preg_match('/ restaurant | food | dining | cafe | dish | menu /', $text) ? 'restaurant' : null,
            preg_match('/ dental | dentist | clinic /', $text) ? 'dental' : null,
            preg_match('/ medical | healthcare | care pathway | facility /', $text) ? 'medical' : null,
            preg_match('/ finance | financial | loan | mortgage /', $text) ? 'finance' : null,
            preg_match('/ lawyer | legal | attorney /', $text) ? 'legal' : null,
        ]));
        if ($industry === []) $industry = ['universal'];

        $position = [];
        if ($semanticType === 'hero') $position[] = 'top';
        if ($semanticType === 'footer') $position[] = 'bottom';
        if ($position === []) $position = ['mid-page','flexible'];

        $visualTraits = array_values(array_unique(array_filter([
            in_array('full-bleed', $layout, true) ? 'full-bleed' : null,
            in_array($media, ['image','video','gallery','slider'], true) ? 'media-rich' : null,
            str_contains($text, ' overlay ') ? 'overlay' : null,
            str_contains($text, ' cards ') || str_contains($text, ' card ') ? 'cards' : null,
            str_contains($text, ' floating ') ? 'floating' : null,
            str_contains($text, ' overlap ') ? 'overlap' : null,
            str_contains($text, ' asymmetric ') ? 'asymmetric' : null,
            str_contains($text, ' mosaic ') || str_contains($text, ' bento ') ? 'masonry' : null,
            str_contains($text, ' accordion ') ? 'accordion' : null,
            str_contains($text, ' timeline ') || str_contains($text, ' steps ') ? 'sequence' : null,
            $media === 'slider' ? 'interactive-slider' : null,
        ])));
        if ($visualTraits === []) $visualTraits = ['standard-composition'];

        $styleTraits = array_values(array_unique(array_merge($style, array_values(array_filter([
            str_contains($text, ' premium ') ? 'premium-finish' : null,
            str_contains($text, ' editorial ') ? 'editorial-composition' : null,
            str_contains($text, ' cinematic ') ? 'cinematic' : null,
            str_contains($text, ' visual ') || in_array($media, ['image','gallery','slider','video'], true) ? 'visual-first' : null,
        ])))));

        $useCases = self::useCases($semanticType, $industry);
        $traits = array_values(array_unique(array_merge([$semanticType], $layout, $styleTraits, $visualTraits, $intent)));

        $capabilities = array_values(array_unique(array_filter([
            $media === 'slider' ? 'supports-slider' : null,
            $media === 'video' ? 'supports-video' : null,
            in_array($media, ['image','gallery','slider','mixed'], true) ? 'supports-images' : null,
            str_contains($text, ' parallax ') ? 'supports-parallax' : null,
            str_contains($text, ' form ') || in_array($semanticType, ['contact','lead'], true) ? 'supports-form' : null,
            in_array($media, ['slider','gallery'], true) || preg_match('/ cards | grid | list | rows | panels | items /', $text) ? 'supports-multiple-items' : null,
            $semanticType === 'hero' ? 'opening-section-safe' : null,
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

        $derived = [
            'semantic_type' => $semanticType,
            'aliases' => $aliases,
            'traits' => $traits,
            'use_cases' => $useCases,
            'media' => $media,
            'layout' => $layout,
            'style' => $style,
            'style_traits' => $styleTraits,
            'visual_traits' => $visualTraits,
            'intent' => $intent,
            'industry_fit' => $industry,
            'position_fit' => $position,
            'capabilities' => $capabilities,
        ];

        foreach (['aliases','traits','use_cases','layout','style','style_traits','visual_traits','intent','industry_fit','position_fit','capabilities'] as $key) {
            if (array_key_exists($key, $override)) {
                $derived[$key] = array_merge($derived[$key], self::listValue($override[$key]));
            }
            $derived[$key] = self::normalizeTerms($derived[$key]);
        }
        if (isset($override['media']) && is_string($override['media']) && trim($override['media']) !== '') {
            $derived['media'] = strtolower(trim($override['media']));
        }
        if (isset($override['semantic_type']) && is_string($override['semantic_type']) && trim($override['semantic_type']) !== '') {
            $derived['semantic_type'] = strtolower(trim($override['semantic_type']));
        }

        $derived['search_terms'] = self::normalizeTerms(array_merge(
            [$type, str_replace('_', ' ', $type), self::displayName($type, null), self::category($type), $derived['semantic_type'], $derived['media']],
            $derived['aliases'], $derived['traits'], $derived['use_cases'], $derived['layout'], $derived['style_traits'],
            $derived['visual_traits'], $derived['intent'], $derived['industry_fit']
        ));

        return $derived;
    }

    private static function semanticType(string $type, string $text, string $category): string
    {
        return match (true) {
            str_starts_with($type, 'mini_hero_'), str_starts_with($type, 'hero_'), $type === 'image_cta_banner' => 'hero',
            str_starts_with($type, 'services_'), str_starts_with($type, 'service_'), str_contains($text, ' service '), str_contains($text, ' treatment '), str_contains($text, ' care pathways '), str_contains($text, ' experience cards ') => 'services',
            str_starts_with($type, 'features_'), str_starts_with($type, 'feature_'), str_contains($text, ' capability ') => 'features',
            str_starts_with($type, 'about_'), str_contains($text, ' story ') && !str_contains($text, ' case story ') && !str_contains($text, ' destination story ') => 'about',
            str_starts_with($type, 'cta_'), str_contains($type, '_cta') => 'cta',
            str_contains($type, 'pricing') => 'pricing',
            str_contains($type, 'testimonial'), str_contains($type, 'review') => 'testimonials',
            str_contains($type, 'team') => 'team',
            str_starts_with($type, 'contact_'), str_contains($text, ' inquiry '), str_contains($text, ' availability board ') => 'contact',
            str_contains($type, 'faq') => 'faq',
            str_starts_with($type, 'lead_') => 'lead',
            str_starts_with($type, 'sales_') => 'sales',
            str_starts_with($type, 'portfolio_'), str_contains($text, ' project '), str_contains($text, ' property '), str_contains($text, ' listing '), str_contains($text, ' room collection '), str_contains($text, ' destination story ') => 'portfolio',
            str_starts_with($type, 'gallery_') => 'gallery',
            str_contains($type, 'case_stud') => 'case-studies',
            str_starts_with($type, 'proof_'), str_starts_with($type, 'trust_'), str_starts_with($type, 'stats_'), str_contains($text, ' metric '), str_contains($text, ' evidence '), str_contains($text, ' site progress ') => 'proof',
            str_starts_with($type, 'process_'), str_contains($text, ' timeline '), str_contains($text, ' steps '), str_contains($text, ' itinerary ') => 'process',
            str_starts_with($type, 'brand_') => 'brand',
            str_starts_with($type, 'content_'), str_contains($type, 'blog'), str_contains($type, 'newsletter'), str_contains($type, 'resource') => 'content',
            str_contains($type, 'event') => 'events',
            str_starts_with($type, 'footer_') => 'footer',
            str_starts_with($type, 'commerce_'), str_contains($text, ' product '), str_contains($text, ' shop '), str_contains($text, ' dishes ') => 'commerce',
            str_contains($type, 'job') => 'careers',
            $category === 'agency' => 'agency',
            $category === 'ai' => 'ai',
            default => 'general',
        };
    }

    private static function useCases(string $semanticType, array $industry): array
    {
        $base = match ($semanticType) {
            'hero' => ['homepage opening','landing page opening','campaign introduction'],
            'services' => ['service discovery','offerings overview'],
            'features' => ['feature explanation','benefit showcase'],
            'about' => ['company story','brand introduction'],
            'cta' => ['lead conversion','next-step conversion'],
            'pricing' => ['plan comparison','purchase decision'],
            'testimonials' => ['social proof','customer trust'],
            'team' => ['team introduction','leadership showcase'],
            'contact', 'lead' => ['lead capture','customer enquiry'],
            'faq' => ['objection handling','support questions'],
            'portfolio', 'gallery', 'case-studies' => ['work showcase','project discovery'],
            'proof' => ['credibility','results proof'],
            'process' => ['process explanation','workflow education'],
            'brand' => ['brand values','brand positioning'],
            'content' => ['content discovery','editorial browsing'],
            'commerce' => ['product discovery','commerce conversion'],
            default => ['general section'],
        };

        foreach ($industry as $value) {
            if ($value !== 'universal') $base[] = $value.' website';
        }

        return self::normalizeTerms($base);
    }

    private static function listValue(mixed $value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && trim($value) !== '') return [$value];
        return [];
    }

    private static function normalizeTerms(array $values): array
    {
        $out = [];
        foreach ($values as $value) {
            if (! is_scalar($value)) continue;
            $value = strtolower(trim((string) $value));
            $value = preg_replace('/\s+/', ' ', $value) ?? $value;
            if ($value === '' || isset($out[$value])) continue;
            $out[$value] = $value;
        }
        return array_values($out);
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
