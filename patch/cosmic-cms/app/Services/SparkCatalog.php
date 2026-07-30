<?php

namespace App\Services;

use App\Cosmic\Pricing\BlockPricingRegistry;

class SparkCatalog
{
    public static function all(): array
    {
        return collect(BlockPricingRegistry::all())
            ->map(function (array $item, string $type) {
                return [
                    'key' => $type,
                    'name' => self::displayName($type, $item['label'] ?? null),
                    'description' => self::description($type),
                    'category' => self::category($type),
                    'collection' => $item['category'] ?? 'growth',
                    'credits' => (int) ($item['credits'] ?? 20),
                    'featured' => in_array($type, [
                        'hero_floating_cards', 'hero_video_background', 'services_bento',
                        'pricing_cards', 'testimonials_carousel', 'case_studies_grid',
                    ], true),
                ];
            })
            ->values()
            ->all();
    }

    public static function find(string $key): ?array
    {
        return collect(self::all())->firstWhere('key', $key);
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
