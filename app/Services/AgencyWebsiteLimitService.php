<?php

namespace App\Services;

use App\Cosmic\Capabilities\CapabilityDecision;
use App\Cosmic\Capabilities\CapabilityEngine;
use App\Models\User;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;

final class AgencyWebsiteLimitService
{
    public function __construct(private readonly CapabilityEngine $capabilities)
    {
    }

    public function summary(User $user): array
    {
        $plan = $this->capabilities->plan($user);
        $limit = $plan->limit('max_sites');
        $used = $this->countFor($user);
        $unlimited = $limit === null || $limit === 'unlimited';
        $numericLimit = $unlimited ? null : max(0, (int) $limit);
        $remaining = $unlimited ? null : max(0, $numericLimit - $used);

        return [
            'plan_key' => $plan->key(),
            'plan_family' => $plan->family(),
            'is_agency_plan' => $plan->family() === 'agency',
            'limit' => $numericLimit,
            'limit_label' => $unlimited ? 'Unlimited' : (string) $numericLimit,
            'used' => $used,
            'remaining' => $remaining,
            'is_unlimited' => $unlimited,
            'can_create' => $unlimited || $used < $numericLimit,
            'is_at_limit' => ! $unlimited && $used >= $numericLimit,
            'upgrade_message' => $plan->family() === 'agency'
                ? 'Upgrade your Agency plan to increase your website allowance.'
                : 'Switch to an Agency plan to manage multiple websites.',
        ];
    }

    public function decision(User $user, int $increment = 1): CapabilityDecision
    {
        return $this->capabilities->withinLimit(
            $user,
            'max_sites',
            $this->countFor($user),
            $increment,
        );
    }

    public function validationMessage(User $user, int $increment = 1): ?string
    {
        $decision = $this->decision($user, $increment);
        if ($decision->allowed) {
            return null;
        }

        $summary = $this->summary($user);
        $word = $summary['limit'] === 1 ? 'website' : 'websites';

        return "Your {$summary['plan_key']} plan supports {$summary['limit_label']} {$word}. {$summary['upgrade_message']}";
    }

    public function countFor(User $user): int
    {
        return $this->queryFor($user)->count();
    }

    public function queryFor(User $user): Builder
    {
        return Website::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('workspace', fn (Builder $workspaceQuery) => $workspaceQuery
                        ->where('owner_user_id', $user->id));
            })
            ->distinct();
    }
}
