<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

class ThemePlanAccessService
{
    public const STARTER_THEMES = [
        'midnight',
        'emerald',
        'ocean',
        'coffee',
        'rose',
    ];

    public const GROWTH_THEMES = [
        ...self::STARTER_THEMES,
        'indigo',
        'amber',
        'charcoal',
        'violet',
        'teal',
    ];

    public function allowedThemeKeys(?string $planKey): ?array
    {
        return match ($this->normalizePlanKey($planKey)) {
            'pro', 'agency_pro' => null,
            'growth', 'agency_growth' => self::GROWTH_THEMES,
            default => self::STARTER_THEMES,
        };
    }

    public function allows(?string $planKey, string $themeKey): bool
    {
        $allowed = $this->allowedThemeKeys($planKey);

        return $allowed === null || in_array($themeKey, $allowed, true);
    }

    public function assertCanUse(User $user, string $themeKey, ?string $currentThemeKey = null): void
    {
        // A downgraded account may keep its current published theme, but cannot
        // switch to another theme outside its current plan allowance.
        if ($currentThemeKey !== null && $currentThemeKey === $themeKey) {
            return;
        }

        if ($this->allows($user->plan_key, $themeKey)) {
            return;
        }

        $nextPlan = in_array($this->normalizePlanKey($user->plan_key), ['starter', 'agency_starter'], true)
            ? 'Growth'
            : 'Pro';

        throw ValidationException::withMessages([
            'theme_settings.primary' => "This theme is locked on your current plan. Upgrade to {$nextPlan} to use it.",
        ]);
    }

    private function normalizePlanKey(?string $planKey): string
    {
        $key = strtolower(trim((string) $planKey));

        return $key !== '' ? $key : 'starter';
    }
}
