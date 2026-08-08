<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use Illuminate\Validation\ValidationException;

class ThemePlanAccessService
{
    public const STARTER_THEMES = [
        'midnight',
        'emerald',
        'ocean',
        'coffee',
        'terracotta',
    ];

    public const GROWTH_THEMES = [
        ...self::STARTER_THEMES,
        'indigo',
        'amber',
        'charcoal',
        'violet',
        'teal',
    ];

    public function allowedThemeKeys(?string $planKey, ?string $includedThemeKey = null): ?array
    {
        $allowed = match ($this->normalizePlanKey($planKey)) {
            'pro', 'agency_pro' => null,
            'growth', 'agency_growth' => self::GROWTH_THEMES,
            default => self::STARTER_THEMES,
        };

        if ($allowed === null) {
            return null;
        }

        return $this->includeThemeWithinLimit($allowed, $includedThemeKey);
    }

    public function allowedThemeKeysForWebsite(?string $planKey, ?Website $website): ?array
    {
        return $this->allowedThemeKeys($planKey, $this->includedThemeKeyForWebsite($website));
    }

    public function includedThemeKeyForWebsite(?Website $website): ?string
    {
        if (! $website) {
            return null;
        }

        $seed = trim((string) data_get($website->settings, 'theme_entitlement_seed', ''));
        if ($seed !== '') {
            return $seed;
        }

        // Compatibility for websites created before this patch: preserve the
        // theme they already have instead of unexpectedly locking it. New trial
        // purchases persist a stable seed in website.settings.
        $current = trim((string) data_get($website->theme_settings, 'primary', ''));

        return $current !== '' ? $current : null;
    }

    public function allows(?string $planKey, string $themeKey, ?string $includedThemeKey = null): bool
    {
        $allowed = $this->allowedThemeKeys($planKey, $includedThemeKey);

        return $allowed === null || in_array($themeKey, $allowed, true);
    }

    public function assertCanUse(
        User $user,
        string $themeKey,
        ?string $currentThemeKey = null,
        ?string $includedThemeKey = null,
    ): void
    {
        // A downgraded account may keep its current published theme, but cannot
        // switch to another theme outside its current plan allowance.
        if ($currentThemeKey !== null && $currentThemeKey === $themeKey) {
            return;
        }

        if ($this->allows($user->effectivePlanKey(), $themeKey, $includedThemeKey)) {
            return;
        }

        $nextPlan = in_array($this->normalizePlanKey($user->effectivePlanKey()), ['starter', 'agency_starter'], true)
            ? 'Growth'
            : 'Pro';

        throw ValidationException::withMessages([
            'theme_settings.primary' => "This theme is locked on your current plan. Upgrade to {$nextPlan} to use it.",
        ]);
    }


    private function includeThemeWithinLimit(array $allowed, ?string $includedThemeKey): array
    {
        $includedThemeKey = strtolower(trim((string) $includedThemeKey));
        if ($includedThemeKey === '' || in_array($includedThemeKey, $allowed, true)) {
            return $allowed;
        }

        // Keep the advertised plan count unchanged. The trial-generated theme
        // takes the final slot instead of granting a sixth/eleventh theme.
        array_pop($allowed);
        $allowed[] = $includedThemeKey;

        return array_values(array_unique($allowed));
    }

    private function normalizePlanKey(?string $planKey): string
    {
        $key = strtolower(trim((string) $planKey));

        return match ($key) {
            'agency_basic', 'basic_agency', 'business' => 'agency_starter',
            'agency' => 'agency_growth',
            '', 'free', 'basic' => 'starter',
            default => $key,
        };
    }
}
