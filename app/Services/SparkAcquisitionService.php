<?php

namespace App\Services;

use App\Models\CosmicUnlock;
use App\Models\User;

final class SparkAcquisitionService
{
    public function __construct(
        private readonly PlanEntitlementService $entitlements,
        private readonly OwnedSparkSlotService $slots,
        private readonly CreditService $credits,
    ) {}

    /** @param array<string,mixed> $spark @return array<string,mixed> */
    public function decision(User $user, array $spark, ?CosmicUnlock $unlock = null): array
    {
        $unlock ??= $user->cosmicUnlocks()
            ->where('unlock_type', 'spark')
            ->where('unlock_key', (string) $spark['key'])
            ->first();

        $installed = (bool) ($unlock?->is_installed);
        $owned = $unlock !== null;
        $purchased = (int) ($unlock?->credits_paid ?? 0) > 0;
        $price = max(0, (int) ($spark['credits'] ?? 0));
        $access = $this->entitlements->sparkAccess($user, (string) ($spark['access_level'] ?? 'free'));
        $slotUsage = $this->slots->usage($user, $installed ? 0 : 1);
        $balance = $this->credits->balance($user);

        if ($installed) {
            return $this->result(true, 'installed', 'Already installed', null, $access, $slotUsage, $balance, $price, $owned, $purchased);
        }

        // Ownership is permanent. A removed Spark may be restored after a plan
        // change, provided the account has an available active-library slot.
        if ($owned) {
            if (! $slotUsage['can_add']) {
                return $this->result(false, 'restore', 'Restore Spark', 'owned_spark_limit', $access, $slotUsage, $balance, $price, true, $purchased, $slotUsage['message']);
            }

            return $this->result(true, 'restore', 'Restore Spark', null, $access, $slotUsage, $balance, $price, true, $purchased);
        }

        if (! (bool) $access['allowed']) {
            return $this->result(false, $price > 0 ? 'purchase' : 'install', $price > 0 ? 'Purchase Spark' : 'Install Spark', 'spark_access_level', $access, $slotUsage, $balance, $price, false, false, $access['message']);
        }

        if (! $slotUsage['can_add']) {
            return $this->result(false, $price > 0 ? 'purchase' : 'install', $price > 0 ? 'Purchase Spark' : 'Install Spark', 'owned_spark_limit', $access, $slotUsage, $balance, $price, false, false, $slotUsage['message']);
        }

        if ($price > 0 && $balance < $price) {
            return $this->result(false, 'purchase', 'Purchase for '.$price.' credits', 'insufficient_credits', $access, $slotUsage, $balance, $price, false, false, 'You need '.($price - $balance).' more Cosmic Credits to purchase this Spark.');
        }

        return $this->result(true, $price > 0 ? 'purchase' : 'install', $price > 0 ? 'Purchase for '.$price.' credits' : 'Install free', null, $access, $slotUsage, $balance, $price, false, false);
    }

    /** @return array<string,mixed> */
    private function result(bool $allowed, string $action, string $label, ?string $reason, array $access, array $slots, int $balance, int $price, bool $owned, bool $purchased, ?string $message = null): array
    {
        return compact('allowed', 'action', 'label', 'reason', 'message', 'access', 'slots', 'balance', 'price', 'owned', 'purchased') + [
            'is_free' => $price === 0,
            'requires_payment' => $price > 0 && ! $owned,
        ];
    }
}
