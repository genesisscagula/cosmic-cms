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
            'clean' => self::style('Clean', 'clean', 'Crisp spacing with a calm, trustworthy rhythm.', ['white','surface','white','surface','white','surface','white','surface','white','surface','white','surface','white','surface','white']),
            'minimal' => self::style('Minimal', 'clean', 'Quiet, spacious and deliberately restrained.', ['white','surface','white','surface','primary','white','surface','white','surface','primary','white','surface','white','primary','surface']),
            'editorial' => self::style('Editorial', 'clean', 'A magazine-inspired flow with strong breathing room.', ['white','surface','white','primary','white','surface','white','primary','surface','white','primary','white','surface','primary','surface']),
            'airy' => self::style('Airy', 'clean', 'Bright sections and generous visual pauses.', ['white','white','surface','white','primary','white','white','surface','white','primary','surface','white','white','primary','surface']),
            'balanced' => self::style('Balanced', 'clean', 'An even mix of light, surface and brand moments.', ['white','surface','primary','surface','white','primary','white','surface','primary','white','surface','primary','white','surface','primary']),
            'corporate' => self::style('Corporate', 'clean', 'Structured, polished and suitable for established teams.', ['primary','white','surface','white','primary','surface','white','primary','white','surface','primary','white','surface','primary','surface']),
            'classic' => self::style('Classic', 'clean', 'Familiar hierarchy with dependable contrast.', ['white','primary','white','surface','white','primary','surface','white','primary','white','surface','white','primary','surface','white']),

            'premium' => self::style('Premium', 'premium', 'Confident contrast with an elevated visual cadence.', ['primary','white','surface','primary','surface','white','primary','white','surface','white','primary','surface','white','primary','surface']),
            'luxury' => self::style('Luxury', 'premium', 'Rich contrast balanced by refined light sections.', ['primary','surface','white','primary','surface','white','primary','white','surface','primary','surface','white','primary','white','surface']),
            'executive' => self::style('Executive', 'premium', 'Authoritative, controlled and presentation-ready.', ['primary','white','primary','surface','white','primary','surface','white','primary','surface','white','primary','white','surface','primary']),
            'refined' => self::style('Refined', 'premium', 'Soft sophistication without excessive decoration.', ['surface','white','primary','white','surface','primary','white','surface','white','primary','surface','white','primary','white','surface']),
            'glass' => self::style('Glass', 'premium', 'Light surfaces punctuated by immersive brand sections.', ['primary','surface','white','surface','primary','white','surface','primary','white','surface','primary','white','surface','white','primary']),
            'cinematic' => self::style('Cinematic', 'premium', 'Immersive brand moments with dramatic pacing.', ['primary','primary','white','surface','primary','white','primary','surface','white','primary','white','surface','primary','white','surface']),

            'bold' => self::style('Bold', 'bold', 'High-impact contrast and decisive section changes.', ['primary','white','primary','surface','primary','white','primary','surface','primary','white','primary','surface','primary','white','surface']),
            'creative' => self::style('Creative', 'bold', 'Expressive pacing with varied visual energy.', ['primary','surface','white','primary','white','surface','primary','surface','white','primary','surface','primary','white','surface','white']),
            'dynamic' => self::style('Dynamic', 'bold', 'Frequent contrast shifts that keep the page moving.', ['primary','white','surface','primary','white','primary','surface','white','primary','surface','primary','white','surface','primary','white']),
            'contrast' => self::style('High Contrast', 'bold', 'Alternating light and brand sections for maximum clarity.', ['primary','white','primary','white','surface','primary','white','primary','surface','white','primary','white','primary','surface','white']),
            'immersive' => self::style('Immersive', 'bold', 'Strong branded sections with cinematic transitions.', ['primary','primary','surface','white','primary','surface','primary','white','primary','surface','white','primary','surface','primary','white']),
            'startup' => self::style('Startup', 'bold', 'Modern momentum with clear product storytelling.', ['primary','white','surface','white','primary','white','surface','primary','white','surface','primary','white','surface','primary','white']),
            'agency' => self::style('Agency', 'bold', 'Portfolio-forward rhythm with confident brand blocks.', ['primary','surface','white','primary','white','surface','primary','white','primary','surface','white','primary','surface','white','primary']),
        ];
    }

    /** @return array<int, array<string,mixed>> */
    public static function suggestions(?string $industry, ?string $currentStyle = null): array
    {
        $industry = Str::lower(trim((string) $industry));
        $keys = match (true) {
            Str::contains($industry, ['technology','software','saas','cyber','digital','web','it']) => ['editorial','premium','startup'],
            Str::contains($industry, ['medical','dental','clinic','health']) => ['clean','minimal','corporate'],
            Str::contains($industry, ['hotel','travel','yacht','luxury','real estate']) => ['luxury','refined','cinematic'],
            Str::contains($industry, ['restaurant','coffee','bakery','salon','creative']) => ['creative','editorial','airy'],
            Str::contains($industry, ['construction','roofing','automotive','electrician','plumbing','landscaping']) => ['bold','corporate','dynamic'],
            Str::contains($industry, ['law','finance','education']) => ['executive','corporate','balanced'],
            default => ['clean','premium','creative'],
        };

        if ($currentStyle && in_array($currentStyle, $keys, true)) {
            $fallback = ['balanced','agency','refined','dynamic'];
            foreach ($fallback as $candidate) {
                if ($candidate !== $currentStyle && ! in_array($candidate, $keys, true)) {
                    $keys[2] = $candidate;
                    break;
                }
            }
        }

        $styles = self::all();
        return collect(array_values(array_unique($keys)))
            ->take(3)
            ->map(fn (string $key) => ['key' => $key, ...$styles[$key]])
            ->values()
            ->all();
    }

    public static function pattern(?string $style): array
    {
        return self::all()[$style]['pattern'] ?? ['primary','white','surface','white'];
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
