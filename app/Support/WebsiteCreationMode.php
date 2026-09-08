<?php

namespace App\Support;

final class WebsiteCreationMode
{
    public const LUNA_AI = 'luna_ai';
    public const SPARKS = 'sparks';
    public const PAGE_BUILDER = 'page_builder';
    public const MARKETPLACE = 'marketplace';

    public const DASHBOARD_MODES = [
        self::LUNA_AI,
        self::SPARKS,
        self::PAGE_BUILDER,
    ];

    public const ALL = [
        self::LUNA_AI,
        self::SPARKS,
        self::PAGE_BUILDER,
        self::MARKETPLACE,
    ];

    public static function normalize(?string $mode, bool $hasTemplate = false): string
    {
        if ($hasTemplate) {
            return self::SPARKS;
        }

        return in_array($mode, self::DASHBOARD_MODES, true)
            ? $mode
            : self::LUNA_AI;
    }

    public static function isKnown(?string $mode): bool
    {
        return in_array($mode, self::ALL, true);
    }

    public static function label(?string $mode): ?string
    {
        return match ($mode) {
            self::LUNA_AI => 'Luna AI',
            self::SPARKS => 'Sparks',
            self::PAGE_BUILDER => 'Page Builder',
            self::MARKETPLACE => 'Marketplace',
            default => null,
        };
    }
}
