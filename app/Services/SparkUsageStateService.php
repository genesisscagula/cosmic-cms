<?php

namespace App\Services;

use App\Models\User;

final class SparkUsageStateService
{
    public function __construct(private readonly PlanEntitlementService $entitlements) {}

    /** @param array<string,mixed> $spark @param array<string,mixed> $acquisition @param array<string,mixed> $preview */
    public function resolve(User $user, array $spark, array $acquisition, array $preview, bool $shared = false): array
    {
        $owned = (bool) ($acquisition['owned'] ?? false);
        $installed = ($acquisition['action'] ?? null) === 'installed';
        $purchased = (bool) ($acquisition['purchased'] ?? false);
        $reason = $acquisition['reason'] ?? null;

        if ($shared && ! $installed) {
            return $this->state('shared', 'Shared', 'Available from your agency workspace.', 'use', 'Use shared Spark', 'info');
        }
        if ($installed) {
            return $this->state($purchased ? 'purchased' : 'owned', $purchased ? 'Purchased' : 'Owned', 'Installed in your account library.', 'use', 'Add to page', 'success');
        }
        if ($owned) {
            return $this->state('removed', 'Owned · removed', 'Owned permanently and ready to restore.', 'restore', 'Restore Spark', 'neutral');
        }
        if (! (bool) ($preview['allowed'] ?? true)) {
            return $this->state('preview_locked', 'Preview locked', (string) ($preview['message'] ?? 'Upgrade to preview this Spark.'), 'upgrade', 'Upgrade to preview', 'warning', $this->entitlements->recommendedSparkUpgrade($user, (string) ($spark['access_level'] ?? 'free'), true));
        }
        if ($reason === 'spark_access_level') {
            return $this->state('plan_locked', 'Higher plan', (string) ($acquisition['message'] ?? 'Upgrade your plan to install this Spark.'), 'upgrade', 'Upgrade to add', 'warning', $this->entitlements->recommendedSparkUpgrade($user, (string) ($spark['access_level'] ?? 'free')));
        }
        if ($reason === 'owned_spark_limit') {
            return $this->state('slots_full', 'Slots full', (string) ($acquisition['message'] ?? 'Your Owned Spark slots are full.'), 'manage_or_upgrade', 'Manage slots', 'warning', $this->entitlements->recommendedSparkSlotUpgrade($user));
        }
        if ($reason === 'insufficient_credits') {
            return $this->state('credits_needed', 'More credits needed', (string) ($acquisition['message'] ?? 'Add Cosmic Credits to purchase this Spark.'), 'buy_credits', 'Add credits', 'warning');
        }

        $isFree = (bool) ($acquisition['is_free'] ?? false);
        return $this->state($isFree ? 'free' : 'available', $isFree ? 'Free' : 'Available', $isFree ? 'Included with your plan.' : 'Available to purchase with Cosmic Credits.', $acquisition['action'] ?? 'install', (string) ($acquisition['label'] ?? ($isFree ? 'Install free' : 'Purchase Spark')), 'default');
    }

    private function state(string $key, string $label, string $message, string $action, string $actionLabel, string $tone, ?array $upgrade = null): array
    {
        return compact('key', 'label', 'message', 'action', 'actionLabel', 'tone', 'upgrade') + [
            'upgrade_url' => $upgrade ? '/credits?'.http_build_query(['family' => $upgrade['family'] ?? null, 'plan' => $upgrade['plan_key'] ?? null, 'source' => 'spark']) : null,
        ];
    }
}
