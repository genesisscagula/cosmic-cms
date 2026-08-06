<?php

namespace App\Services;

use App\Cosmic\Pricing\BlockPricingRegistry;
use App\Models\CosmicUnlock;
use App\Models\User;

final class PlanBuiltInSparkGrantService
{
    /** @return array<int, string> */
    public function ensure(User $user): array
    {
        $capabilities = app(PlanRegistry::class)->capabilities($user->plan_key);
        $grantCount = max(0, (int) ($capabilities['free_built_in_sparks'] ?? 0));
        if ($grantCount === 0) {
            return [];
        }

        $keys = collect(BlockPricingRegistry::all())
            ->filter(fn (array $spark): bool => ($spark['category'] ?? '') === 'core')
            ->keys()
            ->take($grantCount)
            ->values()
            ->all();

        foreach ($keys as $key) {
            CosmicUnlock::query()->firstOrCreate(
                ['user_id' => $user->id, 'unlock_type' => 'spark', 'unlock_key' => $key],
                ['credits_paid' => 0, 'is_installed' => true],
            );
        }

        CosmicUnlock::query()
            ->where('user_id', $user->id)
            ->where('unlock_type', 'spark')
            ->whereIn('unlock_key', $keys)
            ->update(['is_installed' => true]);

        return $keys;
    }
}
