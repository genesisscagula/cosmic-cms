<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PageStyleRegistry
{
    public const CREDIT_COST = 20;

    /** @return array<string, array{label:string,direction:string,description:string,pattern:array<int,string>}> */
    public static function all(): array
    {
        return [
            'balanced' => self::style('Balanced', 'premium', 'A flexible default that repeats a branded moment, white space, surface depth and white space.', ['primary','white','surface','white','primary','white','surface','white','primary','white','surface','white','primary','white','surface']),
            'clean' => self::style('Clean', 'clean', 'Crisp spacing with a calm, trustworthy rhythm.', ['white','surface','primary','white','surface','white','primary','surface','white','primary','white','surface','primary','white','surface']),
            'premium' => self::style('Premium', 'premium', 'Confident contrast with an elevated visual cadence.', ['primary','white','surface','primary','surface','white','primary','white','surface','white','primary','surface','white','primary','surface']),
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public static function suggestions(?string $industry, ?string $currentStyle = null): array
    {
        $styles = self::all();
        return collect(['balanced', 'clean', 'premium'])
            ->map(fn (string $key) => ['key' => $key, ...$styles[$key]])
            ->values()
            ->all();
    }

    /** Legacy/removed page styles resolve safely to Balanced without a migration. */
    public static function normalize(?string $style): string
    {
        $style = Str::lower(trim((string) $style));
        return isset(self::all()[$style]) ? $style : 'balanced';
    }

    public static function pattern(?string $style): array
    {
        return self::all()[self::normalize($style)]['pattern'];
    }

    public static function exists(string $style): bool
    {
        return isset(self::all()[$style]);
    }

    private static function style(string $label, string $direction, string $description, array $pattern): array
    {
        return compact('label', 'direction', 'description', 'pattern');
    }
}
