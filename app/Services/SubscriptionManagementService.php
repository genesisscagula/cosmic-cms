<?php

namespace App\Services;

use App\Models\PaymentOrder;
use App\Models\User;
use Carbon\Carbon;
use RuntimeException;

class SubscriptionManagementService
{
    public function __construct(private readonly PayPalService $paypal)
    {
    }

    public function currentOrder(User $user): ?PaymentOrder
    {
        return $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->whereNotNull('external_subscription_id')
            ->whereNotIn('status', ['failed', 'cancelled', 'expired', 'replaced'])
            ->latest('id')
            ->first();
    }

    public function assertCanStartPlanCheckout(User $user, string $planKey): ?PaymentOrder
    {
        $current = $this->currentOrder($user);
        $status = strtolower((string) $user->plan_status);

        if ($current && $user->plan_key === $planKey && in_array($status, ['active', 'approved'], true)) {
            throw new RuntimeException('This is already your current active plan.');
        }

        $pendingDuplicate = $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('product_key', $planKey)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->exists();

        if ($pendingDuplicate) {
            throw new RuntimeException('A checkout for this plan is already pending. Please finish or cancel it first.');
        }

        return $current;
    }

    public function cancelCurrent(User $user): array
    {
        $order = $this->currentOrder($user);

        if (! $order || ! $order->external_subscription_id) {
            throw new RuntimeException('No active PayPal subscription was found.');
        }

        $this->paypal->cancelSubscription(
            (string) $order->external_subscription_id,
            'Cancelled by the customer from Cosmic CMS.'
        );

        $order->update([
            'status' => 'cancelled',
            'metadata' => array_merge($order->metadata ?? [], [
                'cancelled_by_user_at' => now()->toIso8601String(),
                'access_until' => $user->plan_renews_at?->toIso8601String(),
            ]),
        ]);

        $user->update([
            'plan_status' => 'cancelled',
            'plan_cancel_at_period_end' => true,
            'plan_cancelled_at' => now(),
        ]);

        return [
            'message' => $user->plan_renews_at
                ? 'Subscription cancelled. Your plan remains available until '. $user->plan_renews_at->format('M j, Y').'.'
                : 'Subscription cancelled successfully.',
        ];
    }

    public function sync(User $user): array
    {
        $order = $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->whereNotNull('external_subscription_id')
            ->latest('id')
            ->first();

        if (! $order || ! $order->external_subscription_id) {
            throw new RuntimeException('No PayPal subscription was found to sync.');
        }

        return $this->syncOrder($order);
    }

    public function syncOrder(PaymentOrder $order): array
    {
        $subscription = $this->paypal->getSubscription((string) $order->external_subscription_id);
        $status = strtolower((string) ($subscription['status'] ?? 'inactive'));
        $nextBilling = data_get($subscription, 'billing_info.next_billing_time');
        $cancelled = $status === 'cancelled';

        $order->update([
            'status' => $status,
            'metadata' => array_merge($order->metadata ?? [], [
                'paypal_synced_at' => now()->toIso8601String(),
                'paypal_subscription_status' => strtoupper($status),
                'paypal_next_billing_time' => $nextBilling,
            ]),
        ]);

        $order->user->update([
            'plan_key' => $order->product_key,
            'plan_status' => $status,
            'plan_provider' => 'paypal',
            'plan_renews_at' => $nextBilling ? Carbon::parse($nextBilling) : $order->user->plan_renews_at,
            'plan_cancel_at_period_end' => $cancelled,
            'plan_cancelled_at' => $cancelled ? ($order->user->plan_cancelled_at ?? now()) : null,
        ]);

        return $subscription;
    }

    public function finalizeSwitch(PaymentOrder $newOrder): void
    {
        $previousSubscriptionId = (string) data_get($newOrder->metadata, 'previous_subscription_id', '');

        if ($previousSubscriptionId === '' || $previousSubscriptionId === $newOrder->external_subscription_id) {
            return;
        }

        try {
            $this->paypal->cancelSubscription($previousSubscriptionId, 'Replaced by a new Cosmic CMS plan.');

            PaymentOrder::query()
                ->where('external_subscription_id', $previousSubscriptionId)
                ->where('id', '!=', $newOrder->id)
                ->update(['status' => 'replaced']);

            $newOrder->update([
                'metadata' => array_merge($newOrder->metadata ?? [], [
                    'previous_subscription_cancelled_at' => now()->toIso8601String(),
                ]),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            $newOrder->update([
                'metadata' => array_merge($newOrder->metadata ?? [], [
                    'previous_subscription_cancel_error' => $exception->getMessage(),
                ]),
            ]);
        }
    }
}
