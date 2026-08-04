<?php

namespace App\Services;

use App\Models\PaymentOrder;
use App\Models\User;
use App\Support\SubscriptionStatus;
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
            ->whereNotNull('fulfilled_at')
            ->whereNotIn('status', ['failed', 'expired', 'replaced'])
            ->latest('id')
            ->first();
    }

    public function pendingPlanChange(User $user): ?PaymentOrder
    {
        return $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('status', 'pending')
            ->whereNotNull('external_subscription_id')
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->first();
    }


    public function resumableCheckout(User $user, string $planKey): ?PaymentOrder
    {
        $order = $user->paymentOrders()
            ->where('provider', 'paypal')
            ->where('product_type', 'plan')
            ->where('product_key', $planKey)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subDay())
            ->latest('id')
            ->first();

        if (! $order) {
            return null;
        }

        $checkoutUrl = (string) data_get($order->metadata, 'checkout_url', '');

        if ($checkoutUrl === '' || ! $order->external_subscription_id) {
            $order->update([
                'status' => 'expired',
                'metadata' => array_merge($order->metadata ?? [], [
                    'expired_reason' => 'checkout_not_resumable',
                    'expired_at' => now()->toIso8601String(),
                ]),
            ]);

            return null;
        }

        return $order;
    }

    public function resumeCheckout(User $user, string $planKey): ?array
    {
        $order = $this->resumableCheckout($user, $planKey);

        if (! $order) {
            return null;
        }

        $order->update([
            'metadata' => array_merge($order->metadata ?? [], [
                'checkout_resumed_at' => now()->toIso8601String(),
                'checkout_resume_count' => ((int) data_get($order->metadata, 'checkout_resume_count', 0)) + 1,
            ]),
        ]);

        return [
            'checkout_url' => (string) data_get($order->metadata, 'checkout_url'),
            'order_reference' => $order->reference,
            'resumed' => true,
        ];
    }

    public function assertCanStartPlanCheckout(User $user, string $planKey): ?PaymentOrder
    {
        $current = $this->currentOrder($user);
        $status = SubscriptionStatus::normalize($user->plan_status);

        if ($current && $user->plan_key === $planKey && in_array($status, [
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::PAST_DUE,
            SubscriptionStatus::SUSPENDED,
            SubscriptionStatus::CANCELLED,
        ], true)) {
            throw new RuntimeException('This is already your current plan.');
        }

        $pendingChange = $this->pendingPlanChange($user);

        if ($pendingChange && $pendingChange->product_key !== $planKey) {
            $target = config("payments.plans.{$pendingChange->product_key}.label", ucfirst($pendingChange->product_key));
            throw new RuntimeException("You already have a pending change to {$target}. Resume or finish that PayPal checkout first.");
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
            'plan_status' => SubscriptionStatus::CANCELLED,
            'plan_cancel_at_period_end' => true,
            'plan_cancelled_at' => now(),
            'plan_status_changed_at' => now(),
        ]);

        return [
            'message' => $user->plan_renews_at
                ? 'Subscription cancelled. Your plan remains available until '. $user->plan_renews_at->format('M j, Y').'.'
                : 'Subscription cancelled successfully.',
        ];
    }

    public function sync(User $user): array
    {
        // Never use a newer unfulfilled/pending checkout as the account source
        // of truth. Prefer the currently fulfilled subscription.
        $order = $this->currentOrder($user)
            ?? $user->paymentOrders()
                ->where('provider', 'paypal')
                ->where('product_type', 'plan')
                ->whereNotNull('external_subscription_id')
                ->whereNotNull('fulfilled_at')
                ->latest('id')
                ->first();

        if (! $order || ! $order->external_subscription_id) {
            throw new RuntimeException('No fulfilled PayPal subscription was found to sync.');
        }

        return $this->syncOrder($order);
    }

    public function recover(User $user): array
    {
        $order = $this->currentOrder($user);

        if (! $order || ! $order->external_subscription_id) {
            throw new RuntimeException('No recoverable PayPal subscription was found.');
        }

        $user->forceFill([
            'plan_recovery_attempted_at' => now(),
            'plan_recovery_error' => null,
        ])->save();

        try {
            $before = SubscriptionStatus::normalize($user->plan_status);

            if ($before === SubscriptionStatus::SUSPENDED) {
                $this->paypal->activateSubscription((string) $order->external_subscription_id);
            }

            $subscription = $this->syncOrder($order->fresh());
            $after = SubscriptionStatus::normalize((string) ($subscription['status'] ?? 'pending'));

            if ($after === SubscriptionStatus::ACTIVE) {
                $this->finalizeSwitch($order->fresh());
            }

            return [
                'subscription' => $subscription,
                'before' => $before,
                'after' => $after,
                'recovered' => $after === SubscriptionStatus::ACTIVE,
            ];
        } catch (\Throwable $exception) {
            $user->forceFill(['plan_recovery_error' => $exception->getMessage()])->save();
            throw $exception;
        }
    }

    public function syncOrder(PaymentOrder $order): array
    {
        $subscription = $this->paypal->getSubscription((string) $order->external_subscription_id);
        $providerStatus = strtolower((string) ($subscription['status'] ?? 'pending'));
        $status = SubscriptionStatus::normalize($providerStatus);
        $nextBilling = data_get($subscription, 'billing_info.next_billing_time');
        $cancelled = $status === SubscriptionStatus::CANCELLED;

        $order->update([
            'status' => $status,
            'metadata' => array_merge($order->metadata ?? [], [
                'paypal_synced_at' => now()->toIso8601String(),
                'paypal_subscription_status' => strtoupper($providerStatus),
                'subscription_lifecycle_status' => $status,
                'paypal_next_billing_time' => $nextBilling,
            ]),
        ]);

        $user = $order->user;
        $current = $this->currentOrder($user);
        $mayUpdateAccount = ! $current || $current->id === $order->id;

        // A delayed sync for an old/replaced subscription may update its own
        // order record, but must never downgrade the user's newer plan.
        if ($mayUpdateAccount) {
            $user->update([
                'plan_key' => $order->product_key,
                'plan_status' => $status,
                'plan_provider' => 'paypal',
                'plan_renews_at' => $nextBilling ? Carbon::parse($nextBilling) : ($status === SubscriptionStatus::EXPIRED ? null : $user->plan_renews_at),
                'plan_cancel_at_period_end' => $cancelled,
                'plan_cancelled_at' => $cancelled ? ($user->plan_cancelled_at ?? now()) : null,
                'plan_status_changed_at' => $user->plan_status !== $status ? now() : $user->plan_status_changed_at,
                'plan_past_due_at' => $status === SubscriptionStatus::PAST_DUE ? ($user->plan_past_due_at ?? now()) : null,
                'plan_suspended_at' => $status === SubscriptionStatus::SUSPENDED ? ($user->plan_suspended_at ?? now()) : null,
                'plan_expired_at' => $status === SubscriptionStatus::EXPIRED ? ($user->plan_expired_at ?? now()) : null,
                'plan_last_synced_at' => now(),
                'plan_recovery_error' => null,
            ]);
        }

        return $subscription;
    }

    public function finalizeSwitch(PaymentOrder $newOrder): void
    {
        $newOrder->refresh();
        $previousSubscriptionId = (string) data_get($newOrder->metadata, 'previous_subscription_id', '');

        if (
            ! $newOrder->fulfilled_at
            || SubscriptionStatus::normalize($newOrder->user->plan_status) !== SubscriptionStatus::ACTIVE
            || $previousSubscriptionId === ''
            || $previousSubscriptionId === $newOrder->external_subscription_id
            || data_get($newOrder->metadata, 'previous_subscription_cancelled_at')
        ) {
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
                    'plan_switch_completed_at' => now()->toIso8601String(),
                    'plan_switch_status' => 'completed',
                ]),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            $newOrder->update([
                'metadata' => array_merge($newOrder->metadata ?? [], [
                    'previous_subscription_cancel_error' => $exception->getMessage(),
                    'plan_switch_status' => 'new_plan_active_old_plan_cancel_failed',
                ]),
            ]);
        }
    }
}
