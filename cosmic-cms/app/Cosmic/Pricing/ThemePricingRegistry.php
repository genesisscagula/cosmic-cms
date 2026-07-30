<?php

namespace App\Cosmic\Pricing;

class ThemePricingRegistry
{
    public static function all(): array
    {
        return [
            'emerald' => ['label' => 'Emerald', 'tier' => 'core', 'credits' => 1],
            'ocean' => ['label' => 'Ocean', 'tier' => 'core', 'credits' => 1],
            'indigo' => ['label' => 'Indigo', 'tier' => 'core', 'credits' => 1],
            'amber' => ['label' => 'Amber', 'tier' => 'core', 'credits' => 1],
            'teal' => ['label' => 'Teal', 'tier' => 'core', 'credits' => 1],
            'coffee' => ['label' => 'Coffee', 'tier' => 'growth', 'credits' => 2],
            'rose' => ['label' => 'Rose', 'tier' => 'growth', 'credits' => 2],
            'forest' => ['label' => 'Forest', 'tier' => 'growth', 'credits' => 2],
            'terracotta' => ['label' => 'Terracotta', 'tier' => 'growth', 'credits' => 2],
            'charcoal' => ['label' => 'Charcoal', 'tier' => 'growth', 'credits' => 2],
            'midnight' => ['label' => 'Midnight', 'tier' => 'signature', 'credits' => 3],
            'navy' => ['label' => 'Navy', 'tier' => 'signature', 'credits' => 3],
            'obsidian' => ['label' => 'Obsidian', 'tier' => 'signature', 'credits' => 4],
            'espresso' => ['label' => 'Espresso', 'tier' => 'signature', 'credits' => 4],
            'violet' => ['label' => 'Violet', 'tier' => 'signature', 'credits' => 5],
            'ruby' => ['label' => 'Ruby', 'tier' => 'signature', 'credits' => 5],
            'asphalt' => ['label' => 'Asphalt', 'tier' => 'signature', 'credits' => 5],
        ];
    }

    public static function get(string $theme): array
    {
        return self::all()[$theme] ?? ['label' => str($theme)->headline()->toString(), 'tier' => 'growth', 'credits' => 2];
    }

    public static function cost(string $theme): int
    {
        return (int) self::get($theme)['credits'];
    }
}
