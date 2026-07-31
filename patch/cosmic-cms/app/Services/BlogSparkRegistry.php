<?php

namespace App\Services;

use Throwable;

/**
 * Central registry for the free Blog Spark layouts.
 *
 * Keeping the available variants here means future free or premium Spark
 * additions only need a registry update; page creation does not need to know
 * how many layouts belong to each section group.
 */
class BlogSparkRegistry
{
    private const GROUPS = [
        'blog_mini_hero' => [
            'mini-header-01',
            'mini-header-02',
            'mini-header-03',
        ],
        'blog_hub' => [
            'blog-cards-01',
            'blog-cards-02',
            'blog-cards-03',
        ],
        'newsletter_cta' => [
            'newsletter-01',
            'newsletter-02',
            'newsletter-03',
        ],
        'latest_resources' => [
            'resources-01',
            'resources-02',
            'resources-03',
        ],
    ];

    /**
     * @return array<int, string>
     */
    public static function variants(string $blockType): array
    {
        return self::GROUPS[$blockType] ?? [];
    }

    public static function defaultVariant(string $blockType): ?string
    {
        return self::variants($blockType)[0] ?? null;
    }

    /**
     * Pick one registered layout. The first registered Spark is always the
     * safe fallback, so page creation can never produce a blank layout.
     */
    public static function randomVariant(string $blockType): ?string
    {
        $variants = self::variants($blockType);

        if ($variants === []) {
            return null;
        }

        try {
            return $variants[random_int(0, count($variants) - 1)];
        } catch (Throwable) {
            return $variants[0];
        }
    }
}
