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
            str_starts_with($type, 'hero_'), $type === 'image_cta_banner' => 'Hero',
            str_starts_with($type, 'services_') => 'Services',
            str_starts_with($type, 'feature_') => 'Features',
            str_contains($type, 'pricing') => 'Pricing',
            str_contains($type, 'testimonial') => 'Testimonials',
            str_contains($type, 'team') => 'Team',
            str_contains($type, 'faq') => 'FAQ',
            str_contains($type, 'contact'), str_contains($type, 'location') => 'Contact',
            str_contains($type, 'case_stud') => 'Case Studies',
            str_contains($type, 'job') => 'Careers',
            str_contains($type, 'event') => 'Events',
            str_contains($type, 'blog'), str_contains($type, 'newsletter'), str_contains($type, 'resource') => 'Blog',
            str_contains($type, 'stats'), str_contains($type, 'process') => 'Proof',
            default => 'Other',
        };
    }

    private static function description(string $type): string
    {
        return match (self::category($type)) {
            'Hero' => 'A polished opening section designed to create a strong first impression.',
            'Services' => 'Present your services clearly with a reusable, conversion-friendly layout.',
            'Features' => 'Explain an important benefit with balanced content and imagery.',
            'Pricing' => 'Show packages and pricing in a clear, easy-to-compare format.',
            'Testimonials' => 'Build trust with customer stories and social proof.',
            'Team' => 'Introduce the people behind the business with a professional team layout.',
            'FAQ' => 'Answer common questions in a clean, easy-to-scan section.',
            'Contact' => 'Give visitors a clear path to contact, visit, or enquire.',
            'Case Studies' => 'Show selected work, outcomes, and proof of capability.',
            'Careers' => 'Share open roles and invite people to join your team.',
            'Events' => 'Promote upcoming events, sessions, and important dates.',
            'Blog' => 'Add editorial content, resources, or newsletter promotion.',
            'Proof' => 'Highlight your process, results, experience, and measurable proof.',
            default => 'A reusable premium section for your Cosmic CMS pages.',
        };
    }
}
