<?php

namespace App\Services;

use App\Models\User;

final class OwnedSparkSlotService
{
    public function __construct(private readonly PlanRegistry $plans)
    {
    }

    /** @return array<string, mixed> */
    public function usage(User $user, int $increment = 0): array
    {
        $limit = $this->plans->capabilities($user->plan_key)['max_owned_sparks'] ?? null;
        $used = $user->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->where('is_installed', true)
            ->count();
        $unlimited = $limit === null || $limit === 'unlimited';
        $numericLimit = $unlimited ? null : max(0, (int) $limit);
        $remaining = $unlimited ? null : max(0, $numericLimit - $used);
        $allowed = $unlimited || ($used + max(0, $increment)) <= $numericLimit;

        return [
            'used' => $used,
            'limit' => $numericLimit,
            'limit_label' => $unlimited ? 'Unlimited' : (string) $numericLimit,
            'remaining' => $remaining,
            'unlimited' => $unlimited,
            'at_limit' => ! $unlimited && $used >= $numericLimit,
            'can_add' => $allowed,
            'percentage' => $unlimited || $numericLimit === 0
                ? null
                : min(100, (int) round(($used / $numericLimit) * 100)),
            'message' => $allowed
                ? null
                : "Your Owned Sparks library is full ({$used}/{$numericLimit}). Remove a Spark or upgrade your plan to add another.",
        ];
    }

    public function canAdd(User $user, int $increment = 1): bool
    {
        return (bool) $this->usage($user, $increment)['can_add'];
    }
}
