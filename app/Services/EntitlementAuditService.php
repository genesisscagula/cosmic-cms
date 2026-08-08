<?php

namespace App\Services;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Support\SubscriptionStatus;

class EntitlementAuditService
{
    public function __construct(
        private readonly PlanRegistry $plans,
        private readonly ThemePlanAccessService $themes,
    ) {}

    /**
     * Produce a read-only entitlement snapshot. This service intentionally does
     * not repair data; Patch 1 is diagnostic so plan-source drift can be proven
     * before the resolver is unified.
     */
    public function auditUser(User $user): array
    {
        $rawPlan = $this->plans->normalizeKey((string) $user->plan_key);
        $effectivePlan = $this->plans->normalizeKey($user->effectivePlanKey());

        $latestPlanOrder = PaymentOrder::query()
            ->where('user_id', $user->id)
            ->where('product_type', 'plan')
            ->whereNotNull('fulfilled_at')
            ->latest('fulfilled_at')
            ->latest('id')
            ->first();

        $latestPaidPlan = $latestPlanOrder
            ? $this->plans->normalizeKey((string) $latestPlanOrder->product_key)
            : null;

        $allowedThemes = $this->themes->allowedThemeKeys($effectivePlan);
        $themeCount = $allowedThemes === null ? 'all' : count($allowedThemes);
        $normalizedStatus = SubscriptionStatus::normalize((string) $user->plan_status);

        $issues = [];

        if (! $user->isPlatformOwner() && $latestPaidPlan && $latestPaidPlan !== $rawPlan) {
            $issues[] = [
                'code' => 'paid_plan_user_row_mismatch',
                'severity' => 'critical',
                'message' => "Latest fulfilled plan is [{$latestPaidPlan}] but users.plan_key is [{$rawPlan}].",
            ];
        }

        if (! $user->isPlatformOwner() && $rawPlan !== $effectivePlan) {
            $issues[] = [
                'code' => 'raw_effective_plan_mismatch',
                'severity' => 'warning',
                'message' => "Raw plan [{$rawPlan}] differs from effective plan [{$effectivePlan}].",
            ];
        }

        if ($latestPlanOrder && $latestPlanOrder->status === 'paid' && $normalizedStatus !== SubscriptionStatus::ACTIVE) {
            $issues[] = [
                'code' => 'paid_order_inactive_user_status',
                'severity' => 'warning',
                'message' => "Latest fulfilled plan order is paid while user plan status is [{$normalizedStatus}].",
            ];
        }

        if (! $this->plans->find($effectivePlan)) {
            $issues[] = [
                'code' => 'unknown_effective_plan',
                'severity' => 'critical',
                'message' => "Effective plan [{$effectivePlan}] is not present in the Cosmic plan registry.",
            ];
        }

        return [
            'user_id' => $user->id,
            'email' => $user->email,
            'account_type' => $user->account_type,
            'is_platform_owner' => $user->isPlatformOwner(),
            'raw_plan_key' => $rawPlan,
            'effective_plan_key' => $effectivePlan,
            'plan_status' => $normalizedStatus,
            'plan_provider' => $user->plan_provider,
            'theme_access' => [
                'count' => $themeCount,
                'keys' => $allowedThemes,
            ],
            'latest_fulfilled_plan_order' => $latestPlanOrder ? [
                'id' => $latestPlanOrder->id,
                'reference' => $latestPlanOrder->reference,
                'product_key' => $latestPaidPlan,
                'provider' => $latestPlanOrder->provider,
                'status' => $latestPlanOrder->status,
                'paid_at' => optional($latestPlanOrder->paid_at)?->toIso8601String(),
                'fulfilled_at' => optional($latestPlanOrder->fulfilled_at)?->toIso8601String(),
                'external_subscription_id' => $latestPlanOrder->external_subscription_id,
            ] : null,
            'issues' => $issues,
            'healthy' => $issues === [],
        ];
    }
}
