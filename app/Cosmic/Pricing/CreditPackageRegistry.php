<?php

namespace App\Cosmic\Pricing;

class CreditPackageRegistry
{
    public static function all(): array
    {
        return [
            'starter' => ['label' => 'Starter', 'credits' => 50, 'price_usd' => 5],
            'creator' => ['label' => 'Creator', 'credits' => 120, 'price_usd' => 10],
            'growth' => ['label' => 'Growth', 'credits' => 300, 'price_usd' => 20],
            'agency' => ['label' => 'Agency', 'credits' => 900, 'price_usd' => 50],
            'power' => ['label' => 'Power Pack', 'credits' => 2000, 'price_usd' => 99],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
