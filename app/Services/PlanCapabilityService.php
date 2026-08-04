<?php

namespace App\Services;

use App\Models\User;
use App\Models\Website;

class PlanCapabilityService
{
    public function forUser(User $user): array
    {
        $planKey = $user->plan_key ?: 'starter';
        $plan = config("payments.plans.{$planKey}", []);
        $capabilities = $plan['capabilities'] ?? [];
        $maxSites = $capabilities['max_sites'] ?? 1;

        // Count websites owned directly by the account and websites attached to
        // workspaces owned by the account. Workspace members do not consume
        // their personal allowance for another owner's websites.
        $siteCount = Website::query()
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('workspace', fn ($workspaceQuery) => $workspaceQuery->where('owner_user_id', $user->id));
            })
            ->distinct('websites.id')
            ->count('websites.id');

        $isUnlimited = $maxSites === null || $maxSites === 'unlimited';
        $numericMax = $isUnlimited ? null : max(1, (int) $maxSites);
        $canAddSites = $isUnlimited || $siteCount < $numericMax;
        $planType = $capabilities['plan_type'] ?? 'personal';

        return [
            'plan_key' => $planKey,
            'plan_label' => $plan['label'] ?? ucfirst(str_replace('_', ' ', $planKey)),
            'plan_type' => $planType,
            'max_sites' => $numericMax,
            'max_sites_label' => $isUnlimited ? 'Unlimited' : (string) $numericMax,
            'site_count' => $siteCount,
            'remaining_sites' => $isUnlimited ? null : max(0, $numericMax - $siteCount),
            'can_add_sites' => $canAddSites,
            'upgrade_required' => ! $canAddSites,
            'upgrade_message' => $this->upgradeMessage($planType, $numericMax),
            'upgrade_options' => config('payments.website_upgrade_options', []),
        ];
    }

    public function assertCanCreateWebsite(User $user): ?string
    {
        $capabilities = $this->forUser($user);

        if ($capabilities['can_add_sites']) {
            return null;
        }

        $limit = $capabilities['max_sites_label'];
        $websiteWord = (int) $capabilities['max_sites'] === 1 ? 'website' : 'websites';

        return "Your current plan supports {$limit} {$websiteWord}. Upgrade to a Business or Agency plan to create another site.";
    }

    private function upgradeMessage(string $planType, ?int $maxSites): string
    {
        if ($planType === 'personal' || $maxSites === 1) {
            return 'Upgrade to a Business or Agency plan to add more websites.';
        }

        return 'Upgrade your plan to increase your website allowance.';
    }
}
