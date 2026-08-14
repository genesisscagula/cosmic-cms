<?php

namespace App\Cosmic\Pricing;

class ThemePricingRegistry
{
    public static function all(): array
    {
        return [
            'emerald' => ['label' => 'Emerald', 'tier' => 'core', 'credits' => 10],
            'ocean' => ['label' => 'Ocean', 'tier' => 'core', 'credits' => 10],
            'indigo' => ['label' => 'Indigo', 'tier' => 'core', 'credits' => 10],
            'amber' => ['label' => 'Amber', 'tier' => 'core', 'credits' => 10],
            'teal' => ['label' => 'Teal', 'tier' => 'core', 'credits' => 10],
            'coffee' => ['label' => 'Coffee', 'tier' => 'growth', 'credits' => 20],
            'rose' => ['label' => 'Rose', 'tier' => 'growth', 'credits' => 20],
            'forest' => ['label' => 'Forest', 'tier' => 'growth', 'credits' => 20],
            'terracotta' => ['label' => 'Terracotta', 'tier' => 'growth', 'credits' => 20],
            'charcoal' => ['label' => 'Charcoal', 'tier' => 'growth', 'credits' => 20],
            'void' => ['label' => 'Void', 'tier' => 'growth', 'credits' => 20],
            'olive' => ['label' => 'Olive', 'tier' => 'growth', 'credits' => 20],
            'slate' => ['label' => 'Slate', 'tier' => 'growth', 'credits' => 20],
            'midnight' => ['label' => 'Midnight', 'tier' => 'signature', 'credits' => 30],
            'navy' => ['label' => 'Navy', 'tier' => 'signature', 'credits' => 30],
            'sapphire' => ['label' => 'Sapphire', 'tier' => 'signature', 'credits' => 30],
            'obsidian' => ['label' => 'Obsidian', 'tier' => 'signature', 'credits' => 40],
            'espresso' => ['label' => 'Espresso', 'tier' => 'signature', 'credits' => 40],
            'plum' => ['label' => 'Plum', 'tier' => 'signature', 'credits' => 40],
            'violet' => ['label' => 'Violet', 'tier' => 'signature', 'credits' => 50],
            'ruby' => ['label' => 'Ruby', 'tier' => 'signature', 'credits' => 50],
            'asphalt' => ['label' => 'Asphalt', 'tier' => 'signature', 'credits' => 50],
            'burgundy' => ['label' => 'Burgundy', 'tier' => 'signature', 'credits' => 50],
            'sage' => ['label' => 'Sage', 'tier' => 'signature', 'credits' => 50],
            'sandstone' => ['label' => 'Sandstone', 'tier' => 'signature', 'credits' => 50],
            'copper' => ['label' => 'Copper', 'tier' => 'signature', 'credits' => 50],
            'arctic' => ['label' => 'Arctic', 'tier' => 'signature', 'credits' => 50],
            'blush' => ['label' => 'Blush', 'tier' => 'signature', 'credits' => 50],
            'graphite' => ['label' => 'Graphite', 'tier' => 'signature', 'credits' => 50],
            'cobalt' => ['label' => 'Cobalt', 'tier' => 'signature', 'credits' => 50],
            'moss' => ['label' => 'Moss', 'tier' => 'signature', 'credits' => 50],
            'champagne' => ['label' => 'Champagne', 'tier' => 'signature', 'credits' => 50],
        ];
    }

    public static function get(string $theme): array
    {
        return self::all()[$theme] ?? ['label' => str($theme)->headline()->toString(), 'tier' => 'growth', 'credits' => 20];
    }

    public static function cost(string $theme): int
    {
        return (int) self::get($theme)['credits'];
    }
}
