<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;
use App\Cosmic\Capabilities\CapabilityEngine;

class PlanCapabilityService
{
    public function __construct(
        private readonly PlanRegistry $plans,
        private readonly CapabilityEngine $engine,
        private readonly AgencyWebsiteLimitService $websiteLimits,
    ) {
    }

    public function forUser(User $user): array
    {
        $plan = $this->plans->get($user->effectivePlanKey());
        $capabilities = (array) ($plan['capabilities'] ?? []);
        $websiteLimits = $this->websiteLimits->summary($user);

        return [
            'plan_key' => $plan['key'],
            'plan_label' => $plan['label'],
            'plan_family' => $plan['family'] ?? 'personal',
            'plan_tier' => $plan['tier'] ?? 'starter',
            'plan_rank' => (int) ($plan['rank'] ?? 0),
            'max_sites' => $websiteLimits['limit'],
            'max_sites_label' => $websiteLimits['limit_label'],
            'site_count' => $websiteLimits['used'],
            'remaining_sites' => $websiteLimits['remaining'],
            'can_add_sites' => $websiteLimits['can_create'],
            'upgrade_required' => $websiteLimits['is_at_limit'],
            'upgrade_message' => $websiteLimits['upgrade_message'],
            'website_limit' => $websiteLimits,
            'upgrade_options' => config('payments.website_upgrade_options', []),
            'capabilities' => $capabilities,
        ];
    }

    public function allows(User $user, string $capability, mixed $expected = true): bool
    {
        return $this->engine->allows($user, $capability, $expected);
    }

    public function value(User $user, string $capability, mixed $default = null): mixed
    {
        return $this->engine->value($user, $capability, $default);
    }

    public function assertCanCreateWebsite(User $user): ?string
    {
        return $this->websiteLimits->validationMessage($user);
    }

}
