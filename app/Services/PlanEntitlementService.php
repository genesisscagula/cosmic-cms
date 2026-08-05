<?php

namespace App\Services;

use App\Models\User;
use App\Cosmic\Plans\PlanTransitionMatrix;

class PlanEntitlementService
{
    private const TEMPLATE_LEVELS = [
        'starter' => 10,
        'growth' => 20,
        'pro' => 30,
        'agency_starter' => 110,
        'agency_growth' => 120,
        'agency_pro' => 130,
        'all' => PHP_INT_MAX,
    ];

    public function __construct(
        private readonly PlanCapabilityService $capabilities,
        private readonly PlanRegistry $plans,
        private readonly PersonalPlanEntitlementService $personalEntitlements,
        private readonly AgencyPlanEntitlementService $agencyEntitlements,
        private readonly PlanTransitionMatrix $transitions,
    ) {
    }

    public function summary(User $user): array
    {
        $resolved = $this->capabilities->forUser($user);
        $values = (array) ($resolved['capabilities'] ?? []);

        return [
            ...$resolved,
            'page_limit_label' => $this->limitLabel($values['max_pages_per_site'] ?? null),
            'spark_limit_label' => $this->limitLabel($values['max_sparks_per_site'] ?? null),
            'owned_spark_limit_label' => $this->limitLabel($values['max_owned_sparks'] ?? null),
            'template_limit_label' => $this->limitLabel($values['template_limit'] ?? null),
            'is_personal' => ($resolved['plan_family'] ?? 'personal') === 'personal',
            'is_agency' => ($resolved['plan_family'] ?? 'personal') === 'agency',
            'personal_entitlements' => ($resolved['plan_family'] ?? 'personal') === 'personal'
                ? $this->personalEntitlements->summary($user)
                : null,
            'agency_entitlements' => ($resolved['plan_family'] ?? 'personal') === 'agency'
                ? $this->agencyEntitlements->summary($user)
                : null,
        ];
    }

    public function canUseTemplate(User $user, string $requiredLevel, ?int $accessibleIndex = null): bool
    {
        $capabilities = $this->plans->capabilities($user->plan_key);
        $limit = $capabilities['template_limit'] ?? null;

        if ($limit !== null && $accessibleIndex !== null && $accessibleIndex >= (int) $limit) {
            return false;
        }

        return $this->levelAllows(
            (string) ($capabilities['template_access_level'] ?? 'starter'),
            $requiredLevel,
            self::TEMPLATE_LEVELS,
        );
    }


    /**
     * Canonical server-side decision for using a catalog template.
     *
     * @return array<string, mixed>
     */
    public function templateAccess(User $user, string $template, WebsiteTemplateCatalog $catalog): array
    {
        $definition = $catalog->definition($template);
        $index = $catalog->catalogIndex($template);
        $capabilities = $this->plans->capabilities($user->plan_key);
        $requiredLevel = (string) ($definition['minimum_plan'] ?? 'pro');
        $accountLevel = (string) ($capabilities['template_access_level'] ?? 'starter');
        $limit = $capabilities['template_limit'] ?? null;

        $base = [
            'allowed' => false,
            'template' => $template,
            'required_level' => $requiredLevel,
            'account_level' => $accountLevel,
            'catalog_index' => $index,
            'reason' => null,
            'message' => null,
            'upgrade' => null,
        ];

        if (! $definition) {
            return [...$base, 'reason' => 'unknown_template', 'message' => 'The selected website template is not available.'];
        }

        if ($limit !== null && $index !== null && $index >= (int) $limit) {
            return [
                ...$base,
                'reason' => 'template_limit',
                'message' => sprintf('Your current plan includes access to %d templates. Upgrade your plan to use this template.', (int) $limit),
                'upgrade' => $this->recommendedTemplateUpgrade($user, $requiredLevel, $index, (string) ($definition['collection'] ?? 'personal')),
            ];
        }

        if (! $this->levelAllows($accountLevel, $requiredLevel, self::TEMPLATE_LEVELS)) {
            return [
                ...$base,
                'reason' => 'access_level',
                'message' => sprintf('This template requires the %s template tier. Upgrade your plan to continue.', str_replace('_', ' ', $requiredLevel)),
                'upgrade' => $this->recommendedTemplateUpgrade($user, $requiredLevel, $index, (string) ($definition['collection'] ?? 'personal')),
            ];
        }

        return [...$base, 'allowed' => true];
    }


    /**
     * Find the least expensive plan that can use the requested template.
     *
     * @return array<string, mixed>|null
     */
    private function recommendedTemplateUpgrade(User $user, string $requiredLevel, ?int $catalogIndex, string $collection): ?array
    {
        $preferredFamily = str_starts_with($collection, 'agency') ? 'agency' : 'personal';
        $candidates = collect($this->plans->all())
            ->filter(function (array $plan, string $key) use ($user, $requiredLevel, $catalogIndex, $preferredFamily) {
                if ($key === $this->plans->normalizeKey($user->plan_key)) {
                    return false;
                }

                if (($plan['family'] ?? 'personal') !== $preferredFamily) {
                    return false;
                }

                $capabilities = (array) ($plan['capabilities'] ?? []);
                $limit = $capabilities['template_limit'] ?? null;

                if ($limit !== null && $catalogIndex !== null && $catalogIndex >= (int) $limit) {
                    return false;
                }

                return $this->levelAllows(
                    (string) ($capabilities['template_access_level'] ?? 'starter'),
                    $requiredLevel,
                    self::TEMPLATE_LEVELS,
                );
            })
            ->sortBy(fn (array $plan) => (int) ($plan['price_usd'] ?? PHP_INT_MAX));

        $key = $candidates->keys()->first();
        $plan = $key ? $candidates->get($key) : null;

        if (! $key || ! is_array($plan)) {
            return null;
        }

        return [
            'plan_key' => $key,
            'label' => (string) ($plan['label'] ?? ucfirst(str_replace('_', ' ', $key))),
            'family' => (string) ($plan['family'] ?? $preferredFamily),
            'price_usd' => (int) ($plan['price_usd'] ?? 0),
            'credits' => (int) ($plan['credits'] ?? 0),
            'description' => (string) ($plan['description'] ?? ''),
            'action_label' => 'View '.(string) ($plan['label'] ?? 'upgrade'),
        ];
    }

    public function canPreviewSpark(User $user, int $catalogIndex): bool
    {
        return (bool) ($this->sparkPreviewAccess($user, $catalogIndex)['allowed'] ?? false);
    }

    /**
     * Canonical Marketplace preview decision. Personal plans and Agency Pro may
     * preview the full catalog; Starter/Growth Agency are limited to the first
     * 15/30 catalog entries respectively.
     *
     * @return array<string, mixed>
     */
    public function sparkPreviewAccess(User $user, int $catalogIndex): array
    {
        $plan = $this->plans->find($user->plan_key) ?? [];
        $capabilities = (array) ($plan['capabilities'] ?? []);
        $family = (string) ($plan['family'] ?? 'personal');
        $limit = $capabilities['marketplace_preview_limit'] ?? null;
        $allowed = $limit === null || $catalogIndex < (int) $limit;

        return [
            'allowed' => $allowed,
            'catalog_index' => $catalogIndex,
            'preview_limit' => $limit === null ? null : (int) $limit,
            'preview_limit_label' => $limit === null ? 'All Sparks' : 'First '.(int) $limit.' Sparks',
            'plan_family' => $family,
            'reason' => $allowed ? null : 'marketplace_preview_limit',
            'message' => $allowed
                ? null
                : sprintf('Your current Agency plan can preview the first %d Marketplace Sparks. Upgrade to preview this Spark.', (int) $limit),
        ];
    }

    /** @return array<string, mixed> */
    public function sparkPreviewSummary(User $user): array
    {
        $decision = $this->sparkPreviewAccess($user, 0);

        return [
            'limit' => $decision['preview_limit'],
            'limit_label' => $decision['preview_limit_label'],
            'unlimited' => $decision['preview_limit'] === null,
            'plan_family' => $decision['plan_family'],
        ];
    }

    public function canInstallSpark(User $user, string $requiredLevel): bool
    {
        return (bool) ($this->sparkAccess($user, $requiredLevel)['allowed'] ?? false);
    }

    /**
     * Canonical Spark access decision used by Marketplace, Builder, and APIs.
     *
     * @return array<string, mixed>
     */
    public function sparkAccess(User $user, string $requiredLevel): array
    {
        $accountLevel = (string) ($this->plans->capabilities($user->plan_key)['spark_access_level'] ?? 'free');
        $levels = $this->sparkLevelRanks();
        $allowed = $this->levelAllows($accountLevel, $requiredLevel, $levels);

        return [
            'allowed' => $allowed,
            'account_level' => $accountLevel,
            'required_level' => $requiredLevel,
            'account_label' => (string) config("cosmic-sparks.access_levels.{$accountLevel}.label", str($accountLevel)->headline()->toString()),
            'required_label' => (string) config("cosmic-sparks.access_levels.{$requiredLevel}.label", str($requiredLevel)->headline()->toString()),
            'reason' => $allowed ? null : 'spark_access_level',
            'message' => $allowed
                ? null
                : sprintf('This Spark requires the %s Spark tier.', (string) config("cosmic-sparks.access_levels.{$requiredLevel}.label", str($requiredLevel)->headline()->toString())),
        ];
    }

    /** @return array<string,mixed>|null */
    public function recommendedSparkUpgrade(User $user, string $requiredLevel, bool $requireFullPreview = false): ?array
    {
        $current = $this->plans->normalizeKey($user->plan_key);
        $levels = $this->sparkLevelRanks();
        $candidates = collect($this->plans->all())
            ->filter(function (array $plan, string $key) use ($current, $requiredLevel, $requireFullPreview, $levels) {
                if ($key === $current) return false;
                $capabilities = (array) ($plan['capabilities'] ?? []);
                if (! $this->levelAllows((string) ($capabilities['spark_access_level'] ?? 'free'), $requiredLevel, $levels)) return false;
                if ($requireFullPreview && ($capabilities['marketplace_preview_limit'] ?? null) !== null) return false;
                return true;
            })
            ->sortBy(fn (array $plan) => (int) ($plan['price_usd'] ?? PHP_INT_MAX));

        $key = $candidates->keys()->first();
        $plan = $key ? $candidates->get($key) : null;
        return $key && is_array($plan) ? $this->upgradePayload($key, $plan) : null;
    }

    /** @return array<string,mixed>|null */
    public function recommendedSparkSlotUpgrade(User $user): ?array
    {
        $current = $this->plans->normalizeKey($user->plan_key);
        $currentLimit = $this->plans->capabilities($current)['max_owned_sparks'] ?? 0;
        $candidates = collect($this->plans->all())
            ->filter(fn (array $plan, string $key) => $key !== $current && (($plan['capabilities']['max_owned_sparks'] ?? 0) === null || (int) ($plan['capabilities']['max_owned_sparks'] ?? 0) > (int) $currentLimit))
            ->sortBy(fn (array $plan) => (int) ($plan['price_usd'] ?? PHP_INT_MAX));
        $key = $candidates->keys()->first();
        $plan = $key ? $candidates->get($key) : null;
        return $key && is_array($plan) ? $this->upgradePayload($key, $plan) : null;
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private function upgradePayload(string $key, array $plan): array
    {
        return [
            'plan_key' => $key,
            'label' => (string) ($plan['label'] ?? str($key)->headline()),
            'family' => (string) ($plan['family'] ?? 'personal'),
            'price_usd' => (int) ($plan['price_usd'] ?? 0),
            'credits' => (int) ($plan['credits'] ?? 0),
            'description' => (string) ($plan['description'] ?? ''),
            'action_label' => 'View '.(string) ($plan['label'] ?? 'upgrade'),
        ];
    }

    public function canAddOwnedSpark(User $user, int $currentOwnedCount): bool
    {
        $limit = $this->plans->capabilities($user->plan_key)['max_owned_sparks'] ?? null;

        return $limit === null || $currentOwnedCount < (int) $limit;
    }

    public function changeMatrix(?string $currentPlanKey): array
    {
        return $this->transitions->matrix($currentPlanKey);
    }


    /** @return array<string, int> */
    private function sparkLevelRanks(): array
    {
        return collect((array) config('cosmic-sparks.access_levels', []))
            ->mapWithKeys(fn (array $definition, string $key) => [$key => (int) ($definition['rank'] ?? -1)])
            ->all();
    }

    private function levelAllows(string $accountLevel, string $requiredLevel, array $levels): bool
    {
        $accountRank = $levels[$accountLevel] ?? -1;
        $requiredRank = $levels[$requiredLevel] ?? PHP_INT_MAX;

        if (str_starts_with($requiredLevel, 'agency_') && ! str_starts_with($accountLevel, 'agency_') && $accountLevel !== 'all') {
            return false;
        }

        return $accountRank >= $requiredRank;
    }

    private function limitLabel(mixed $limit): string
    {
        return $limit === null || $limit === 'unlimited' ? 'Unlimited' : (string) max(0, (int) $limit);
    }
}
