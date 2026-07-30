<?php

namespace App\Cosmic\Pricing;

class ThemePricingRegistry
{
    public static function all(): array
    {
        return [
            'emerald' => ['label' => 'Emerald', 'tier' => 'core', 'credits' => 100],
            'ocean' => ['label' => 'Ocean', 'tier' => 'core', 'credits' => 100],
            'indigo' => ['label' => 'Indigo', 'tier' => 'core', 'credits' => 100],
            'amber' => ['label' => 'Amber', 'tier' => 'core', 'credits' => 100],
            'teal' => ['label' => 'Teal', 'tier' => 'core', 'credits' => 100],
            'coffee' => ['label' => 'Coffee', 'tier' => 'growth', 'credits' => 200],
            'rose' => ['label' => 'Rose', 'tier' => 'growth', 'credits' => 200],
            'forest' => ['label' => 'Forest', 'tier' => 'growth', 'credits' => 200],
            'terracotta' => ['label' => 'Terracotta', 'tier' => 'growth', 'credits' => 200],
            'charcoal' => ['label' => 'Charcoal', 'tier' => 'growth', 'credits' => 200],
            'midnight' => ['label' => 'Midnight', 'tier' => 'signature', 'credits' => 300],
            'navy' => ['label' => 'Navy', 'tier' => 'signature', 'credits' => 300],
            'obsidian' => ['label' => 'Obsidian', 'tier' => 'signature', 'credits' => 400],
            'espresso' => ['label' => 'Espresso', 'tier' => 'signature', 'credits' => 400],
            'violet' => ['label' => 'Violet', 'tier' => 'signature', 'credits' => 500],
            'ruby' => ['label' => 'Ruby', 'tier' => 'signature', 'credits' => 500],
            'asphalt' => ['label' => 'Asphalt', 'tier' => 'signature', 'credits' => 500],
        ];
    }

    public static function get(string $theme): array
    {
        return self::all()[$theme] ?? ['label' => str($theme)->headline()->toString(), 'tier' => 'growth', 'credits' => 200];
    }

    public static function cost(string $theme): int
    {
        return (int) self::get($theme)['credits'];
    }
}
